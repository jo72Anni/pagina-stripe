<?php
session_start();

// ==============================================
// CONFIGURAZIONE DA VARIABILI D'AMBIENTE
// ==============================================

// Funzione per leggere variabili d'ambiente con fallback
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// Configurazione PostgreSQL (obbligatorie)
$DB_CONFIG = [
    'host'     => env('DB_HOST'),
    'port'     => (int)env('DB_PORT', 5432),
    'dbname'   => env('DB_NAME'),
    'user'     => env('DB_USER'),
    'password' => env('DB_PASSWORD'),
    'ssl_mode' => env('DB_SSL_MODE', 'require')
];

// Configurazione Admin (opzionali)
$ADMIN_CREDENTIALS = [
    'username' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD') ? password_hash(env('ADMIN_PASSWORD'), PASSWORD_DEFAULT) 
                  : password_hash('admin123', PASSWORD_DEFAULT)
];

// Verifica variabili obbligatorie
$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($required as $var) {
    if (empty(env($var))) {
        die("<div class='alert alert-danger'>Errore: Variabile <code>$var</code> mancante</div>");
    }
}

// ==============================================
// AUTENTICAZIONE
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_CREDENTIALS['username'] && 
        password_verify($_POST['password'], $ADMIN_CREDENTIALS['password'])) {
        $_SESSION['authenticated'] = true;
        header("Location: ?");
        exit;
    } else {
        $login_error = "Credenziali non valide";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

// ==============================================
// FUNZIONI DATABASE
// ==============================================

function getPDO() {
    global $DB_CONFIG;
    try {
        $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s",
            $DB_CONFIG['host'],
            $DB_CONFIG['port'],
            $DB_CONFIG['dbname']);

        $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Forza SSL se richiesto
        if ($DB_CONFIG['ssl_mode'] === 'require') {
            $pdo->exec("SET sslmode=require");
        }

        return $pdo;
    } catch (PDOException $e) {
        die("<div class='alert alert-danger'>Errore di connessione: " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

// ==============================================
// GESTIONE AZIONI
// ==============================================

$action = $_POST['action'] ?? '';
$response = [];

if (($_SESSION['authenticated'] ?? false) && $action) {
    try {
        $pdo = getPDO();
        
        switch ($action) {
            case 'test_connection':
                $response = ['status' => 'success', 'data' => $pdo->query("SELECT NOW() AS time, version() AS version")->fetch()];
                break;
                
            case 'get_tables':
                $response = ['status' => 'success', 'data' => $pdo->query("
                    SELECT table_name 
                    FROM information_schema.tables 
                    WHERE table_schema = 'public'
                    ORDER BY table_name
                ")->fetchAll(PDO::FETCH_COLUMN)];
                break;
                
            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }
                
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $stmt = $pdo->prepare("SELECT * FROM $table LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $data = $stmt->fetchAll();
                
                $response = [
                    'status' => 'success',
                    'data' => $data,
                    'columns' => !empty($data) ? array_keys($data[0]) : [],
                    'total' => (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()
                ];
                break;
                
            case 'insert_mock_data':
                $events = [
                    'payment_intent.succeeded' => [
                        'id' => 'pi_' . bin2hex(random_bytes(8)),
                        'amount' => rand(1000, 10000),
                        'currency' => 'usd',
                        'status' => 'succeeded'
                    ],
                    'charge.succeeded' => [
                        'id' => 'ch_' . bin2hex(random_bytes(8)),
                        'amount' => rand(500, 5000),
                        'currency' => 'eur',
                        'paid' => true
                    ]
                ];
                
                $inserted = 0;
                $stmt = $pdo->prepare("INSERT INTO stripe_events (event_type, payload) VALUES (?, ?)");
                
                foreach ($events as $type => $data) {
                    if ($stmt->execute([$type, json_encode($data)])) {
                        $inserted++;
                    }
                }
                
                $response = ['status' => 'success', 'inserted' => $inserted];
                break;
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ==============================================
// INTERFACCIA HTML
// ==============================================

if (!($_SESSION['authenticated'] ?? false)) {
    // Pagina di Login
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; }
            .login-card { max-width: 400px; margin: 0 auto; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="login-card card">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Accesso Admin</h2>
                    <?php if (isset($login_error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="login" value="1">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Accedi</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Pagina Admin
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PostgreSQL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #6772e5;
            --secondary: #6c757d;
        }
        body { background-color: #f8f9fa; }
        .navbar { background-color: var(--primary); }
        .card-hover:hover { transform: translateY(-5px); transition: transform 0.2s; }
        .table-container { max-height: 65vh; overflow-y: auto; }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <span class="navbar-brand">PostgreSQL Admin</span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Benvenuto, <?= htmlspecialchars($ADMIN_CREDENTIALS['username']) ?></span>
                <a href="?logout=1" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="bi bi-database"></i> Stato Database
                        </h5>
                        <div id="db-status" class="text-success">
                            <i class="bi bi-check-circle-fill"></i> Connesso
                        </div>
                        <small class="text-muted d-block mt-2">Host: <?= htmlspecialchars($DB_CONFIG['host']) ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="bi bi-file-earmark-plus"></i> Dati Fittizi
                        </h5>
                        <button id="mock-data-btn" class="btn btn-success">
                            <i class="bi bi-magic"></i> Genera Dati Stripe
                        </button>
                        <div id="mock-data-result" class="mt-2 small"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <i class="bi bi-list-check"></i> Tabelle Disponibili
                        </h5>
                        <select id="table-select" class="form-select">
                            <option value="">Caricamento...</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-table"></i> Dati Tabella
                </h5>
                <button id="refresh-btn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Aggiorna
                </button>
            </div>
            
            <div class="table-container p-3">
                <table id="data-table" class="table table-striped table-hover">
                    <thead class="table-light"></thead>
                    <tbody></tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white">
                <nav id="pagination" class="d-flex justify-content-center"></nav>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Funzioni di supporto
        const escapeHtml = str => str?.toString()?.replace(/[&<>"']/g, 
            m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])) || '';
        
        const showError = err => {
            console.error(err);
            alert('Errore: ' + (err.message || err.statusText || 'Errore sconosciuto'));
        };

        // API Request
        async function apiRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                return await response.json();
            } catch (error) {
                showError(error);
                return { status: 'error', message: error.message };
            }
        }

        // Test connessione
        async function testConnection() {
            const res = await apiRequest('test_connection');
            if (res.status === 'success') {
                document.getElementById('db-status').innerHTML = `
                    <i class="bi bi-check-circle-fill text-success"></i> Connesso
                    <div class="small">${escapeHtml(res.data.version)}</div>
                    <div class="small">${escapeHtml(res.data.time)}</div>
                `;
            }
        }

        // Carica tabelle
        async function loadTables() {
            const res = await apiRequest('get_tables');
            if (res.status === 'success') {
                const select = document.getElementById('table-select');
                select.innerHTML = '<option value="">Seleziona una tabella</option>';
                res.data.forEach(table => {
                    select.innerHTML += `<option value="${escapeHtml(table)}">${escapeHtml(table)}</option>`;
                });
            }
        }

        // Carica dati tabella
        async function loadTableData(table, page = 1) {
            if (!table) return;
            
            const res = await apiRequest('get_table_data', { table, page });
            if (res.status !== 'success') return;
            
            // Intestazione
            let thead = '';
            res.columns?.forEach(col => {
                thead += `<th>${escapeHtml(col)}</th>`;
            });
            document.querySelector('#data-table thead').innerHTML = `<tr>${thead}</tr>`;
            
            // Dati
            let tbody = '';
            res.data?.forEach(row => {
                let tr = '<tr class="cursor-pointer">';
                res.columns?.forEach(col => {
                    tr += `<td>${row[col] === null ? '<em>NULL</em>' : escapeHtml(row[col])}</td>`;
                });
                tbody += tr + '</tr>';
            });
            document.querySelector('#data-table tbody').innerHTML = tbody;
            
            // Paginazione
            const totalPages = Math.ceil(res.total / 20);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                pagination.innerHTML += `
                    <li class="page-item ${i === page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }
            
            // Eventi paginazione
            document.querySelectorAll('#pagination .page-link').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    loadTableData(table, parseInt(e.target.dataset.page));
                });
            });
        }

        // Genera dati fittizi
        document.getElementById('mock-data-btn').addEventListener('click', async () => {
            if (confirm('Generare dati di test Stripe?')) {
                const res = await apiRequest('insert_mock_data');
                if (res.status === 'success') {
                    document.getElementById('mock-data-result').innerHTML = `
                        <div class="alert alert-success p-2 mt-2">
                            <i class="bi bi-check-circle"></i> Inseriti ${res.inserted} eventi
                        </div>
                    `;
                    loadTables();
                }
            }
        });

        // Event listeners
        document.getElementById('table-select').addEventListener('change', function() {
            loadTableData(this.value);
        });
        
        document.getElementById('refresh-btn').addEventListener('click', function() {
            const table = document.getElementById('table-select').value;
            if (table) loadTableData(table);
        });

        // Inizializzazione
        testConnection();
        loadTables();
    });
    </script>
</body>
</html>

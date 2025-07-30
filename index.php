<?php
session_start();

// ==============================================
// CARICAMENTO VARIABILI D'AMBIENTE (senza dipendenze esterne)
// ==============================================

function loadEnv() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Rimuovi virgolette se presenti
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

loadEnv();

// Funzione helper per leggere env con default
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// ==============================================
// CONFIGURAZIONE DA VARIABILI D'AMBIENTE
// ==============================================

$DB_CONFIG = [
    'host'     => env('DB_HOST'),
    'port'     => (int)env('DB_PORT', '5432'),
    'dbname'   => env('DB_NAME'),
    'user'     => env('DB_USER'),
    'password' => env('DB_PASSWORD'),
    'ssl_mode' => env('DB_SSL_MODE', 'require')
];

$ADMIN_CREDENTIALS = [
    'username' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD') ? password_hash(env('ADMIN_PASSWORD'), PASSWORD_DEFAULT) 
                  : password_hash('admin123', PASSWORD_DEFAULT)
];

// Verifica credenziali obbligatorie
$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($requiredVars as $var) {
    if (empty(env($var))) {
        die("<div class='alert alert-danger'>Errore: Variabile d'ambiente $var mancante</div>");
    }
}

// ==============================================
// AUTENTICAZIONE
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_CREDENTIALS['username'] && 
        password_verify($_POST['password'], $ADMIN_CREDENTIALS['password'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $ADMIN_CREDENTIALS['username'];
        header("Location: ?");
        exit;
    } else {
        $loginError = "Credenziali non valide";
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
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
                $response = [
                    'status' => 'success',
                    'data' => $pdo->query("SELECT NOW() AS db_time, version() AS pg_version")->fetch()
                ];
                break;
                
            case 'get_tables':
                $tables = $pdo->query("
                    SELECT table_name 
                    FROM information_schema.tables 
                    WHERE table_schema = 'public'
                    ORDER BY table_name
                ")->fetchAll(PDO::FETCH_COLUMN);
                
                $response = ['status' => 'success', 'data' => $tables];
                break;
                
            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }
                
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $stmt = $pdo->prepare("SELECT * FROM $table LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $data = $stmt->fetchAll();
                $columns = $data ? array_keys($data[0]) : [];
                
                $response = [
                    'status' => 'success',
                    'data' => $data,
                    'columns' => $columns,
                    'total' => (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()
                ];
                break;
                
            case 'insert_mock_data':
                $mockEvents = [
                    [
                        'type' => 'payment_intent.succeeded',
                        'data' => [
                            'id' => 'pi_' . bin2hex(random_bytes(8)),
                            'amount' => rand(1000, 10000),
                            'currency' => 'usd',
                            'status' => 'succeeded',
                            'created' => time()
                        ]
                    ],
                    [
                        'type' => 'charge.succeeded',
                        'data' => [
                            'id' => 'ch_' . bin2hex(random_bytes(8)),
                            'amount' => rand(500, 5000),
                            'currency' => 'eur',
                            'paid' => true
                        ]
                    ]
                ];
                
                $inserted = 0;
                $stmt = $pdo->prepare("INSERT INTO stripe_events (event_type, payload) VALUES (?, ?)");
                
                foreach ($mockEvents as $event) {
                    if ($stmt->execute([$event['type'], json_encode($event['data'])])) {
                        $inserted++;
                    }
                }
                
                $response = ['status' => 'success', 'inserted' => $inserted];
                break;
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    if ($action) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
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
            body { background-color: #f8f9fa; }
            .login-container {
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-card card">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Accesso Admin</h2>
                    <?php if (isset($loginError)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
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
        .navbar { background-color: var(--primary) !important; }
        .card-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.2s;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .table-container {
            max-height: 65vh;
            overflow-y: auto;
        }
        .cursor-pointer { cursor: pointer; }
        .monospace { font-family: SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <span class="navbar-brand">PostgreSQL Admin</span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Benvenuto, <?= htmlspecialchars($_SESSION['username']) ?></span>
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
                        <small class="text-muted">Database: <?= htmlspecialchars($DB_CONFIG['dbname']) ?></small>
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
                            <option value="">Caricamento in corso...</option>
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
                <div>
                    <button id="refresh-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Aggiorna
                    </button>
                </div>
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
        // Funzione per escape HTML
        function escapeHtml(unsafe) {
            return unsafe?.toString()?.replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[m];
            }) || '';
        }
        
        // Funzione per mostrare errori
        function showError(error) {
            console.error(error);
            alert('Errore: ' + (error.message || error.statusText || 'Errore sconosciuto'));
        }
        
        // Test connessione al database
        function testConnection() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_connection'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const dbStatus = document.getElementById('db-status');
                    dbStatus.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success"></i> Connesso
                        <div class="small mt-1">${escapeHtml(data.data.pg_version)}</div>
                        <div class="small">${escapeHtml(data.data.db_time)}</div>
                    `;
                }
            })
            .catch(showError);
        }
        
        // Carica elenco tabelle
        function loadTables() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_tables'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const select = document.getElementById('table-select');
                    select.innerHTML = '<option value="">Seleziona una tabella</option>';
                    
                    data.data.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table;
                        option.textContent = table;
                        select.appendChild(option);
                    });
                }
            })
            .catch(showError);
        }
        
        // Carica dati tabella
        function loadTableData(table, page = 1) {
            if (!table) return;
            
            const formData = new FormData();
            formData.append('action', 'get_table_data');
            formData.append('table', table);
            formData.append('page', page);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Errore nel caricamento dati');
                }
                
                const tableEl = document.getElementById('data-table');
                const thead = tableEl.querySelector('thead');
                const tbody = tableEl.querySelector('tbody');
                
                // Intestazione
                thead.innerHTML = '';
                if (data.columns && data.columns.length > 0) {
                    const headerRow = document.createElement('tr');
                    data.columns.forEach(col => {
                        const th = document.createElement('th');
                        th.textContent = col;
                        headerRow.appendChild(th);
                    });
                    thead.appendChild(headerRow);
                }
                
                // Dati
                tbody.innerHTML = '';
                if (data.data && data.data.length > 0) {
                    data.data.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.className = 'cursor-pointer';
                        
                        data.columns.forEach(col => {
                            const td = document.createElement('td');
                            const value = row[col];
                            
                            if (value === null) {
                                td.innerHTML = '<em>NULL</em>';
                            } else if (typeof value === 'object') {
                                td.textContent = JSON.stringify(value);
                            } else {
                                td.textContent = value;
                            }
                            
                            tr.appendChild(td);
                        });
                        
                        tbody.appendChild(tr);
                    });
                }
                
                // Paginazione
                const totalPages = Math.ceil(data.total / 20);
                const pagination = document.getElementById('pagination');
                pagination.innerHTML = '';
                
                if (totalPages > 1) {
                    for (let i = 1; i <= totalPages; i++) {
                        const li = document.createElement('li');
                        li.className = `page-item ${i === page ? 'active' : ''}`;
                        
                        const a = document.createElement('a');
                        a.className = 'page-link';
                        a.href = '#';
                        a.textContent = i;
                        
                        a.addEventListener('click', (e) => {
                            e.preventDefault();
                            loadTableData(table, i);
                        });
                        
                        li.appendChild(a);
                        pagination.appendChild(li);
                    }
                }
            })
            .catch(showError);
        }
        
        // Genera dati fittizi
        document.getElementById('mock-data-btn').addEventListener('click', function() {
            if (confirm('Generare dati di test Stripe?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=insert_mock_data'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const resultDiv = document.getElementById('mock-data-result');
                        resultDiv.innerHTML = `
                            <div class="alert alert-success p-2 mt-2">
                                <i class="bi bi-check-circle"></i> Inseriti ${data.inserted} eventi fittizi
                            </div>
                        `;
                        loadTables();
                    }
                })
                .catch(showError);
            }
        });
        
        // Gestione selezione tabella
        document.getElementById('table-select').addEventListener('change', function() {
            loadTableData(this.value);
        });
        
        // Aggiorna dati
        document.getElementById('refresh-btn').addEventListener('click', function() {
            const table = document.getElementById('table-select').value;
            if (table) {
                loadTableData(table);
            }
        });
        
        // Inizializzazione
        testConnection();
        loadTables();
    });
    </script>
</body>
</html>

<?php
session_start();

// ==============================================
// CONFIGURAZIONE E AUTENTICAZIONE
// ==============================================

// Funzione per leggere variabili d'ambiente con fallback
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// Carica variabili da .env se il file esiste
if (file_exists(__DIR__.'/.env')) {
    $env_lines = file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Configurazione Database
$db_config = [
    'host'     => env('DB_HOST'),
    'port'     => (int)env('DB_PORT', 5432),
    'dbname'   => env('DB_NAME'),
    'user'     => env('DB_USER'),
    'password' => env('DB_PASSWORD'),
    'ssl_mode' => env('DB_SSL_MODE', 'require')
];

// Configurazione Admin
$admin_credentials = [
    'username' => env('ADMIN_USER', 'admin'),
    'password' => password_hash(env('ADMIN_PASSWORD', 'admin123'), PASSWORD_DEFAULT)
];

// Verifica credenziali obbligatorie
$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($required_vars as $var) {
    if (empty(env($var))) {
        die("<div class='alert alert-danger'>Errore: La variabile $var non è configurata</div>");
    }
}

// Gestione Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $admin_credentials['username'] && 
        password_verify($_POST['password'], $admin_credentials['password'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $admin_credentials['username'];
    } else {
        $login_error = "Credenziali non valide";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ==============================================
// FUNZIONI DATABASE
// ==============================================

function getPDO() {
    global $db_config;
    try {
        $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s",
            $db_config['host'],
            $db_config['port'],
            $db_config['dbname']);

        return new PDO($dsn, $db_config['user'], $db_config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        die("<div class='alert alert-danger'>Errore di connessione: ".htmlspecialchars($e->getMessage())."</div>");
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
                $response = ['status' => 'success', 'data' => $pdo->query("SELECT NOW() AS time")->fetch()];
                break;
                
            case 'get_tables':
                $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
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
                
                $stmt = $pdo->prepare("SELECT * FROM $table LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $data = $stmt->fetchAll();
                
                $response = [
                    'status' => 'success',
                    'data' => $data,
                    'columns' => !empty($data) ? array_keys($data[0]) : []
                ];
                break;
                
            case 'insert_mock_data':
                $mock_events = [
                    'payment_intent.succeeded' => [
                        'id' => 'pi_'.bin2hex(random_bytes(8)),
                        'amount' => rand(100, 10000),
                        'currency' => 'usd',
                        'status' => 'succeeded',
                        'created' => time()
                    ],
                    'charge.succeeded' => [
                        'id' => 'ch_'.bin2hex(random_bytes(8)),
                        'amount' => rand(100, 5000),
                        'currency' => 'eur',
                        'paid' => true
                    ]
                ];
                
                $inserted = 0;
                foreach ($mock_events as $type => $data) {
                    $stmt = $pdo->prepare("INSERT INTO stripe_events (event_type, payload) VALUES (?, ?)");
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
        <title>Login Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f8f9fa; }
            .login-container { max-width: 400px; margin-top: 100px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12 login-container">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h2 class="text-center mb-4">Accesso Amministratore</h2>
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
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Pagina Principale Admin
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PostgreSQL Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #6772e5;
            --secondary: #6c757d;
        }
        body { background-color: #f8f9fa; }
        .navbar { background-color: var(--primary); }
        .card-hover:hover { transform: translateY(-5px); transition: transform 0.3s; }
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
                <span class="text-white me-3">Benvenuto, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout=1" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container pb-5">
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
                        <small class="text-muted d-block mt-2">Host: <?= htmlspecialchars($db_config['host']) ?></small>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(function() {
        // Funzione per escape HTML
        const escapeHtml = (unsafe) => unsafe?.toString()?.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            '"': '&quot;', "'": '&#039;'
        }[m])) || '';
        
        // Carica elenco tabelle
        function loadTables() {
            $.post('', {action: 'get_tables'}, function(res) {
                let options = '<option value="">Seleziona tabella</option>';
                $.each(res.data || [], function(i, table) {
                    options += `<option value="${escapeHtml(table)}">${escapeHtml(table)}</option>`;
                });
                $('#table-select').html(options);
            }, 'json').fail(showError);
        }
        
        // Carica dati tabella
        function loadTableData(table, page = 1) {
            if (!table) return;
            
            $.post('', {
                action: 'get_table_data',
                table: table,
                page: page
            }, function(res) {
                if (res.error) return showError(res.error);
                
                // Intestazione
                let thead = '<tr>';
                $.each(res.columns || [], function(i, col) {
                    thead += `<th>${escapeHtml(col)}</th>`;
                });
                thead += '</tr>';
                $('#data-table thead').html(thead);
                
                // Corpo
                let tbody = '';
                $.each(res.data || [], function(i, row) {
                    let tr = '<tr class="cursor-pointer">';
                    $.each(row, function(key, val) {
                        tr += `<td>${val === null ? '<em>NULL</em>' : escapeHtml(val?.toString())}</td>`;
                    });
                    tbody += tr + '</tr>';
                });
                $('#data-table tbody').html(tbody);
                
                // Inizializza DataTable
                $('#data-table').DataTable({
                    responsive: true,
                    destroy: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json'
                    }
                });
            }, 'json').fail(showError);
        }
        
        // Genera dati fittizi
        $('#mock-data-btn').click(function() {
            if (confirm('Generare dati di test Stripe?')) {
                $.post('', {action: 'insert_mock_data'}, function(res) {
                    $('#mock-data-result').html(
                        `<div class="alert alert-success p-2">
                            <i class="bi bi-check-circle"></i> Inseriti ${res.inserted} eventi
                        </div>`
                    );
                    loadTables();
                }, 'json').fail(showError);
            }
        });
        
        // Gestione selezione tabella
        $('#table-select').change(function() {
            loadTableData($(this).val());
        });
        
        // Aggiorna dati
        $('#refresh-btn').click(function() {
            const table = $('#table-select').val();
            if (table) loadTableData(table);
        });
        
        // Mostra errori
        function showError(err) {
            console.error(err);
            const msg = err.responseJSON?.message || err.statusText || 'Errore sconosciuto';
            alert(`Errore: ${msg}`);
        }
        
        // Inizializzazione
        loadTables();
        
        // Test automatico connessione
        $.post('', {action: 'test_connection'}, function(res) {
            if (res.error) {
                $('#db-status').html(`<i class="bi bi-x-circle-fill text-danger"></i> Errore`);
            }
        }, 'json');
    });
    </script>
</body>
</html>

<?php
session_start();

// ==============================================
// CARICAMENTO VARIABILI D'AMBIENTE (senza librerie esterne)
// ==============================================
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// ==============================================
// CONFIGURAZIONE DA ENV
// ==============================================
$DB_CONFIG = [
    'host'     => $_ENV['DB_HOST'] ?? getenv('DB_HOST'),
    'port'     => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 5432,
    'dbname'   => $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
    'user'     => $_ENV['DB_USER'] ?? getenv('DB_USER'),
    'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD'),
    'ssl_mode' => $_ENV['DB_SSL_MODE'] ?? getenv('DB_SSL_MODE') ?? 'require'
];

$ADMIN_CREDENTIALS = [
    'username' => $_ENV['ADMIN_USER'] ?? getenv('ADMIN_USER') ?? 'admin',
    'password' => $_ENV['ADMIN_PASSWORD'] ? password_hash($_ENV['ADMIN_PASSWORD'], PASSWORD_DEFAULT) 
                : (getenv('ADMIN_PASSWORD') ? password_hash(getenv('ADMIN_PASSWORD'), PASSWORD_DEFAULT)
                : password_hash('admin123', PASSWORD_DEFAULT))
];

// ==============================================
// AUTENTICAZIONE
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_CREDENTIALS['username'] && 
        password_verify($_POST['password'], $ADMIN_CREDENTIALS['password'])) {
        $_SESSION['authenticated'] = true;
    } else {
        $login_error = "Credenziali non valide";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ==============================================
// CONNESSIONE AL DATABASE
// ==============================================
function getPDO() {
    global $DB_CONFIG;
    try {
        $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s",
            $DB_CONFIG['host'],
            $DB_CONFIG['port'],
            $DB_CONFIG['dbname']);

        return new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        die("<div class='alert alert-danger'>Errore di connessione al database: " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

// ==============================================
// GESTIONE AZIONI
// ==============================================
$action = $_POST['action'] ?? '';
$response = [];

if ($_SESSION['authenticated'] ?? false) {
    try {
        $pdo = getPDO();
        
        switch ($action) {
            case 'get_tables':
                $response['data'] = $pdo->query("
                    SELECT table_name 
                    FROM information_schema.tables 
                    WHERE table_schema = 'public'
                ")->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }
                
                $page = max(1, intval($_POST['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $response['data'] = $pdo->prepare("
                    SELECT * FROM {$table} 
                    LIMIT ? OFFSET ?
                ")->execute([$limit, $offset])->fetchAll();
                break;
                
            case 'insert_mock_data':
                $mock_events = [
                    'payment_intent.succeeded' => [
                        'id' => 'pi_' . bin2hex(random_bytes(8)),
                        'amount' => rand(100, 10000),
                        'currency' => 'usd',
                        'status' => 'succeeded',
                        'created' => time()
                    ],
                    // Altri eventi Stripe...
                ];
                
                foreach ($mock_events as $type => $data) {
                    $pdo->prepare("
                        INSERT INTO stripe_events (event_type, payload) 
                        VALUES (?, ?)
                    ")->execute([$type, json_encode($data)]);
                }
                
                $response['inserted'] = count($mock_events);
                break;
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
}

// ==============================================
// OUTPUT HTML
// ==============================================
if (!($_SESSION['authenticated'] ?? false)) {
    // Mostra form di login
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Login Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
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

// Se autenticato, mostra il pannello admin
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PostgreSQL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.3s;
        }
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand">PostgreSQL Admin</span>
            <div class="ms-auto">
                <a href="?logout=1" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-database"></i> Stato Database
                        </h5>
                        <div id="db-status" class="text-success">
                            <i class="bi bi-check-circle-fill"></i> Connesso
                        </div>
                        <pre class="mt-2 small"><?= htmlspecialchars(json_encode($DB_CONFIG, JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-earmark-plus"></i> Dati Fittizi
                        </h5>
                        <button id="mock-data-btn" class="btn btn-primary">
                            <i class="bi bi-magic"></i> Genera Dati Stripe
                        </button>
                        <div id="mock-data-result" class="mt-2 small"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card card-hover h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-list-check"></i> Tabelle Disponibili
                        </h5>
                        <select id="table-select" class="form-select">
                            <option value="">Caricamento...</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabella Dati -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dati Tabella</h5>
                <button id="refresh-btn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Aggiorna
                </button>
            </div>
            <div class="table-container">
                <table id="data-table" class="table table-striped">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="card-footer">
                <nav id="pagination" class="d-flex justify-content-center"></nav>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(function() {
        // Carica tabelle
        function loadTables() {
            $.post('', {action: 'get_tables'}, function(res) {
                let options = '<option value="">Seleziona tabella</option>';
                $.each(res.data || [], function(i, table) {
                    options += `<option value="${table}">${table}</option>`;
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
                if (res.error) {
                    showError(res.error);
                    return;
                }
                
                // Costruisci intestazione
                let thead = '';
                if (res.data && res.data.length > 0) {
                    $.each(Object.keys(res.data[0]), function(i, col) {
                        thead += `<th>${col}</th>`;
                    });
                }
                $('#data-table thead').html(`<tr>${thead}</tr>`);
                
                // Costruisci corpo
                let tbody = '';
                $.each(res.data || [], function(i, row) {
                    let tr = '';
                    $.each(row, function(key, val) {
                        tr += `<td>${val === null ? '<em>NULL</em>' : escapeHtml(val.toString())}</td>`;
                    });
                    tbody += `<tr>${tr}</tr>`;
                });
                $('#data-table tbody').html(tbody);
            }, 'json').fail(showError);
        }
        
        // Genera dati fittizi
        $('#mock-data-btn').click(function() {
            if (confirm('Generare dati di test Stripe?')) {
                $.post('', {action: 'insert_mock_data'}, function(res) {
                    $('#mock-data-result').html(
                        `<div class="alert alert-success">
                            Inseriti ${res.inserted} eventi fittizi
                        </div>`
                    );
                    loadTables();
                }, 'json').fail(showError);
            }
        });
        
        // Selezione tabella
        $('#table-select').change(function() {
            loadTableData($(this).val());
        });
        
        // Aggiorna dati
        $('#refresh-btn').click(function() {
            loadTableData($('#table-select').val());
        });
        
        // Helper per escape HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Mostra errori
        function showError(err) {
            console.error(err);
            alert('Errore: ' + (err.responseJSON?.error || err.statusText || 'Errore sconosciuto'));
        }
        
        // Inizializzazione
        loadTables();
    });
    </script>
</body>
</html>

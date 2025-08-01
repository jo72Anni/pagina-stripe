<?php
// Configurazione di sicurezza e sessione
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Configurazione ambiente
define('ENVIRONMENT', 'development'); // Cambiare in 'production' in produzione

// Mostra errori solo in sviluppo
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Autenticazione semplice (modificare per produzione)
$valid_username = 'admin';
$valid_password_hash = password_hash('your_strong_password', PASSWORD_DEFAULT);

// Gestione login/logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: '.str_replace('?logout', '', $_SERVER['REQUEST_URI']));
    exit;
}

if (!isset($_SESSION['loggedin']) && isset($_POST['login'])) {
    if ($_POST['username'] === $valid_username && password_verify($_POST['password'], $valid_password_hash)) {
        $_SESSION['loggedin'] = true;
    } else {
        $login_error = 'Credenziali non valide';
    }
}

// Configurazione DB con fallback a variabili d'ambiente
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSL_MODE') ?: 'require'
];

// Funzione connessione con gestione errori migliorata
function getConnection($config) {
    try {
        $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s",
            $config['host'],
            $config['port'],
            $config['dbname']);
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ];
        
        if ($config['ssl_mode'] === 'require') {
            $options[PDO::PGSQL_ATTR_SSL_MODE] = PDO::PGSQL_SSL_REQUIRE;
        }
        
        return new PDO($dsn, $config['user'], $config['password'], $options);
    } catch (PDOException $e) {
        error_log('Database connection error: '.$e->getMessage());
        if (ENVIRONMENT === 'development') {
            die('Database connection failed: '.$e->getMessage());
        } else {
            die('Database connection failed');
        }
    }
}

// Gestione azioni AJAX solo se autenticati
$action = $_POST['action'] ?? '';
$response = [];

if ($action && (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true)) {
    $response = ['status' => 'error', 'message' => 'Non autorizzato'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($action && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $pdo = getConnection($dbConfig);

        switch ($action) {
            case 'test_connection':
                $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                $response = [
                    'status' => 'success', 
                    'data' => [
                        'time' => $pdo->query("SELECT NOW() AS time")->fetchColumn(),
                        'version' => $serverVersion,
                        'tables_count' => $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public'")->fetchColumn()
                    ]
                ];
                break;

            case 'get_tables':
                $tables = $pdo->query("
                    SELECT table_name, 
                           pg_size_pretty(pg_total_relation_size('\"' || table_name || '\"')) as size,
                           (SELECT reltuples FROM pg_class WHERE relname = table_name) as estimated_rows
                    FROM information_schema.tables 
                    WHERE table_schema='public'
                    ORDER BY table_name
                ")->fetchAll();
                $response = ['status' => 'success', 'data' => $tables];
                break;

            case 'get_table_structure':
                $table = $_POST['table'] ?? '';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }
                
                $columns = $pdo->query("
                    SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns 
                    WHERE table_name = '$table'
                    ORDER BY ordinal_position
                ")->fetchAll();
                
                $indexes = $pdo->query("
                    SELECT indexname, indexdef 
                    FROM pg_indexes 
                    WHERE tablename = '$table'
                ")->fetchAll();
                
                $response = [
                    'status' => 'success',
                    'data' => [
                        'columns' => $columns,
                        'indexes' => $indexes
                    ]
                ];
                break;

            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                $page = (int)($_POST['page'] ?? 1);
                $limit = (int)($_POST['limit'] ?? 50);
                $limit = max(1, min($limit, 1000)); // Limita tra 1 e 1000
                $offset = ($page - 1) * $limit;
                $search = $_POST['search'] ?? '';
                $sortColumn = $_POST['sort'] ?? '';
                $sortDirection = $_POST['dir'] ?? 'ASC';

                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }

                // Verifica che la tabella esista
                $tableExists = $pdo->query("SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = 'public' AND table_name = '$table'
                )")->fetchColumn();
                
                if (!$tableExists) {
                    throw new Exception("La tabella non esiste");
                }

                // Ottieni le colonne
                $columns = $pdo->query("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = '$table'
                    ORDER BY ordinal_position
                ")->fetchAll(PDO::FETCH_COLUMN);

                // Costruzione query
                $where = '';
                $params = [];
                if ($search) {
                    $searchTerms = [];
                    foreach ($columns as $col) {
                        $searchTerms[] = "$col::text ILIKE ?";
                        $params[] = "%$search%";
                    }
                    $where = 'WHERE ' . implode(' OR ', $searchTerms);
                }

                // Ordinamento sicuro
                $orderBy = '';
                if ($sortColumn && in_array($sortColumn, $columns)) {
                    $orderBy = "ORDER BY \"$sortColumn\" $sortDirection";
                }

                // Query per i dati
                $query = "SELECT * FROM \"$table\" $where $orderBy LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                // Conteggio totale per paginazione
                $countQuery = "SELECT COUNT(*) FROM \"$table\" $where";
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute(array_slice($params, 0, -2));
                $total = $countStmt->fetchColumn();

                $response = [
                    'status' => 'success',
                    'data' => $rows,
                    'columns' => $columns,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ];
                break;

            case 'execute_query':
                $query = $_POST['query'] ?? '';
                $limit = 1000; // Limite di sicurezza
                
                if (empty(trim($query))) {
                    throw new Exception("Query vuota");
                }
                
                // Rileva il tipo di query
                $queryType = strtoupper(strtok(trim($query), " "));
                
                // Esegui la query appropriata
                if (in_array($queryType, ['SELECT', 'SHOW', 'WITH'])) {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $data = $stmt->fetchAll();
                    
                    if (count($data) > $limit) {
                        $data = array_slice($data, 0, $limit);
                        $response = [
                            'status' => 'partial',
                            'message' => 'Mostrati solo i primi '.$limit.' risultati',
                            'data' => $data,
                            'columns' => array_keys($data[0] ?? [])
                        ];
                    } else {
                        $response = [
                            'status' => 'success',
                            'data' => $data,
                            'columns' => array_keys($data[0] ?? [])
                        ];
                    }
                } else {
                    // Query di modifica (INSERT, UPDATE, DELETE, etc.)
                    $affected = $pdo->exec($query);
                    $response = [
                        'status' => 'success',
                        'message' => 'Query eseguita con successo',
                        'affected_rows' => $affected
                    ];
                }
                break;

            case 'export_data':
                $table = $_POST['table'] ?? '';
                $format = $_POST['format'] ?? 'csv';
                
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }
                
                // Verifica che la tabella esista
                $tableExists = $pdo->query("SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = 'public' AND table_name = '$table'
                )")->fetchColumn();
                
                if (!$tableExists) {
                    throw new Exception("La tabella non esiste");
                }
                
                // Ottieni i dati
                $stmt = $pdo->query("SELECT * FROM \"$table\"");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($format === 'json') {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="'.$table.'_export.json"');
                    echo json_encode($data, JSON_PRETTY_PRINT);
                    exit;
                } else {
                    // CSV export
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="'.$table.'_export.csv"');
                    
                    $output = fopen('php://output', 'w');
                    
                    // Intestazioni
                    if (!empty($data)) {
                        fputcsv($output, array_keys($data[0]));
                        
                        // Dati
                        foreach ($data as $row) {
                            fputcsv($output, $row);
                        }
                    }
                    
                    fclose($output);
                    exit;
                }
                break;

            default:
                $response = ['status' => 'error', 'message' => 'Azione non valida'];
        }
    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Pagina di login se non autenticato
if (!isset($_SESSION['loggedin']) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PostgreSQL Admin - Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #f8f9fa; }
            .login-container { max-width: 400px; margin-top: 100px; }
            .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="login-container mx-auto">
                <div class="card">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">PostgreSQL Admin</h2>
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Accedi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Interfaccia principale
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-dark.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 20px;
            background: #1a252f;
        }
        .sidebar-menu {
            padding: 0;
            list-style: none;
        }
        .sidebar-menu li a {
            display: block;
            padding: 12px 20px;
            color: #b8c7ce;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            color: white;
            background: #1e282c;
        }
        .sidebar-menu li a i {
            margin-right: 10px;
        }
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
        }
        .dashboard-card {
            transition: all 0.3s;
            border-left: 4px solid;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .table-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-postgres {
            background-color: #336791;
            color: white;
        }
        .query-editor {
            min-height: 150px;
            font-family: monospace;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .hljs {
            background: transparent !important;
        }
        .table-sm td, .table-sm th {
            padding: 0.3rem;
        }
        .table-details td {
            vertical-align: top;
        }
        .table-details td:first-child {
            font-weight: 600;
            width: 30%;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">PostgreSQL Admin</h5>
            <button class="btn btn-sm btn-outline-light d-md-none" id="sidebarToggle">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li><a href="#" class="active" data-section="dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="#" data-section="tables"><i class="bi bi-table"></i> Tabelle</a></li>
            <li><a href="#" data-section="query"><i class="bi bi-code-square"></i> Query Editor</a></li>
            <li><a href="#" data-section="backup"><i class="bi bi-database"></i> Backup/Ripristino</a></li>
            <li><a href="#" data-section="users"><i class="bi bi-people"></i> Utenti</a></li>
            <li><a href="#" data-section="settings"><i class="bi bi-gear"></i> Impostazioni</a></li>
            <li><a href="?logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
        <div class="px-3 py-2 position-absolute bottom-0 w-100 text-white-50 small">
            <div class="mb-1">Connesso a: <span class="text-white"><?= htmlspecialchars($dbConfig['host']) ?></span></div>
            <div class="mb-1">Utente DB: <span class="text-white"><?= htmlspecialchars($dbConfig['user']) ?></span></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-3">
            <!-- Dashboard Section -->
            <div id="dashboardSection">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-speedometer2 me-2"></i> Dashboard</h4>
                    <button class="btn btn-sm btn-primary" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Aggiorna
                    </button>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card border-left-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-primary">Tabelle</h6>
                                        <h3 id="tablesCount">-</h3>
                                    </div>
                                    <div class="icon-circle bg-primary text-white">
                                        <i class="bi bi-table"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card border-left-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-success">Dimensione DB</h6>
                                        <h3 id="dbSize">-</h3>
                                    </div>
                                    <div class="icon-circle bg-success text-white">
                                        <i class="bi bi-hdd"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card border-left-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-info">Connessioni</h6>
                                        <h3 id="connectionsCount">-</h3>
                                    </div>
                                    <div class="icon-circle bg-info text-white">
                                        <i class="bi bi-plug"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card border-left-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-warning">Versione</h6>
                                        <h3 id="dbVersion">-</h3>
                                    </div>
                                    <div class="icon-circle bg-warning text-white">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Attività Recente</span>
                                <span class="badge bg-primary">Live</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0" id="activityTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Query</th>
                                                <th>Durata</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                Statistiche Tabelle
                            </div>
                            <div class="card-body">
                                <canvas id="tablesChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tables Section -->
            <div id="tablesSection" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-table me-2"></i> Gestione Tabelle</h4>
                    <div>
                        <button class="btn btn-sm btn-success me-2" id="createTableBtn">
                            <i class="bi bi-plus-circle"></i> Nuova Tabella
                        </button>
                        <button class="btn btn-sm btn-primary" id="refreshTablesBtn">
                            <i class="bi bi-arrow-clockwise"></i> Aggiorna
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-tab="tables-list">Lista Tabelle</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-tab="table-structure">Struttura</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-tab="table-data">Dati</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <!-- Tables List Tab -->
                        <div id="tables-list-tab">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tablesTable">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Righe (stimate)</th>
                                            <th>Dimensione</th>
                                            <th>Ultima Modifica</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Table Structure Tab -->
                        <div id="table-structure-tab" class="d-none">
                            <div class="alert alert-info">
                                Seleziona una tabella dalla lista per visualizzarne la struttura
                            </div>
                            <div id="tableStructureContent"></div>
                        </div>
                        
                        <!-- Table Data Tab -->
                        <div id="table-data-tab" class="d-none">
                            <div class="alert alert-info">
                                Seleziona una tabella dalla lista per visualizzarne i dati
                            </div>
                            <div id="tableDataContent"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Query Editor Section -->
            <div id="querySection" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-code-square me-2"></i> Query Editor</h4>
                    <div>
                        <button class="btn btn-sm btn-success me-2" id="saveQueryBtn">
                            <i class="bi bi-save"></i> Salva
                        </button>
                        <button class="btn btn-sm btn-primary" id="executeQueryBtn">
                            <i class="bi bi-play"></i> Esegui
                        </button>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body p-0">
                        <textarea id="queryEditor" class="form-control query-editor" placeholder="Scrivi la tua query SQL qui..."></textarea>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Risultati
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0" id="queryResultsTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="queryMessage" class="p-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Backup Section -->
            <div id="backupSection" class="d-none">
                <h4><i class="bi bi-database me-2"></i> Backup & Ripristino</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-cloud-arrow-down me-2"></i> Backup Database
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Formato</label>
                                    <select class="form-select" id="backupFormat">
                                        <option value="sql">SQL (Dump)</option>
                                        <option value="csv">CSV</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tabelle (lasciare vuoto per tutto il DB)</label>
                                    <select class="form-select" id="backupTables" multiple>
                                        <!-- Popolato via JS -->
                                    </select>
                                </div>
                                <button class="btn btn-primary" id="generateBackupBtn">
                                    <i class="bi bi-download me-2"></i> Genera Backup
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-cloud-arrow-up me-2"></i> Ripristino Database
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">File di Backup</label>
                                    <input type="file" class="form-control" id="restoreFile">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="dropTablesCheck">
                                        <label class="form-check-label" for="dropTablesCheck">
                                            Elimina tabelle esistenti prima del ripristino
                                        </label>
                                    </div>
                                </div>
                                <button class="btn btn-warning" id="restoreBackupBtn">
                                    <i class="bi bi-upload me-2"></i> Ripristina
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Section -->
            <div id="usersSection" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-people me-2"></i> Gestione Utenti</h4>
                    <button class="btn btn-sm btn-success" id="createUserBtn">
                        <i class="bi bi-plus-circle"></i> Nuovo Utente
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Superuser</th>
                                        <th>Creazione</th>
                                        <th>Valido fino</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Section -->
            <div id="settingsSection" class="d-none">
                <h4><i class="bi bi-gear me-2"></i> Impostazioni</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                Configurazione Connessione
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-details">
                                        <tbody>
                                            <tr>
                                                <td>Host</td>
                                                <td><?= htmlspecialchars($dbConfig['host']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Porta</td>
                                                <td><?= htmlspecialchars($dbConfig['port']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Database</td>
                                                <td><?= htmlspecialchars($dbConfig['dbname']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Utente</td>
                                                <td><?= htmlspecialchars($dbConfig['user']) ?></td>
                                            </tr>
                                            <tr>
                                                <td>Modalità SSL</td>
                                                <td><?= htmlspecialchars($dbConfig['ssl_mode']) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                Impostazioni Interfaccia
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Tema</label>
                                    <select class="form-select" id="themeSelect">
                                        <option value="light">Light</option>
                                        <option value="dark">Dark</option>
                                        <option value="system">Sistema</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Righe per pagina</label>
                                    <input type="number" class="form-control" id="rowsPerPage" min="10" max="500" value="50">
                                </div>
                                <button class="btn btn-primary" id="saveSettingsBtn">
                                    <i class="bi bi-save me-2"></i> Salva Impostazioni
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="createTableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crea Nuova Tabella</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm">
                        <div class="mb-3">
                            <label class="form-label">Nome Tabella</label>
                            <input type="text" class="form-control" name="tableName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Colonne</label>
                            <div id="columnsContainer">
                                <div class="row g-2 mb-2 column-row">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" placeholder="Nome" name="columns[0][name]" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="columns[0][type]" required>
                                            <option value="">Tipo</option>
                                            <option value="integer">Integer</option>
                                            <option value="bigint">Bigint</option>
                                            <option value="serial">Serial</option>
                                            <option value="text">Text</option>
                                            <option value="varchar">Varchar</option>
                                            <option value="boolean">Boolean</option>
                                            <option value="date">Date</option>
                                            <option value="timestamp">Timestamp</option>
                                            <option value="numeric">Numeric</option>
                                            <option value="json">JSON</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" placeholder="Lunghezza" name="columns[0][length]">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex">
                                            <div class="form-check me-2">
                                                <input class="form-check-input" type="checkbox" name="columns[0][primary]" id="col0Primary">
                                                <label class="form-check-label" for="col0Primary">PK</label>
                                            </div>
                                            <div class="form-check me-2">
                                                <input class="form-check-input" type="checkbox" name="columns[0][nullable]" id="col0Nullable" checked>
                                                <label class="form-check-label" for="col0Nullable">NULL</label>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger ms-auto remove-column">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" id="addColumnBtn">
                                <i class="bi bi-plus"></i> Aggiungi Colonna
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="submitCreateTable">Crea Tabella</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crea Nuovo Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="superuser" id="superuserCheck">
                                <label class="form-check-label" for="superuserCheck">Superuser</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="submitCreateUser">Crea Utente</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="queryHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cronologia Query</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="queryHistoryTable">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Data</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/it.min.js"></script>
    
    <script>
    // Variabili globali
    let currentTable = null;
    let queryHistory = JSON.parse(localStorage.getItem('queryHistory') || [];
    let appSettings = JSON.parse(localStorage.getItem('pgAdminSettings')) || {
        theme: 'light',
        rowsPerPage: 50
    };
    
    // Inizializzazione
    $(document).ready(function() {
        // Applica le impostazioni
        applySettings();
        
        // Gestione sidebar mobile
        $('#sidebarToggle').click(function() {
            $('.sidebar').toggleClass('active');
            $('.main-content').toggleClass('active');
        });
        
        // Navigazione tra sezioni
        $('[data-section]').click(function(e) {
            e.preventDefault();
            const section = $(this).data('section');
            
            // Nascondi tutte le sezioni
            $('[id$="Section"]').addClass('d-none');
            
            // Mostra la sezione selezionata
            $(`#${section}Section`).removeClass('d-none');
            
            // Aggiorna menu attivo
            $('.sidebar-menu li a').removeClass('active');
            $(this).addClass('active');
            
            // Carica i dati della sezione se necessario
            if (section === 'dashboard') {
                loadDashboard();
            } else if (section === 'tables') {
                loadTables();
            } else if (section === 'users') {
                loadUsers();
            }
        });
        
        // Gestione tabs tabelle
        $('[data-tab]').click(function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            
            // Aggiorna tab attivo
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            
            // Nascondi tutti i tab content
            $('[id$="-tab"]').addClass('d-none');
            
            // Mostra il tab selezionato
            $(`#${tab}-tab`).removeClass('d-none');
        });
        
        // Carica dashboard iniziale
        loadDashboard();
        
        // Setup modali
        setupModals();
    });
    
    // Funzioni
    function applySettings() {
        // Applica tema
        if (appSettings.theme === 'dark' || (appSettings.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            $('body').addClass('dark-theme');
        } else {
            $('body').removeClass('dark-theme');
        }
        
        // Seleziona tema corrente nel dropdown
        $('#themeSelect').val(appSettings.theme);
        $('#rowsPerPage').val(appSettings.rowsPerPage);
    }
    
    function loadDashboard() {
        $.post('', {action: 'test_connection'}, function(res) {
            if (res.status === 'success') {
                $('#tablesCount').text(res.data.tables_count);
                $('#dbVersion').text(res.data.version.split(' ')[0]);
                
                // Formatta la data
                const dbTime = moment(res.data.time).format('DD/MM/YYYY HH:mm:ss');
                $('#dbStatus').html(`<span class="badge bg-success">Connesso</span> ${dbTime}`);
                
                // Carica statistiche aggiuntive
                loadDatabaseStats();
                loadRecentActivity();
            } else {
                $('#dbStatus').html(`<span class="badge bg-danger">Errore</span> ${res.message}`);
            }
        }, 'json');
    }
    
    function loadDatabaseStats() {
        // Simulazione dati - in un'app reale queste verrebbero dal server
        $('#dbSize').text('12.5 MB');
        $('#connectionsCount').text('8');
        
        // Grafico tabelle
        const ctx = document.getElementById('tablesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Users', 'Products', 'Orders', 'Logs', 'Settings'],
                datasets: [{
                    label: 'Righe (stimate)',
                    data: [1200, 850, 3200, 15000, 15],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 99, 132, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function loadRecentActivity() {
        // Simulazione dati - in un'app reale queste verrebbero dal server
        const activityData = [
            { query: 'SELECT * FROM users LIMIT 100', duration: '0.45ms' },
            { query: 'UPDATE products SET price = price * 1.1', duration: '2.3ms' },
            { query: 'DELETE FROM logs WHERE created_at < NOW() - INTERVAL \'30 days\'', duration: '15.2ms' },
            { query: 'CREATE INDEX idx_users_email ON users(email)', duration: '8.7ms' },
            { query: 'VACUUM ANALYZE', duration: '125.8ms' }
        ];
        
        const $tbody = $('#activityTable tbody').empty();
        activityData.forEach(activity => {
            $tbody.append(`
                <tr>
                    <td><code>${activity.query}</code></td>
                    <td>${activity.duration}</td>
                </tr>
            `);
        });
    }
    
    function loadTables() {
        $.post('', {action: 'get_tables'}, function(res) {
            if (res.status === 'success') {
                const $tbody = $('#tablesTable tbody').empty();
                
                res.data.forEach(table => {
                    $tbody.append(`
                        <tr data-table="${table.table_name}">
                            <td>
                                <strong>${table.table_name}</strong>
                            </td>
                            <td>${table.estimated_rows || 'N/A'}</td>
                            <td>${table.size || 'N/A'}</td>
                            <td>${moment().format('DD/MM/YYYY')}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary view-table-btn" title="Visualizza">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-table-btn" title="Elimina">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
                
                // Inizializza DataTables
                $('#tablesTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json'
                    }
                });
                
                // Gestione click su tabella
                $('tr[data-table]').click(function() {
                    currentTable = $(this).data('table');
                    loadTableStructure(currentTable);
                    loadTableData(currentTable);
                });
                
                // Popola dropdown backup
                $('#backupTables').empty();
                res.data.forEach(table => {
                    $('#backupTables').append(`<option value="${table.table_name}">${table.table_name}</option>`);
                });
            }
        }, 'json');
    }
    
    function loadTableStructure(tableName) {
        $.post('', {action: 'get_table_structure', table: tableName}, function(res) {
            if (res.status === 'success') {
                let html = `
                    <h5 class="mb-3">${tableName}</h5>
                    <div class="mb-4">
                        <button class="btn btn-sm btn-outline-primary me-2" id="exportTableBtn" data-table="${tableName}">
                            <i class="bi bi-download"></i> Esporta
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="showSqlBtn" data-table="${tableName}">
                            <i class="bi bi-code"></i> Mostra SQL
                        </button>
                    </div>
                    
                    <h6 class="mb-3">Colonne</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th>Nullable</th>
                                    <th>Default</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                res.data.columns.forEach(col => {
                    html += `
                        <tr>
                            <td>${col.column_name}</td>
                            <td>${col.data_type}</td>
                            <td>${col.is_nullable === 'YES' ? 'Sì' : 'No'}</td>
                            <td>${col.column_default || 'NULL'}</td>
                        </tr>`;
                });
                
                html += `</tbody></table></div>`;
                
                if (res.data.indexes.length > 0) {
                    html += `
                        <h6 class="mb-3">Indici</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Definizione</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    res.data.indexes.forEach(idx => {
                        html += `
                            <tr>
                                <td>${idx.indexname}</td>
                                <td><code>${idx.indexdef}</code></td>
                            </tr>`;
                    });
                    
                    html += `</tbody></table></div>`;
                }
                
                $('#tableStructureContent').html(html);
                
                // Mostra il tab della struttura
                $('[data-tab="table-structure"]').click();
            }
        }, 'json');
    }
    
    function loadTableData(tableName, page = 1, search = '') {
        $.post('', {
            action: 'get_table_data',
            table: tableName,
            page: page,
            search: search,
            limit: appSettings.rowsPerPage
        }, function(res) {
            if (res.status === 'success') {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 me-3">${tableName}</h5>
                            <span class="badge bg-secondary">${res.total} record</span>
                        </div>
                        <div class="d-flex">
                            <div class="input-group me-2" style="width: 300px;">
                                <input type="text" class="form-control" id="tableSearchInput" placeholder="Cerca..." value="${search}">
                                <button class="btn btn-outline-secondary" id="tableSearchBtn">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="refreshTableDataBtn">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>`;
                
                res.columns.forEach(col => {
                    html += `<th>${col}</th>`;
                });
                
                html += `</tr></thead><tbody>`;
                
                res.data.forEach(row => {
                    html += `<tr>`;
                    res.columns.forEach(col => {
                        const val = row[col];
                        html += `<td>${val !== null ? val : '<em class="text-muted">NULL</em>'}</td>`;
                    });
                    html += `</tr>`;
                });
                
                html += `</tbody></table></div>`;
                
                // Paginazione
                const totalPages = Math.ceil(res.total / res.limit);
                if (totalPages > 1) {
                    html += `<nav class="mt-3">
                        <ul class="pagination justify-content-center">`;
                    
                    // Bottone precedente
                    html += `<li class="page-item ${res.page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${res.page - 1}">Precedente</a>
                    </li>`;
                    
                    // Pagine
                    for (let i = 1; i <= totalPages; i++) {
                        html += `<li class="page-item ${i === res.page ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>`;
                    }
                    
                    // Bottone successivo
                    html += `<li class="page-item ${res.page === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${res.page + 1}">Successivo</a>
                    </li>`;
                    
                    html += `</ul></nav>`;
                }
                
                $('#tableDataContent').html(html);
                
                // Gestione click paginazione
                $('.page-link').click(function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadTableData(tableName, page, search);
                });
                
                // Gestione ricerca
                $('#tableSearchBtn').click(function() {
                    const search = $('#tableSearchInput').val();
                    loadTableData(tableName, 1, search);
                });
                
                $('#tableSearchInput').keypress(function(e) {
                    if (e.which === 13) {
                        const search = $(this).val();
                        loadTableData(tableName, 1, search);
                    }
                });
                
                // Gestione refresh
                $('#refreshTableDataBtn').click(function() {
                    loadTableData(tableName, res.page, search);
                });
            }
        }, 'json');
    }
    
    function loadUsers() {
        // Simulazione dati - in un'app reale queste verrebbero dal server
        const users = [
            { username: 'postgres', superuser: true, created: '2023-01-15', valid_until: null },
            { username: 'admin', superuser: true, created: '2023-05-20', valid_until: null },
            { username: 'app_user', superuser: false, created: '2023-06-10', valid_until: '2024-06-10' },
            { username: 'reporting', superuser: false, created: '2023-07-01', valid_until: null }
        ];
        
        const $tbody = $('#usersTable tbody').empty();
        users.forEach(user => {
            $tbody.append(`
                <tr>
                    <td>${user.username}</td>
                    <td>${user.superuser ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>'}</td>
                    <td>${moment(user.created).format('DD/MM/YYYY')}</td>
                    <td>${user.valid_until ? moment(user.valid_until).format('DD/MM/YYYY') : 'Nessuna scadenza'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" title="Modifica">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" title="Elimina">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }
    
    function setupModals() {
        // Modal creazione tabella
        $('#createTableBtn').click(function() {
            $('#createTableModal').modal('show');
        });
        
        // Aggiungi colonna
        let columnCount = 1;
        $('#addColumnBtn').click(function() {
            const $newRow = $(`
                <div class="row g-2 mb-2 column-row">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Nome" name="columns[${columnCount}][name]" required>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="columns[${columnCount}][type]" required>
                            <option value="">Tipo</option>
                            <option value="integer">Integer</option>
                            <option value="bigint">Bigint</option>
                            <option value="serial">Serial</option>
                            <option value="text">Text</option>
                            <option value="varchar">Varchar</option>
                            <option value="boolean">Boolean</option>
                            <option value="date">Date</option>
                            <option value="timestamp">Timestamp</option>
                            <option value="numeric">Numeric</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" placeholder="Lunghezza" name="columns[${columnCount}][length]">
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex">
                            <div class="form-check me-2">
                                <input class="form-check-input" type="checkbox" name="columns[${columnCount}][primary]" id="col${columnCount}Primary">
                                <label class="form-check-label" for="col${columnCount}Primary">PK</label>
                            </div>
                            <div class="form-check me-2">
                                <input class="form-check-input" type="checkbox" name="columns[${columnCount}][nullable]" id="col${columnCount}Nullable" checked>
                                <label class="form-check-label" for="col${columnCount}Nullable">NULL</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger ms-auto remove-column">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $('#columnsContainer').append($newRow);
            columnCount++;
        });
        
        // Rimuovi colonna
        $(document).on('click', '.remove-column', function() {
            if ($('.column-row').length > 1) {
                $(this).closest('.column-row').remove();
            } else {
                alert('Una tabella deve avere almeno una colonna');
            }
        });
        
        // Crea tabella
        $('#submitCreateTable').click(function() {
            const tableName = $('input[name="tableName"]').val();
            
            // Validazione semplice
            if (!tableName || !tableName.match(/^[a-zA-Z_][a-zA-Z0-9_]*$/)) {
                alert('Nome tabella non valido. Deve iniziare con una lettera e contenere solo lettere, numeri e underscore.');
                return;
            }
            
            // Simulazione creazione tabella (in un'app reale fare una chiamata AJAX)
            alert(`Tabella "${tableName}" creata con successo!`);
            $('#createTableModal').modal('hide');
            $('#createTableForm')[0].reset();
            loadTables();
        });
        
        // Modal creazione utente
        $('#createUserBtn').click(function() {
            $('#createUserModal').modal('show');
        });
        
        // Crea utente
        $('#submitCreateUser').click(function() {
            const username = $('input[name="username"]').val();
            const password = $('input[name="password"]').val();
            
            if (!username || !password) {
                alert('Inserisci username e password');
                return;
            }
            
            // Simulazione creazione utente (in un'app reale fare una chiamata AJAX)
            alert(`Utente "${username}" creato con successo!`);
            $('#createUserModal').modal('hide');
            $('#createUserForm')[0].reset();
            loadUsers();
        });
        
        // Esporta dati tabella
        $(document).on('click', '#exportTableBtn', function() {
            const tableName = $(this).data('table');
            const format = confirm('Esportare in formato JSON?') ? 'json' : 'csv';
            
            // Simula il download
            window.location.href = `?action=export_data&table=${tableName}&format=${format}`;
        });
        
        // Mostra SQL creazione tabella
        $(document).on('click', '#showSqlBtn', function() {
            const tableName = $(this).data('table');
            alert(`Questa funzionalità mostrerebbe lo SQL per creare la tabella ${tableName}`);
        });
        
        // Esegui query
        $('#executeQueryBtn').click(function() {
            const query = $('#queryEditor').val().trim();
            
            if (!query) {
                alert('Inserisci una query SQL');
                return;
            }
            
            $.post('', {action: 'execute_query', query: query}, function(res) {
                if (res.status === 'success' || res.status === 'partial') {
                    // Aggiungi alla cronologia
                    addToQueryHistory(query);
                    
                    // Mostra risultati
                    if (res.data && res.data.length > 0) {
                        let html = '<table class="table table-striped mb-0"><thead><tr>';
                        
                        // Intestazioni
                        res.columns.forEach(col => {
                            html += `<th>${col}</th>`;
                        });
                        
                        html += '</tr></thead><tbody>';
                        
                        // Dati
                        res.data.forEach(row => {
                            html += '<tr>';
                            res.columns.forEach(col => {
                                html += `<td>${row[col] !== null ? row[col] : '<em class="text-muted">NULL</em>'}</td>`;
                            });
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        
                        $('#queryResultsTable').html(html);
                        $('#queryMessage').html(`
                            <div class="alert alert-success">
                                ${res.status === 'partial' ? res.message : 'Query eseguita con successo'}.
                                ${res.affected_rows ? `Righe interessate: ${res.affected_rows}` : ''}
                            </div>
                        `);
                    } else {
                        $('#queryResultsTable').html('');
                        $('#queryMessage').html(`
                            <div class="alert alert-success">
                                Query eseguita con successo. ${res.affected_rows ? `Righe interessate: ${res.affected_rows}` : 'Nessun dato restituito'}
                            </div>
                        `);
                    }
                } else {
                    $('#queryResultsTable').html('');
                    $('#queryMessage').html(`
                        <div class="alert alert-danger">
                            Errore: ${res.message}
                        </div>
                    `);
                }
            }, 'json');
        });
        
        // Salva query
        $('#saveQueryBtn').click(function() {
            const query = $('#queryEditor').val().trim();
            
            if (!query) {
                alert('Inserisci una query SQL da salvare');
                return;
            }
            
            addToQueryHistory(query);
            alert('Query salvata nella cronologia');
        });
        
        // Genera backup
        $('#generateBackupBtn').click(function() {
            const format = $('#backupFormat').val();
            const tables = $('#backupTables').val() || [];
            
            if (tables.length === 0) {
                if (!confirm('Vuoi esportare TUTTO il database? Questo potrebbe richiedere molto tempo per database grandi.')) {
                    return;
                }
            }
            
            // Simula il download
            const params = new URLSearchParams();
            params.append('action', 'export_data');
            params.append('format', format);
            tables.forEach(table => params.append('tables[]', table));
            
            window.location.href = `?${params.toString()}`;
        });
        
        // Ripristina backup
        $('#restoreBackupBtn').click(function() {
            const fileInput = $('#restoreFile')[0];
            const dropTables = $('#dropTablesCheck').is(':checked');
            
            if (!fileInput.files.length) {
                alert('Seleziona un file di backup');
                return;
            }
            
            if (!confirm(`Sei sicuro di voler ripristinare il backup? ${dropTables ? 'Tutte le tabelle esistenti verranno eliminate!' : ''}`)) {
                return;
            }
            
            // Simula ripristino
            alert('Backup ripristinato con successo!');
        });
        
        // Salva impostazioni
        $('#saveSettingsBtn').click(function() {
            appSettings.theme = $('#themeSelect').val();
            appSettings.rowsPerPage = $('#rowsPerPage').val();
            
            localStorage.setItem('pgAdminSettings', JSON.stringify(appSettings));
            applySettings();
            alert('Impostazioni salvate');
        });
        
        // Refresh dati
        $('#refreshBtn, #refreshTablesBtn').click(function() {
            const activeSection = $('.sidebar-menu li a.active').data('section');
            
            if (activeSection === 'dashboard') {
                loadDashboard();
            } else if (activeSection === 'tables') {
                loadTables();
            }
        });
    }
    
    function addToQueryHistory(query) {
        // Aggiungi alla cronologia se non è già presente
        if (!queryHistory.some(item => item.query === query)) {
            queryHistory.unshift({
                query: query,
                timestamp: new Date().toISOString()
            });
            
            // Mantieni solo le ultime 50 query
            if (queryHistory.length > 50) {
                queryHistory.pop();
            }
            
            localStorage.setItem('queryHistory', JSON.stringify(queryHistory));
        }
    }
    
    function showQueryHistory() {
        const $tbody = $('#queryHistoryTable tbody').empty();
        
        queryHistory.forEach((item, index) => {
            $tbody.append(`
                <tr>
                    <td><code>${item.query.substring(0, 100)}${item.query.length > 100 ? '...' : ''}</code></td>
                    <td>${moment(item.timestamp).format('DD/MM/YYYY HH:mm')}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary use-query-btn" data-index="${index}" title="Usa questa query">
                            <i class="bi bi-arrow-left-circle"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
        
        // Gestione click su query nella cronologia
        $('.use-query-btn').click(function() {
            const index = $(this).data('index');
            $('#queryEditor').val(queryHistory[index].query);
            $('#queryHistoryModal').modal('hide');
        });
        
        $('#queryHistoryModal').modal('show');
    }
    </script>
</body>
</html>
<?php } ?>

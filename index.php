<?php
// Configurazione diretta (usa getenv oppure scrivi i valori direttamente)
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'miodb',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => 'require'
];

// Funzione per connettersi al DB
function getConnection($config) {
    $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", 
        $config['host'], 
        $config['port'], 
        $config['dbname']);
    
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// Azioni AJAX
$action = $_POST['action'] ?? '';
$response = [];

if ($action) {
    try {
        $pdo = getConnection($dbConfig);
        
        switch($action) {
            case 'test_connection':
                $response = ['status' => 'success', 'data' => $pdo->query("SELECT NOW() AS time")->fetch()];
                break;
            
            case 'get_tables':
                $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")
                              ->fetchAll(PDO::FETCH_COLUMN);
                $response = ['status' => 'success', 'data' => $tables];
                break;
            
            case 'get_table_data':
                $table = $_POST['table'];
                $page = $_POST['page'] ?? 1;
                $limit = 20;
                $offset = ($page - 1) * $limit;

                // Sanitize table name (basic)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    throw new Exception("Invalid table name");
                }

                $stmt = $pdo->prepare("SELECT * FROM $table LIMIT ? OFFSET ?");
                $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
                $stmt->execute();

                $rows = $stmt->fetchAll();
                $columns = !empty($rows) ? array_keys($rows[0]) : [];

                $response = [
                    'status' => 'success',
                    'data' => $rows,
                    'columns' => $columns
                ];
                break;

            case 'insert_test_data':
                $testData = [
                    'payment_intent.succeeded' => [
                        'id' => 'pi_'.uniqid(),
                        'amount' => rand(1000, 10000),
                        'currency' => 'usd',
                        'status' => 'succeeded'
                    ]
                ];
                // Simulazione insert (aggiungi insert reale se necessario)
                $response = ['status' => 'success', 'inserted' => count($testData)];
                break;
        }
    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!-- HTML RESTO INVARIATO -->
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PostgreSQL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .dashboard-card { transition: all 0.3s; }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Tuo HTML originale con i pulsanti e la tabella -->
    <!-- Resta invariato, puoi incollarlo qui sotto come nel tuo esempio precedente -->

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Il tuo codice JS (come nella versione originale) per gestire test, mock, tabelle
    </script>
</body>
</html>



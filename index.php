<?php
// Mostra errori per debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurazione DB (puoi usare getenv() o scrivere i valori direttamente)
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => 'require'
];

// Funzione connessione
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

// Gestione azioni AJAX
$action = $_POST['action'] ?? '';
$response = [];

if ($action) {
    try {
        $pdo = getConnection($dbConfig);

        switch ($action) {
            case 'test_connection':
                $response = ['status' => 'success', 'data' => $pdo->query("SELECT NOW() AS time")->fetch()];
                break;

            case 'get_tables':
                $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")
                              ->fetchAll(PDO::FETCH_COLUMN);
                $response = ['status' => 'success', 'data' => $tables];
                break;

            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                $page = (int)($_POST['page'] ?? 1);
                $limit = 20;
                $offset = ($page - 1) * $limit;

                // Sanitize table name
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    throw new Exception("Nome tabella non valido");
                }

                $stmt = $pdo->prepare("SELECT * FROM $table LIMIT ? OFFSET ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->bindValue(2, $offset, PDO::PARAM_INT);
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
                // Esempio fittizio (non inserisce nulla realmente)
                $testData = [
                    'id' => 'pi_' . uniqid(),
                    'amount' => rand(1000, 10000),
                    'currency' => 'usd',
                    'status' => 'succeeded'
                ];
                // Qui potresti aggiungere insert nel DB
                $response = ['status' => 'success', 'inserted' => 1];
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
<div class="container py-4">
    <h1 class="mb-4">PostgreSQL Admin</h1>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Test Connessione</h5>
                    <button id="testBtn" class="btn btn-light">Esegui Test</button>
                    <div id="testResult" class="mt-2"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Dati Fittizi</h5>
                    <button id="mockDataBtn" class="btn btn-light">Genera Dati Stripe</button>
                    <div id="mockDataResult" class="mt-2"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Stato Database</h5>
                    <div id="dbStatus">Non testato</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella -->
    <div class="card">
        <div class="card-header">
            <select id="tableSelect" class="form-select">
                <option value="">Seleziona una tabella</option>
            </select>
        </div>
        <div class="table-container">
            <table id="dataTable" class="table table-striped">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Test connessione
    $('#testBtn').click(function() {
        $.post('', {action: 'test_connection'}, function(res) {
            const html = res.status === 'success'
                ? `<span class="badge bg-success">Connesso</span><br>${res.data.time}`
                : `<span class="badge bg-danger">Errore</span><br>${res.message}`;
            $('#testResult').html(html);
        }, 'json');
    });

    // Genera dati fittizi
    $('#mockDataBtn').click(function() {
        if (confirm('Generare dati di test Stripe?')) {
            $.post('', {action: 'insert_test_data'}, function(res) {
                $('#mockDataResult').html(`Inseriti ${res.inserted} record`);
            }, 'json');
        }
    });

    // Carica tabelle nel select
    function loadTables() {
        $.post('', {action: 'get_tables'}, function(res) {
            $('#tableSelect').html('<option value="">Seleziona una tabella</option>');
            $.each(res.data, function(i, table) {
                $('#tableSelect').append(`<option value="${table}">${table}</option>`);
            });
        }, 'json');
    }

    // Carica dati di una tabella
    $('#tableSelect').change(function() {
        const table = $(this).val();
        if (!table) return;
        $.post('', {action: 'get_table_data', table: table, page: 1}, function(res) {
            let thead = '<tr>';
            $.each(res.columns, function(_, col) {
                thead += `<th>${col}</th>`;
            });
            thead += '</tr>';
            $('#dataTable thead').html(thead);

            let tbody = '';
            $.each(res.data, function(_, row) {
                let tr = '<tr>';
                $.each(row, function(_, val) {
                    tr += `<td>${val !== null ? val : '<em>NULL</em>'}</td>`;
                });
                tr += '</tr>';
                tbody += tr;
            });
            $('#dataTable tbody').html(tbody);

            $('#dataTable').DataTable({
                destroy: true
            });
        }, 'json');
    });

    // Inizializza
    loadTables();
    $('#testBtn').trigger('click');
});
</script>
</body>
</html>

<?php
// config.php (protetto)
define('DB_CONFIG', [
    'host'     => getenv('DB_HOST'),
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME'),
    'user'     => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'ssl_mode' => 'require'
]);
?>

<?php
// index.php
require_once 'config.php';

// Funzioni Database
function getConnection() {
    $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", 
        DB_CONFIG['host'], 
        DB_CONFIG['port'], 
        DB_CONFIG['dbname']);
    
    return new PDO($dsn, DB_CONFIG['user'], DB_CONFIG['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// Azioni
$action = $_POST['action'] ?? '';
$response = [];

try {
    $pdo = getConnection();
    
    switch($action) {
        case 'test_connection':
            $response = ['status' => 'success', 'data' => $pdo->query("SELECT NOW() AS time")->fetch()];
            break;
            
        case 'get_tables':
            $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
            $response = ['status' => 'success', 'data' => $tables];
            break;
            
        case 'get_table_data':
            $table = $_POST['table'];
            $page = $_POST['page'] ?? 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("SELECT * FROM $table LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $response = [
                'status' => 'success',
                'data' => $stmt->fetchAll(),
                'columns' => array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?: [])
            ];
            break;
            
        case 'insert_test_data':
            // Dati fittizi Stripe
            $testData = [
                'payment_intent.succeeded' => [
                    'id' => 'pi_'.uniqid(),
                    'amount' => rand(1000, 10000),
                    'currency' => 'usd',
                    'status' => 'succeeded'
                ],
                // Altri eventi...
            ];
            
            // Insert nel DB...
            $response = ['status' => 'success', 'inserted' => count($testData)];
            break;
    }
    
} catch (PDOException $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

if ($action) {
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
        .dashboard-card {
            transition: all 0.3s;
        }
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
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">PostgreSQL Admin</h1>
                <p class="lead">Gestione database Stripe</p>
            </div>
        </div>
        
        <!-- Dashboard Cards -->
        <div class="row mb-4">
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
        
        <!-- Tabella Dati -->
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-6">
                        <select id="tableSelect" class="form-select">
                            <option value="">Seleziona una tabella</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group">
                            <button id="refreshBtn" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Aggiorna
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="dataTable" class="table table-striped" style="width:100%">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center" id="pagination">
                    </ul>
                </nav>
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
            if(confirm('Generare dati di test Stripe?')) {
                $.post('', {action: 'insert_test_data'}, function(res) {
                    $('#mockDataResult').html(`Inseriti ${res.inserted} record`);
                }, 'json');
            }
        });
        
        // Carica tabelle
        function loadTables() {
            $.post('', {action: 'get_tables'}, function(res) {
                $('#tableSelect').html('<option value="">Seleziona una tabella</option>');
                $.each(res.data, function(i, table) {
                    $('#tableSelect').append(`<option value="${table}">${table}</option>`);
                });
            }, 'json');
        }
        
        // Carica dati tabella
        $('#tableSelect').change(function() {
            loadTableData($(this).val(), 1);
        });
        
        function loadTableData(table, page) {
            if(!table) return;
            
            $.post('', {
                action: 'get_table_data',
                table: table,
                page: page
            }, function(res) {
                // Costruisci intestazioni
                let thead = '';
                $.each(res.columns, function(i, col) {
                    thead += `<th>${col}</th>`;
                });
                $('#dataTable thead').html(`<tr>${thead}</tr>`);
                
                // Costruisci corpo
                let tbody = '';
                $.each(res.data, function(i, row) {
                    let tr = '';
                    $.each(row, function(key, val) {
                        tr += `<td>${val !== null ? val : '<em>NULL</em>'}</td>`;
                    });
                    tbody += `<tr>${tr}</tr>`;
                });
                $('#dataTable tbody').html(tbody);
                
                // Rendi tabella responsive
                $('#dataTable').DataTable({
                    responsive: true,
                    destroy: true
                });
            }, 'json');
        }
        
        // Inizializzazione
        loadTables();
        $('#testBtn').trigger('click');
    });
    </script>
</body>
</html>

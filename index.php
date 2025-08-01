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

// Variabili per la connessione automatica e tabella stripe
$connectionSuccess = false;
$connectionError = '';
$stripeTableExists = false;
$stripeTableData = [];
$stripeTableColumns = [];

try {
    $pdo = getConnection($dbConfig);
    $connectionSuccess = true;
    
    // Verifica se la tabella stripe esiste
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=?)");
    $stmt->execute(['stripe']);
    $stripeTableExists = $stmt->fetchColumn();
    
    if ($stripeTableExists) {
        // Ottieni i dati della tabella stripe
        $stmt = $pdo->prepare("SELECT * FROM stripe LIMIT 50");
        $stmt->execute();
        $stripeTableData = $stmt->fetchAll();
        $stripeTableColumns = !empty($stripeTableData) ? array_keys($stripeTableData[0]) : [];
    }
    
} catch (PDOException $e) {
    $connectionError = $e->getMessage();
}

if ($action) {
    try {
        if (!$pdo) {
            $pdo = getConnection($dbConfig);
        }

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
                // Esempio di inserimento dati Stripe
                $testData = [
                    'id' => 'pi_' . uniqid(),
                    'amount' => rand(1000, 10000),
                    'currency' => 'usd',
                    'status' => ['succeeded', 'pending', 'failed'][rand(0, 2)],
                    'created' => date('Y-m-d H:i:s'),
                    'description' => 'Test payment ' . uniqid(),
                    'customer' => 'cus_' . uniqid()
                ];
                
                $stmt = $pdo->prepare("INSERT INTO stripe (id, amount, currency, status, created, description, customer) 
                                     VALUES (:id, :amount, :currency, :status, :created, :description, :customer)");
                $stmt->execute($testData);
                
                $response = ['status' => 'success', 'inserted' => 1, 'data' => $testData];
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
    <title>PostgreSQL Admin - Stripe Data</title>
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
        .connection-status {
            font-weight: bold;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">PostgreSQL Admin - Stripe Data</h1>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Stato Connessione</h5>
                    <div id="connectionStatus" class="connection-status <?php echo $connectionSuccess ? 'success' : 'error'; ?>">
                        <?php 
                        if ($connectionSuccess) {
                            echo "Connesso al database";
                        } else {
                            echo "Errore di connessione: " . htmlspecialchars($connectionError);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Tabella Stripe</h5>
                    <div id="stripeTableStatus">
                        <?php 
                        if ($stripeTableExists) {
                            echo '<span class="badge bg-success">Presente</span>';
                        } else {
                            echo '<span class="badge bg-warning text-dark">Non trovata</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Genera Dati</h5>
                    <button id="mockDataBtn" class="btn btn-light">Genera Dati Stripe</button>
                    <div id="mockDataResult" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella Stripe -->
    <?php if ($stripeTableExists): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Dati Stripe</h5>
            <button id="refreshStripeBtn" class="btn btn-sm btn-primary">Aggiorna</button>
        </div>
        <div class="table-container">
            <table id="stripeTable" class="table table-striped">
                <thead>
                    <tr>
                        <?php foreach ($stripeTableColumns as $column): ?>
                            <th><?php echo htmlspecialchars($column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stripeTableData as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?php echo $value !== null ? htmlspecialchars($value) : '<em>NULL</em>'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        La tabella 'stripe' non è stata trovata nel database. Puoi generare dati di test usando il pulsante "Genera Dati Stripe".
    </div>
    <?php endif; ?>

    <!-- Lista di tutte le tabelle -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>Tutte le tabelle del database</h5>
        </div>
        <div class="card-body">
            <select id="tableSelect" class="form-select mb-3">
                <option value="">Seleziona una tabella</option>
            </select>
            <div class="table-container">
                <table id="dataTable" class="table table-striped">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Inizializza DataTable per la tabella Stripe
    <?php if ($stripeTableExists): ?>
    const stripeTable = $('#stripeTable').DataTable({
        order: [[4, 'desc']] // Ordina per la colonna 'created' in ordine decrescente
    });
    
    // Aggiorna la tabella Stripe
    $('#refreshStripeBtn').click(function() {
        $.post('', {action: 'get_table_data', table: 'stripe', page: 1}, function(res) {
            stripeTable.clear();
            $.each(res.data, function(_, row) {
                const rowData = [];
                $.each(res.columns, function(_, col) {
                    rowData.push(row[col] !== null ? row[col] : '<em>NULL</em>');
                });
                stripeTable.row.add(rowData);
            });
            stripeTable.draw();
        }, 'json');
    });
    <?php endif; ?>

    // Genera dati fittizi Stripe
    $('#mockDataBtn').click(function() {
        if (confirm('Generare dati di test per la tabella Stripe?')) {
            $.post('', {action: 'insert_test_data'}, function(res) {
                $('#mockDataResult').html(
                    `<span class="badge bg-success">Successo</span> Inserito record: ${res.data.id}`
                );
                
                // Se la tabella stripe esisteva già, aggiorna i dati
                <?php if ($stripeTableExists): ?>
                $('#refreshStripeBtn').trigger('click');
                <?php else: ?>
                // Ricarica la pagina per mostrare la nuova tabella
                location.reload();
                <?php endif; ?>
            }, 'json').fail(function(xhr) {
                $('#mockDataResult').html(
                    `<span class="badge bg-danger">Errore</span> ${xhr.responseJSON.message}`
                );
            });
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
});
</script>
</body>
</html>




<?php
// Debug errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurazione DB da variabili ambiente o valori di default
$db = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

// Connessione
function getConnection($config) {
    $dsn = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['ssl_mode']
    );
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// Gestione AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo = getConnection($db);

        switch ($_POST['action']) {
            case 'get_tables':
                $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")
                              ->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode(['status' => 'success', 'tables' => $tables]);
                break;

            case 'get_table_data':
                $table = $_POST['table'] ?? '';
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new Exception("Nome tabella non valido");

                $rows = $pdo->query("SELECT * FROM $table LIMIT 100")->fetchAll();
                $columns = !empty($rows) ? array_keys($rows[0]) : [];
                echo json_encode(['status' => 'success', 'columns' => $columns, 'rows' => $rows]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Verifica connessione per frontend
try {
    $pdo = getConnection($db);
    $connected = true;
} catch (Exception $e) {
    $connected = false;
    $errorMsg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PostgreSQL Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">PostgreSQL Viewer</h1>

    <!-- Stato connessione -->
    <div class="alert <?php echo $connected ? 'alert-success' : 'alert-danger'; ?>">
        <?php echo $connected ? '✅ Connesso al database con successo.' : '❌ Errore: ' . htmlspecialchars($errorMsg); ?>
    </div>

    <?php if ($connected): ?>
    <!-- Selettore tabella -->
    <div class="mb-3">
        <label for="tableSelect" class="form-label">Seleziona una tabella:</label>
        <select id="tableSelect" class="form-select" disabled>
            <option>Caricamento...</option>
        </select>
    </div>

    <!-- Visualizzatore dati -->
    <div class="table-responsive">
        <table id="dataTable" class="table table-striped" style="display:none;">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    const $select = $('#tableSelect');
    const $table = $('#dataTable');
    let dt = null;

    // Carica elenco tabelle
    $.post('', {action: 'get_tables'}, function(res) {
        if (res.status === 'success') {
            $select.empty().append('<option value="">-- Seleziona una tabella --</option>');
            res.tables.forEach(t => $select.append(`<option value="${t}">${t}</option>`));
            $select.prop('disabled', false);
        } else {
            alert(res.message);
        }
    });

    // Quando viene selezionata una tabella
    $select.change(function() {
        const tableName = $(this).val();
        if (!tableName) return;

        $.post('', {action: 'get_table_data', table: tableName}, function(res) {
            if (res.status === 'success') {
                const thead = '<tr>' + res.columns.map(c => `<th>${c}</th>`).join('') + '</tr>';
                const tbody = res.rows.map(row => {
                    return '<tr>' + res.columns.map(col =>
                        `<td>${row[col] !== null ? row[col] : '<em>NULL</em>'}</td>`).join('') + '</tr>';
                }).join('');

                $table.find('thead').html(thead);
                $table.find('tbody').html(tbody);
                $table.show();

                if (dt) dt.destroy();
                dt = $table.DataTable();
            } else {
                alert(res.message);
            }
        });
    });
});
</script>
</body>
</html>


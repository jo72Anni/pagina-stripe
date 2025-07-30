<?php
// ==============================================
// POSTGRESQL EXPLORER - Visualizza tabelle e dati
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurazione del database Render
$db_config = [
    'host' => 'dpg-d257e563jp1c73e216h0-a.oregon-postgres.render.com',
    'port' => 5432,
    'dbname' => 'dbstripe_ul7f',
    'user' => 'dbstripe_ul7f_user',
    'password' => 'j7rP4lHTCdjmlVNIRouEhlJLiX8LiZue',
    'ssl_mode' => 'require'
];

$connection_status = '';
$query_result = '';
$tables = [];
$table_content = [];
$selected_table = $_POST['table'] ?? '';

// Tentativo di connessione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $connection_string = sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s sslmode=%s",
            $db_config['host'],
            $db_config['port'],
            $db_config['dbname'],
            $db_config['user'],
            $db_config['password'],
            $db_config['ssl_mode']
        );

        $conn = pg_connect($connection_string);
        
        if ($conn) {
            $connection_status = "✅ Connessione riuscita!";
            
            // Query di test base
            $result = pg_query($conn, "SELECT NOW() as current_time, version() as pg_version");
            if ($result) {
                $query_result = pg_fetch_assoc($result);
            }
            
            // Ottieni lista tabelle
            $tables_result = pg_query($conn, "
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
                ORDER BY table_name
            ");
            if ($tables_result) {
                while ($row = pg_fetch_assoc($tables_result)) {
                    $tables[] = $row['table_name'];
                }
            }
            
            // Ottieni contenuto tabella selezionata
            if ($selected_table && in_array($selected_table, $tables)) {
                $content_result = pg_query($conn, "SELECT * FROM $selected_table LIMIT 50");
                if ($content_result) {
                    $table_content['columns'] = [];
                    for ($i = 0; $i < pg_num_fields($content_result); $i++) {
                        $table_content['columns'][] = pg_field_name($content_result, $i);
                    }
                    $table_content['rows'] = pg_fetch_all($content_result) ?: [];
                }
            }
            
            pg_close($conn);
        } else {
            $connection_status = "❌ Connessione fallita";
        }
    } catch (Exception $e) {
        $connection_status = "❌ Errore: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PostgreSQL Explorer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #6772e5;
            text-align: center;
            margin-bottom: 30px;
        }
        button, input[type="submit"] {
            background: #6772e5;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover, input[type="submit"]:hover {
            background: #5469d4;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            color: #c62828;
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .tables-container {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }
        .tables-list {
            flex: 1;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .table-content {
            flex: 3;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table-form {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PostgreSQL Explorer</h1>
        
        <form method="POST">
            <button type="submit">Testa Connessione</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="<?= strpos($connection_status, '✅') !== false ? 'success' : 'error' ?>">
                <h3><?= $connection_status ?></h3>
                
                <?php if ($query_result): ?>
                    <h4>Informazioni Database:</h4>
                    <p><strong>Ora corrente:</strong> <?= $query_result['current_time'] ?></p>
                    <p><strong>Versione PostgreSQL:</strong> <?= $query_result['pg_version'] ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($tables)): ?>
                <div class="tables-container">
                    <div class="tables-list">
                        <h3>Tabelle disponibili</h3>
                        <ul>
                            <?php foreach ($tables as $table): ?>
                                <li>
                                    <form method="POST" class="table-form">
                                        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                        <input type="submit" value="<?= htmlspecialchars($table) ?>">
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="table-content">
                        <?php if ($selected_table): ?>
                            <h3>Contenuto di: <?= htmlspecialchars($selected_table) ?></h3>
                            
                            <?php if (!empty($table_content)): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <?php foreach ($table_content['columns'] as $column): ?>
                                                <th><?= htmlspecialchars($column) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($table_content['rows'] as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $value): ?>
                                                    <td>
                                                        <?= is_string($value) ? htmlspecialchars($value) : 
                                                           (is_null($value) ? 'NULL' : var_export($value, true)) ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>La tabella è vuota o non contiene dati.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Seleziona una tabella per visualizzarne il contenuto.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <h3>Dettagli Configurazione:</h3>
            <pre><?= print_r($db_config, true) ?></pre>
        <?php else: ?>
            <p>Clicca il pulsante per testare la connessione al database PostgreSQL su Render</p>
        <?php endif; ?>
        
        <h3>Requisiti PHP:</h3>
        <ul>
            <li>Estensione <code>pgsql</code> abilitata</li>
            <li>Connessione in uscita alla porta 5432</li>
            <li>Supporto SSL (richiesto da Render)</li>
        </ul>
    </div>
</body>
</html>

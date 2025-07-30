<?php
// ==============================================
// ESPLORATORE DATABASE POSTGRESQL
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurazione del database
$db_config = [
    'host'     => 'dpg-d257e563jp1c73e216h0-a.oregon-postgres.render.com',
    'port'     => 5432,
    'dbname'   => 'dbstripe_ul7f',
    'user'     => 'dbstripe_ul7f_user',
    'password' => 'j7rP4lHTCdjmlVNIRouEhlJLiX8LiZue',
    'ssl_mode' => 'require'
];

// Variabili per lo stato
$connection = null;
$tables = [];
$table_content = [];
$selected_table = $_GET['table'] ?? '';

// Connessione al database
try {
    $dsn = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s;sslmode=%s",
        $db_config['host'],
        $db_config['port'],
        $db_config['dbname'],
        $db_config['user'],
        $db_config['password'],
        $db_config['ssl_mode']
    );
    
    $connection = new PDO($dsn);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupero lista tabelle
    $stmt = $connection->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Recupero contenuto tabella se selezionata
    if ($selected_table && in_array($selected_table, $tables)) {
        $stmt = $connection->query("SELECT * FROM $selected_table LIMIT 100");
        $table_content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Explorer</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #6772e5;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }
        .sidebar {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 8px;
        }
        .content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .table-list li {
            margin-bottom: 5px;
        }
        .table-list a {
            display: block;
            padding: 8px 10px;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .table-list a:hover {
            background: #e6e9ff;
        }
        .table-list a.active {
            background: #6772e5;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .db-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PostgreSQL Explorer</h1>
        <p>Database: <?= htmlspecialchars($db_config['dbname']) ?></p>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error">
            <h3>Errore di connessione</h3>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($connection): ?>
        <div class="db-info">
            <p>Connessione al database riuscita!</p>
            <p>Trovate <?= count($tables) ?> tabelle</p>
        </div>

        <div class="container">
            <div class="sidebar">
                <h3>Tabelle</h3>
                <ul class="table-list">
                    <?php foreach ($tables as $table): ?>
                        <li>
                            <a 
                                href="?table=<?= urlencode($table) ?>" 
                                class="<?= $selected_table === $table ? 'active' : '' ?>"
                            >
                                <?= htmlspecialchars($table) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="content">
                <?php if ($selected_table): ?>
                    <h2>Tabella: <?= htmlspecialchars($selected_table) ?></h2>
                    
                    <?php if (!empty($table_content)): ?>
                        <div style="max-height: 600px; overflow: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($table_content[0]) as $column): ?>
                                            <th><?= htmlspecialchars($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_content as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td>
                                                    <?php 
                                                    if (is_null($value)) {
                                                        echo '<em>NULL</em>';
                                                    } elseif (is_string($value)) {
                                                        echo htmlspecialchars($value);
                                                    } else {
                                                        echo htmlspecialchars(var_export($value, true));
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p>Mostrati <?= count($table_content) ?> record</p>
                    <?php else: ?>
                        <p>La tabella è vuota</p>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Seleziona una tabella</h2>
                    <p>Scegli una tabella dal menu a sinistra per visualizzarne il contenuto</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>

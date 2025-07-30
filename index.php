<?php
// ==============================================
// TEST DI CONNESSIONE POSTGRESQL (Versione Semplificata)
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
            
            // Esegui una query di test
            $result = pg_query($conn, "SELECT NOW() as current_time, version() as pg_version");
            if ($result) {
                $query_result = pg_fetch_assoc($result);
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
    <title>Test PostgreSQL con PHP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        }
        button {
            background: #6772e5;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
        }
        .error {
            color: #c62828;
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Connessione PostgreSQL</h1>
        
        <form method="POST">
            <button type="submit">Testa Connessione</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="<?= strpos($connection_status, '✅') !== false ? 'success' : 'error' ?>">
                <h3><?= $connection_status ?></h3>
                
                <?php if ($query_result): ?>
                    <h4>Risultato Query:</h4>
                    <pre><?= print_r($query_result, true) ?></pre>
                <?php endif; ?>
            </div>
            
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

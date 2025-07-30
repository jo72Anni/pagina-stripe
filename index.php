<?php
// ==============================================
// BACKEND PHP (eseguito sul server)
// ==============================================
$db_connected = false;
$db_error = "";
$db_result = null;

// Configurazione del database (modifica con i tuoi dati Render)
$db_config = [
    'host' => 'dpg-d257e563jp1c73e216h0-a.oregon-postgres.render.com',
    'port' => 5432,
    'dbname' => 'dbstripe_ul7f',
    'user' => 'dbstripe_ul7f_user',
    'password' => 'j7rP4lHTCdjmlVNIRouEhlJLiX8LiZue',
    'ssl_mode' => 'require'
];

// Tentativo di connessione quando si invia il form
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

        $db_conn = pg_connect($connection_string);
        
        if ($db_conn) {
            $db_connected = true;
            $result = pg_query($db_conn, "SELECT NOW() AS current_time, version() AS pg_version");
            $db_result = pg_fetch_assoc($result);
            pg_close($db_conn);
        } else {
            $db_error = "Connessione fallita senza errori specifici";
        }
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}
?>

<!-- ============================================== -->
<!-- FRONTEND HTML -->
<!-- ============================================== -->
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Connessione PostgreSQL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #6772e5;
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
        button:hover {
            background: #5469d4;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #e6f7e6;
            border: 1px solid #4CAF50;
            color: #2d572c;
        }
        .error {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #d32f2f;
        }
        .config {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-family: monospace;
        }
        pre {
            background: #f8f8f8;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Test Connessione PostgreSQL</h1>
    
    <div class="config">
        <h3>Configurazione Database</h3>
        <pre><?= htmlspecialchars(json_encode($db_config, JSON_PRETTY_PRINT)) ?></pre>
    </div>

    <form method="POST">
        <button type="submit">Testa Connessione</button>
    </form>

    <div class="result <?= $db_connected ? 'success' : 'error' ?>">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php if ($db_connected): ?>
                <h3>✅ Connessione Riuscita!</h3>
                <pre><?= htmlspecialchars(json_encode($db_result, JSON_PRETTY_PRINT)) ?></pre>
                <p>Ora corrente nel database: <strong><?= $db_result['current_time'] ?></strong></p>
                <p>Versione PostgreSQL: <strong><?= $db_result['pg_version'] ?></strong></p>
            <?php else: ?>
                <h3>❌ Errore di Connessione</h3>
                <p><?= htmlspecialchars($db_error) ?></p>
                <h4>Problemi comuni:</h4>
                <ul>
                    <li>Credenziali errate</li>
                    <li>Server PostgreSQL non raggiungibile</li>
                    <li>Mancanza dell'estensione <code>pgsql</code> in PHP</li>
                    <li>Problemi di SSL (Render richiede connessioni sicure)</li>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <p>Clicca il pulsante per testare la connessione al database</p>
        <?php endif; ?>
    </div>

    <h2>Requisiti PHP</h2>
    <p>Per far funzionare questo script, il tuo server PHP deve avere:</p>
    <ul>
        <li>Estensione <code>pgsql</code> abilitata</li>
        <li>Connessione in uscita verso <code>dpg-d257e563jp1c73e216h0-a.oregon-postgres.render.com:5432</code></li>
    </ul>

    <h2>Come verificare l'estensione pgsql</h2>
    <p>Crea un file <code>phpinfo.php</code> con:</p>
    <pre><?= htmlspecialchars('<?php phpinfo(); ?>') ?></pre>
    <p>Cerca "pgsql" nella pagina risultante. Se non è presente, abilitala nel php.ini.</p>
</body>
</html>

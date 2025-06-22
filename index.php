<?php
// Configurazione database con variabili d'ambiente (usa valori di default se non definite)
$db_config = [
    'host'     => getenv('DB_HOST') ?: 'dpg-d19h6lfgi27c73crpsrg-a.oregon-postgres.render.com',
    'port'     => getenv('DB_PORT') ?: '5432',
    'dbname'   => getenv('DB_NAME') ?: 'stripe_test_ase0',
    'user'     => getenv('DB_USER') ?: 'stripe_test_ase0_user',
    'password' => getenv('DB_PASSWORD') ?: '0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ'
];

// Crea la stringa di connessione
$conn_string = "host={$db_config['host']} port={$db_config['port']} dbname={$db_config['dbname']} user={$db_config['user']} password={$db_config['password']} sslmode=require";

// Prova a connetterti al database
$conn = @pg_connect($conn_string);

// Raccogli variabili d'ambiente legate al database per debug
$env_vars = [
    'DB_HOST' => getenv('DB_HOST'),
    'DB_PORT' => getenv('DB_PORT'),
    'DB_NAME' => getenv('DB_NAME'),
    'DB_USER' => getenv('DB_USER'),
    'DB_PASSWORD' => getenv('DB_PASSWORD'),
    'DATABASE_URL' => getenv('DATABASE_URL'),
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Test Connessione DB Completo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Test Connessione al Database PostgreSQL</h1>

    <?php if ($conn): ?>
        <p class="success">Connessione al database riuscita!</p>
    <?php else: ?>
        <p class="error">Errore nella connessione al database.</p>
    <?php endif; ?>

    <h2>Parametri di Connessione Usati</h2>
    <table>
        <tr><th>Parametro</th><th>Valore</th></tr>
        <tr><td>host</td><td><?=htmlspecialchars($db_config['host'])?></td></tr>
        <tr><td>port</td><td><?=htmlspecialchars($db_config['port'])?></td></tr>
        <tr><td>dbname</td><td><?=htmlspecialchars($db_config['dbname'])?></td></tr>
        <tr><td>user</td><td><?=htmlspecialchars($db_config['user'])?></td></tr>
        <tr><td>password</td><td><?=htmlspecialchars($db_config['password'])?></td></tr>
        <tr><td>sslmode</td><td>require</td></tr>
    </table>

    <h2>Stringa di Connessione Completa</h2>
    <pre><?=htmlspecialchars($conn_string)?></pre>

    <h2>Variabili d'Ambiente (database correlate)</h2>
    <table>
        <tr><th>Variabile</th><th>Valore</th></tr>
        <?php foreach ($env_vars as $key => $value): ?>
            <tr><td><?=htmlspecialchars($key)?></td><td><?=htmlspecialchars($value ?? 'Non impostata')?></td></tr>
        <?php endforeach; ?>
    </table>

    <?php if (!$conn): ?>
        <h2>Messaggio di errore PostgreSQL</h2>
        <pre><?=htmlspecialchars(pg_last_error())?></pre>
    <?php endif; ?>

</body>
</html>
<?php
if ($conn) {
    pg_close($conn);
}
?>

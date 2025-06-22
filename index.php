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
$conn = pg_connect($conn_string);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Test Connessione DB</title>
</head>
<body>
    <h1>Test Connessione al Database PostgreSQL</h1>

    <?php if ($conn): ?>
        <p style="color:green;">Connessione al database riuscita!</p>
    <?php else: ?>
        <p style="color:red;">Errore nella connessione al database.</p>
        <p><?php echo htmlspecialchars(pg_last_error()); ?></p>
    <?php endif; ?>

</body>
</html>
<?php
if ($conn) {
    pg_close($conn);
}
?>

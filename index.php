<?php
// Recupera i dati di connessione dal sistema (variabili d'ambiente)
$db_config = [
    'host'     => getenv('DB_HOST'),
    'port'     => getenv('DB_PORT'),
    'dbname'   => getenv('DB_NAME'),
    'user'     => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD')
];

// Funzione per dire se un valore è “vuoto” o mancante
function is_missing($val) {
    return !isset($val) || trim($val) === '';
}

// Verifica se mancano parametri essenziali
$missing_params = [];
foreach ($db_config as $key => $val) {
    if (is_missing($val)) {
        $missing_params[] = $key;
    }
}

// Stringa di connessione (anche se mancano parametri, la mostriamo per debug)
$conn_string = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $db_config['host'] ?? '[non impostato]',
    $db_config['port'] ?? '[non impostato]',
    $db_config['dbname'] ?? '[non impostato]',
    $db_config['user'] ?? '[non impostato]',
    $db_config['password'] ? '********' : '[non impostata]'
);

// Provo a connettermi solo se non mancano parametri
$conn = null;
$connect_error = null;
if (empty($missing_params)) {
    $conn = @pg_connect($conn_string);
    if (!$conn) {
        $connect_error = pg_last_error();
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Test Connessione DB - Variabili d'Ambiente</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #eee; }
    </style>
</head>
<body>

<h1>Test Connessione DB con Variabili d'Ambiente</h1>

<?php if ($missing_params): ?>
    <p class="error"><strong>Mancano queste variabili d'ambiente obbligatorie:</strong></p>
    <ul>
        <?php foreach ($missing_params as $param): ?>
            <li><?=htmlspecialchars($param)?></li>
        <?php endforeach; ?>
    </ul>
<?php elseif ($conn): ?>
    <p class="success">Connessione al database riuscita!</p>
<?php else: ?>
    <p class="error">Connessione fallita.</p>
<?php endif; ?>

<h2>Parametri di connessione attuali</h2>
<table>
    <tr><th>Parametro</th><th>Valore</th></tr>
    <?php foreach ($db_config as $key => $val): ?>
        <tr>
            <td><?=htmlspecialchars($key)?></td>
            <td><?= $key === 'password' ? (isset($val) ? '********' : '[non impostata]') : htmlspecialchars($val ?? '[non impostata]') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Stringa di connessione usata</h2>
<pre><?=htmlspecialchars($conn_string)?></pre>

<?php if ($connect_error): ?>
    <h2>Messaggio di errore PostgreSQL</h2>
    <pre><?=htmlspecialchars($connect_error)?></pre>
<?php endif; ?>

</body>
</html>

<?php
if ($conn) {
    pg_close($conn);
}
?>

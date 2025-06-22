<?php
// Test Connessione PostgreSQL usando DATABASE_URL come variabile d'ambiente

// Recupera la DATABASE_URL
$database_url = getenv('DATABASE_URL');

function html_escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Connessione DB con DATABASE_URL</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f9f9f9; }
        table { border-collapse: collapse; margin-top: 1rem; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #eee; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<h1>Test Connessione DB con DATABASE_URL</h1>

<?php if (!$database_url): ?>
    <p class="error">❌ Variabile d'ambiente <strong>DATABASE_URL</strong> non trovata.</p>
    <?php exit; ?>
<?php endif; ?>

<?php
// Parsing della DATABASE_URL (es: postgres://user:pass@host:port/dbname)
$dbopts = parse_url($database_url);

$host = $dbopts['host'] ?? '';
$port = $dbopts['port'] ?? '';
$user = $dbopts['user'] ?? '';
$password = $dbopts['pass'] ?? '';
$dbname = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : '';

$missing = [];
if (!$host) $missing[] = 'host';
if (!$port) $missing[] = 'port';
if (!$user) $missing[] = 'user';
if (!$password) $missing[] = 'password';
if (!$dbname) $missing[] = 'dbname';

if (!empty($missing)) {
    echo '<p class="error">Mancano queste variabili nella DATABASE_URL:</p><ul>';
    foreach ($missing as $var) {
        echo '<li><code>' . html_escape($var) . '</code></li>';
    }
    echo '</ul>';
}
?>

<h2>Parametri estratti da DATABASE_URL</h2>
<table>
    <tr><th>Parametro</th><th>Valore</th></tr>
    <tr><td>host</td><td><?= html_escape($host) ?></td></tr>
    <tr><td>port</td><td><?= html_escape($port) ?></td></tr>
    <tr><td>user</td><td><?= html_escape($user) ?></td></tr>
    <tr><td>password</td><td>********</td></tr>
    <tr><td>dbname</td><td><?= html_escape($dbname) ?></td></tr>
</table>

<?php
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
?>

<h2>Stringa di connessione usata</h2>
<pre><?= html_escape($conn_string) ?></pre>

<h2>Risultato</h2>
<?php
$conn = @pg_connect($conn_string);
if ($conn):
    echo '<p class="success">✅ Connessione al database riuscita!</p>';
    pg_close($conn);
else:
    echo '<p class="error">❌ Connessione fallita:</p>';
    echo '<pre>' . html_escape(pg_last_error() ?: 'Errore sconosciuto') . '</pre>';
endif;
?>
</body>
</html>

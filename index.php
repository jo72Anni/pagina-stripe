<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Connessione PostgreSQL da DATABASE_URL</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #f0f0f0; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test Connessione DB con DATABASE_URL</h1>

<?php
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo "<p class='error'>❌ Variabile <strong>DATABASE_URL</strong> non impostata.</p>";
    exit;
}

// Parsing della URL
$dbopts = parse_url($databaseUrl);

$host     = $dbopts['host'] ?? '';
$port     = $dbopts['port'] ?? '';
$user     = $dbopts['user'] ?? '';
$password = $dbopts['pass'] ?? '';
$dbname   = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : '';

// Verifica variabili mancanti
$missing = [];
if (!$host)     $missing[] = 'host';
if (!$port)     $missing[] = 'port';
if (!$user)     $missing[] = 'user';
if (!$password) $missing[] = 'password';
if (!$dbname)   $missing[] = 'dbname';

if (!empty($missing)) {
    echo "<p class='error'>❌ Manca(ano) le seguenti informazioni in DATABASE_URL: <strong>" . implode(', ', $missing) . "</strong></p>";
}

// Mostra parametri
echo "<h2>Parametri estratti da DATABASE_URL</h2>";
echo "<table><tr><th>Parametro</th><th>Valore</th></tr>";
echo "<tr><td>host</td><td>$host</td></tr>";
echo "<tr><td>port</td><td>$port</td></tr>";
echo "<tr><td>user</td><td>$user</td></tr>";
echo "<tr><td>password</td><td>********</td></tr>";
echo "<tr><td>dbname</td><td>$dbname</td></tr>";
echo "</table>";

// Costruzione connessione
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";

echo "<h2>Stringa di connessione usata</h2>";
echo "<pre>$conn_string</pre>";

// Prova a connettersi
$conn = @pg_connect($conn_string);

if (!$conn) {
    echo "<p class='error'>❌ Connessione fallita.</p>";
    echo "<pre>" . pg_last_error() . "</pre>";
} else {
    echo "<p class='success'>✅ Connessione al database riuscita!</p>";

    // Test query opzionale
    $res = @pg_query($conn, "SELECT current_database(), current_user, version()");
    if ($res && $row = pg_fetch_row($res)) {
        echo "<h2>Informazioni DB</h2>";
        echo "<ul>";
        echo "<li><strong>Database:</strong> $row[0]</li>";
        echo "<li><strong>Utente:</strong> $row[1]</li>";
        echo "<li><strong>Versione:</strong> $row[2]</li>";
        echo "</ul>";
    }
    pg_close($conn);
}
?>
</body>
</html>

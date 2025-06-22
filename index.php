<?php
echo "<h2>Test Connessione DB con DATABASE_URL</h2>";

// Ottieni DATABASE_URL
$databaseUrl = getenv('DATABASE_URL');
echo "<p><strong>DATABASE_URL:</strong> " . htmlspecialchars($databaseUrl ?: 'NON IMPOSTATA') . "</p>";

if (!$databaseUrl) {
    echo "<p style='color:red;'>❌ Variabile DATABASE_URL non impostata.</p>";
    exit;
}

// Parsiamo la URL
$dbopts = parse_url($databaseUrl);

$host     = $dbopts['host']     ?? '';
$port     = $dbopts['port']     ?? '';
$user     = $dbopts['user']     ?? '';
$password = $dbopts['pass']     ?? '';
$dbname   = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : ''; // Rimuove lo slash iniziale

// Visualizza i parametri
echo "<h3>Parametri estratti da DATABASE_URL</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Parametro</th><th>Valore</th></tr>";
echo "<tr><td>host</td><td>$host</td></tr>";
echo "<tr><td>port</td><td>$port</td></tr>";
echo "<tr><td>user</td><td>$user</td></tr>";
echo "<tr><td>password</td><td>********</td></tr>";
echo "<tr><td>dbname</td><td>$dbname</td></tr>";
echo "</table>";

// Costruisci la stringa di connessione
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";

echo "<h3>Stringa di connessione usata</h3>";
echo "<pre>$conn_string</pre>";

// Prova connessione
$conn = @pg_connect($conn_string);

if (!$conn) {
    echo "<p style='color:red;'>❌ Connessione fallita.</p>";
    $lastError = @pg_last_error(); // Evita errore se connessione non esiste
    if ($lastError) {
        echo "<pre>$lastError</pre>";
    } else {
        echo "<pre>Nessun errore disponibile. Verifica se tutti i parametri sono corretti e se il server è raggiungibile.</pre>";
    }
} else {
    echo "<p style='color:green;'>✅ Connessione riuscita!</p>";
    pg_close($conn);
}
?>

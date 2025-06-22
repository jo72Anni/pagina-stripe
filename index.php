<?php
// Imposta intestazione HTML
header('Content-Type: text/html; charset=utf-8');

// Ottieni DATABASE_URL dalle variabili d'ambiente
$databaseUrl = getenv('DATABASE_URL');

// Output HTML iniziale
echo "<h1>Test Connessione DB con <code>DATABASE_URL</code></h1>";

if (!$databaseUrl) {
    echo "<p style='color:red;'>❌ Variabile <code>DATABASE_URL</code> non trovata nell'ambiente.</p>";
    exit;
}

// Parsiamo la URL in componenti
$dbopts = parse_url($databaseUrl);

// Estraiamo i parametri
$host     = $dbopts['host'] ?? '';
$port     = $dbopts['port'] ?? '';
$user     = $dbopts['user'] ?? '';
$password = $dbopts['pass'] ?? '';
$dbname   = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : '';

// Mostriamo i parametri estratti
echo "<h3>Parametri estratti da DATABASE_URL</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Parametro</th><th>Valore</th></tr>";
echo "<tr><td>host</td><td>$host</td></tr>";
echo "<tr><td>port</td><td>$port</td></tr>";
echo "<tr><td>user</td><td>$user</td></tr>";
echo "<tr><td>password</td><td>********</td></tr>";
echo "<tr><td>dbname</td><td>$dbname</td></tr>";
echo "</table>";

// Costruzione stringa di connessione
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";

// Mostriamo la stringa (parziale per sicurezza)
echo "<h3>Stringa di connessione usata</h3>";
echo "<pre>" . htmlspecialchars(str_replace($password, '[password nascosta]', $conn_string)) . "</pre>";

// Tentiamo la connessione
$conn = @pg_connect($conn_string);

if ($conn) {
    echo "<p style='color:green;'>✅ Connessione al database riuscita!</p>";
    pg_close($conn);
} else {
    $lastError = pg_last_error() ?: 'Connessione non inizializzata o fallita.';
    echo "<p style='color:red;'>❌ Connessione fallita.</p>";
    echo "<h4>Dettaglio errore:</h4><pre>$lastError</pre>";
}
?>

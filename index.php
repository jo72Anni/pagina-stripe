<?php
// Prendi DATABASE_URL da variabili d'ambiente
$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    echo "❌ Variabile DATABASE_URL non impostata.";
    exit;
}

// Parsing della URL
$dbopts = parse_url($database_url);

$host = $dbopts['host'] ?? null;
$port = $dbopts['port'] ?? null;
// Rimuovi slash e spazi extra dal dbname
$dbname = isset($dbopts['path']) ? trim(ltrim($dbopts['path'], '/')) : null;
$user = $dbopts['user'] ?? null;
$password = $dbopts['pass'] ?? null;

// Mostra dati estratti (nascondi password)
echo "<h3>Parametri estratti da DATABASE_URL</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Parametro</th><th>Valore</th></tr>";
echo "<tr><td>host</td><td>" . htmlspecialchars($host) . "</td></tr>";
echo "<tr><td>port</td><td>" . htmlspecialchars($port) . "</td></tr>";
echo "<tr><td>user</td><td>" . htmlspecialchars($user) . "</td></tr>";
echo "<tr><td>password</td><td>********</td></tr>";
echo "<tr><td>dbname</td><td>" . htmlspecialchars($dbname) . "</td></tr>";
echo "</table>";

// Controlla se mancano parametri
$missing = [];
foreach (['host', 'port', 'user', 'password', 'dbname'] as $param) {
    if (!$$param) $missing[] = $param;
}

if ($missing) {
    echo "<p style='color:red;'>❌ Mancano le seguenti variabili: " . implode(', ', $missing) . "</p>";
    exit;
}

// Costruisci stringa connessione
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";
echo "<p>Stringa di connessione usata:<br><code>" . htmlspecialchars($conn_string) . "</code></p>";

// Prova connessione
$conn = @pg_connect($conn_string);

if (!$conn) {
    // Prova a recuperare errore dal sistema o da pg_last_error in modo sicuro
    $error_msg = "Errore sconosciuto.";
    
    // pg_last_error richiede connessione, quindi usiamo error_get_last
    $last_error = error_get_last();
    if ($last_error && !empty($last_error['message'])) {
        $error_msg = $last_error['message'];
    }

    echo "<p style='color:red;'>❌ Connessione fallita.</p>";
    echo "<p><strong>Dettagli errore:</strong> " . htmlspecialchars($error_msg) . "</p>";

    // Opzionale: informazioni di debug extra (sconsigliato in produzione)
    echo "<pre>";
    print_r($dbopts);
    echo "</pre>";

    exit;
}

echo "<p style='color:green;'>✅ Connessione al database riuscita!</p>";

// Chiudi connessione
pg_close($conn);
?>

<?php
// Configurazione
$host = "dpg-d19h6lfgi27c73crpsrg-a.oregon-postgres.render.com";
$port = "5432";
$dbname = "stripe_test_ase0";
$user = "stripe_test_ase0_user";
$password = "0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ";

// Connessione
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require");

if (!$conn) {
    http_response_code(500);
    exit;
}

// Incrementa il contatore
$result = pg_query($conn, "INSERT INTO visit_counter DEFAULT VALUES;");

if ($result) {
    http_response_code(200); // OK per Stripe
} else {
    http_response_code(500); // Errore interno
}

pg_close($conn);

?>

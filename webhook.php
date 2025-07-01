<?php
// Carica la libreria Stripe (assicurati di averla installata con: composer require stripe/stripe-php)
require_once 'vendor/autoload.php';

// Funzione helper per stampa sicura (evita warning htmlspecialchars con null)
function safe_html(string|null $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Prendi DATABASE_URL dalle variabili d'ambiente
$database_url = getenv('DATABASE_URL');
if (!$database_url) {
    error_log("Variabile DATABASE_URL non impostata");
    http_response_code(500);
    exit("DATABASE_URL non impostata");
}

// Parsiamo la URL in componenti
$dbopts = parse_url($database_url);

$host = $dbopts['host'] ?? null;
$port = isset($dbopts['port']) && is_numeric($dbopts['port']) ? (int)$dbopts['port'] : 5432;  // Porta default PostgreSQL
$dbname = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : null;
$user = $dbopts['user'] ?? null;
$password = $dbopts['pass'] ?? null;

// Verifica variabili essenziali
if (!$host || !$dbname || !$user || !$password) {
    error_log("DATABASE_URL malformata o variabili mancanti");
    http_response_code(500);
    exit("DATABASE_URL malformata o variabili mancanti");
}

// Stampa parametri (per debug sicuro, rimuovi in produzione)
echo "<h3>Parametri DB estratti:</h3>";
echo "host: " . safe_html($host) . "<br>";
echo "port: " . safe_html((string)$port) . "<br>";
echo "user: " . safe_html($user) . "<br>";
echo "password: ********<br>";
echo "dbname: " . safe_html($dbname) . "<br>";

// Costruisci la stringa di connessione PostgreSQL
$conn_string = sprintf(
    "host=%s port=%d dbname=%s user=%s password=%s sslmode=require",
    $host,
    $port,
    $dbname,
    $user,
    $password
);

// Connessione al database
$conn = pg_connect($conn_string);
if (!$conn) {
    error_log("[STRIPE_WEBHOOK] Errore connessione database: " . pg_last_error());
    http_response_code(500);
    exit("Errore connessione database");
}

// Configura la chiave segreta Stripe
$stripe_secret_key = getenv('STRIPE_SECRET_KEY');
if (!$stripe_secret_key) {
    error_log("STRIPE_SECRET_KEY non impostata");
    http_response_code(500);
    exit("STRIPE_SECRET_KEY non impostata");
}
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Leggi payload webhook e intestazione firma
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');
if (!$endpoint_secret) {
    error_log("STRIPE_WEBHOOK_SECRET non impostata");
    http_response_code(500);
    exit("STRIPE_WEBHOOK_SECRET non impostata");
}

// Verifica firma webhook Stripe
try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(Exception $e) {
    error_log("[STRIPE_WEBHOOK] Errore verifica webhook: " . $e->getMessage());
    http_response_code(400);
    exit("Firma webhook non valida");
}

// Estrai dati pagamento
$payment_data = $event->data->object;

// Controllo campi obbligatori
$required_fields = ['id', 'amount', 'currency', 'status'];
foreach ($required_fields as $field) {
    if (!isset($payment_data->$field)) {
        error_log("[STRIPE_WEBHOOK] Manca campo obbligatorio: $field");
        http_response_code(400);
        exit("Campo obbligatorio mancante: $field");
    }
}

// Prepara dati per inserimento DB
$dati_db = [
    'stripe_payment_id' => $payment_data->id,
    'amount'            => $payment_data->amount,
    'currency'          => $payment_data->currency,
    'status'            => $payment_data->status,
    'customer_id'       => $payment_data->customer ?? null,
    'payment_method'    => $payment_data->payment_method ?? null,
    'receipt_email'     => $payment_data->receipt_email ?? null,
    'stripe_created_at' => isset($payment_data->created) ? date('Y-m-d H:i:s', $payment_data->created) : null,
    'raw_event'         => json_encode($payment_data),
];

// Query parametrizzata per inserire dati
$query = "INSERT INTO stripe_transactions (
    stripe_payment_id, amount, currency, status, customer_id,
    payment_method, receipt_email, stripe_created_at, raw_event
) VALUES (
    $1, $2, $3, $4, $5, $6, $7, $8, $9
)";

$result = pg_query_params($conn, $query, array_values($dati_db));

if (!$result) {
    error_log("[STRIPE_WEBHOOK] Errore inserimento database: " . pg_last_error($conn));
    http_response_code(500);
    exit("Errore inserimento dati");
}

// Tutto ok
http_response_code(200);
error_log("[STRIPE_WEBHOOK] Pagamento {$payment_data->id} registrato con successo");

// Chiudi connessione DB
pg_close($conn);
?>



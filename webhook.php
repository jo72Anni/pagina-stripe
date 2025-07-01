<?php
require_once 'vendor/autoload.php';

// Funzione helper per ottenere variabili d'ambiente con fallback
function env(string $key, $default = null) {
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// Prendi DATABASE_URL dalle variabili d'ambiente
$database_url = env('DATABASE_URL');
if (!$database_url) {
    error_log("[STRIPE_WEBHOOK] DATABASE_URL non impostata");
    http_response_code(500);
    exit("DATABASE_URL non configurata");
}

// Parsiamo la DATABASE_URL in componenti
$dbopts = parse_url($database_url);
if (!$dbopts) {
    error_log("[STRIPE_WEBHOOK] DATABASE_URL malformata");
    http_response_code(500);
    exit("DATABASE_URL malformata");
}

$host = $dbopts['host'] ?? null;
$port = $dbopts['port'] ?? 5432; // porta default PostgreSQL
$user = $dbopts['user'] ?? null;
$password = $dbopts['pass'] ?? null;
$dbname = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : null;

if (!$host || !$user || !$password || !$dbname) {
    error_log("[STRIPE_WEBHOOK] DATABASE_URL incompleta");
    http_response_code(500);
    exit("DATABASE_URL incompleta");
}

$conn_string = sprintf(
    "host=%s port=%d dbname=%s user=%s password=%s sslmode=require",
    $host, $port, $dbname, $user, $password
);

$conn = pg_connect($conn_string);
if (!$conn) {
    error_log("[STRIPE_WEBHOOK] Errore connessione database: " . pg_last_error());
    http_response_code(500);
    exit("Errore connessione database");
}

// Configura la chiave segreta Stripe
$stripe_secret_key = env('STRIPE_SECRET_KEY');
if (!$stripe_secret_key) {
    error_log("[STRIPE_WEBHOOK] STRIPE_SECRET_KEY non impostata");
    http_response_code(500);
    exit("Chiave Stripe non configurata");
}
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Recupera il payload e la firma dal webhook Stripe
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
if (!$endpoint_secret) {
    error_log("[STRIPE_WEBHOOK] STRIPE_WEBHOOK_SECRET non impostata");
    http_response_code(500);
    exit("Segreto webhook non configurato");
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
    error_log("[STRIPE_WEBHOOK] Errore verifica webhook: " . $e->getMessage());
    http_response_code(400);
    exit("Firma webhook non valida");
}

$payment_data = $event->data->object;
$required_fields = ['id', 'amount', 'currency', 'status'];
foreach ($required_fields as $field) {
    if (!isset($payment_data->$field)) {
        error_log("[STRIPE_WEBHOOK] Manca campo obbligatorio: $field");
        http_response_code(400);
        exit("Campo obbligatorio mancante: $field");
    }
}

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

http_response_code(200);
error_log("[STRIPE_WEBHOOK] Pagamento {$payment_data->id} registrato con successo");
pg_close($conn);

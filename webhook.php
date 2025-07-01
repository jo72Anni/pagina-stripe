<?php
// Carica la libreria Stripe (assicurati di aver installato con: composer require stripe/stripe-php)
require_once 'vendor/autoload.php';

// Recupera DATABASE_URL dalle variabili d'ambiente
$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    error_log("Variabile DATABASE_URL non impostata");
    http_response_code(500);
    exit;
}

// Parsing della URL del database
$dbopts = parse_url($database_url);

$host = $dbopts['host'] ?? null;
$port = $dbopts['port'] ?? 5432; // default PostgreSQL port se non presente
$dbname = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : null;
$user = $dbopts['user'] ?? null;
$password = $dbopts['pass'] ?? null;

if (!$host || !$dbname || !$user || !$password) {
    error_log("DATABASE_URL malformata");
    http_response_code(500);
    exit;
}

// Configura stringa di connessione per PostgreSQL
$conn_string = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $host,
    $port,
    $dbname,
    $user,
    $password
);

// Connessione al database PostgreSQL
$conn = pg_connect($conn_string);
if (!$conn) {
    error_log("[STRIPE_WEBHOOK] Errore connessione database: " . pg_last_error());
    http_response_code(500);
    exit;
}

// Configura la chiave segreta Stripe
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
if (!$stripeSecretKey) {
    error_log("[STRIPE_WEBHOOK] STRIPE_SECRET_KEY non impostata");
    http_response_code(500);
    exit;
}
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Leggi payload e header Stripe
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

if (!$endpoint_secret) {
    error_log("[STRIPE_WEBHOOK] STRIPE_WEBHOOK_SECRET non impostata");
    http_response_code(500);
    exit;
}

try {
    // Verifica la firma e costruisci l'evento Stripe
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException $e) {
    // Payload non valido
    error_log("[STRIPE_WEBHOOK] Payload non valido: " . $e->getMessage());
    http_response_code(400);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Firma non valida
    error_log("[STRIPE_WEBHOOK] Firma webhook non valida: " . $e->getMessage());
    http_response_code(400);
    exit;
}

// Estrai i dati di pagamento dall'evento
$payment_data = $event->data->object;

// Controlla i campi obbligatori
$required_fields = ['id', 'amount', 'currency', 'status'];
foreach ($required_fields as $field) {
    if (!isset($payment_data->$field)) {
        error_log("[STRIPE_WEBHOOK] Campo obbligatorio mancante: $field");
        http_response_code(400);
        exit;
    }
}

// Prepara dati da inserire nel database
$dati_db = [
    'stripe_id' => $payment_data->id,
    'amount' => $payment_data->amount,
    'currency' => $payment_data->currency,
    'status' => $payment_data->status,
    'customer' => $payment_data->customer ?? null,
    'method' => $payment_data->payment_method ?? null,
    'email' => $payment_data->receipt_email ?? null,
    'created' => isset($payment_data->created) ? date('Y-m-d H:i:s', $payment_data->created) : null,
    'raw_data' => json_encode($payment_data)
];

// Query SQL parametrizzata per inserire i dati
$query = "
    INSERT INTO stripe_transactions (
        stripe_payment_id, amount, currency, status, customer_id,
        payment_method, receipt_email, stripe_created_at, raw_event
    ) VALUES (
        $1, $2, $3, $4, $5, $6, $7, $8, $9
    )
";

// Esecuzione della query
$result = pg_query_params($conn, $query, [
    $dati_db['stripe_id'],
    $dati_db['amount'],
    $dati_db['currency'],
    $dati_db['status'],
    $dati_db['customer'],
    $dati_db['method'],
    $dati_db['email'],
    $dati_db['created'],
    $dati_db['raw_data']
]);

if (!$result) {
    error_log("[STRIPE_WEBHOOK] Errore inserimento database: " . pg_last_error($conn));
    http_response_code(500);
} else {
    http_response_code(200);
    error_log("[STRIPE_WEBHOOK] Pagamento {$payment_data->id} registrato con successo");
}

pg_close($conn);
?>

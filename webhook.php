<?php
// Carica la libreria Stripe (assicurati che composer sia stato usato: composer require stripe/stripe-php)
require_once 'vendor/autoload.php';

// Funzione helper per log personalizzato
function log_message($message) {
    $logFile = getenv('LOG_FILE') ?: null;
    $timestamp = date('Y-m-d H:i:s');
    if ($logFile) {
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    } else {
        error_log("[$timestamp] $message");
    }
}

// Parsing sicuro della DATABASE_URL
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    log_message("[CONFIG ERROR] Variabile DATABASE_URL mancante.");
    http_response_code(500);
    exit;
}

$url = parse_url($databaseUrl);
$db_config = [
    'host'     => $url['host'],
    'port'     => $url['port'],
    'dbname'   => ltrim($url['path'], '/'),
    'user'     => $url['user'],
    'password' => $url['pass']
];

// Connessione al database PostgreSQL
$conn_string = "host={$db_config['host']} port={$db_config['port']} dbname={$db_config['dbname']} 
                user={$db_config['user']} password={$db_config['password']} sslmode=require";

$conn = pg_connect($conn_string);

if (!$conn) {
    log_message("[DB ERROR] Errore connessione database: " . pg_last_error());
    http_response_code(500);
    exit;
}

// Configura chiave segreta di Stripe
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');

if (!$stripeSecretKey || !$webhookSecret) {
    log_message("[CONFIG ERROR] STRIPE_SECRET_KEY o STRIPE_WEBHOOK_SECRET mancanti.");
    http_response_code(500);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Riceve il payload e la firma
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhookSecret);
} catch (Exception $e) {
    log_message("[WEBHOOK ERROR] Firma non valida: " . $e->getMessage());
    http_response_code(400);
    exit;
}

// Gestisci solo payment_intent.succeeded
if ($event->type !== 'payment_intent.succeeded') {
    log_message("[WEBHOOK IGNORED] Evento non gestito: " . $event->type);
    http_response_code(200);
    exit;
}

// Estrai oggetto evento
$payment = $event->data->object;

// Verifica campi obbligatori
$required_fields = ['id', 'amount', 'currency', 'status'];
foreach ($required_fields as $field) {
    if (!isset($payment->$field)) {
        log_message("[WEBHOOK ERROR] Campo mancante: $field");
        http_response_code(400);
        exit;
    }
}

// Prepara dati per inserimento
$data = [
    'stripe_id' => $payment->id,
    'amount'    => $payment->amount,
    'currency'  => $payment->currency,
    'status'    => $payment->status,
    'customer'  => $payment->customer ?? null,
    'method'    => $payment->payment_method ?? null,
    'email'     => $payment->receipt_email ?? null,
    'created'   => isset($payment->created) ? date('Y-m-d H:i:s', $payment->created) : null,
    'raw_data'  => json_encode($payment)
];

// Query con prevenzione duplicati (idempotenza)
$query = "INSERT INTO stripe_transactions (
    stripe_payment_id, amount, currency, status, customer_id,
    payment_method, receipt_email, stripe_created_at, raw_event
) VALUES (
    $1, $2, $3, $4, $5, $6, $7, $8, $9
) ON CONFLICT (stripe_payment_id) DO NOTHING";

$result = pg_query_params($conn, $query, [
    $data['stripe_id'],
    $data['amount'],
    $data['currency'],
    $data['status'],
    $data['customer'],
    $data['method'],
    $data['email'],
    $data['created'],
    $data['raw_data']
]);

if (!$result) {
    log_message("[DB ERROR] Errore durante l'inserimento: " . pg_last_error($conn));
    http_response_code(500);
} else {
    log_message("[WEBHOOK SUCCESS] Pagamento registrato: {$payment->id}");
    http_response_code(200);
}

pg_close($conn);
?>

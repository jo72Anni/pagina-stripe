<?php
// Carica la libreria Stripe (assicurati di aver installato con: composer require stripe/stripe-php)
require_once 'vendor/autoload.php';

// Prendi DATABASE_URL dalle variabili d'ambiente
$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    error_log("Variabile DATABASE_URL non impostata");
    http_response_code(500);
    exit;
}

// Parsiamo la URL in componenti
$dbopts = parse_url($database_url);

$host = $dbopts['host'] ?? null;
$port = $dbopts['port'] ?? null;
$dbname = isset($dbopts['path']) ? ltrim($dbopts['path'], '/') : null;
$user = $dbopts['user'] ?? null;
$password = $dbopts['pass'] ?? null;

if (!$host || !$port || !$dbname || !$user || !$password) {
    error_log("DATABASE_URL malformata");
    http_response_code(500);
    exit;
}

// Assegna a $db_config
$db_config = [
    'host'     => $host,
    'port'     => $port,
    'dbname'   => $dbname,
    'user'     => $user,
    'password' => $password,
];

// Connessione al database PostgreSQL
$conn_string = "host={$db_config['host']} port={$db_config['port']} dbname={$db_config['dbname']} user={$db_config['user']} password={$db_config['password']} sslmode=require";

                
$conn = pg_connect($conn_string);

if (!$conn) {
    error_log("[STRIPE_WEBHOOK] Errore connessione database: " . pg_last_error());
    http_response_code(500);
    exit;
}

// Configura la chiave segreta di Stripe
\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));

// Elabora il webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

try {
    // Verifica la firma del webhook
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(Exception $e) {
    error_log("[STRIPE_WEBHOOK] Errore verifica webhook: " . $e->getMessage());
    http_response_code(400);
    exit;
}

// Estrai i dati dal payload
$payment_data = $event->data->object;

// Verifica i campi obbligatori
$campi_obbligatori = ['id', 'amount', 'currency', 'status'];
foreach ($campi_obbligatori as $campo) {
    if (!isset($payment_data->$campo)) {
        error_log("[STRIPE_WEBHOOK] Manca campo obbligatorio: $campo");
        http_response_code(400);
        exit;
    }
}

// Prepara i dati per il database
$dati_db = [
    'stripe_id' => $payment_data->id,
    'amount'    => $payment_data->amount,
    'currency'  => $payment_data->currency,
    'status'    => $payment_data->status,
    'customer'  => $payment_data->customer ?? null,
    'method'    => $payment_data->payment_method ?? null,
    'email'     => $payment_data->receipt_email ?? null,
    'created'   => isset($payment_data->created) ? date('Y-m-d H:i:s', $payment_data->created) : null,
    'raw_data'  => json_encode($payment_data)
];

// Query per inserire i dati
$query = "INSERT INTO stripe_transactions (
    stripe_payment_id, amount, currency, status, customer_id,
    payment_method, receipt_email, stripe_created_at, raw_event
) VALUES (
    $1, $2, $3, $4, $5, $6, $7, $8, $9
)";

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

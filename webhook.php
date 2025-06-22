<?php
require_once 'vendor/autoload.php';

// 1. Recupero e validazione della DATABASE_URL
$databaseUrl = getenv('DATABASE_URL');
if (empty($databaseUrl)) {
    error_log("[STRIPE_WEBHOOK] ERRORE: DATABASE_URL non configurata");
    http_response_code(500);
    exit(json_encode(['error' => 'Database configuration missing']));
}

// 2. Parsing specializzato per Render.com
$pattern = '/postgres:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/(.+)/';
if (!preg_match($pattern, $databaseUrl, $matches)) {
    error_log("[STRIPE_WEBHOOK] ERRORE: Formato DATABASE_URL non valido");
    http_response_code(500);
    exit(json_encode(['error' => 'Invalid database URL format']));
}

// 3. Estrazione componenti con URL decoding
$user = $matches[1];
$password = urldecode($matches[2]);
$host = $matches[3];
$port = $matches[4];
$dbname = $matches[5];

// 4. Stringa di connessione ottimizzata per Render.com
$conn_string = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $host,
    $port,
    $dbname,
    $user,
    $password
);

// 5. Connessione con gestione avanzata degli errori
$conn = @pg_connect($conn_string . " connect_timeout=5");
if (!$conn) {
    $error = pg_last_error();
    error_log("[STRIPE_WEBHOOK] ERRORE CONNESSIONE: " . $error);
    error_log("[STRIPE_WEBHOOK] DETTAGLI: Host=$host, Port=$port, DB=$dbname, User=$user");
    http_response_code(500);
    exit(json_encode([
        'error' => 'Database connection failed',
        'details' => $error,
        'connection_info' => [
            'host' => $host,
            'port' => $port,
            'database' => $dbname,
            'user' => $user
        ]
    ]));
}

// 6. Configurazioni post-connessione
pg_set_client_encoding($conn, 'UTF-8');
pg_query($conn, "SET TIME ZONE 'UTC'");

// Verifica finale
if (pg_connection_status($conn) !== PGSQL_CONNECTION_OK) {
    error_log("[STRIPE_WEBHOOK] ERRORE: Connessione instabile");
    http_response_code(500);
    exit(json_encode(['error' => 'Unstable database connection']));
}

// Impostiamo il client encoding a UTF-8 per evitare problemi con i caratteri speciali
pg_set_client_encoding($conn, 'UTF-8');


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

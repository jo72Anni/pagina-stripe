<?php
// Carica la libreria Stripe (assicurati di aver installato con: composer require stripe/stripe-php)
require_once 'vendor/autoload.php';

// Ottieni DATABASE_URL dalle variabili d'ambiente con fallback a un valore predefinito se necessario
$databaseUrl = getenv('DATABASE_URL');

if (empty($databaseUrl)) {
    error_log("[STRIPE_WEBHOOK] ERRORE: Variabile DATABASE_URL non trovata o vuota");
    http_response_code(500);
    exit(json_encode(['error' => 'Configurazione database mancante']));
}

// Parsing della URL per estrarre i componenti con gestione degli errori
$dbOptions = parse_url($databaseUrl);
if ($dbOptions === false) {
    error_log("[STRIPE_WEBHOOK] ERRORE: Parsing DATABASE_URL fallito - URL malformata");
    http_response_code(500);
    exit(json_encode(['error' => 'Configurazione database non valida']));
}

// Estrazione e validazione dei parametri
$host = $dbOptions['host'] ?? null;
$port = $dbOptions['port'] ?? '5432'; // Default PostgreSQL port
$dbName = isset($dbOptions['path']) ? trim($dbOptions['path'], '/') : null;
$user = $dbOptions['user'] ?? null;
$password = isset($dbOptions['pass']) ? urldecode($dbOptions['pass']) : null;

// Verifica che tutti i componenti obbligatori siano presenti
$missingParams = [];
if (empty($host)) $missingParams[] = 'host';
if (empty($dbName)) $missingParams[] = 'dbname';
if (empty($user)) $missingParams[] = 'user';
if (empty($password)) $missingParams[] = 'password';

if (!empty($missingParams)) {
    error_log("[STRIPE_WEBHOOK] ERRORE: DATABASE_URL incompleta - Parametri mancanti: " . implode(', ', $missingParams));
    http_response_code(500);
    exit(json_encode(['error' => 'Configurazione database incompleta']));
}

// Correzione: c'era una discrepanza tra $dbName (maiuscolo) e $dbname (minuscolo)
$dbname = $dbName; // Uniformiamo le variabili

// Costruzione della stringa di connessione con parametri aggiuntivi per Render.com
$conn_string = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $host,
    $port,
    $dbname,
    $user,
    $password
);

// Tentativo di connessione con timeout
$conn = pg_connect($conn_string . " connect_timeout=5");

if (!$conn) {
    $errorMsg = pg_last_error();
    error_log("[STRIPE_WEBHOOK] ERRORE CONNESSIONE DB: " . $errorMsg);
    error_log("[STRIPE_WEBHOOK] Stringa connessione: " . str_replace($password, '*****', $conn_string));
    http_response_code(500);
    exit(json_encode([
        'error' => 'Database connection failed',
        'details' => $errorMsg
    ]));
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

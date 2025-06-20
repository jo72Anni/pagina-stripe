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

// Leggo il payload JSON inviato da Stripe (webhook)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Controllo base che il JSON sia valido
if (!$data) {
    http_response_code(400); // Bad Request
    exit;
}

// Estrazione dei campi (adatta in base al payload Stripe che ti interessa)
$stripe_payment_id = $data['data']['object']['id'] ?? null;
$amount = $data['data']['object']['amount'] ?? null;
$currency = $data['data']['object']['currency'] ?? null;
$status = $data['data']['object']['status'] ?? null;
$customer_id = $data['data']['object']['customer'] ?? null;
$payment_method = $data['data']['object']['payment_method'] ?? null;
$receipt_email = $data['data']['object']['receipt_email'] ?? null;
$stripe_created_at = isset($data['data']['object']['created']) ? date('Y-m-d H:i:s', $data['data']['object']['created']) : null;
$raw_event = json_encode($data);

// Verifica campi essenziali
if (!$stripe_payment_id || !$amount || !$currency || !$status) {
    http_response_code(400);
    exit;
}

// Query di inserimento (uso pg_query_params per sicurezza)
$query = "INSERT INTO stripe_transactions (
    stripe_payment_id, amount, currency, status, customer_id,
    payment_method, receipt_email, stripe_created_at, raw_event
) VALUES (
    $1, $2, $3, $4, $5, $6, $7, $8, $9
)";

$result = pg_query_params($conn, $query, [
    $stripe_payment_id,
    $amount,
    $currency,
    $status,
    $customer_id,
    $payment_method,
    $receipt_email,
    $stripe_created_at,
    $raw_event
]);

if ($result) {
    http_response_code(200);
} else {
    http_response_code(500);
}

pg_close($conn);
?>

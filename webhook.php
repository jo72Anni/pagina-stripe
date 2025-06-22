<?php
require 'vendor/autoload.php'; // Composer: stripe/stripe-php

// Recupero variabili d’ambiente (fornite via Render Dashboard)
$databaseUrl = getenv('DATABASE_URL');
$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
$logFile = getenv('LOG_FILE') ?: './logs/stripe_webhook.log';

// Parse della DATABASE_URL
$dbParts = parse_url($databaseUrl);
$dbHost = $dbParts['host'];
$dbPort = $dbParts['port'];
$dbUser = $dbParts['user'];
$dbPass = $dbParts['pass'];
$dbName = ltrim($dbParts['path'], '/');

// Connessione al database PostgreSQL
$conn = pg_connect("host=$dbHost port=$dbPort dbname=$dbName user=$dbUser password=$dbPass sslmode=require");
if (!$conn) {
    error_log("DB connection failed\n", 3, $logFile);
    http_response_code(500);
    exit;
}

// Ricezione payload Stripe
$input = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($input, $sig_header, $webhookSecret);
} catch (\UnexpectedValueException $e) {
    error_log("Invalid payload: " . $e->getMessage() . "\n", 3, $logFile);
    http_response_code(400);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log("Invalid signature: " . $e->getMessage() . "\n", 3, $logFile);
    http_response_code(400);
    exit;
}

// Estrazione dati dal payload verificato
$data = $event->data->object;

$stripe_payment_id = $data->id ?? null;
$amount = $data->amount ?? null;
$currency = $data->currency ?? null;
$status = $data->status ?? null;
$customer_id = $data->customer ?? null;
$payment_method = $data->payment_method ?? null;
$receipt_email = $data->receipt_email ?? null;
$stripe_created_at = isset($data->created) ? date('Y-m-d H:i:s', $data->created) : null;
$raw_event = json_encode($event);

// Controllo dati essenziali
if (!$stripe_payment_id || !$amount || !$currency || !$status) {
    error_log("Missing essential fields\n", 3, $logFile);
    http_response_code(400);
    exit;
}

// Verifica se l'evento è già stato registrato (evita duplicati)
$check_query = "SELECT 1 FROM stripe_transactions WHERE stripe_payment_id = $1 LIMIT 1";
$check_result = pg_query_params($conn, $check_query, [$stripe_payment_id]);
if (pg_num_rows($check_result) > 0) {
    http_response_code(200); // Già presente, ma tutto OK
    exit;
}

// Inserimento dati
$insert_query = "INSERT INTO stripe_transactions (
    stripe_payment_id, amount, currency, status, customer_id,
    payment_method, receipt_email, stripe_created_at, raw_event
) VALUES (
    $1, $2, $3, $4, $5, $6, $7, $8, $9
)";

$insert_result = pg_query_params($conn, $insert_query, [
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

if ($insert_result) {
    http_response_code(200);
} else {
    error_log("DB insert failed: " . pg_last_error($conn) . "\n", 3, $logFile);
    http_response_code(500);
}

pg_close($conn);


?>

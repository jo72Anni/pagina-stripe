<?php
// ==============================================
// CONFIGURAZIONE COMPLETA DEL WEBHOOK
// ==============================================
header('Content-Type: application/json');
require_once 'vendor/autoload.php';

// Configurazione logging avanzato
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', getenv('LOG_FILE') ?: __DIR__.'/stripe_webhook_errors.log');

// ==============================================
// INIZIALIZZAZIONE DATABASE
// ==============================================
$db_url = parse_url(getenv('DATABASE_URL'));

try {
    $db = new PDO(
        "pgsql:host={$db_url['host']};port={$db_url['port']};dbname=".ltrim($db_url['path'], '/').";sslmode=require",
        $db_url['user'],
        $db_url['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("[DB CONNECTION FAILED] ".$e->getMessage());
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error_code' => 'db_connection_failed'
    ]));
}

// ==============================================
// GESTIONE RICHIESTA WEBHOOK
// ==============================================
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // 1. Validazione del payload
    if (empty($payload)) {
        throw new Exception("Empty payload received");
    }

    // 2. Verifica la firma Stripe
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        getenv('STRIPE_WEBHOOK_SECRET')
    );

    // 3. Estrazione dati avanzata
    $eventData = $event->data->object;
    $customerDetails = $eventData->customer_details ?? null;
    
    $dbData = [
        'event_id' => $event->id,
        'event_type' => $event->type,
        'customer_email' => $customerDetails->email ?? $eventData->customer_email ?? null,
        'customer_name' => $customerDetails->name ?? null,
        'amount' => $eventData->amount ?? $eventData->amount_total ?? null,
        'currency' => $eventData->currency ?? null,
        'payment_status' => $eventData->payment_status ?? null,
        'created' => date('Y-m-d H:i:s', $event->created),
        'raw_data' => json_encode($event)
    ];

    // 4. Inserimento transazionale
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        INSERT INTO stripe_webhooks (
            event_id, event_type, customer_email, customer_name,
            amount, currency, payment_status, created, raw_data
        ) VALUES (
            :event_id, :event_type, :customer_email, :customer_name,
            :amount, :currency, :payment_status, :created, :raw_data
        )
        ON CONFLICT (event_id) DO NOTHING
    ");

    $stmt->execute($dbData);
    
    // 5. Verifica inserimento
    if ($stmt->rowCount() === 0) {
        error_log("[DUPLICATE EVENT] Event ID: ".$event->id);
        $db->rollBack();
    } else {
        $db->commit();
    }

    // 6. Risposta JSON completa
    echo json_encode([
        'status' => 'success',
        'event_id' => $event->id,
        'type' => $event->type,
        'timestamp' => $event->created,
        'database_id' => $db->lastInsertId()
    ]);

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid signature',
        'error_code' => 'invalid_signature'
    ]);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid payload',
        'error_code' => 'invalid_payload'
    ]);
} catch (Exception $e) {
    error_log("[PROCESSING ERROR] ".$e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_code' => 'processing_error'
    ]);
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
}

// Chiusura connessione
$db = null;
?>

<?php
// ==============================================
// CONFIGURAZIONE INIZIALE
// ==============================================
header('Content-Type: application/json');
define('LOG_FILE', __DIR__.'/stripe_webhook.log');

// Inizializza logging
file_put_contents(LOG_FILE, "\n\n".date('[Y-m-d H:i:s]')." === NUOVA RICHIESTA ===\n", FILE_APPEND);

// ==============================================
// FUNZIONI DI UTILITY
// ==============================================
function logError($message, $context = []) {
    $logEntry = date('[Y-m-d H:i:s]')." ERRORE: ".$message."\n";
    if (!empty($context)) {
        $logEntry .= "Contesto: ".json_encode($context, JSON_PRETTY_PRINT)."\n";
    }
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// ==============================================
// CONNESSIONE AL DATABASE
// ==============================================
try {
    $dbUrl = parse_url(getenv('DATABASE_URL'));
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
        $dbUrl['host'],
        $dbUrl['port'],
        ltrim($dbUrl['path'], '/')
    );
    
    $db = new PDO($dsn, $dbUrl['user'], $dbUrl['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]')." Connessione al DB riuscita\n", FILE_APPEND);
} catch (PDOException $e) {
    logError("Connessione DB fallita", ['error' => $e->getMessage()]);
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// ==============================================
// ELABORAZIONE WEBHOOK
// ==============================================
try {
    // Leggi il payload
    $payload = json_decode(file_get_contents('php://input'), true);
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]')." Payload ricevuto: ".json_encode($payload)."\n", FILE_APPEND);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Payload JSON non valido");
    }

    // Estrai dati obbligatori
    $requiredFields = ['id', 'type', 'data'];
    foreach ($requiredFields as $field) {
        if (!isset($payload[$field])) {
            throw new Exception("Campo mancante: $field");
        }
    }

    $eventId = $payload['id'];
    $eventType = $payload['type'];
    $eventData = $payload['data']['object'] ?? [];

    // Prepara dati per il database
    $dbData = [
        ':event_id' => $eventId,
        ':event_type' => $eventType,
        ':customer_email' => $eventData['customer_email'] ?? $eventData['customer_details']['email'] ?? null,
        ':customer_name' => $eventData['customer_name'] ?? $eventData['customer_details']['name'] ?? null,
        ':amount_total' => $eventData['amount_total'] ?? null,
        ':payment_status' => $eventData['payment_status'] ?? null,
        ':raw_payload' => json_encode($payload)
    ];

    // Query di inserimento
    $stmt = $db->prepare("
        INSERT INTO stripe_webhooks (
            event_id, event_type, customer_email,
            customer_name, amount_total, payment_status,
            raw_payload
        ) VALUES (
            :event_id, :event_type, :customer_email,
            :customer_name, :amount_total, :payment_status,
            :raw_payload
        )
    ");

    $stmt->execute($dbData);
    
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]')." Evento registrato: $eventId\n", FILE_APPEND);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    logError($e->getMessage(), [
        'payload' => $payload ?? null,
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// ==============================================
// CHIUSURA CONNESSIONE
// ==============================================
$db = null;
?>

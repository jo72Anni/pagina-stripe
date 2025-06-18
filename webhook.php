<?php
// ==============================================
// WEBHOOK COMPLETO PER STRIPE
// ==============================================

// Configurazione iniziale
header('Content-Type: application/json');
define('LOG_FILE', __DIR__.'/stripe_webhook.log');
date_default_timezone_set('Europe/Rome');

// Funzione di logging avanzata
function logMessage($message, $context = []) {
    $logEntry = date('[Y-m-d H:i:s]')." ".$message."\n";
    if (!empty($context)) {
        $logEntry .= "Context: ".json_encode($context, JSON_PRETTY_PRINT)."\n";
    }
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// Inizializzazione
logMessage("=== NUOVA RICHIESTA WEBHOOK ===");

try {
    // 1. Connessione al Database PostgreSQL
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false
    ]);
    
    logMessage("Connessione al database riuscita");

    // 2. Validazione del payload
    $payload = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Payload JSON non valido");
    }
    
    logMessage("Payload ricevuto", ['payload' => $payload]);

    // 3. Verifica campi obbligatori
    $requiredFields = ['id', 'type', 'data'];
    foreach ($requiredFields as $field) {
        if (!isset($payload[$field])) {
            throw new Exception("Campo obbligatorio mancante: $field");
        }
    }

    // 4. Estrazione dati
    $eventId = $payload['id'];
    $eventType = $payload['type'];
    $eventData = $payload['data']['object'] ?? [];
    
    $dbData = [
        ':event_id' => $eventId,
        ':event_type' => $eventType,
        ':customer_email' => $eventData['customer_email'] ?? $eventData['customer_details']['email'] ?? null,
        ':customer_name' => $eventData['customer_name'] ?? $eventData['customer_details']['name'] ?? null,
        ':amount_total' => $eventData['amount_total'] ?? null,
        ':currency' => $eventData['currency'] ?? null,
        ':payment_status' => $eventData['payment_status'] ?? null,
        ':raw_payload' => json_encode($payload)
    ];

    // 5. Inserimento nel database
    $stmt = $db->prepare("
        INSERT INTO stripe_webhooks (
            event_id, event_type, customer_email,
            customer_name, amount_total, currency,
            payment_status, raw_payload
        ) VALUES (
            :event_id, :event_type, :customer_email,
            :customer_name, :amount_total, :currency,
            :payment_status, :raw_payload
        )
    ");

    $stmt->execute($dbData);
    $insertId = $db->lastInsertId();
    
    logMessage("Evento registrato con ID: $insertId", ['event_id' => $eventId]);

    // 6. Risposta di successo
    echo json_encode([
        'status' => 'success',
        'event_id' => $eventId,
        'database_id' => $insertId
    ]);

} catch (PDOException $e) {
    logMessage("Errore database", ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    logMessage("Errore elaborazione", ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Chiusura connessione
$db = null;
logMessage("=== FINE ELABORAZIONE ===");
?>($payload[$field])) {
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

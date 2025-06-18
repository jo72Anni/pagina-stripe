<?php
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook Stripe ricevuto ===");

// Legge il body della richiesta
$payload = file_get_contents('php://input');
logMessage("Payload grezzo ricevuto: " . $payload);

// Decodifica JSON
$data = json_decode($payload, true);

// Controlla se è un JSON valido
if (!$data) {
    logMessage("Errore: payload non JSON valido");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

logMessage("Payload decodificato: " . var_export($data, true));

try {
    $pdo = new PDO(
        "pgsql:host=dpg-d19h6lfgi27c73crpsrg-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_ase0;sslmode=require",
        "stripe_test_ase0_user",
        "0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inserisci evento in stripe_webhooks
    $event_id = substr($data['id'] ?? 'missing_id', 0, 255);
    $event_type = substr($data['type'] ?? 'missing_type', 0, 255);
    $payload_serialized = substr(json_encode($data), 0, 10000);

    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed)
        VALUES (:event_id, :event_type, :payload, NOW(), false)
        ON CONFLICT (event_id) DO NOTHING
    ");
    $stmt->execute([
        ':event_id' => $event_id,
        ':event_type' => $event_type,
        ':payload' => $payload_serialized
    ]);
    logMessage("Evento ID $event_id inserito correttamente in stripe_webhooks");

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
}
?>

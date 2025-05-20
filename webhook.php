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

// Log della struttura del JSON per debug
logMessage("Payload decodificato: " . var_export($data, true));

try {
    $pdo = new PDO(
        "pgsql:host=dpg-d0chkfh5pdvs73dn6jog-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_hwr1;sslmode=require",
        "stripe_test_hwr1_user",
        "ctnl7Y70eQFUNXMOdY1ddREJIm9sVf09"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Estrazione sicura dei campi
    $event_id = substr($data['id'] ?? 'missing_id', 0, 255);
    $event_type = substr($data['type'] ?? 'missing_type', 0, 255);
    $payload_serialized = substr(json_encode($data), 0, 1000);  // puoi aumentare il limite

    // Inserimento nel DB
    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed)
        VALUES (:event_id, :event_type, :payload, NOW(), false)
    ");
    $stmt->execute([
        ':event_id' => $event_id,
        ':event_type' => $event_type,
        ':payload' => $payload_serialized
    ]);

    logMessage("Evento ID $event_id inserito correttamente nel DB");

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
?>

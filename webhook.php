<?php
// Funzione semplice per logging errori (scrive nel log PHP)
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook semplice chiamato ===");

// Leggi il payload raw
$payload = file_get_contents('php://input');
logMessage("Payload ricevuto: " . $payload);

// Decodifica JSON
$data = json_decode($payload, true);

if (!$data) {
    logMessage("Errore: payload non JSON valido");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    // Connessione al DB PostgreSQL (adatta i parametri ai tuoi)
    $pdo = new PDO(
        "pgsql:host=dpg-d0chkfh5pdvs73dn6jog-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_hwr1;sslmode=require",
        "stripe_test_hwr1_user",
        "ctnl7Y70eQFUNXMOdY1ddREJIm9sVf09"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Estrai campi utili, con fallback a stringhe vuote
    $event_id = $data['id'] ?? '';
    $event_type = $data['type'] ?? '';
    $payload_serialized = json_encode($data);

    // Query INSERT
    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed)
        VALUES (:event_id, :event_type, :payload, NOW(), false)
    ");
    $stmt->execute([
        ':event_id' => $event_id,
        ':event_type' => $event_type,
        ':payload' => $payload_serialized
    ]);

    logMessage("Inserito evento ID $event_id nel DB");

    // Risposta OK a Stripe
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
?>

<?php
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook test con dati prefissati chiamato ===");

try {
    $pdo = new PDO(
        "pgsql:host=dpg-d0chkfh5pdvs73dn6jog-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_hwr1;sslmode=require",
        "stripe_test_hwr1_user",
        "ctnl7Y70eQFUNXMOdY1ddREJIm9sVf09"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dati finti prefissati
    $event_id = 'evt_test_hardcoded_001';
    $event_type = 'checkout.session.completed';
    $payload_serialized = json_encode([
        "id" => $event_id,
        "type" => $event_type,
        "data" => ["object" => ["fake" => "data"]],
        "created" => time()
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed)
        VALUES (:event_id, :event_type, :payload, NOW(), false)
    ");
    $stmt->execute([
        ':event_id' => $event_id,
        ':event_type' => $event_type,
        ':payload' => $payload_serialized
    ]);

    logMessage("Inserito evento hardcoded ID $event_id nel DB");

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Dati prefissati inseriti']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
?>

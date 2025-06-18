<?php

header('Content-Type: application/json');

$input = @file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id'], $data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Dettagli DB dal tuo DATABASE_URL
$host = 'dpg-d19h6lfgi27c73crpsrg-a.oregon-postgres.render.com';
$port = '5432';
$dbname = 'stripe_test_ase0';
$user = 'stripe_test_ase0_user';
$password = '0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Inserisci nella tabella stripe_webhooks
    $stmt = $pdo->prepare("INSERT INTO stripe_webhooks (event_id, event_type, payload) VALUES (:event_id, :event_type, :payload)");
    $stmt->execute([
        ':event_id' => $data['id'],
        ':event_type' => $data['type'],
        ':payload' => json_encode($data)
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    // Scrive l’errore su un file per il debug
    file_put_contents('error_debug.log', $e->getMessage() . " on line " . $e->getLine() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

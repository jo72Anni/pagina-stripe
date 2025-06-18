<?php
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook Stripe ricevuto ===");

// Legge il body della richiesta
$payload = file_get_contents('php://input');
logMessage("Payload ricevuto: " . $payload);

$data = json_decode($payload, true);
if (!$data) {
    logMessage("Errore: payload non JSON valido");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        throw new Exception("Variabile DATABASE_URL non trovata");
    }

    $dbopts = parse_url($databaseUrl);
    $host = $dbopts["host"];
    $port = $dbopts["port"];
    $user = $dbopts["user"];
    $pass = $dbopts["pass"];
    $dbname = ltrim($dbopts["path"], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

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

    logMessage("Evento $event_id inserito correttamente");

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}

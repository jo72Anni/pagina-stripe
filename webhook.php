

<?php

require __DIR__ . '/../vendor/autoload.php'; // Se il file è in public/

// Prendi la DATABASE_URL da env
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    file_put_contents('php://stderr', "❌ DATABASE_URL non impostata\n");
    http_response_code(500);
    exit();
}

// Parse DATABASE_URL (es: postgres://user:pass@host:port/dbname)
$dbopts = parse_url($databaseUrl);

$host = $dbopts["host"];
$port = $dbopts["port"];
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    file_put_contents('php://stderr', "❌ Connessione DB fallita: " . $e->getMessage() . "\n");
    http_response_code(500);
    exit();
}

// Leggi il segreto webhook Stripe
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

file_put_contents('php://stderr', "📥 Webhook ricevuto\n");

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    file_put_contents('php://stderr', "✅ Evento verificato: " . $event->type . "\n");
} catch (\UnexpectedValueException $e) {
    file_put_contents('php://stderr', "❌ Payload non valido\n");
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    file_put_contents('php://stderr', "❌ Firma non valida\n");
    http_response_code(400);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, processed)
        VALUES (:event_id, :event_type, :payload, FALSE)
        ON CONFLICT (event_id) DO NOTHING
    ");
    $stmt->execute([
        ':event_id' => $event->id,
        ':event_type' => $event->type,
        ':payload' => $payload
    ]);
    file_put_contents('php://stderr', "💾 Evento salvato nel DB: " . $event->id . "\n");
} catch (PDOException $e) {
    file_put_contents('php://stderr', "❌ Errore salvataggio DB: " . $e->getMessage() . "\n");
}

if ($event->type === 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    file_put_contents('php://stderr', "💰 Pagamento riuscito: " . $paymentIntent->id . "\n");
}

http_response_code(200);

?>


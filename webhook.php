<?php

require 'vendor/autoload.php'; // Assicurati che stripe/stripe-php sia installato

// Configurazione DB Render
$db_host = 'dpg-d0chkfh5pdvs73dn6jog-a.oregon-postgres.render.com';
$db_port = '5432';
$db_name = 'stripe_test_hwr1';
$db_user = 'stripe_test_hwr1_user';
$db_pass = 'ctnl7Y70eQFUNXMOdY1ddREJIm9sVf09';

// Connessione PDO
try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    file_put_contents('php://stderr', "❌ Connessione DB fallita: " . $e->getMessage() . "\n");
    http_response_code(500);
    exit();
}

// Leggi il segreto webhook dalla variabile d'ambiente
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET'); // Devi impostarla tu, es: whsec_xxx

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

file_put_contents('php://stderr', "📥 Webhook ricevuto\n");

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
    file_put_contents('php://stderr', "✅ Evento verificato: " . $event->type . "\n");
} catch(\UnexpectedValueException $e) {
    file_put_contents('php://stderr', "❌ Payload non valido\n");
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    file_put_contents('php://stderr', "❌ Firma non valida\n");
    http_response_code(400);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO stripe_webhooks (event_id, event_type, payload, processed) VALUES (:event_id, :event_type, :payload, FALSE) ON CONFLICT (event_id) DO NOTHING");
    $stmt->execute([
        ':event_id' => $event->id,
        ':event_type' => $event->type,
        ':payload' => $payload
    ]);
    file_put_contents('php://stderr', "💾 Evento salvato nel DB: " . $event->id . "\n");
} catch (PDOException $e) {
    file_put_contents('php://stderr', "❌ Errore salvataggio DB: " . $e->getMessage() . "\n");
}

if ($event->type == 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    file_put_contents('php://stderr', "💰 Pagamento riuscito: " . $paymentIntent->id . "\n");
}

http_response_code(200);

?>


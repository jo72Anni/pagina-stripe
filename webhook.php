<?php

require 'vendor/autoload.php'; // Assicurati che stripe/stripe-php sia installato

// Legge la variabile d'ambiente con il segreto del webhook
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET'); // es: whsec_xxx

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

// LOG sui log di Render
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

// Esempio di gestione evento
if ($event->type == 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    file_put_contents('php://stderr', "💰 Pagamento riuscito: " . $paymentIntent->id . "\n");
}

http_response_code(200);
?>

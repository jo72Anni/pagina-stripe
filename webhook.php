<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Funzione per inviare l'email
function sendEmail($orderId, $customerEmail) {
    $mail = new PHPMailer(true);

    try {
        // Configurazione per Brevo (SMTP)
        $apiKey = $_ENV['BREVO_API_KEY'];
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'SMTP';
        $mail->Password = $apiKey;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_TLS;
        $mail->Port = 587;

        // Impostazioni email
        $mail->setFrom('no-reply@yourdomain.com', 'Il Tuo Negozio');
        $mail->addAddress($customerEmail);  // Email del cliente
        $mail->Subject = "Conferma Ordine #{$orderId}";
        $mail->isHTML(true);
        $mail->Body    = "<h1>Grazie per il tuo ordine!</h1><p>Il tuo ordine #{$orderId} è stato ricevuto.</p>";
        $mail->AltBody = "Grazie per il tuo ordine! Il tuo ordine #{$orderId} è stato ricevuto.";

        $mail->send();
        echo 'Email inviata con successo.';
    } catch (Exception $e) {
        echo "Errore nell'invio dell'email: {$mail->ErrorInfo}";
    }
}

// Ricezione dell'evento webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $_ENV['STRIPE_WEBHOOK_SECRET']
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo 'Firma del webhook non valida';
    exit();
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    
    // Ottieni l'ID dell'ordine e l'email del cliente
    $orderId = $session->metadata->order_id ?? $session->id;
    $customerEmail = $session->customer_email;

    // Invia l'email
    sendEmail($orderId, $customerEmail);

    http_response_code(200);
    echo 'Webhook processed successfully';
} else {
    http_response_code(200);
    echo 'Evento non gestito: ' . $event->type;
}
?>

<?php
// success.php
require_once 'vendor/autoload.php';

// Carica variabili ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configura Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$session_id = $_GET['session_id'] ?? '';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Pagamento Confermato</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if ($session_id): ?>
                <?php
                try {
                    $session = \Stripe\Checkout\Session::retrieve($session_id);
                    $payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
                    
                    if ($session->payment_status === 'paid') {
                        echo '<div class="alert alert-success text-center">';
                        echo '<h1>✅ Pagamento Confermato!</h1>';
                        echo '<p>Grazie per il tuo acquisto. Riceverai una email di conferma a: <strong>' . htmlspecialchars($session->customer_details->email) . '</strong></p>';
                        echo '<p>ID Transazione: <code>' . htmlspecialchars($session->payment_intent) . '</code></p>';
                        echo '<p>Importo pagato: <strong>€' . number_format($payment_intent->amount_received / 100, 2) . '</strong></p>';
                        echo '</div>';
                        echo '<div class="text-center mt-3">';
                        echo '<a href="index.php" class="btn btn-primary">Torna alla Home</a>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-warning text-center">';
                        echo '<h1>⚠️ Pagamento in sospeso</h1>';
                        echo '<p>Il tuo pagamento è ancora in elaborazione.</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger text-center">';
                    echo '<h1>❌ Errore</h1>';
                    echo '<p>Si è verificato un errore: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                ?>
            <?php else: ?>
                <div class="alert alert-danger text-center">
                    <h1>❌ Errore</h1>
                    <p>Nessuna sessione di pagamento specificata.</p>
                    <a href="index.php" class="btn btn-primary">Torna alla Home</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

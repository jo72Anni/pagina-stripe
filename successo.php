<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

session_start();

// Recupera l'ID della sessione dalla query string
$sessionId = $_GET['session_id'] ?? null;

if (!$sessionId) {
    echo 'Sessione non valida o incompleta. Riprova.';
    exit();
}

try {
    // Recupera la sessione Stripe
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    
    // Recupera i line items separatamente dalla sessione (perché non sono inclusi per default)
    $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId, ['limit' => 50]);
    
    // Verifica se la sessione contiene i dati necessari
    if (empty($lineItems->data)) {
        echo 'Dettagli dell\'ordine non disponibili.';
        exit();
    }

    // Recupera i dati dell'acquisto
    $customerName = htmlspecialchars($session->metadata->customer_name ?? 'N/D');
    $email = htmlspecialchars($session->customer_email ?? 'N/D');
    $products = $lineItems->data;
    $totalAmount = $session->amount_total / 100; // in euro, dato che Stripe usa i centesimi

    // Dettagli ordine
    echo '<h1>Grazie per il tuo acquisto, ' . $customerName . '!</h1>';
    echo '<p>Email: ' . $email . '</p>';
    echo '<h2>Dettagli Ordine:</h2>';
    echo '<ul>';

    foreach ($products as $item) {
        echo '<li>';
        echo $item->quantity . 'x ' . htmlspecialchars($item->description) . ' (€' . number_format($item->price->unit_amount / 100, 2) . ' ciascuno)';
        echo '</li>';
    }

    echo '</ul>';
    echo '<p><strong>Totale: €' . number_format($totalAmount, 2) . '</strong></p>';
    
    // Stato del pagamento
    if ($session->payment_status === 'paid') {
        echo '<p>Il tuo pagamento è stato completato con successo!</p>';
    } else {
        echo '<p>Qualcosa è andato storto con il pagamento.</p>';
    }

} catch (\Exception $e) {
    // In caso di errore, mostra un messaggio generico all'utente
    echo 'Errore: Si è verificato un problema con il recupero dei dettagli dell\'ordine. Riprova più tardi.';
    error_log('Errore durante il recupero della sessione Stripe: ' . $e->getMessage());
    exit();
}
?>



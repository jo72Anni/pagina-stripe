<?php
require 'vendor/autoload.php';

// Imposta la chiave segreta Stripe dall'ambiente
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Imposta la versione API stabile compatibile con Stripe PHP 16.x
\Stripe\Stripe::setApiVersion('2025-01-27');

// Debug: mostra alcune informazioni utili (puoi rimuoverlo in produzione)
echo "<h3>Debug informazioni:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
$curl_info = curl_version();
echo "cURL: " . $curl_info['version'] . "<br>";
echo "Stripe PHP Version: " . \Stripe\Stripe::VERSION . "<br>";
echo "Environment Variables Set: <br>";
echo "STRIPE_SECRET_KEY? " . ($stripeSecretKey ? 'Sì' : 'No') . "<br>";
echo "STRIPE_PUBLISHABLE_KEY? " . (getenv('STRIPE_PUBLISHABLE_KEY') ? 'Sì' : 'No') . "<br>";
echo "STRIPE_WEBHOOK_SECRET? " . (getenv('STRIPE_WEBHOOK_SECRET') ? 'Sì' : 'No') . "<br>";

// Crea sessione Stripe se si clicca sul pulsante
if (isset($_POST['checkout'])) {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => 'Maglietta'],
                'unit_amount' => 1500,
            ],
            'quantity' => 1,
        ]],
        'success_url' => 'https://tuo-progetto.onrender.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://tuo-progetto.onrender.com/cancel.php',
    ]);

    header("Location: " . $session->url);
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Carrello Stripe Test</title>
</head>
<body>
  <h1>Carrello Test</h1>
  <p>Prodotto: Maglietta - 15,00€</p>
  <form method="POST">
    <button type="submit" name="checkout">Procedi al pagamento</button>
  </form>
</body>
</html>


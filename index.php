<?php
require 'vendor/autoload.php';

// Legge la chiave segreta Stripe dall'ambiente
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Creiamo una sessione di checkout
if (isset($_POST['checkout'])) {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'success_url' => 'https://pagina-stripe-08kd.onrender.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://pagina-stripe-08kd.onrender.com/cancel.php',
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => 'Maglietta'],
                'unit_amount' => 1500,
            ],
            'quantity' => 1,
        ]],
    ]);

    header("Location: " . $session->url);
    exit();
}
?>


<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Carrello Stripe</title>
</head>
<body>
  <h1>Carrello</h1>
  <p>Prodotto: Maglietta - 15,00€</p>
  <form method="POST">
    <button type="submit" name="checkout">Procedi al pagamento</button>
  </form>
</body>
</html>


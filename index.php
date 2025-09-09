<?php
require 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('sk_test_51QtDji2X4PJWtjNB6TPNZV7grmjSKRJvAHzY0ZgxdydwCZPSdQSDYrOsvzaGrejOh9vriE0Di7LQeMajQxJmClWn00FLOQVe6Y');

// Creiamo una sessione di checkout con un prodotto d’esempio
if (isset($_POST['checkout'])) {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'success_url' => 'https://pagina-stripe-08kd.onrender.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://pagina-stripe-08kd.onrender.com/cancel.php',
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => 'Maglietta',
                ],
                'unit_amount' => 1500, // 15,00€ (in centesimi)
            ],
            'quantity' => 1,
        ]],
    ]);

    // Reindirizza l’utente a Stripe Checkout
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


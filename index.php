
<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$product = [
    'id' => 'unique_product_id',
    'name' => 'Zaino da Escursione',
    'price' => 4999, // in centesimi (es. 49,99€)
    'sku' => 'ZAINO-ESC-001',
    'image' => 'https://via.placeholder.com/300'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Acquisto Prodotto</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 2em auto; }
        img { width: 100%; max-width: 300px; }
        input, button { margin-top: 10px; padding: 8px; width: 100%; }
    </style>
</head>
<body>

    <h1><?= $product['name'] ?></h1>
    <img src="<?= $product['image'] ?>" alt="Prodotto">
    <p>Prezzo: €<?= number_format($product['price'] / 100, 2) ?></p>

    <form id="purchase-form">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <input type="hidden" name="product_name" value="<?= $product['name'] ?>">
        <input type="hidden" name="price" value="<?= $product['price'] ?>">
        <input type="hidden" name="sku" value="<?= $product['sku'] ?>">

        <label for="quantity">Quantità:</label>
        <input type="number" name="quantity" value="1" min="1" required>

        <label for="name">Nome:</label>
        <input type="text" name="customer_name" required>

        <label for="email">Email:</label>
        <input type="email" name="email" required>

        <button type="submit">Acquista Ora</button>
    </form>

    <script>
        const stripe = Stripe('<?= $_ENV['STRIPE_PUBLIC_KEY'] ?>');

        document.getElementById('purchase-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.quantity = parseInt(data.quantity);

            try {
                const response = await fetch('/crea_sessione.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.sessionId) {
                    stripe.redirectToCheckout({ sessionId: result.sessionId });
                } else {
                    alert('Errore: ' + result.error);
                }
            } catch (error) {
                alert('Errore durante il checkout.');
                console.error(error);
            }
        });
    </script>

</body>
</html>


<?php
// Abilita tutti gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Caricamento variabili d'ambiente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configurazione prodotto
$product = [
    'id' => 'unique_product_id',
    'name' => 'Zaino da Escursione',
    'price' => 4999, // in centesimi (es. 49,99€)
    'sku' => 'ZAINO-ESC-001',
    'image' => 'https://via.placeholder.com/300'
];

// ========= DEBUG BREVO =========
function debugBrevo() {
    $output = "=== DEBUG BREVO ===\n";
    
    // 1. Verifica variabili d'ambiente
    $requiredVars = ['BREVO_API_KEY', 'SENDER_EMAIL', 'TEST_EMAIL'];
    foreach ($requiredVars as $var) {
        $value = getenv($var);
        $output .= "$var: ".(empty($value) ? 'MISSING' : substr($value, 0, 3).'...')."\n";
    }

    // 2. Test connessione Brevo
    try {
        require __DIR__ . '/vendor/autoload.php';
        $config = Brevo\Client\Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', getenv('BREVO_API_KEY'));
        
        $api = new Brevo\Client\Api\AccountApi(new GuzzleHttp\Client(), $config);
        $account = $api->getAccount();
        $output .= "Connessione OK! Piano: ".$account->getPlan()[0]->getType()."\n";
        
    } catch (Exception $e) {
        $output .= "ERRORE: ".$e->getMessage()."\n";
    }
    
    return nl2br($output);
}
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
        .debug { 
            background: #f5f5f5; 
            padding: 15px; 
            margin-top: 20px;
            border: 1px solid #ddd;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

    <h1><?= htmlspecialchars($product['name']) ?></h1>
    <img src="<?= htmlspecialchars($product['image']) ?>" alt="Prodotto">
    <p>Prezzo: €<?= number_format($product['price'] / 100, 2) ?></p>

    <form id="purchase-form">
        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
        <input type="hidden" name="product_name" value="<?= htmlspecialchars($product['name']) ?>">
        <input type="hidden" name="price" value="<?= htmlspecialchars($product['price']) ?>">
        <input type="hidden" name="sku" value="<?= htmlspecialchars($product['sku']) ?>">

        <label for="quantity">Quantità:</label>
        <input type="number" name="quantity" value="1" min="1" required>

        <label for="name">Nome:</label>
        <input type="text" name="customer_name" required>

        <label for="email">Email:</label>
        <input type="email" name="email" required>

        <button type="submit">Acquista Ora</button>
    </form>

    <!-- Sezione Debug -->
    <div class="debug">
        <h3>Stato Sistema</h3>
        <?= debugBrevo() ?>
        <p>Stripe Key: <?= isset($_ENV['STRIPE_PUBLIC_KEY']) ? substr($_ENV['STRIPE_PUBLIC_KEY'], 0, 6).'...' : 'MISSING' ?></p>
    </div>

    <script>
        const stripe = Stripe('<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? '' ?>');

        document.getElementById('purchase-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Mostra loader
            const btn = this.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Elaborazione...';

            try {
                const formData = new FormData(this);
                const response = await fetch('/crea_sessione.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(formData.entries()))
                });

                const result = await response.json();
                
                if (result.sessionId) {
                    stripe.redirectToCheckout({ sessionId: result.sessionId });
                } else {
                    alert(result.error || 'Errore durante il pagamento');
                    btn.disabled = false;
                    btn.textContent = 'Acquista Ora';
                }
            } catch (error) {
                console.error(error);
                alert('Errore di connessione');
                btn.disabled = false;
                btn.textContent = 'Acquista Ora';
            }
        });
    </script>
</body>
</html>

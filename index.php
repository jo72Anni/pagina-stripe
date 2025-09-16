<?php
// Carica autoload di Composer
require_once __DIR__ . '/vendor/autoload.php';

// Configurazione Stripe - LEGGE DA VARIABILI RENDER
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY');

// ✅ DEBUG CRUCIALE - controlla se le variabili sono caricate
error_log("Stripe Secret Key: " . ($stripeSecretKey ? 'PRESENTE' : 'ASSENTE'));
error_log("Stripe Publishable Key: " . ($stripePublishableKey ? 'PRESENTE' : 'ASSENTE'));

// ✅ FERMA TUTTO se manca la chiave segreta
if (!$stripeSecretKey) {
    die("ERRORE CRITICO: STRIPE_SECRET_KEY non configurata su Render. Controlla le Environment Variables.");
}

// ✅ Configura Stripe API key GLOBALMENTE (SOLO UNA VOLTA)
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Database
$pdo = require __DIR__ . '/db.php';

// Funzione per ottenere tutte le tabelle
function getAllTables(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$tables = getAllTables($pdo);

// Creazione Payment Intent per Stripe
$paymentIntent = null;
$stripeError = null;

if ($stripeSecretKey && $stripePublishableKey) {
    try {
        // ✅ Versione CORRETTA - senza passare api_key nel secondo parametro
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => 1999,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true]
        ]);
        
        error_log("PaymentIntent creato con successo: " . $paymentIntent->id);
        
    } catch (Exception $e) {
        $stripeError = $e->getMessage();
        error_log("Errore Stripe: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Sistema</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 50%; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background-color: #eee; }
        .stripe-section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; }
        #card-element { margin: 15px 0; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #5469d4; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Informazioni Sistema</h1>
    <ul>
        <li><strong>PHP Version:</strong> <?= phpversion() ?></li>
        <li><strong>Stripe Configurato:</strong> <?= ($stripeSecretKey && $stripePublishableKey) ? 'Sì' : 'No' ?></li>
        <li><strong>Database:</strong> <?= htmlspecialchars(getenv('DB_NAME')) ?></li>
    </ul>

    <!-- SEZIONE STRIPE CHECKOUT -->
    <div class="stripe-section">
        <h2>💳 Pagamento con Stripe</h2>
        
        <?php if (!($stripeSecretKey && $stripePublishableKey)): ?>
            <p style="color: red;">❌ Stripe non configurato correttamente. Controlla le variabili d'ambiente su Render.</p>
        <?php elseif (isset($stripeError)): ?>
            <div style="color: red;">❌ Errore Stripe: <?= htmlspecialchars($stripeError) ?></div>
        <?php endif; ?>

        <?php if ($stripeSecretKey && $stripePublishableKey && $paymentIntent): ?>
            <form id="payment-form">
                <div id="card-element"></div>
                <button id="submit-button">Paga 19,99 €</button>
                <div id="payment-result"></div>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($stripeSecretKey && $stripePublishableKey && $paymentIntent): ?>
    <script>
        const stripe = Stripe('<?= $stripePublishableKey ?>');
        const elements = stripe.elements();
        const cardElement = elements.create('card');
        cardElement.mount('#card-element');

        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';

            const { error, paymentIntent } = await stripe.confirmCardPayment(
                '<?= $paymentIntent->client_secret ?>',
                {
                    payment_method: {
                        card: cardElement,
                    }
                }
            );

            if (error) {
                document.getElementById('payment-result').innerHTML = 
                    `<p style="color: red;">Errore: ${error.message}</p>`;
                submitButton.disabled = false;
                submitButton.textContent = 'Paga 19,99 €';
            } else if (paymentIntent) {
                document.getElementById('payment-result').innerHTML = 
                    `<p style="color: green;">Pagamento riuscito! ID: ${paymentIntent.id}</p>`;
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
<?php
// Carica autoload di Composer
require_once __DIR__ . '/vendor/autoload.php';

// Configurazione Stripe - LEGGE DA VARIABILI RENDER
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY');

// Configura Stripe API key PRIMA di qualsiasi operazione
if ($stripeSecretKey && $stripePublishableKey) {
    \Stripe\Stripe::setApiKey($stripeSecretKey);
}

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

// Creazione Payment Intent per Stripe (solo se le keys sono configurate)
$paymentIntent = null;
$stripeError = null;

if ($stripeSecretKey && $stripePublishableKey) {
    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => 1999, // 19.99 EUR
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true]
            // RIMOSSO: 'metadata' => ['integration_check' => 'accept_a_payment']
        ]);
    } catch (Exception $e) {
        $stripeError = $e->getMessage();
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
        .stripe-disabled { background: #ccc; color: #666; }
    </style>
</head>
<body>
    <h1>Informazioni Sistema</h1>
    <ul>
        <li><strong>PHP Version:</strong> <?= phpversion() ?></li>
        <li><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></li>
        <li><strong>Environment:</strong> <?= getenv('APP_ENV') ?: 'N/A' ?></li>
        <li><strong>Debug Mode:</strong> <?= getenv('APP_DEBUG') ?: 'N/A' ?></li>
        <li><strong>Stripe Configurato:</strong> <?= ($stripeSecretKey && $stripePublishableKey) ? 'Sì' : 'No' ?></li>
    </ul>

    <h2>Database PostgreSQL</h2>
    <p>Connesso a: <?= htmlspecialchars(getenv('DB_NAME')) ?> (Host: <?= htmlspecialchars(getenv('DB_HOST')) ?>)</p>

    <h2>Tabelle presenti</h2>
    <?php if (empty($tables)): ?>
        <p>Nessuna tabella trovata.</p>
    <?php else: ?>
        <table>
            <tr><th>#</th><th>Nome Tabella</th></tr>
            <?php foreach ($tables as $i => $t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($t) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- SEZIONE STRIPE CHECKOUT -->
    <div class="stripe-section">
        <h2>💳 Pagamento con Stripe</h2>
        
        <?php if (!($stripeSecretKey && $stripePublishableKey)): ?>
            <p style="color: orange;">Stripe non configurato.</p>
        <?php elseif (isset($stripeError)): ?>
            <div style="color: red;">Errore Stripe: <?= htmlspecialchars($stripeError) ?></div>
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
            } else if (paymentIntent.status === 'succeeded') {
                document.getElementById('payment-result').innerHTML = 
                    `<p style="color: green;">Pagamento riuscito! ID: ${paymentIntent.id}</p>`;
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
<?php
require_once 'vendor/autoload.php';
use Dotenv\Dotenv;

// Carica variabili d'ambiente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$stripePublicKey = getenv('STRIPE_PUBLIC_KEY');

// Inizializza Stripe solo se le keys sono presenti
if ($stripeSecretKey) {
    // ✅ SOLO API KEY - NESSUNA versione forzata
    // La libreria gestisce automaticamente gli headers
    \Stripe\Stripe::setApiKey($stripeSecretKey);
}

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

// Creazione sessione di checkout Stripe
$checkoutSession = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_checkout']) && $stripeSecretKey) {
    try {
        // ✅ CORRETTO: solo parametri essenziali
        // NESSUNA versione API nei parametri
        $checkoutSession = \Stripe\Checkout\Session::create([
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Prodotto di Test',
                        'description' => 'Pagamento di test per il sistema'
                    ],
                    'unit_amount' => 2000, // 20.00 EUR
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/?success=true',
            'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/?canceled=true',
            
            // ✅ Payment method types è IMPLICITO
            // La libreria usa 'card' automaticamente per mode: 'payment'
        ]);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Sistema + Stripe Checkout</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #5469d4; padding-bottom: 10px; }
        h2 { color: #5469d4; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; }
        .btn { background: #5469d4; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; margin: 20px 0; }
        .btn:hover { background: #4252a8; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { color: #2e7d32; background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .system-info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 Dashboard Sistema v2.0</h1>
        
        <div class="system-info">
            <h2>📊 Informazioni Sistema</h2>
            <ul>
                <li><strong>PHP Version:</strong> <?= phpversion() ?></li>
                <li><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></li>
                <li><strong>Environment:</strong> <?= getenv('APP_ENV') ?: 'production' ?></li>
                <li><strong>Stripe Keys:</strong> <?= $stripePublicKey ? '✅ Configured' : '❌ Missing' ?></li>
                <li><strong>Stripe API Version:</strong> Automatica (libreria)</li>
            </ul>
        </div>

        <h2>🗄️ Database PostgreSQL</h2>
        <p>Connesso a: <strong><?= htmlspecialchars(getenv('DB_NAME')) ?></strong> 
        (Host: <?= htmlspecialchars(getenv('DB_HOST')) ?>)</p>

        <h2>📋 Tabelle presenti nel database</h2>
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

        <h2>💳 Stripe Checkout Test</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success">
                ✅ Pagamento completato con successo!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['canceled'])): ?>
            <div class="error">
                ❌ Pagamento cancellato dall'utente.
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error">❌ Errore Stripe: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$stripeSecretKey): ?>
            <div class="error">
                ❌ Stripe non configurato. Imposta le variabili d'ambiente STRIPE_SECRET_KEY e STRIPE_PUBLIC_KEY su Render.
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>Test Payment</h3>
            <p><strong>Prodotto:</strong> Prodotto di Test</p>
            <p><strong>Prezzo:</strong> €20.00</p>
            <p><strong>Carta di test:</strong> 4242 4242 4242 4242</p>
        </div>

        <form method="POST">
            <button type="submit" name="create_checkout" class="btn" <?= !$stripeSecretKey ? 'disabled' : '' ?>>
                🚀 Crea Checkout Session (€20.00)
            </button>
        </form>

        <?php if ($checkoutSession): ?>
            <script>
                var stripe = Stripe('<?= $stripePublicKey ?>');
                stripe.redirectToCheckout({
                    sessionId: '<?= $checkoutSession->id ?>'
                });
            </script>
            <div class="info-box">
                🔄 Reindirizzamento a Stripe Checkout...
            </div>
        <?php endif; ?>

    </div>
</body>
</html>


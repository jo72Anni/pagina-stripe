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

// ========= FUNZIONE DI DEBUG COMPLETA =========
function debugSystem() {
    $output = "<h4>Stato Sistema</h4>";
    
    // 1. Verifica variabili essenziali
    $output .= "<div class='debug-section'>";
    $output .= "<h5>Configurazione Base</h5>";
    $essentialVars = [
        'BREVO_API_KEY' => 'Brevo API',
        'SENDER_EMAIL' => 'Email Mittente', 
        'TEST_EMAIL' => 'Email Test',
        'BREVO_SMTP_PASSWORD' => 'Password SMTP',
        'STRIPE_PUBLIC_KEY' => 'Stripe Key'
    ];
    
    foreach ($essentialVars as $var => $label) {
        $value = getenv($var);
        $status = empty($value) ? "<span class='missing'>Mancante</span>" : "<span class='ok'>Configurata</span>";
        $output .= "<p><strong>$label:</strong> $status</p>";
    }
    $output .= "</div>";

    // 2. Test Brevo API
    $output .= "<div class='debug-section'>";
    $output .= "<h5>Test API Brevo</h5>";
    try {
        require __DIR__ . '/vendor/autoload.php';
        $config = Brevo\Client\Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', getenv('BREVO_API_KEY'));
        
        $api = new Brevo\Client\Api\AccountApi(new GuzzleHttp\Client(), $config);
        $account = $api->getAccount();
        $output .= "<p class='success'>✓ Connessione API riuscita</p>";
        $output .= "<p>Piano: ".$account->getPlan()[0]->getType()."</p>";
    } catch (Exception $e) {
        $output .= "<p class='error'>✗ Errore API: ".htmlspecialchars($e->getMessage())."</p>";
    }
    $output .= "</div>";

    // 3. Test Connessione SMTP
    $output .= "<div class='debug-section'>";
    $output .= "<h5>Test SMTP Brevo</h5>";
    
    $smtpHost = 'smtp-relay.brevo.com';
    $smtpPort = 587;
    $timeout = 5;
    
    // Test connessione base
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, $timeout);
    
    if ($socket) {
        $output .= "<p class='success'>✓ Connessione alla porta $smtpPort riuscita</p>";
        fclose($socket);
        
        // Test avanzato se esiste la password SMTP
        if (getenv('BREVO_SMTP_PASSWORD')) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = $smtpPort;
                $mail->SMTPAuth = false; // Solo test connessione
                $mail->Timeout = $timeout;
                
                if ($mail->smtpConnect()) {
                    $output .= "<p class='success'>✓ Handshake SMTP completato</p>";
                    $mail->smtpClose();
                }
            } catch (Exception $e) {
                $output .= "<p class='error'>✗ Errore PHPMailer: ".htmlspecialchars($e->getMessage())."</p>";
            }
        } else {
            $output .= "<p class='warning'>ℹ Password SMTP non configurata</p>";
        }
    } else {
        $output .= "<p class='error'>✗ Connessione fallita: ".htmlspecialchars($errstr)." (codice $errno)</p>";
        $output .= "<div class='solutions'><p>Soluzioni:</p><ul>";
        $output .= "<li>Prova la porta 465 con SSL</li>";
        $output .= "<li>Verifica le restrizioni di Render.com</li>";
        $output .= "<li>Usa l'API Brevo come alternativa</li></ul></div>";
    }
    $output .= "</div>";

    return $output;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Acquisto Prodotto</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 2em auto; padding: 20px; }
        img { max-width: 300px; height: auto; display: block; margin: 0 auto 20px; }
        .product-info { text-align: center; margin-bottom: 30px; }
        form { background: #f9f9f9; padding: 20px; border-radius: 8px; }
        input, button { width: 100%; padding: 10px; margin-top: 8px; box-sizing: border-box; }
        button { background: #6772e5; color: white; border: none; cursor: pointer; }
        button:disabled { background: #aaa; }
        .debug { background: #f5f5f5; padding: 20px; margin-top: 30px; border-radius: 8px; }
        .debug-section { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
        .debug-section:last-child { border-bottom: none; }
        .ok { color: green; }
        .missing { color: red; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .solutions { background: #fff8e1; padding: 10px; margin-top: 10px; border-radius: 4px; }
        .solutions ul { margin: 5px 0 0 20px; }
    </style>
</head>
<body>

    <div class="product-info">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        <p>Prezzo: €<?= number_format($product['price'] / 100, 2) ?></p>
    </div>

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

        <button type="submit" id="submit-btn">Acquista Ora</button>
    </form>

    <div class="debug">
        <?= debugSystem() ?>
    </div>

    <script>
        const stripe = Stripe('<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? '' ?>');
        const submitBtn = document.getElementById('submit-btn');

        document.getElementById('purchase-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Disabilita il pulsante durante l'elaborazione
            submitBtn.disabled = true;
            submitBtn.textContent = 'Elaborazione...';

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
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Acquista Ora';
                }
            } catch (error) {
                console.error(error);
                alert('Errore di connessione');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Acquista Ora';
            }
        });
    </script>
</body>
</html>

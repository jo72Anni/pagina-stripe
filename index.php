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
    $requiredVars = ['BREVO_API_KEY', 'SENDER_EMAIL', 'TEST_EMAIL', 'BREVO_SMTP_PASSWORD'];
    foreach ($requiredVars as $var) {
        $value = getenv($var);
        $output .= "$var: ".(empty($value) ? 'MISSING' : substr($value, 0, 3).'...')."\n";
    }

    // 2. Test connessione API Brevo
    try {
        require __DIR__ . '/vendor/autoload.php';
        $config = Brevo\Client\Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', getenv('BREVO_API_KEY'));
        
        $api = new Brevo\Client\Api\AccountApi(new GuzzleHttp\Client(), $config);
        $account = $api->getAccount();
        $output .= "API OK! Piano: ".$account->getPlan()[0]->getType()."\n";
        
    } catch (Exception $e) {
        $output .= "ERRORE API: ".$e->getMessage()."\n";
    }
    
    // 3. Test connessione SMTP (nuova sezione integrata)
    $output .= "\n=== TEST SMTP ===\n";
    $smtpHost = 'smtp-relay.brevo.com';
    $smtpPort = 587;
    $timeout = 5;
    
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, $timeout);
    
    if ($socket) {
        $output .= "✅ Connessione SMTP riuscita (porta $smtpPort)\n";
        fclose($socket);
        
        // Test avanzato con PHPMailer se le credenziali sono presenti
        if (getenv('BREVO_SMTP_PASSWORD')) {
            try {
                $mail = new PHPMailer(true);
                $mail->SMTPDebug = 1; // Livello base di debug
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = $smtpPort;
                $mail->SMTPAuth = false; // Solo test connessione
                $mail->Timeout = $timeout;
                
                if ($mail->smtpConnect()) {
                    $output .= "✔ Handshake SMTP completato\n";
                }
            } catch (Exception $e) {
                $output .= "⚠ Errore PHPMailer: ".$e->getMessage()."\n";
            }
        }
    } else {
        $output .= "❌ Connessione SMTP fallita: $errstr ($errno)\n";
        $output .= "Prova alternative:\n";
        $output .= "- Porta 465 con SSL\n";
        $output .= "- Usa l'API Brevo\n";
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
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>

    <!-- ... (resto del tuo HTML esistente) ... -->

    <!-- Sezione Debug migliorata -->
    <div class="debug">
        <h3>Stato Sistema</h3>
        <?= debugBrevo() ?>
        <p>Stripe Key: <?= isset($_ENV['STRIPE_PUBLIC_KEY']) ? substr($_ENV['STRIPE_PUBLIC_KEY'], 0, 6).'...' : 'MISSING' ?></p>
        
        <?php if (!getenv('BREVO_SMTP_PASSWORD')): ?>
            <p class="warning">Attenzione: BREVO_SMTP_PASSWORD non configurata</p>
        <?php endif; ?>
    </div>

    <!-- ... (resto del tuo JavaScript) ... -->
</body>
</html>

<?php
// Abilita tutti gli errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carica variabili d'ambiente se presente file .env
if (file_exists(__DIR__ . '/.env')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Prodotto di esempio
$product = [
    'id' => 'unique_product_id',
    'name' => 'Zaino da Escursione',
    'price' => 4999, // centesimi (€49,99)
    'sku' => 'ZAINO-ESC-001',
    'image' => 'https://via.placeholder.com/300'
];

// Funzione debug sistema
function debugSystem() {
    $output = "<div class='debug-container'>";
    $output .= "<h4>Stato Sistema</h4>";
    
    // Verifica variabili essenziali (senza email)
    $output .= "<div class='debug-section'><h5>Configurazione Base</h5>";
    $essentialVars = [
        'BREVO_API_KEY' => 'Brevo API',
        'STRIPE_PUBLIC_KEY' => 'Stripe Key'
    ];
    foreach ($essentialVars as $var => $label) {
        $val = getenv($var);
        $status = empty($val) ? 
            "<span class='missing'>Mancante</span>" : 
            "<span class='ok'>Configurata</span> (" . htmlspecialchars(substr($val, 0, 3)) . "...)";
        $output .= "<p><strong>$label:</strong> $status</p>";
    }
    $output .= "</div>";

    // Test API Brevo
    $output .= "<div class='debug-section'><h5>Test API Brevo</h5>";
    if (getenv('BREVO_API_KEY')) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $config = Brevo\Client\Configuration::getDefaultConfiguration()
                ->setApiKey('api-key', getenv('BREVO_API_KEY'));
            $api = new Brevo\Client\Api\AccountApi(new GuzzleHttp\Client(), $config);
            $account = $api->getAccount();
            $output .= "<p class='success'>✓ Connessione API riuscita</p>";
            $output .= "<p>Piano: " . htmlspecialchars($account->getPlan()[0]->getType()) . "</p>";
        } catch (Exception $e) {
            $output .= "<p class='error'>✗ Errore API: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        $output .= "<p class='warning'>ℹ API Key non configurata</p>";
    }
    $output .= "</div>";

    // Test connessione SMTP (senza riferimenti email)
    $output .= "<div class='debug-section'><h5>Test Connessione SMTP</h5>";
    $smtpHost = 'smtp-relay.brevo.com';
    $smtpPort = 587;
    $timeout = 5;
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, $timeout);
    if ($socket) {
        $output .= "<p class='success'>✓ Connessione alla porta $smtpPort riuscita</p>";
        $response = fgets($socket, 1024);
        $output .= "<p>Risposta server: <code>" . htmlspecialchars(trim($response)) . "</code></p>";
        fclose($socket);
        if (getenv('BREVO_SMTP_PASSWORD')) {
            $output .= "<p class='info'>ℹ Credenziali SMTP presenti (test completo con PHPMailer disponibile)</p>";
        }
    } else {
        $output .= "<p class='error'>✗ Connessione fallita: " . htmlspecialchars($errstr) . " (codice $errno)</p>";
        $output .= "<div class='solutions'><p>Possibili soluzioni:</p><ul>";
        $output .= "<li>Prova la porta 465 con SSL</li>";
        $output .= "<li>Verifica che Render non blocchi le connessioni in uscita</li>";
        $output .= "<li>Installa PHPMailer per test avanzati: <code>composer require phpmailer/phpmailer</code></li>";
        $output .= "</ul></div>";
    }
    $output .= "</div>";

    $output .= "</div>";
    return $output;
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Acquisto Prodotto</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        :root {
            --success: #4CAF50;
            --error: #F44336;
            --warning: #FF9800;
            --info: #2196F3;
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        .product-info { 
            text-align: center;
            margin-bottom: 30px;
        }
        .product-info img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        form {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            background: #6772e5;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5469d4;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .debug-container {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            border: 1px solid #e1e4e8;
        }
        .debug-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e1e4e8;
        }
        .debug-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .ok { color: var(--success); }
        .missing { color: var(--error); }
        .success { color: var(--success); }
        .error { color: var(--error); }
        .warning { color: var(--warning); }
        .info { color: var(--info); }
        .solutions {
            background: #fff8e1;
            padding: 15px;
            margin-top: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--warning);
        }
        .solutions ul {
            margin: 10px 0 0 20px;
        }
        code {
            background: #f0f0f0;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            form, .debug-container {
                padding: 15px;
            }
        }
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

        <label

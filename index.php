<?php
// ========================
// index.php - Carrello Stripe con Debug Avanzato
// ========================

// Debug globale PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log su file/server
error_log("===== Avvio applicazione =====");

// Autoload Composer
require_once __DIR__ . '/vendor/autoload.php';

// -------------------
// Config DB
// -------------------
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 5432,
    'dbname' => getenv('DB_NAME') ?: 'postgres',
    'user' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

error_log("DB Config: " . print_r($dbConfig, true));

// -------------------
// Config Stripe
// -------------------
$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_publishable_key',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_secret_key'
];

error_log("Stripe Config: " . print_r([
    'publishable_key' => substr($stripeConfig['publishable_key'], 0, 8) . '...',
    'secret_key' => substr($stripeConfig['secret_key'], 0, 8) . '...'
], true));

$stripeInitialized = false;
if (!empty($stripeConfig['secret_key']) && $stripeConfig['secret_key'] !== 'sk_test_your_secret_key') {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    \Stripe\Stripe::setApiVersion('2025-02-24');
    $stripeInitialized = true;
}

// -------------------
// Funzione connessione DB con logging avanzato
// -------------------
function getDBConnection($config) {
    try {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        if (!empty($config['ssl_mode'])) {
            $dsn .= ";sslmode={$config['ssl_mode']}";
        }

        error_log("DSN: $dsn");

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_PERSISTENT => false
        ];

        $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
        $pdo->query("SELECT 1")->fetch();

        error_log("DB connessione riuscita");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Errore DB: " . $e->getMessage());
        throw new Exception("Errore connessione DB: " . $e->getMessage());
    }
}

// -------------------
// Check tabella
// -------------------
function checkProductsTable($pdo) {
    try {
        $query = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'products')";
        error_log("Eseguo query check tabella: $query");
        return $pdo->query($query)->fetchColumn();
    } catch (PDOException $e) {
        error_log("Errore check tabella: " . $e->getMessage());
        return false;
    }
}

// -------------------
// Creazione tabella + dati esempio
// -------------------
function ensureProductsTable($pdo) {
    try {
        $create = "CREATE TABLE IF NOT EXISTS products (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        error_log("Creo tabella products se non esiste");
        $pdo->exec($create);

        $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        error_log("Products count: $count");

        if ($count == 0) {
            $sampleProducts = [
                ['Prodotto Premium', 'Un prodotto di alta qualità', 49.99],
                ['Prodotto Standard', 'Un prodotto affidabile', 29.99],
                ['Prodotto Basic', 'Un prodotto essenziale', 19.99]
            ];
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price) VALUES (?, ?, ?)");
            foreach ($sampleProducts as $p) {
                $stmt->execute($p);
                error_log("Inserito prodotto: " . implode(", ", $p));
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("Errore tabella: " . $e->getMessage());
        throw new Exception("Errore gestione tabella: " . $e->getMessage());
    }
}

// -------------------
// Risposta JSON con debug dettagliato
// -------------------
function jsonErrorResponse($e, $context = []) {
    http_response_code(400);
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'context' => $context
    ];
    error_log("Errore JSON: " . print_r($response, true));
    echo json_encode($response);
    exit;
}

// -------------------
// AJAX
// -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $pdo = getDBConnection($dbConfig);
        $action = $_POST['action'];
        error_log("AJAX Action: $action");

        switch ($action) {
            case 'get_products':
                if (!checkProductsTable($pdo)) {
                    ensureProductsTable($pdo);
                }
                $products = $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll();
                echo json_encode(['status' => 'success', 'products' => $products]);
                break;

            case 'create_checkout_session':
                if (!$stripeInitialized) {
                    throw new Exception("Stripe non configurato correttamente.");
                }

                $cart = json_decode($_POST['cart'], true);
                if (empty($cart)) {
                    throw new Exception("Carrello vuoto");
                }

                $lineItems = [];
                foreach ($cart as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => ['name' => $item['name']],
                            'unit_amount' => (int)($item['price'] * 100),
                        ],
                        'quantity' => $item['quantity']
                    ];
                }

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $baseUrl . '/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $baseUrl . '/index.php',
                    'customer_email' => $_POST['email'] ?? ''
                ]);

                echo json_encode(['status' => 'success', 'sessionId' => $session->id]);
                break;

            default:
                throw new Exception("Azione non valida: $action");
        }
    } catch (Exception $e) {
        jsonErrorResponse($e, [
            'post' => $_POST,
            'dbConfig' => $dbConfig,
            'stripeConfigured' => $stripeInitialized
        ]);
    }
}

// -------------------
// Frontend: Debug visivo
// -------------------
try {
    $pdo = getDBConnection($dbConfig);
    $dbConnected = true;
    $tableExists = checkProductsTable($pdo);
    $dbError = null;
} catch (Exception $e) {
    $dbConnected = false;
    $tableExists = false;
    $dbError = $e->getMessage();
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']) &&
                   $stripeConfig['publishable_key'] !== 'pk_test_your_publishable_key';

$currentStripeVersion = class_exists('\\Stripe\\Stripe') ? (\Stripe\Stripe::getApiVersion() ?? 'default') : 'not-set';
$pdoExtensionLoaded = extension_loaded('pdo');
$pdoPgSqlExtensionLoaded = extension_loaded('pdo_pgsql');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Carrello Stripe (Debug)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4 text-center">🛒 Carrello Stripe - Modalità Debug</h1>

    <div class="alert alert-info">
        <strong>Debug Info:</strong><br>
        Stripe API Version: <?= htmlspecialchars($currentStripeVersion) ?><br>
        DB Host: <?= htmlspecialchars($dbConfig['host']) ?><br>
        DB Name: <?= htmlspecialchars($dbConfig['dbname']) ?><br>
        DB User: <?= htmlspecialchars($dbConfig['user']) ?><br>
        SSL Mode: <?= htmlspecialchars($dbConfig['ssl_mode']) ?><br>
        PDO: <?= $pdoExtensionLoaded ? 'Caricata ✅' : 'Mancante ❌' ?><br>
        PDO_PGSQL: <?= $pdoPgSqlExtensionLoaded ? 'Caricata ✅' : 'Mancante ❌' ?><br>
        Tabella Products: <?= $tableExists ? 'Presente ✅' : 'Assente ❌' ?><br>
        Stripe Configurato: <?= $stripeConfigured ? 'Sì ✅' : 'No ❌' ?><br>
        <?php if ($dbError): ?>
            <div class="mt-2 alert alert-danger">Errore DB: <?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

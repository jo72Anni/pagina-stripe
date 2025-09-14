<?php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

// Carica variabili d'ambiente solo per sviluppo locale
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

header('Content-Type: application/json');

try {
    // ==================== DATABASE ====================
    $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
    $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

    $dbh = new PDO(
        "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
        $dbUser,
        $dbPass
    );
    
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crea tabella se non esiste
    $dbh->exec("CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Inserisci prodotto demo
    $stmt = $dbh->prepare("INSERT INTO products (name, price, description) 
                          VALUES (:name, :price, :description)
                          ON CONFLICT DO NOTHING 
                          RETURNING id, name, price");
    $stmt->execute([
        ':name' => 'Premium Software License',
        ':price' => 99.99,
        ':description' => 'Full access to all features with 1 year support'
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==================== STRIPE ====================
    $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
    Stripe::setApiKey($stripeSecret);

    $checkoutSession = Session::create([
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $product['name'],
                    'description' => 'Annual subscription with full support'
                ],
                'unit_amount' => (int)($product['price'] * 100),
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://your-app.onrender.com/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://your-app.onrender.com/cancel',
        'metadata' => [
            'product_id' => $product['id'],
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    ]);

    // ==================== SYSTEM INFO ====================
    $systemInfo = [
        'server' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time())
        ],
        'database' => [
            'driver' => $dbh->getAttribute(PDO::ATTR_DRIVER_NAME),
            'version' => $dbh->getAttribute(PDO::ATTR_SERVER_VERSION),
            'connection_status' => $dbh->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ],
        'request' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A'
        ],
        'stripe' => [
            'api_version' => '2024-06-20',
            'checkout_session_id' => $checkoutSession->id,
            'payment_status' => $checkoutSession->payment_status
        ]
    ];

    // ==================== RESPONSE ====================
    echo json_encode([
        'status' => 'success',
        'message' => 'System is fully operational',
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'currency' => 'EUR'
        ],
        'checkout' => [
            'url' => $checkoutSession->url,
            'session_id' => $checkoutSession->id,
            'expires_at' => date('Y-m-d H:i:s', $checkoutSession->expires_at)
        ],
        'system' => $systemInfo,
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'database_error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'system_info' => [
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'
        ]
    ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'stripe_error',
        'message' => 'Stripe API error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'application_error',
        'message' => 'Unexpected error',
        'error' => $e->getMessage()
    ]);
}
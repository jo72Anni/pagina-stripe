<?php
require 'vendor/autoload.php';

// Carica variabili da .env se presente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Legge i dati dal body JSON
$data = json_decode(file_get_contents('php://input'), true);

try {
    // ✅ 1. Crea il customer con nome ed email
    $customer = \Stripe\Customer::create([
        'email' => $data['email'],
        'name' => $data['customer_name'],
    ]);

    // ✅ 2. Crea la sessione di pagamento usando il customer appena creato
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer' => $customer->id, // associamo il customer esplicitamente
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $data['product_name'],
                    'metadata' => [
                        'sku' => $data['sku'],
                        'customer_name' => $data['customer_name']
                    ],
                ],
                'unit_amount' => (int)$data['price'], // importo in centesimi
            ],
            'quantity' => (int)$data['quantity'],
        ]],
        'mode' => 'payment',
        'success_url' => $_ENV['SUCCESS_URL'] . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $_ENV['CANCEL_URL'],
        'metadata' => [
            'product_id' => $data['product_id'],
            'customer_name' => $data['customer_name'],
            'email' => $data['email'],
        ],
        'client_reference_id' => uniqid('client_'),
    ]);

    // ✅ 3. Risponde con l'ID della sessione
    header('Content-Type: application/json');
    echo json_encode(['sessionId' => $session->id]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

?>

<?php
require 'vendor/autoload.php';

// Controlla se il file .env esiste prima di caricarlo
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$data = json_decode(file_get_contents('php://input'), true);

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer_email' => $data['email'],
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
                'unit_amount' => (int)$data['price'], // già in centesimi
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

    header('Content-Type: application/json');
    echo json_encode(['sessionId' => $session->id]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

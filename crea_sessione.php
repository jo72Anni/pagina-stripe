<?php
require 'vendor/autoload.php';

// Carica variabili ambiente da .env, se presente
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Imposta la chiave segreta Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Leggi i dati JSON inviati (ad es. da frontend)
$data = json_decode(file_get_contents('php://input'), true);

try {
    // 1. Creo il customer in Stripe con email e nome
    $customer = \Stripe\Customer::create([
        'email' => $data['email'] ?? null,
        'name' => $data['customer_name'] ?? null,
        'metadata' => [
            'customer_source' => 'checkout_creation'
        ],
    ]);

    // 2. Creo la sessione di checkout associandola al customer creato
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer' => $customer->id, // Passa qui il customer ID
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $data['product_name'] ?? 'Prodotto senza nome',
                    'metadata' => [
                        'sku' => $data['sku'] ?? '',
                        'customer_name' => $data['customer_name'] ?? '',
                    ],
                ],
                'unit_amount' => (int)($data['price'] ?? 0), // prezzo in centesimi
            ],
            'quantity' => (int)($data['quantity'] ?? 1),
        ]],
        'mode' => 'payment',
        'success_url' => ($_ENV['SUCCESS_URL'] ?? '') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $_ENV['CANCEL_URL'] ?? '',
        'metadata' => [
            'product_id' => $data['product_id'] ?? '',
            'customer_name' => $data['customer_name'] ?? '',
            'email' => $data['email'] ?? '',
        ],
        'client_reference_id' => uniqid('client_'),
    ]);

    // Risposta JSON con session ID
    header('Content-Type: application/json');
    echo json_encode(['sessionId' => $session->id]);

} catch (\Exception $e) {
    // In caso di errore ritorno 400 e messaggio errore
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}


?>

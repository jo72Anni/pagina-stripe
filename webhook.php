<?php
require_once 'vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Webhook;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Impostazioni Stripe
Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Funzione per inviare email tramite template Brevo
function sendOrderEmailUsingTemplate(array $orderData, string $recipientEmail) {
    // Parametri dell'email
    $apiKey = $_ENV['BREVO_API_KEY']; // La tua chiave API Brevo
    $templateId = 123456; // ID del template Brevo

    // Parametri dinamici per il template
    $attributes = [
        'NAME' => $orderData['customer_name'],
        'ORDER_ID' => $orderData['order_id'],
        'TOTAL_AMOUNT' => $orderData['amount_total'],
        'ORDER_DATE' => $orderData['created_at'],
        'SHIPPING_METHOD' => $orderData['shipping_method'],
        'SHIPPING_COST' => $orderData['shipping_cost'],
        'PRODUCTS' => implode(', ', array_map(function($product) {
            return $product['name'] . " (x" . $product['quantity'] . ")";
        }, $orderData['products']))
    ];

    // Configura cURL per inviare l'email con il template Brevo
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);

    $data = [
        'sender' => [
            'name' => 'Il Nome del Tuo Negozio',
            'email' => 'test@stripe.com'
        ],
        'to' => [
            [
                'email' => $recipientEmail,
                'name' => $orderData['customer_name']
            ]
        ],
        'templateId' => $templateId,
        'params' => $attributes // Parametri dinamici per il template
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        error_log("Email inviata con successo: " . $response);
        return true;
    } else {
        error_log("Errore durante l'invio dell'email");
        return false;
    }
}

try {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $event = null;

    try {
        $event = Webhook::constructEvent(
            $payload,
            $sig_header,
            $_ENV['STRIPE_WEBHOOK_SECRET']
        );
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        error_log('Firma del webhook non valida: ' . $e->getMessage());
        exit('Errore: Firma del webhook non valida.');
    } catch (\Exception $e) {
        http_response_code(400);
        error_log('Errore durante la costruzione dell\'evento webhook: ' . $e->getMessage());
        exit('Errore: Impossibile costruire l\'evento webhook.');
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);

        // Estrazione dati prodotti
        try {
            $lineItems = \Stripe\Checkout\Session::allLineItems($session->id, ['limit' => 50]);
            $allLineItems = $lineItems->data;

            while ($lineItems->has_more) {
                $lineItems = \Stripe\Checkout\Session::allLineItems($session->id, ['starting_after' => $lineItems->data[count($lineItems->data)-1]->id, 'limit' => 50]);
                $allLineItems = array_merge($allLineItems, $lineItems->data);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            http_response_code(500);
            error_log('Errore durante il recupero delle line items: ' . $e->getMessage());
            exit('Errore interno del server: Impossibile recuperare i dettagli del prodotto.');
        }

        $products = [];
        $subtotal = 0;
        $shippingCost = 0;
        foreach ($allLineItems as $item) {
             if ($item->price->product->metadata->sku === 'shipping') {
                $shippingCost = $item->amount_total / 100;
                continue;
            }
            $products[] = [
                'name' => $item->description,
                'sku' => $item->price->product->metadata->sku ?? 'N/A',
                'quantity' => $item->quantity,
                'price' => $item->amount_total / 100
            ];
            $subtotal += $item->amount_total / 100;
        }

        // Preparazione dati per l'email
        $orderData = [
            'order_id' => $session->metadata->order_id ?? $session->id,
            'user_id' => $session->metadata->user_id ?? $session->client_reference_id,
            'created_at' => date('d/m/Y H:i', $session->created),
            'payment_status' => $paymentIntent->status,
            'payment_intent_id' => $session->payment_intent,
            'customer_name' => $session->customer_details->name ?? 'N/D',
            'customer_email' => $session->customer_details->email ?? 'N/D',
            'shipping' => $session->shipping ? (array)$session->shipping : null,
            'shipping_method' => $session->metadata->shipping_method ?? 'Standard',
            'shipping_cost' => $shippingCost,
            'subtotal' => number_format($subtotal, 2),
            'amount_total' => number_format($session->amount_total / 100, 2),
            'products' => $products
        ];

        // Invia l'email
        $recipientEmail = 'perdifumo72@libero.it'; // Email destinatario
        if (sendOrderEmailUsingTemplate($orderData, $recipientEmail)) {
            http_response_code(200);
            echo 'Webhook processed successfully and email sent';
        } else {
            http_response_code(500);
            error_log('Webhook processed successfully, but failed to send email');
            echo 'Webhook processed successfully, but failed to send email';
        }

    } else {
        http_response_code(200); // Accetta altri eventi senza errori, ma non fare nulla
        echo 'Evento webhook non gestito: ' . $event->type;
    }
} catch (\Exception $e) {
    http_response_code(500);
    error_log('Errore durante l\'elaborazione del webhook: ' . $e->getMessage());
    exit('Errore interno del server: ' . $e->getMessage());
}
?>


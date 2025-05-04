<?php
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Rimozione del controllo del file .env
// if (file_exists(__DIR__ . '/.env')) {
//     $dotenv = Dotenv\Dotenv\Dotenv::createImmutable(__DIR__);
//     $dotenv->load();
// }
// Carica le variabili d'ambiente.  Questo è necessario se stai ancora usando dotenv.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Funzione per generare HTML
function generateOrderHtml($data) {
    $productsHtml = '';
    foreach ($data['products'] as $product) {
        $productsHtml .= "
        <tr>
            <td>{$product['name']}</td>
            <td>{$product['sku']}</td>
            <td>{$product['quantity']}</td>
            <td>€{$product['price']}</td>
        </tr>
        ";
    }

    $shippingAddressHtml = '';
    if (isset($data['shipping'])) {
        $shippingAddressHtml = "
                <h2>🚚 Spedizione</h2>
                <div class=\"address-box\">
                    <p><strong>Indirizzo:</strong></p>
                    <p>" . (isset($data['shipping']['name']) ? htmlspecialchars($data['shipping']['name']) : 'N/D') . "</p>
                    <p>" . (isset($data['shipping']['address']['line1']) ? htmlspecialchars($data['shipping']['address']['line1']) : 'N/D') . "</p>
                    <p>" . (isset($data['shipping']['address']['postal_code']) ? htmlspecialchars($data['shipping']['address']['postal_code']) : 'N/D') . " " . (isset($data['shipping']['address']['city']) ? htmlspecialchars($data['shipping']['address']['city']) : 'N/D') . "</p>
                    <p>" . (isset($data['shipping']['address']['country']) ? htmlspecialchars($data['shipping']['address']['country']) : 'N/D') . "</p>
                </div>
                <p><strong>Metodo:</strong> " . htmlspecialchars($data['shipping_method'] ?? 'N/D') . "</p>
            ";
    } else {
        $shippingAddressHtml = "
                <h2>🚚 Spedizione</h2>
                <p>Indirizzo di spedizione non disponibile.</p>
                <p><strong>Metodo:</strong> " . htmlspecialchars($data['shipping_method'] ?? 'N/D') . "</p>
            ";
    }

    return "
        <!DOCTYPE html>
        <html lang=\"it\">
        <head>
            <meta charset=\"UTF-8\">
            <title>Ordine #{$data['order_id']}</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #e1e1e1; border-radius: 8px; }
                h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin: 25px 0; }
                th { background-color: #3498db; color: white; text-align: left; }
                th, td { padding: 12px 15px; border: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f8f9fa; }
                .status { padding: 6px 12px; border-radius: 4px; font-weight: bold; }
                .status-completed { background-color: #d4edda; color: #155724; }
                .status-pending { background-color: #fff3cd; color: #856404; }
                .address-box { background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <h1>📦 Ordine #{$data['order_id']}</h1>

                <h2>🔍 Informazioni Generali</h2>
                <p><strong>Data:</strong> {$data['created_at']}</p>
                <p><strong>Stato Pagamento:</strong> <span class=\"status status-{$data['payment_status']}\">{$data['payment_status']}</span></p>
                <p><strong>Totale:</strong> €{$data['amount_total']}</p>

                <h2>👤 Informazioni Cliente</h2>
                <p><strong>ID Utente:</strong> {$data['user_id']}</p>
                <p><strong>Nome:</strong> {$data['customer_name']}</p>
                <p><strong>Email:</strong> {$data['customer_email']}</p>

                {$shippingAddressHtml}

                <h2>🛒 Prodotti Acquisti</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Prodotto</th>
                            <th>SKU</th>
                            <th>Quantità</th>
                            <th>Prezzo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$productsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan=\"3\"><strong>Totale Prodotti</strong></td>
                            <td>€{$data['subtotal']}</td>
                        </tr>
                        <tr>
                            <td colspan=\"3\"><strong>Spedizione</strong></td>
                            <td>€{$data['shipping_cost']}</td>
                        </tr>
                        <tr>
                            <td colspan=\"3\"><strong>Totale</strong></td>
                            <td><strong>€{$data['amount_total']}</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <h2>💳 Pagamento</h2>
                <p><strong>Metodo:</strong> Carta di credito</p>
                <p><strong>ID Transazione:</strong> {$data['payment_intent_id']}</p>
            </div>
        </body>
        </html>
        ";
}

function sendOrderEmail(array $orderData, string $recipientEmail) {
    $mail = new PHPMailer(true);

    try {
        // Configurazione per l'API di Brevo
        $apiKey = $_ENV['BREVO_API_KEY'];
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com'; // Host SMTP di Brevo
        $mail->SMTPAuth = true;
        $mail->Username = 'SMTP'; // Username SMTP di Brevo
        $mail->Password = $apiKey; // La chiave API come password SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_TLS;
        $mail->Port = 587;

        // Impostazioni email
        $mail->setFrom('test@stripe.com', 'Il Nome del Tuo Negozio'); // Sostituisci con il tuo indirizzo
        $mail->addAddress($recipientEmail);
        $mail->Subject = "Nuovo Ordine Ricevuto #{$orderData['order_id']}";
        $mail->isHTML(true);
        $mail->Body = generateOrderHtml($orderData);
        $mail->AltBody = strip_tags(generateOrderHtml($orderData));

        $mail->send();
        error_log("Email dell'ordine #{$orderData['order_id']} inviata con successo a {$recipientEmail}");
        return true;

    } catch (Exception $e) {
        error_log("Errore nell'invio dell'email dell'ordine #{$orderData['order_id']} a {$recipientEmail}: {$mail->ErrorInfo}");
        return false;
    }
}

try {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $event = null;

    try {
        $event = \Stripe\Webhook::constructEvent(
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
        $recipientEmail = 'perdifumo72@libero.it';
        if (sendOrderEmail($orderData, $recipientEmail)) {
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

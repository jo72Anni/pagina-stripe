<?php
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook Stripe ricevuto ===");

// Legge il body della richiesta
$payload = file_get_contents('php://input');
logMessage("Payload grezzo ricevuto: " . $payload);

// Decodifica JSON
$data = json_decode($payload, true);

// Controlla se è un JSON valido
if (!$data) {
    logMessage("Errore: payload non JSON valido");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

logMessage("Payload decodificato: " . var_export($data, true));

try {
    $pdo = new PDO(
        "pgsql:host=dpg-d0chkfh5pdvs73dn6jog-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_hwr1;sslmode=require",
        "stripe_test_hwr1_user",
        "ctnl7Y70eQFUNXMOdY1ddREJIm9sVf09"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inserisci evento in stripe_webhooks
    $event_id = substr($data['id'] ?? 'missing_id', 0, 255);
    $event_type = substr($data['type'] ?? 'missing_type', 0, 255);
    $payload_serialized = substr(json_encode($data), 0, 10000); // aumenta se serve

    $stmt = $pdo->prepare("
        INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed)
        VALUES (:event_id, :event_type, :payload, NOW(), false)
        ON CONFLICT (event_id) DO NOTHING
    ");
    $stmt->execute([
        ':event_id' => $event_id,
        ':event_type' => $event_type,
        ':payload' => $payload_serialized
    ]);
    logMessage("Evento ID $event_id inserito correttamente in stripe_webhooks");

    // Se evento è checkout.session.completed, inserisci dati in stripe_payments
    if ($event_type === 'checkout.session.completed') {
        $session = $data['data']['object'] ?? null;
        if ($session) {
            $payment_intent = $session['payment_intent'] ?? null;
            $customer_email = $session['customer_email'] ?? null;
            $amount_total = $session['amount_total'] ?? null; // in centesimi
            $currency = $session['currency'] ?? null;
            $metadata = $session['metadata'] ?? [];

            // Esempio di campi: modifica in base alla tua tabella stripe_payments
            $product_id = $metadata['product_id'] ?? null;
            $customer_name = $metadata['customer_name'] ?? null;

            $stmt2 = $pdo->prepare("
                INSERT INTO stripe_payments (
                    payment_intent_id,
                    customer_email,
                    amount,
                    currency,
                    product_id,
                    customer_name,
                    created_at
                ) VALUES (
                    :payment_intent_id,
                    :customer_email,
                    :amount,
                    :currency,
                    :product_id,
                    :customer_name,
                    NOW()
                )
                ON CONFLICT (payment_intent_id) DO NOTHING
            ");

            $stmt2->execute([
                ':payment_intent_id' => $payment_intent,
                ':customer_email' => $customer_email,
                ':amount' => $amount_total,
                ':currency' => $currency,
                ':product_id' => $product_id,
                ':customer_name' => $customer_name
            ]);

            logMessage("Pagamento inserito in stripe_payments: $payment_intent");
        } else {
            logMessage("Errore: checkout.session.completed senza dati session");
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    logMessage("Errore DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
?>

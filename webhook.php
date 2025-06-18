<?php
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    $pdo = new PDO(
        "pgsql:host=dpg-d19h6lfgi27c73crpsrg-a.oregon-postgres.render.com;port=5432;dbname=stripe_test_ase0;sslmode=require",
        "stripe_test_ase0_user",
        "0zMaW0fLMN9N8XCgHJqQZ7gevMesVeCZ"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (($data['type'] ?? '') === 'checkout.session.completed') {
        $session = $data['data']['object'] ?? null;
        if ($session) {
            $stmt = $pdo->prepare("
                INSERT INTO stripe_payments (
                    session_id,
                    customer_email,
                    customer_name,
                    product_id,
                    sku,
                    quantity,
                    amount_total,
                    created_at
                ) VALUES (
                    :session_id,
                    :customer_email,
                    :customer_name,
                    :product_id,
                    :sku,
                    :quantity,
                    :amount_total,
                    NOW()
                )
                ON CONFLICT (session_id) DO NOTHING
            ");

            $stmt->execute([
                ':session_id' => $session['session_id'] ?? null,
                ':customer_email' => $session['customer_email'] ?? null,
                ':customer_name' => $session['customer_name'] ?? null,
                ':product_id' => $session['product_id'] ?? null,
                ':sku' => $session['sku'] ?? null,
                ':quantity' => $session['quantity'] ?? null,
                ':amount_total' => $session['amount_total'] ?? null,
            ]);

            echo json_encode(['status' => 'ok']);
            exit;
        }
    }

    echo json_encode(['status' => 'ignored']);
} catch (Exception $e) {
    error_log("DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

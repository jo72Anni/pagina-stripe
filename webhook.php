<?php
// Debug errori (solo sviluppo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

// Configurazione DB
$db = [
    'host'     => getenv('DB_HOST'),
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME'),
    'user'     => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

// Chiavi Stripe
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
$webhookSecret  = getenv('STRIPE_WEBHOOK_SECRET'); // whsec_...

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Connessione DB
function getConnection($config) {
    $dsn = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['ssl_mode']
    );
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// Legge payload e header
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhookSecret
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Payload non valido');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Firma non valida');
}

$pdo = getConnection($db);

// Controlla duplicati
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM stripe_events WHERE stripe_event_id = :id");
$stmtCheck->execute([':id' => $event->id]);
if ($stmtCheck->fetchColumn() > 0) {
    http_response_code(200);
    exit; // Evento già elaborato
}

// Salva evento
$stmt = $pdo->prepare("INSERT INTO stripe_events (stripe_event_id, type, data) VALUES (:id, :type, :data)");
$stmt->execute([
    ':id' => $event->id,
    ':type' => $event->type,
    ':data' => json_encode($event->data->object)
]);

// Funzione per creare ordine
function createOrder($pdo, $session) {
    $pdo->beginTransaction();
    try {
        $email = $session->customer_email ?? '';
        $totalAmount = $session->amount_total ?? 0;
        $currency = $session->currency ?? 'eur';
        $status = ($session->payment_status ?? '') === 'paid' ? 'paid' : 'failed';

        $stmtOrder = $pdo->prepare("INSERT INTO orders (email, total_amount, currency, status) VALUES (:email, :total, :currency, :status) RETURNING id");
        $stmtOrder->execute([
            ':email' => $email,
            ':total' => $totalAmount,
            ':currency' => $currency,
            ':status' => $status
        ]);
        $orderId = $stmtOrder->fetchColumn();

        // Inserisci righe ordine dai line_items se disponibili
        if (!empty($session->display_items)) {
            $stmtItem = $pdo->prepare("
                INSERT INTO order_items (order_id, product_name, unit_price, quantity, total_price)
                VALUES (:order_id, :product_name, :unit_price, :quantity, :total_price)
            ");
            foreach ($session->display_items as $item) {
                $name = $item['custom']['name'] ?? $item['plan']['nickname'] ?? 'Prodotto';
                $unitPrice = (int)($item['amount'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 1);
                $totalPrice = $unitPrice * $quantity;

                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':product_name' => $name,
                    ':unit_price' => $unitPrice,
                    ':quantity' => $quantity,
                    ':total_price' => $totalPrice
                ]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore insert ordine: " . $e->getMessage());
    }
}

// Funzione per aggiornare stato ordine
function updateOrderStatus($pdo, $metadata, $status) {
    if (empty($metadata['order_id'])) return;

    $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $metadata['order_id']
    ]);
}

// Gestione eventi
switch ($event->type) {
    case 'checkout.session.completed':
        createOrder($pdo, $event->data->object);
        break;

    case 'payment_intent.succeeded':
        $pi = $event->data->object;
        $metadata = (array)($pi->metadata ?? []);
        updateOrderStatus($pdo, $metadata, 'paid');
        break;

    case 'invoice.paid':
        $invoice = $event->data->object;
        $metadata = (array)($invoice->metadata ?? []);
        updateOrderStatus($pdo, $metadata, 'paid');
        break;

    case 'payment_intent.payment_failed':
        $pi = $event->data->object;
        $metadata = (array)($pi->metadata ?? []);
        updateOrderStatus($pdo, $metadata, 'failed');
        break;

    // altri eventi se necessario
}

http_response_code(200);



<?php
// Funzione helper per scrivere nel log di sistema
function logMessage($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}

logMessage("=== Webhook invoked ===");

// Leggi il payload raw
$payload = @file_get_contents('php://input');
logMessage("Payload ricevuto: " . $payload);

$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
logMessage("Signature Stripe: " . $signature);

// Chiave segreta webhook (da Stripe Dashboard)
$endpoint_secret = 'whsec_cEL1I08sLJ8XbJJMnVmSC2GgV0EqXMJh';

require_once 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('tuo_api_key_segreta');

try {
    // Verifica la firma e costruisci l'evento Stripe
    $event = \Stripe\Webhook::constructEvent(
        $payload, $signature, $endpoint_secret
    );
    logMessage("Firma verificata. Evento tipo: " . $event->type);

    // Accedi ai dati evento (puoi adattare a quello che ti serve)
    $event_id = $event->id;
    $event_type = $event->type;
    $event_data = json_encode($event->data->object);

    // Connetti al DB (usa il tuo metodo di connessione)
    $pdo = new PDO("pgsql:host=tuo_host;port=5432;dbname=stripe_test_hwr1", "tuo_utente", "tua_password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inserisci nel DB
    $stmt = $pdo->prepare("INSERT INTO stripe_webhooks (event_id, event_type, payload, received_at, processed) VALUES (?, ?, ?, NOW(), false)");
    $stmt->execute([$event_id, $event_type, $event_data]);
    logMessage("Evento inserito nel DB con ID evento: " . $event_id);

    // Rispondi a Stripe con 200 OK
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    logMessage("Firma NON valida: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
} catch (Exception $e) {
    logMessage("Errore generico: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

logMessage("=== Webhook processing finished ===");

?>


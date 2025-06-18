<?php
// ==============================================
// CONFIGURAZIONE INIZIALE
// ==============================================
header('Content-Type: application/json');
ini_set('display_errors', 0); // Disabilita output errori in produzione
error_reporting(E_ALL); // Registra tutti gli errori nel log

// Carica le variabili d'ambiente
$config = [
    'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN),
    'database' => parse_url(getenv('DATABASE_URL')),
    'stripe' => [
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET'),
        'api_key' => getenv('STRIPE_SECRET_KEY')
    ],
    'logging' => [
        'file' => getenv('LOG_FILE') ?: __DIR__.'/logs/stripe_webhook.log',
        'level' => 'debug'
    ],
    'email' => [
        'sender' => getenv('SENDER_EMAIL'),
        'test' => getenv('TEST_EMAIL')
    ]
];

// ==============================================
// FUNZIONI DI UTILITY
// ==============================================
function logEvent($message, $context = [], $level = 'info') {
    global $config;
    
    $logEntry = sprintf(
        "[%s] %s: %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        !empty($context) ? json_encode($context, JSON_PRETTY_PRINT) : ''
    );
    
    // Crea la directory se non esiste
    if (!is_dir(dirname($config['logging']['file']))) {
        mkdir(dirname($config['logging']['file']), 0755, true);
    }
    
    file_put_contents($config['logging']['file'], $logEntry, FILE_APPEND);
    
    if ($config['debug']) {
        error_log($logEntry);
    }
}

// ==============================================
// CONNESSIONE AL DATABASE
// ==============================================
try {
    $db = new PDO(
        sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
            $config['database']['host'],
            $config['database']['port'],
            ltrim($config['database']['path'], '/')
        ),
        $config['database']['user'],
        $config['database']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    logEvent("Connessione al database stabilita con successo");
} catch (PDOException $e) {
    logEvent("Errore connessione database", ['error' => $e->getMessage()], 'error');
    http_response_code(500);
    die(json_encode(['error' => 'Database connection error']));
}

// ==============================================
// ELABORAZIONE WEBHOOK
// ==============================================
try {
    // Verifica metodo HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metodo non consentito");
    }

    // Leggi il payload
    $payload = @file_get_contents('php://input');
    if (empty($payload)) {
        throw new Exception("Payload vuoto");
    }
    
    logEvent("Payload ricevuto", ['headers' => getallheaders()], 'debug');

    // Verifica la firma del webhook
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $event = null;
    
    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $config['stripe']['webhook_secret']
        );
    } catch(\UnexpectedValueException $e) {
        throw new Exception("Payload JSON non valido");
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        throw new Exception("Firma webhook non valida");
    }

    logEvent("Evento Stripe validato", ['event_id' => $event->id, 'type' => $event->type]);

    // Prepara i dati per il database
    $eventData = $event->data->object;
    $dbData = [
        ':event_id' => $event->id,
        ':event_type' => $event->type,
        ':customer_email' => $eventData->customer_details->email ?? null,
        ':customer_name' => $eventData->customer_details->name ?? null,
        ':amount_total' => $eventData->amount_total ?? null,
        ':currency' => $eventData->currency ?? null,
        ':payment_status' => $eventData->payment_status ?? null,
        ':raw_payload' => json_encode($event)
    ];

    // Inserimento nel database
    $stmt = $db->prepare("
        INSERT INTO stripe_webhooks (
            event_id, event_type, customer_email,
            customer_name, amount_total, currency,
            payment_status, raw_payload
        ) VALUES (
            :event_id, :event_type, :customer_email,
            :customer_name, :amount_total, :currency,
            :payment_status, :raw_payload
        )
    ");
    
    $stmt->execute($dbData);
    $insertId = $db->lastInsertId();
    
    logEvent("Evento registrato nel database", ['db_id' => $insertId]);

    // Risposta di successo
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'event_id' => $event->id,
        'type' => $event->type,
        'database_id' => $insertId
    ]);

} catch (Exception $e) {
    logEvent("Errore elaborazione webhook", [
        'error' => $e->getMessage(),
        'trace' => $config['debug'] ? $e->getTraceAsString() : null
    ], 'error');
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// ==============================================
// PULIZIA FINALE
// ==============================================
$db = null;
logEvent("Elaborazione completata");
?>

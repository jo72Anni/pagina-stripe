<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once 'vendor/autoload.php';

/**
 * Gestore Webhook Stripe con integrazione PostgreSQL
 */
class StripeWebhookProcessor {
    private $dbConnection;
    private $stripeClient;
    private $webhookSecret;

    public function __construct() {
        $this->validateEnvironment();
        $this->initializeStripe();
        $this->dbConnection = $this->establishDbConnection();
    }

    /**
     * Valida le variabili d'ambiente richieste
     */
    private function validateEnvironment(): void {
        $requiredEnvVars = [
            'DATABASE_URL',
            'STRIPE_SECRET_KEY', 
            'STRIPE_WEBHOOK_SECRET'
        ];

        foreach ($requiredEnvVars as $var) {
            if (empty(getenv($var))) {
                $this->terminateWithError(500, "Variabile d'ambiente mancante: {$var}");
            }
        }
    }

    /**
     * Inizializza il client Stripe
     */
    private function initializeStripe(): void {
        $this->stripeClient = new \Stripe\StripeClient(getenv('STRIPE_SECRET_KEY'));
        $this->webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
    }

    /**
     * Stabilisce la connessione al database PostgreSQL
     */
    private function establishDbConnection() {
        $dbUrl = getenv('DATABASE_URL');
        $dbConfig = parse_url($dbUrl);

        if (!$dbConfig) {
            $this->terminateWithError(500, "Configurazione database non valida");
        }

        $connectionString = sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
            $dbConfig['host'],
            $dbConfig['port'] ?? '5432',
            ltrim($dbConfig['path'] ?? '', '/'),
            $dbConfig['user'] ?? '',
            $dbConfig['pass'] ?? ''
        );

        $conn = pg_connect($connectionString . " connect_timeout=5");
        
        if (!$conn) {
            $this->terminateWithError(500, "Connessione database fallita: " . pg_last_error());
        }

        // Configurazioni post-connessione
        pg_set_client_encoding($conn, 'UTF-8');
        pg_query($conn, "SET TIME ZONE 'UTC'");

        return $conn;
    }

    /**
     * Elabora la richiesta webhook
     */
    public function processWebhook(): void {
        try {
            $event = $this->verifyWebhookSignature();
            $this->handleStripeEvent($event);
        } catch (\Throwable $e) {
            $this->handleProcessingError($e);
        } finally {
            $this->cleanupResources();
        }
    }

    /**
     * Verifica la firma del webhook Stripe
     */
    private function verifyWebhookSignature(): \Stripe\Event {
        $payload = @file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($payload) || empty($signature)) {
            throw new RuntimeException("Dati webhook incompleti");
        }

        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new RuntimeException("Verifica firma fallita: " . $e->getMessage());
        }
    }

    /**
     * Gestisce l'evento Stripe
     */
    private function handleStripeEvent(\Stripe\Event $event): void {
        if ($event->type !== 'payment_intent.succeeded') {
            throw new RuntimeException("Tipo evento non supportato: {$event->type}");
        }

        $payment = $event->data->object;
        $this->validatePaymentData($payment);
        $this->storePaymentRecord($payment);
        
        $this->sendSuccessResponse($payment->id);
    }

    /**
     * Valida i dati del pagamento
     */
    private function validatePaymentData(\Stripe\PaymentIntent $payment): void {
        $requiredFields = [
            'id', 'amount', 'currency', 
            'status', 'created'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($payment->$field)) {
                throw new RuntimeException("Campo mancante: {$field}");
            }
        }
    }

    /**
     * Registra il pagamento nel database
     */
    private function storePaymentRecord(\Stripe\PaymentIntent $payment): void {
        pg_query($this->dbConnection, "BEGIN");

        $result = pg_query_params(
            $this->dbConnection,
            "INSERT INTO stripe_transactions (
                stripe_payment_id, amount, currency, status,
                customer_id, payment_method, receipt_email,
                stripe_created_at, raw_event
            ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)",
            [
                $payment->id,
                $payment->amount / 100,
                strtoupper($payment->currency),
                $payment->status,
                $payment->customer ?? null,
                $payment->payment_method ?? null,
                $payment->receipt_email ?? null,
                date('Y-m-d H:i:s', $payment->created),
                json_encode($payment)
            ]
        );

        if (!$result) {
            pg_query($this->dbConnection, "ROLLBACK");
            throw new RuntimeException("Errore database: " . pg_last_error($this->dbConnection));
        }

        pg_query($this->dbConnection, "COMMIT");
    }

    /**
     * Gestione degli errori
     */
    private function handleProcessingError(\Throwable $e): void {
        $statusCode = $e instanceof RuntimeException ? 400 : 500;
        $this->terminateWithError($statusCode, $e->getMessage());
    }

    /**
     * Invia risposta di successo
     */
    private function sendSuccessResponse(string $paymentId): void {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'payment_id' => $paymentId,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Termina con errore
     */
    private function terminateWithError(int $code, string $message): void {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }

    /**
     * Pulizia delle risorse
     */
    private function cleanupResources(): void {
        if ($this->dbConnection) {
            pg_close($this->dbConnection);
        }
    }
}

// Esecuzione principale
(new StripeWebhookProcessor())->processWebhook();
?>

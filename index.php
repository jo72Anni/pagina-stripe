<?php
require_once 'vendor/autoload.php';

// Configurazione variabili d'ambiente
$stripeSecretKey = 'sk_test_51QtDji2X4PJWtjNB6TPNZV7grmjSKRJvAHzY0ZgxdydwCZPSdQSDYrOsvzaGrejOh9vriE0Di7LQeMajQxJmClWn00FLOQVe6Y';
$stripePublishableKey = 'pk_test_51QtDji2X4PJWtjNBd0aFegJrLo9xN8iRkoxgov4Q7d16ASNGlnBIVOcHc2JuaPrRLbBtd3p2ERzbhzMrYE14tixn00FSSWJjpv';
$stripeWebhookSecret = 'whsec_f2dd535b1d5df5d491b2e2947feed8619ca210c720c1ce62e5f05af8b0a5868b';

// Inizializza Stripe
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Database configuration (usando i tuoi dati)
$dbConfig = [
    'host' => 'dpg-d2pllft6ubrc73ch9370-a.oregon-postgres.render.com',
    'dbname' => 'database_73nu',
    'user' => 'database_73nu_user',
    'password' => 'PoFoMAXbPPYVauVwZlpG5QeoaLqANQAk',
    'port' => '5432',
    'ssl' => 'require'
];

// Funzione per creare connessione al database
function getDbConnection() {
    global $dbConfig;
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};sslmode={$dbConfig['ssl']}";
    
    try {
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Pagina principale - Checkout Stripe
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Stripe Checkout</title>
        <script src="https://js.stripe.com/v3/"></script>
        <style>
            body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
            .product { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
            .buy-btn { background: #5469d4; color: white; border: none; padding: 12px 24px; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .buy-btn:hover { background: #3a4fc4; }
        </style>
    </head>
    <body>
        <h1>Acquista Prodotto</h1>
        
        <div class="product">
            <h2>Prodotto Demo - €50.00</h2>
            <p>Descrizione del prodotto di esempio</p>
            <button class="buy-btn" onclick="checkout()">Acquista Ora</button>
        </div>

        <script>
            const stripe = Stripe('<?php echo $stripePublishableKey; ?>');
            
            async function checkout() {
                try {
                    const response = await fetch('/crea_sessione.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            productName: 'Prodotto Demo',
                            amount: 5000, // 50.00 EUR in cents
                            quantity: 1
                        })
                    });
                    
                    const session = await response.json();
                    
                    if (session.id) {
                        const result = await stripe.redirectToCheckout({ sessionId: session.id });
                        if (result.error) {
                            alert(result.error.message);
                        }
                    } else {
                        alert('Errore nella creazione della sessione');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Si è verificato un errore');
                }
            }
        </script>
    </body>
    </html>
    <?php
}

// API endpoint per creare la sessione di checkout (dovresti metterlo in crea_sessione.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['PHP_SELF']) === 'index.php') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $input['productName'],
                    ],
                    'unit_amount' => $input['amount'],
                ],
                'quantity' => $input['quantity'],
            ]],
            'mode' => 'payment',
            'success_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/cancel.php',
        ]);

        // Salva la sessione nel database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO orders (stripe_session_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$session->id, $input['amount'] / 100]);

        echo json_encode(['id' => $session->id]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}
?>
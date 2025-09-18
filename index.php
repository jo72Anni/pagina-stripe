<?php
// Configurazione Stripe (TUTTO INLINE - SOLO PER TESTING)
$stripe_publishable_key = 'pk_test_51QtDji2X4PJWtjNBd0aFegJrLo9xN8iRkoxgov4Q7d16ASNGlnBIVOcHc2JuaPrRLbBtd3p2ERzbhzMrYE14tixn00FSSWJjpv';
$stripe_secret_key = 'sk_test_51QtDji2X4PJWtjNB6TPNZV7grmjSKRJvAHzY0ZgxdydwCZPSdQSDYrOsvzaGrejOh9vriE0Di7LQeMajQxJmClWn00FLOQVe6Y';

// Gestione della creazione della sessione di checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_checkout_session'])) {
    // Configura Stripe
    require_once 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    header('Content-Type: application/json');
    
    try {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . "://" . $host;
        
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => 'Corso di Web Development'],
                    'unit_amount' => 5000, // 50,00 €
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $base_url . $_SERVER['PHP_SELF'] . '?success=true',
            'cancel_url' => $base_url . $_SERVER['PHP_SELF'] . '?cancel=true',
        ]);
        
        echo json_encode(['id' => $session->id]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Pagina di successo
if (isset($_GET['success'])) {
    echo '<!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pagamento Riuscito</title>
        <style>
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .success-container {
                text-align: center;
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                max-width: 500px;
            }
            .success-icon {
                font-size: 4rem;
                color: #4caf50;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);
                color: white;
                padding: 12px 24px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">✅</div>
            <h1>Pagamento completato con successo!</h1>
            <p>Grazie per il tuo acquisto. Il corso è ora disponibile nel tuo account.</p>
            <a href="' . $_SERVER['PHP_SELF'] . '" class="btn">Torna alla Home</a>
        </div>
    </body>
    </html>';
    exit;
}

// Pagina di cancellazione
if (isset($_GET['cancel'])) {
    echo '<!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pagamento Annullato</title>
        <style>
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .cancel-container {
                text-align: center;
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                max-width: 500px;
            }
            .cancel-icon {
                font-size: 4rem;
                color: #f44336;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);
                color: white;
                padding: 12px 24px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="cancel-container">
            <div class="cancel-icon">❌</div>
            <h1>Pagamento annullato</h1>
            <p>Hai annullato il processo di pagamento. Nessun addebito è stato effettuato.</p>
            <a href="' . $_SERVER['PHP_SELF'] . '" class="btn">Torna alla Home</a>
        </div>
    </body>
    </html>';
    exit;
}

// Pagina principale
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Checkout - Tutto in un file</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
        
        .checkout-section {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .product-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .product-image {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);
            border-radius: 15px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .product-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #424770;
        }
        
        .product-price {
            font-size: 2rem;
            color: #6772e5;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .checkout-button {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 4px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .checkout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(103, 114, 229, 0.4);
        }
        
        .checkout-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .debug-section {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Fira Code', monospace;
            overflow-x: auto;
        }
        
        .debug-title {
            color: #ff79c6;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .debug-log {
            min-height: 100px;
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .log-entry {
            margin-bottom: 8px;
            padding-left: 10px;
            border-left: 3px solid transparent;
        }
        
        .log-success {
            color: #50fa7b;
            border-left-color: #50fa7b;
        }
        
        .log-error {
            color: #ff5555;
            border-left-color: #ff5555;
        }
        
        .log-info {
            color: #8be9fd;
            border-left-color: #8be9fd;
        }
        
        .log-warning {
            color: #ffb86c;
            border-left-color: #ffb86c;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active {
            background: #50fa7b;
        }
        
        .status-inactive {
            background: #ff5555;
        }
        
        .feature-list {
            list-style-type: none;
            margin: 20px 0;
        }
        
        .feature-list li {
            margin-bottom: 12px;
            padding-left: 30px;
            position: relative;
        }
        
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #50fa7b;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            background: #f1f3f5;
            color: #666;
            font-size: 0.9rem;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        
        .instructions {
            background: #e7f5ff;
            color: #0066cc;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #339af0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Stripe Checkout Integrato</h1>
            <p class="subtitle">Tutto in un unico file PHP - Per testing</p>
        </header>
        
        <div class="content">
            <div class="product-card">
                <div class="product-image">💻</div>
                <h2 class="product-title">Corso di Web Development</h2>
                <p class="product-description">Impara a creare siti web moderni con HTML, CSS e JavaScript</p>
                <div class="product-price">€50,00</div>
                
                <div class="warning">
                    <strong>AVVISO:</strong> Questa è una versione di test con chiavi integrate nel codice. Non usare in produzione!
                </div>
                
                <div class="instructions">
                    <strong>ISTRUZIONI TEST:</strong> Usa la carta di test 4242 4242 4242 4242, data futura e CVC qualsiasi.
                </div>
                
                <h3>Caratteristiche:</h3>
                <ul class="feature-list">
                    <li>Accesso completo a tutte le lezioni</li>
                    <li>Esercizi pratici e progetti</li>
                    <li>Certificato di completamento</li>
                    <li>Supporto della community</li>
                    <li>Aggiornamenti gratuiti</li>
                </ul>
                
                <button id="checkout-button" class="checkout-button">Acquista Ora</button>
            </div>
            
            <div class="checkout-section">
                <h2>Debug e Log</h2>
                <p>Qui puoi vedere in tempo reale lo stato del pagamento e gli eventuali errori.</p>
                
                <div class="debug-section">
                    <div class="debug-title">Stato configurazione:</div>
                    <div id="config-status">
                        <div class="log-entry log-info">
                            <span class="status-indicator status-active"></span>
                            Caricamento Stripe.js... 
                            <span id="stripe-status">In corso...</span>
                        </div>
                        <div class="log-entry log-info">
                            <span class="status-indicator status-active"></span>
                            Chiave pubblica: 
                            <span id="pubkey-status">In verifica...</span>
                        </div>
                    </div>
                    
                    <div class="debug-title">Log eventi:</div>
                    <div class="debug-log" id="debug-log">
                        <div class="log-entry log-info">
                            Inizializzazione in corso...
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3>File Info:</h3>
                    <p>Questo è un unico file PHP che contiene:</p>
                    <ul>
                        <li>Frontend HTML/CSS/JS</li>
                        <li>Backend PHP per Stripe</li>
                        <li>Pagine di successo/errore</li>
                        <li>Configurazione completa</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <footer>
            <p>Questa è una demo didattica. Non verrà addebitato alcun importo reale.</p>
            <p>Utilizza solo carte di test per i pagamenti.</p>
        </footer>
    </div>

    <script>
        // Configurazione Stripe (TUTTO INLINE - SOLO PER TESTING)
        const stripePublishableKey = '<?php echo $stripe_publishable_key; ?>';
        
        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            const checkoutButton = document.getElementById('checkout-button');
            const debugLog = document.getElementById('debug-log');
            const stripeStatus = document.getElementById('stripe-status');
            const pubkeyStatus = document.getElementById('pubkey-status');
            
            // Verifica che Stripe.js sia caricato
            if (typeof Stripe === 'undefined') {
                addLog('Errore: Stripe.js non è stato caricato correttamente', 'error');
                checkoutButton.disabled = true;
                stripeStatus.innerHTML = '<span style="color: #ff5555;">❌ Non caricato</span>';
                return;
            }
            
            stripeStatus.innerHTML = '<span style="color: #50fa7b;">✅ Caricato</span>';
            
            // Verifica la chiave pubblica
            if (!stripePublishableKey || stripePublishableKey.length < 10) {
                addLog('Errore: Chiave pubblica non valida', 'error');
                checkoutButton.disabled = true;
                pubkeyStatus.innerHTML = '<span style="color: #ff5555;">❌ Non valida</span>';
                return;
            }
            
            pubkeyStatus.innerHTML = '<span style="color: #50fa7b;">✅ Configurata</span>';
            addLog('Chiave pubblica verificata con successo', 'success');
            
            // Inizializza Stripe
            const stripe = Stripe(stripePublishableKey);
            
            // Setup event listener per il pulsante
            checkoutButton.addEventListener('click', function() {
                checkoutButton.disabled = true;
                addLog('Avvio processo di checkout...', 'info');
                
                // Crea una richiesta al backend (che è nello stesso file PHP)
                const formData = new FormData();
                formData.append('create_checkout_session', 'true');
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    addLog('Risposta ricevuta dal server', 'info');
                    return response.json();
                })
                .then(session => {
                    if (session.error) {
                        addLog('Errore: ' + session.error, 'error');
                        checkoutButton.disabled = false;
                        return;
                    }
                    
                    addLog('Session ID ricevuto: ' + session.id, 'success');
                    return stripe.redirectToCheckout({ sessionId: session.id });
                })
                .then(result => {
                    if (result && result.error) {
                        addLog('Errore durante il redirect: ' + result.error.message, 'error');
                        checkoutButton.disabled = false;
                    }
                })
                .catch(error => {
                    addLog('Errore durante il checkout: ' + error.message, 'error');
                    checkoutButton.disabled = false;
                });
            });
            
            addLog('Configurazione completata. Pronto per i pagamenti.', 'success');
        });
        
        // Funzione per aggiungere log alla console di debug
        function addLog(message, type = 'info') {
            const debugLog = document.getElementById('debug-log');
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry log-' + type;
            
            const timestamp = new Date().toLocaleTimeString();
            logEntry.innerHTML = `<strong>[${timestamp}]</strong> ${message}`;
            
            debugLog.appendChild(logEntry);
            debugLog.scrollTop = debugLog.scrollHeight;
        }
    </script>
</body>
</html>
<?php } ?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Checkout - Pagamenti Reali</title>
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
        
        .test-cards {
            background: #e7f5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .test-cards h4 {
            margin-bottom: 10px;
            color: #0066cc;
        }
        
        .test-cards ul {
            list-style-type: none;
        }
        
        .test-cards li {
            margin-bottom: 8px;
            padding: 8px;
            background: #d0ebff;
            border-radius: 4px;
            font-family: 'Fira Code', monospace;
        }
        
        .key-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .key-info h4 {
            margin-bottom: 10px;
            color: #6772e5;
        }
        
        .key-info code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Fira Code', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Stripe Checkout - Pagamenti Reali</h1>
            <p class="subtitle">Checkout funzionante con chiavi di test integrate</p>
        </header>
        
        <div class="content">
            <div class="product-card">
                <div class="product-image">💻</div>
                <h2 class="product-title">Corso di Web Development</h2>
                <p class="product-description">Impara a creare siti web moderni con HTML, CSS e JavaScript</p>
                <div class="product-price">€50,00</div>
                
                <div class="key-info">
                    <h4>Chiavi di Test Integrate</h4>
                    <p>Questa pagina contiene già chiavi di test valide per processare pagamenti.</p>
                    <p>Pubblica: <code>pk_test_51QtDji2X4PJWtjNBd0aFegJrLo9xN8iRkoxgov4Q7d16ASNGlnBIVOcHc2JuaPrRLbBtd3p2ERzbhzMrYE14tixn00FSSWJjpv</code></p>
                </div>
                
                <div class="test-cards">
                    <h4>Carte di Test Stripe</h4>
                    <ul>
                        <li>4242 4242 4242 4242 - Carta valida</li>
                        <li>4000 0025 0000 3155 - Richiede autenticazione</li>
                        <li>4000 0000 0000 9995 - Rifiuta il pagamento</li>
                    </ul>
                    <p>Usa qualsiasi data futura e CVC</p>
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
                <h2>Checkout Reale</h2>
                <p>Questa versione supporta pagamenti reali in modalità test.</p>
                
                <div class="debug-section">
                    <div class="debug-title">Stato configurazione:</div>
                    <div id="config-status">
                        <div class="log-entry log-info">
                            <span class="status-indicator status-active"></span>
                            Stripe.js: 
                            <span id="stripe-status">Caricamento...</span>
                        </div>
                        <div class="log-entry log-info">
                            <span class="status-indicator status-active"></span>
                            Chiave Pubblica: 
                            <span id="pubkey-status">Verifica...</span>
                        </div>
                        <div class="log-entry log-info">
                            <span class="status-indicator status-active"></span>
                            Modalità: 
                            <span id="mode-status">Test</span>
                        </div>
                    </div>
                    
                    <div class="debug-title">Log eventi:</div>
                    <div class="debug-log" id="debug-log">
                        <div class="log-entry log-info">
                            Inizializzazione in corso...
                        </div>
                    </div>
                </div>
                
                <div class="instructions">
                    <h3>Istruzioni per il test:</h3>
                    <ol>
                        <li>Clicca su "Acquista Ora"</li>
                        <li>Completa il checkout nella finestra di Stripe</li>
                        <li>Usa i dati di test della carta: 4242 4242 4242 4242</li>
                        <li>Inserisci una data futura e un CVC qualsiasi</li>
                        <li>Completa il pagamento (non verrà addebitato nulla)</li>
                    </ol>
                </div>
                
                <div class="warning">
                    <strong>NOTA:</strong> Questa è una versione semplificata che simula la creazione della sessione lato client. 
                    In un'implementazione reale, la creazione della sessione dovrebbe avvenire lato server per sicurezza.
                </div>
            </div>
        </div>
        
        <footer>
            <p>Utilizza solo carte di test per i pagamenti. Non verranno addebitati importi reali.</p>
            <p>Le transazioni sono processate in modalità test tramite Stripe.</p>
        </footer>
    </div>

    <script>
        // Configurazione Stripe con chiavi di test integrate
        const stripePublishableKey = 'pk_test_51QtDji2X4PJWtjNBd0aFegJrLo9xN8iRkoxgov4Q7d16ASNGlnBIVOcHc2JuaPrRLbBtd3p2ERzbhzMrYE14tixn00FSSWJjpv';
        const stripeSecretKey = 'sk_test_51QtDji2X4PJWtjNB6TPNZV7grmjSKRJvAHzY0ZgxdydwCZPSdQSDYrOsvzaGrejOh9vriE0Di7LQeMajQxJmClWn00FLOQVe6Y';
        
        // Elementi DOM
        const checkoutButton = document.getElementById('checkout-button');
        const debugLog = document.getElementById('debug-log');
        const stripeStatus = document.getElementById('stripe-status');
        const pubkeyStatus = document.getElementById('pubkey-status');
        const modeStatus = document.getElementById('mode-status');
        
        let stripe = null;
        
        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            addLog('Pagina caricata. Verifica configurazione Stripe...', 'info');
            
            // Verifica che Stripe.js sia caricato
            if (typeof Stripe === 'undefined') {
                addLog('Errore: Stripe.js non è stato caricato correttamente', 'error');
                checkoutButton.disabled = true;
                stripeStatus.innerHTML = '<span style="color: #ff5555;">❌ Non caricato</span>';
                return;
            }
            
            stripeStatus.innerHTML = '<span style="color: #50fa7b;">✅ Caricato</span>';
            
            // Verifica la chiave pubblica
            if (!stripePublishableKey || !stripePublishableKey.startsWith('pk_')) {
                addLog('Errore: Chiave pubblica non valida', 'error');
                checkoutButton.disabled = true;
                pubkeyStatus.innerHTML = '<span style="color: #ff5555;">❌ Non valida</span>';
                return;
            }
            
            pubkeyStatus.innerHTML = '<span style="color: #50fa7b;">✅ Configurata</span>';
            modeStatus.innerHTML = '<span style="color: #50fa7b;">✅ Test</span>';
            
            // Inizializza Stripe
            stripe = Stripe(stripePublishableKey);
            checkoutButton.disabled = false;
            
            addLog('Stripe inizializzato con successo', 'success');
            addLog('Pronto per processare pagamenti di test', 'success');
            
            // Setup event listener per il pulsante di checkout
            checkoutButton.addEventListener('click', function() {
                checkoutButton.disabled = true;
                addLog('Avvio processo di checkout...', 'info');
                
                // Crea una sessione di checkout
                createCheckoutSession()
                    .then(session => {
                        if (session.error) {
                            addLog('Errore creazione sessione: ' + session.error, 'error');
                            checkoutButton.disabled = false;
                            return;
                        }
                        
                        addLog('Sessione creata: ' + session.id, 'success');
                        
                        // Reindirizza a Stripe Checkout
                        return stripe.redirectToCheckout({ sessionId: session.id });
                    })
                    .then(result => {
                        if (result.error) {
                            addLog('Errore durante il redirect: ' + result.error.message, 'error');
                            checkoutButton.disabled = false;
                        }
                    })
                    .catch(error => {
                        addLog('Errore durante il checkout: ' + error.message, 'error');
                        checkoutButton.disabled = false;
                    });
            });
        });
        
        // Funzione per creare una sessione di checkout
        async function createCheckoutSession() {
            addLog('Creazione sessione di pagamento...', 'info');
            
            try {
                // In una situazione reale, questa chiamata andrebbe fatta al tuo server
                // Per questa demo, simuliamo la creazione di una sessione lato client
                
                // Simuliamo una chiamata API al server
                const response = await fetch('https://api.stripe.com/v1/checkout/sessions', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${stripeSecretKey}`,
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'payment_method_types[]': 'card',
                        'line_items[0][price_data][currency]': 'eur',
                        'line_items[0][price_data][product_data][name]': 'Corso di Web Development',
                        'line_items[0][price_data][unit_amount]': '5000',
                        'line_items[0][quantity]': '1',
                        'mode': 'payment',
                        'success_url': `${window.location.origin}/success.html`,
                        'cancel_url': `${window.location.origin}/cancel.html`,
                    })
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error?.message || 'Errore nella creazione della sessione');
                }
                
                const session = await response.json();
                return session;
            } catch (error) {
                addLog('Errore nella creazione della sessione: ' + error.message, 'error');
                
                // Fallback: simuliamo una sessione per permettere il testing dell'interfaccia
                addLog('Utilizzo sessione simulata per dimostrazione...', 'warning');
                
                return {
                    id: 'cs_test_' + Math.random().toString(36).substring(2, 15),
                    url: 'https://checkout.stripe.com/pay/'
                };
            }
        }
        
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

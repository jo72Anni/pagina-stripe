<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Checkout - GitHub Pages</title>
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
        
        .github-info {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #6e5494;
        }
        
        .github-button {
            display: inline-block;
            background: #24292e;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .github-button:hover {
            background: #2ea44f;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Stripe Checkout - GitHub Pages</h1>
            <p class="subtitle">Versione semplificata per testing su GitHub Pages</p>
        </header>
        
        <div class="content">
            <div class="product-card">
                <div class="product-image">💻</div>
                <h2 class="product-title">Corso di Web Development</h2>
                <p class="product-description">Impara a creare siti web moderni con HTML, CSS e JavaScript</p>
                <div class="product-price">€50,00</div>
                
                <div class="warning">
                    <strong>AVVISO:</strong> Questa è una versione di test con funzionalità limitate.
                </div>
                
                <div class="instructions">
                    <strong>ISTRUZIONI TEST:</strong> Clicca il pulsante per simulare il processo di checkout.
                </div>
                
                <div class="github-info">
                    <strong>GITHUB PAGES:</strong> Questo file HTML può essere caricato direttamente su GitHub Pages.
                    <br>
                    <a href="https://github.com" class="github-button" target="_blank">Vai a GitHub</a>
                </div>
                
                <h3>Caratteristiche:</h3>
                <ul class="feature-list">
                    <li>Accesso completo a tutte le lezioni</li>
                    <li>Esercizi pratici e progetti</li>
                    <li>Certificato di completamento</li>
                    <li>Supporto della community</li>
                    <li>Aggiornamenti gratuiti</li>
                </ul>
                
                <button id="checkout-button" class="checkout-button">Simula Acquisto</button>
            </div>
            
            <div class="checkout-section">
                <h2>Debug e Istruzioni</h2>
                <p>Questa versione simula il processo di checkout per GitHub Pages.</p>
                
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
                            Modalità: 
                            <span id="mode-status">Simulazione GitHub Pages</span>
                        </div>
                    </div>
                    
                    <div class="debug-title">Istruzioni GitHub:</div>
                    <div class="debug-log" id="debug-log">
                        <div class="log-entry log-info">
                            1. Crea un nuovo repository su GitHub
                        </div>
                        <div class="log-entry log-info">
                            2. Carica questo file come index.html
                        </div>
                        <div class="log-entry log-info">
                            3. Abilita GitHub Pages nelle impostazioni
                        </div>
                        <div class="log-entry log-info">
                            4. Il sito sarà live su username.github.io/repository
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3>Come modificare su GitHub:</h3>
                    <ol>
                        <li>Accedi al tuo account GitHub</li>
                        <li>Apri il repository dove hai caricato il file</li>
                        <li>Clicca sul file <strong>index.php</strong> o <strong>index.html</strong></li>
                        <li>Clicca sull'icona della matita (edit)</li>
                        <li>Modifica il codice direttamente nel browser</li>
                        <li>Scorri in basso e clicca "Commit changes"</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <footer>
            <p>Questa è una demo didattica per GitHub Pages. Non verranno processati pagamenti reali.</p>
            <p>Per una versione completa con pagamenti reali, è necessario un backend.</p>
        </footer>
    </div>

    <script>
        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            const checkoutButton = document.getElementById('checkout-button');
            const debugLog = document.getElementById('debug-log');
            const stripeStatus = document.getElementById('stripe-status');
            const modeStatus = document.getElementById('mode-status');
            
            // Verifica che Stripe.js sia caricato
            if (typeof Stripe === 'undefined') {
                addLog('Errore: Stripe.js non è stato caricato correttamente', 'error');
                checkoutButton.disabled = true;
                stripeStatus.innerHTML = '<span style="color: #ff5555;">❌ Non caricato</span>';
                return;
            }
            
            stripeStatus.innerHTML = '<span style="color: #50fa7b;">✅ Caricato</span>';
            modeStatus.innerHTML = '<span style="color: #50fa7b;">✅ Simulazione GitHub Pages</span>';
            
            addLog('Stripe.js caricato correttamente', 'success');
            addLog('Modalità simulazione attivata per GitHub Pages', 'info');
            
            // Setup event listener per il pulsante
            checkoutButton.addEventListener('click', function() {
                checkoutButton.disabled = true;
                addLog('Avvio simulazione checkout...', 'info');
                
                // Simula un ritardo di rete
                setTimeout(() => {
                    addLog('Connessione al server di pagamento...', 'info');
                    
                    // Simula la creazione della sessione
                    setTimeout(() => {
                        addLog('Sessione di pagamento creata', 'success');
                        
                        // Simula il reindirizzamento a Stripe
                        setTimeout(() => {
                            addLog('Reindirizzamento a Stripe Checkout...', 'info');
                            
                            // Simula il completamento
                            setTimeout(() => {
                                addLog('Pagamento simulato con successo!', 'success');
                                alert('SIMULAZIONE: Pagamento completato con successo! In una versione reale, saresti stato reindirizzato a Stripe.');
                                checkoutButton.disabled = false;
                            }, 1500);
                        }, 1000);
                    }, 1000);
                }, 1000);
            });
            
            addLog('Configurazione completata. Pronto per la simulazione.', 'success');
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

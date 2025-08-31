<?php
// RIMUOVI COMPLETAMENTE IL PARAMETRO Stripe-Version DA TUTTE LE RICHIESTE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strtolower($key) === 'stripe-version' || $key === 'Stripe-Version') {
            unset($_POST[$key]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($_GET as $key => $value) {
        if (strtolower($key) === 'stripe-version' || $key === 'Stripe-Version') {
            unset($_GET[$key]);
        }
    }
}

// Debug errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include l'autoloader di Composer per Stripe
require_once __DIR__ . '/vendor/autoload.php';

// Configurazione DB da variabili ambiente o valori di default
$db = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

// Configurazione Stripe da variabili ambiente o valori di default
$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_publishable_key',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_secret_key'
];

// Imposta la chiave segreta Stripe SOLO se è configurata correttamente
if (!empty($stripeConfig['secret_key']) && $stripeConfig['secret_key'] !== 'sk_test_your_secret_key') {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    \Stripe\Stripe::setApiVersion('2025-01-27.acacia');
    $stripeInitialized = true;
} else {
    $stripeInitialized = false;
}

// Connessione al database
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

// Gestione AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo = getConnection($db);

        switch ($_POST['action']) {
            case 'get_products':
                $products = $pdo->query("SELECT * FROM products")->fetchAll();
                echo json_encode(['status' => 'success', 'products' => $products]);
                break;
                
            case 'create_checkout_session':
                if (!$stripeInitialized) {
                    throw new Exception("Stripe non configurato correttamente");
                }
                
                $cart = json_decode($_POST['cart'], true);
                if (empty($cart)) {
                    throw new Exception("Il carrello è vuoto");
                }
                
                $lineItems = [];
                foreach ($cart as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => $item['name'],
                                'metadata' => [
                                    'product_id' => $item['id'],
                                ],
                            ],
                            'unit_amount' => (int)($item['price'] * 100),
                        ],
                        'quantity' => $item['quantity'],
                    ];
                }
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
                    'customer_email' => $_POST['email'] ?? '',
                ]);
                
                echo json_encode([
                    'status' => 'success', 
                    'sessionId' => $session->id
                ]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Verifica connessione per frontend
try {
    $pdo = getConnection($db);
    $connected = true;
} catch (Exception $e) {
    $connected = false;
    $errorMsg = $e->getMessage();
}

// Verifica chiavi Stripe
$stripeKeysConfigured = !empty($stripeConfig['publishable_key']) && !empty($stripeConfig['secret_key']) && 
                       $stripeConfig['publishable_key'] !== 'pk_test_your_publishable_key' && 
                       $stripeConfig['secret_key'] !== 'sk_test_your_secret_key';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carrello Acquisti Stripe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card { transition: all 0.3s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .spinner-border { width: 1rem; height: 1rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">Carrello Acquisti Stripe</h1>

    <!-- Stato connessione -->
    <div class="alert <?php echo $connected ? 'alert-success' : 'alert-danger'; ?>">
        <?php echo $connected ? '✅ Connesso al database con successo.' : '❌ Errore: ' . htmlspecialchars($errorMsg); ?>
    </div>

    <!-- Verifica Stripe -->
    <div class="alert <?php echo $stripeKeysConfigured ? 'alert-success' : 'alert-warning'; ?>">
        <?php if ($stripeKeysConfigured): ?>
            ✅ Chiavi Stripe configurate correttamente.
        <?php else: ?>
            ⚠️ Chiavi Stripe non configurate. Configura le variabili d'ambiente:<br>
            <code>STRIPE_PUBLISHABLE_KEY</code> e <code>STRIPE_SECRET_KEY</code>
        <?php endif; ?>
    </div>

    <?php if ($connected): ?>
    <!-- Carrello Stripe -->
    <div class="row">
        <div class="col-md-8">
            <h2>Prodotti Disponibili</h2>
            <div id="products-container" class="row mb-4"></div>
        </div>
        
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h3>Il tuo carrello</h3>
                </div>
                <div class="card-body">
                    <table class="table table-sm" id="cart-table">
                        <thead>
                            <tr>
                                <th>Prodotto</th>
                                <th>Q.tà</th>
                                <th>Totale</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cart-items">
                            <tr id="empty-cart">
                                <td colspan="4" class="text-center py-3">Il carrello è vuoto</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-end"><strong>Totale:</strong></td>
                                <td id="cart-total">€0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <!-- Form per email e checkout -->
                    <div id="checkout-section" style="display: none;">
                        <div class="mb-3">
                            <label for="customer-email" class="form-label">Email per la ricevuta</label>
                            <input type="email" class="form-control" id="customer-email" placeholder="Inserisci la tua email">
                        </div>
                        <button id="checkout-button" class="btn btn-success w-100" <?php echo !$stripeKeysConfigured ? 'disabled' : ''; ?>>
                            <?php echo $stripeKeysConfigured ? 'Vai al pagamento' : 'Configura Stripe prima'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <a href="PostgreSQLViewer.php" class="btn btn-outline-secondary">
            🔎 Apri PostgreSQL Viewer
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script>
$(function() {
    // Inizializza Stripe solo se le chiavi sono configurate
    <?php if ($stripeKeysConfigured): ?>
    const stripe = Stripe('<?php echo $stripeConfig['publishable_key']; ?>');
    <?php else: ?>
    const stripe = null;
    console.warn('Stripe non configurato. Configura le variabili d\'ambiente STRIPE_PUBLISHABLE_KEY e STRIPE_SECRET_KEY');
    <?php endif; ?>
    
    // Carrello
    let cart = [];
    
    // Carica prodotti per il carrello
    $.post('', {action: 'get_products'}, function(res) {
        if (res.status === 'success') {
            renderProducts(res.products);
        } else {
            console.error('Errore nel caricamento prodotti:', res.message);
            $('#products-container').html('<div class="col-12"><div class="alert alert-info">Nessun prodotto disponibile nel database.</div></div>');
        }
    });

    // Funzioni per il carrello
    function renderProducts(products) {
        const container = $('#products-container');
        container.empty();
        
        if (products.length === 0) {
            container.html('<div class="col-12"><div class="alert alert-info">Nessun prodotto disponibile.</div></div>');
            return;
        }
        
        products.forEach(product => {
            const productCard = `
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card product-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${product.name}</h5>
                            <p class="card-text flex-grow-1">${product.description || 'Nessuna descrizione disponibile'}</p>
                            <p class="card-text"><strong>Prezzo: €${parseFloat(product.price).toFixed(2)}</strong></p>
                            <button class="btn btn-primary add-to-cart mt-auto" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}">
                                Aggiungi al carrello
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.append(productCard);
        });
        
        // Aggiungi event listener per i pulsanti
        $('.add-to-cart').click(function() {
            const product = {
                id: $(this).data('id'),
                name: $(this).data('name'),
                price: parseFloat($(this).data('price')),
                quantity: 1
            };
            
            addToCart(product);
        });
    }
    
    function addToCart(product) {
        const existingItem = cart.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.push(product);
        }
        
        updateCart();
    }
    
    function removeFromCart(productId) {
        cart = cart.filter(item => item.id !== productId);
        updateCart();
    }
    
    function updateCart() {
        const cartItems = $('#cart-items');
        const cartTotal = $('#cart-total');
        const emptyCart = $('#empty-cart');
        const checkoutSection = $('#checkout-section');
        
        cartItems.empty();
        
        if (cart.length === 0) {
            cartItems.append('<tr id="empty-cart"><td colspan="4" class="text-center py-3">Il carrello è vuoto</td></tr>');
            checkoutSection.hide();
        } else {
            emptyCart.remove();
            
            let total = 0;
            
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                const row = `
                    <tr>
                        <td>${item.name}</td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 90px;">
                                <button class="btn btn-outline-secondary decrease-quantity" data-id="${item.id}">-</button>
                                <input type="number" class="form-control text-center quantity-input" value="${item.quantity}" min="1" data-id="${item.id}">
                                <button class="btn btn-outline-secondary increase-quantity" data-id="${item.id}">+</button>
                            </div>
                        </td>
                        <td>€${itemTotal.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger remove-item" data-id="${item.id}">
                                &times;
                            </button>
                        </td>
                    </tr>
                `;
                
                cartItems.append(row);
            });
            
            cartTotal.text(`€${total.toFixed(2)}`);
            checkoutSection.show();
            
            // Aggiungi event listener
            $('.remove-item').click(function() {
                removeFromCart($(this).data('id'));
            });
            
            $('.increase-quantity').click(function() {
                const productId = $(this).data('id');
                const item = cart.find(item => item.id === productId);
                if (item) {
                    item.quantity += 1;
                    updateCart();
                }
            });
            
            $('.decrease-quantity').click(function() {
                const productId = $(this).data('id');
                const item = cart.find(item => item.id === productId);
                if (item && item.quantity > 1) {
                    item.quantity -= 1;
                    updateCart();
                }
            });
            
            $('.quantity-input').change(function() {
                const productId = $(this).data('id');
                const quantity = parseInt($(this).val());
                const item = cart.find(item => item.id === productId);
                if (item && quantity > 0) {
                    item.quantity = quantity;
                    updateCart();
                }
            });
        }
    }
    
    // Gestione checkout
    $('#checkout-button').click(function() {
        console.log('Checkout button clicked');
        
        <?php if (!$stripeKeysConfigured): ?>
        alert('Stripe non è configurato. Configura le variabili d\'ambiente STRIPE_PUBLISHABLE_KEY e STRIPE_SECRET_KEY');
        return false;
        <?php endif; ?>
        
        const email = $('#customer-email').val().trim();
        console.log('Email:', email);
        
        if (!email) {
            alert('Inserisci la tua email');
            return false;
        }
        
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('Inserisci un indirizzo email valido');
            return false;
        }
        
        if (cart.length === 0) {
            alert('Il carrello è vuoto');
            return false;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        
        console.log('Sending checkout request...');
        
        // Usa fetch invece di $.post per migliore gestione errori
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'create_checkout_session',
                cart: JSON.stringify(cart),
                email: email
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Server response:', data);
            
            if (data.status === 'success') {
                console.log('Redirecting to Stripe...');
                return stripe.redirectToCheckout({
                    sessionId: data.sessionId
                });
            } else {
                throw new Error(data.message || 'Errore del server');
            }
        })
        .then(result => {
            if (result.error) {
                throw new Error(result.error.message);
            }
        })
        .catch(error => {
            console.error('Checkout error:', error);
            alert('Errore: ' + error.message);
            $button.prop('disabled', false).html('Vai al pagamento');
        });
        
        return false;
    });
});
</script>
</body>
</html>

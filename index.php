<?php
// =============================================
// FIX DEFINITIVO PER STRIPE-VERSION PARAMETER
// =============================================

// Rimuovi completamente qualsiasi parametro Stripe-Version PRIMA di qualsiasi altra cosa
if (isset($_POST['Stripe-Version'])) {
    unset($_POST['Stripe-Version']);
}
if (isset($_GET['Stripe-Version'])) {
    unset($_GET['Stripe-Version']);
}

// Rimuovi anche eventuali variabili con nomi simili
$requestKeys = array_merge(array_keys($_POST), array_keys($_GET));
foreach ($requestKeys as $key) {
    if (stripos($key, 'stripe') !== false && stripos($key, 'version') !== false) {
        if (isset($_POST[$key])) unset($_POST[$key]);
        if (isset($_GET[$key])) unset($_GET[$key]);
    }
}

// Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// CONFIGURAZIONE
// =============================================
require_once __DIR__ . '/vendor/autoload.php';

// Database configuration
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

// Stripe configuration
$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_publishable_key',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_secret_key'
];

// Initialize Stripe
$stripeInitialized = false;
if (!empty($stripeConfig['secret_key']) && $stripeConfig['secret_key'] !== 'sk_test_your_secret_key') {
    try {
        \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
        \Stripe\Stripe::setApiVersion('2025-01-27.acacia');
        $stripeInitialized = true;
    } catch (Exception $e) {
        error_log("Stripe initialization failed: " . $e->getMessage());
    }
}

// Database connection
function getDBConnection($config) {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};sslmode={$config['ssl_mode']}";
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// =============================================
// HANDLE AJAX REQUESTS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection($dbConfig);
        
        switch ($_POST['action']) {
            case 'get_products':
                $stmt = $pdo->query("SELECT * FROM products");
                $products = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'products' => $products]);
                break;
                
            case 'create_checkout_session':
                if (!$stripeInitialized) {
                    throw new Exception("Stripe non configurato correttamente");
                }
                
                $cart = json_decode($_POST['cart'] ?? '[]', true);
                if (empty($cart)) {
                    throw new Exception("Il carrello è vuoto");
                }
                
                $lineItems = [];
                foreach ($cart as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => ['name' => $item['name']],
                            'unit_amount' => (int)($item['price'] * 100),
                        ],
                        'quantity' => $item['quantity'],
                    ];
                }
                
                $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'];
                $successUrl = $baseUrl . '/success.php?session_id={CHECKOUT_SESSION_ID}';
                $cancelUrl = $baseUrl . $_SERVER['PHP_SELF'];
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'customer_email' => $_POST['email'] ?? '',
                ]);
                
                echo json_encode(['status' => 'success', 'sessionId' => $session->id]);
                break;
                
            default:
                throw new Exception("Azione non valida");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =============================================
// FRONTEND
// =============================================
try {
    $pdo = getDBConnection($dbConfig);
    $dbConnected = true;
} catch (Exception $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']) && 
                   $stripeConfig['publishable_key'] !== 'pk_test_your_publishable_key';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrello Stripe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card { transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-2px); }
        .cart-item { transition: background-color 0.2s; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="text-center mb-4">🛒 Carrello Acquisti</h1>

        <!-- System Status -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="alert <?= $dbConnected ? 'alert-success' : 'alert-danger' ?>">
                    <?= $dbConnected ? '✅ Database connesso' : '❌ Database: ' . htmlspecialchars($dbError) ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert <?= $stripeConfigured ? 'alert-success' : 'alert-warning' ?>">
                    <?= $stripeConfigured ? '✅ Stripe configurato' : '⚠️ Stripe non configurato' ?>
                </div>
            </div>
        </div>

        <?php if ($dbConnected): ?>
        <div class="row">
            <!-- Products Column -->
            <div class="col-lg-8">
                <h2>📦 Prodotti</h2>
                <div id="products-container" class="row g-3"></div>
            </div>

            <!-- Cart Column -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">🛒 Il tuo carrello</h3>
                    </div>
                    <div class="card-body">
                        <div id="cart-empty" class="text-center py-4">
                            <p>Il carrello è vuoto</p>
                        </div>
                        <div id="cart-content" style="display: none;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Prodotto</th>
                                        <th>Q.tà</th>
                                        <th>Prezzo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cart-items"></tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <td colspan="2"><strong>Totale:</strong></td>
                                        <td id="cart-total">€0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Checkout Form -->
                            <div id="checkout-section">
                                <div class="mb-3">
                                    <label for="customer-email" class="form-label">📧 Email per la ricevuta</label>
                                    <input type="email" class="form-control" id="customer-email" 
                                           placeholder="la.tua@email.com" required>
                                </div>
                                <button id="checkout-btn" class="btn btn-success w-100" 
                                        <?= !$stripeConfigured ? 'disabled' : '' ?>>
                                    <?= $stripeConfigured ? '💳 Vai al pagamento' : 'Configura Stripe' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
    $(document).ready(function() {
        const stripe = <?= $stripeConfigured ? "Stripe('{$stripeConfig['publishable_key']}')" : 'null' ?>;
        let cart = [];
        let products = [];

        // Load products
        $.post('', {action: 'get_products'}, function(response) {
            if (response.status === 'success') {
                products = response.products;
                renderProducts();
            } else {
                $('#products-container').html(`
                    <div class="col-12">
                        <div class="alert alert-danger">Errore nel caricamento prodotti</div>
                    </div>
                `);
            }
        }).fail(function() {
            $('#products-container').html(`
                <div class="col-12">
                    <div class="alert alert-danger">Errore di connessione</div>
                </div>
            `);
        });

        // Render products
        function renderProducts() {
            const container = $('#products-container');
            container.empty();

            if (products.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="alert alert-info">Nessun prodotto disponibile</div>
                    </div>
                `);
                return;
            }

            products.forEach(product => {
                const card = `
                    <div class="col-md-6 col-lg-4">
                        <div class="card product-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">${product.name}</h5>
                                <p class="card-text">${product.description || ''}</p>
                                <p class="h5 text-primary">€${parseFloat(product.price).toFixed(2)}</p>
                                <button class="btn btn-primary w-100 add-to-cart" 
                                        data-id="${product.id}" 
                                        data-name="${product.name}" 
                                        data-price="${product.price}">
                                    ➕ Aggiungi
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.append(card);
            });

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

        // Cart functions
        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                existing.quantity++;
            } else {
                cart.push({...product});
            }
            updateCart();
        }

        function updateCart() {
            const $empty = $('#cart-empty');
            const $content = $('#cart-content');
            const $items = $('#cart-items');
            const $total = $('#cart-total');

            $items.empty();
            
            if (cart.length === 0) {
                $empty.show();
                $content.hide();
                return;
            }

            $empty.hide();
            $content.show();

            let total = 0;
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                $items.append(`
                    <tr class="cart-item">
                        <td>${item.name}</td>
                        <td>
                            <div class="input-group input-group-sm">
                                <button class="btn btn-outline-secondary minus" data-id="${item.id}">-</button>
                                <input type="number" class="form-control text-center" 
                                       value="${item.quantity}" min="1" data-id="${item.id}">
                                <button class="btn btn-outline-secondary plus" data-id="${item.id}">+</button>
                            </div>
                        </td>
                        <td>€${itemTotal.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger remove" data-id="${item.id}">×</button>
                        </td>
                    </tr>
                `);
            });

            $total.text(`€${total.toFixed(2)}`);

            // Event listeners
            $('.plus').click(e => {
                const id = $(e.target).data('id');
                const item = cart.find(item => item.id === id);
                if (item) item.quantity++;
                updateCart();
            });

            $('.minus').click(e => {
                const id = $(e.target).data('id');
                const item = cart.find(item => item.id === id);
                if (item && item.quantity > 1) item.quantity--;
                updateCart();
            });

            $('.remove').click(e => {
                const id = $(e.target).data('id');
                cart = cart.filter(item => item.id !== id);
                updateCart();
            });
        }

        // Checkout
        $('#checkout-btn').click(async function() {
            const email = $('#customer-email').val().trim();
            
            if (!email) {
                alert('Inserisci la tua email');
                return;
            }

            if (!/\S+@\S+\.\S+/.test(email)) {
                alert('Email non valida');
                return;
            }

            if (cart.length === 0) {
                alert('Il carrello è vuoto');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('⏳ Processing...');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'create_checkout_session',
                        cart: JSON.stringify(cart),
                        email: email
                    })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    const result = await stripe.redirectToCheckout({
                        sessionId: data.sessionId
                    });

                    if (result.error) {
                        throw new Error(result.error.message);
                    }
                } else {
                    throw new Error(data.message || 'Errore del server');
                }
            } catch (error) {
                alert('Errore: ' + error.message);
                $btn.prop('disabled', false).html('💳 Vai al pagamento');
            }
        });
    });
    </script>
</body>
</html>

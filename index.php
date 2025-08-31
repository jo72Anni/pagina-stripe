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

// Stripe configuration - USA LA CHIAVE CORRETTA!
$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_publishable_key',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_secret_key'
];

// Initialize Stripe
$stripeInitialized = false;
$stripeError = '';
if (!empty($stripeConfig['secret_key']) && $stripeConfig['secret_key'] !== 'sk_test_your_secret_key') {
    try {
        \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
        \Stripe\Stripe::setApiVersion('2025-01-27.acacia');
        
        // Test della connessione a Stripe
        $balance = \Stripe\Balance::retrieve();
        $stripeInitialized = true;
        error_log("✅ Stripe configured successfully");
        
    } catch (Exception $e) {
        $stripeError = $e->getMessage();
        error_log("❌ Stripe error: " . $stripeError);
    }
} else {
    $stripeError = "Chiave Stripe non configurata correttamente";
    error_log("❌ Stripe key not configured");
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
                    throw new Exception("Stripe non configurato correttamente: " . $stripeError);
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
                            'product_data' => [
                                'name' => $item['name'],
                                'description' => $item['description'] ?? '',
                            ],
                            'unit_amount' => (int)($item['price'] * 100),
                        ],
                        'quantity' => $item['quantity'],
                    ];
                }
                
                $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'];
                $successUrl = $baseUrl . '/success.php?session_id={CHECKOUT_SESSION_ID}';
                $cancelUrl = $baseUrl . '/index.php';
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'customer_email' => $_POST['email'] ?? '',
                    'metadata' => [
                        'order_time' => date('Y-m-d H:i:s'),
                        'items_count' => count($cart)
                    ]
                ]);
                
                echo json_encode([
                    'status' => 'success', 
                    'sessionId' => $session->id,
                    'message' => 'Checkout session created successfully'
                ]);
                break;
                
            default:
                throw new Exception("Azione non valida");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage(),
            'debug' => 'Stripe initialized: ' . ($stripeInitialized ? 'yes' : 'no')
        ]);
    }
    exit;
}

// =============================================
// FRONTEND - CONNESSIONI E STATO
// =============================================
try {
    $pdo = getDBConnection($dbConfig);
    $dbConnected = true;
    $dbError = '';
} catch (Exception $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

// Carica prodotti per visualizzazione iniziale
$products = [];
if ($dbConnected) {
    try {
        $stmt = $pdo->query("SELECT * FROM products LIMIT 6");
        $products = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error loading products: " . $e->getMessage());
    }
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']) && 
                   $stripeConfig['publishable_key'] !== 'pk_test_your_publishable_key';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛍️ NexShop - Carrello Smart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --success: #27ae60;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container-main {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .status-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .product-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .cart-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 2rem;
        }
        
        .btn-primary {
            background: var(--secondary);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .cart-item {
            transition: background-color 0.2s ease;
        }
        
        .cart-item:hover {
            background-color: #f8f9fa;
        }
        
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="container container-main">
        <!-- Header -->
        <div class="header">
            <h1 class="display-4 fw-bold">🛍️ NexShop</h1>
            <p class="lead">Il tuo carrello intelligente con pagamenti sicuri Stripe</p>
        </div>

        <div class="container-fluid p-4">
            <!-- System Status -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card status-card <?= $dbConnected ? 'border-success' : 'border-danger' ?>">
                        <div class="card-body text-center">
                            <i class="fas fa-database feature-icon"></i>
                            <h5>Database Connection</h5>
                            <?php if ($dbConnected): ?>
                                <span class="badge bg-success">✅ CONNESSO</span>
                                <p class="text-muted mt-2">Connesso al database PostgreSQL</p>
                            <?php else: ?>
                                <span class="badge bg-danger">❌ ERRORE</span>
                                <p class="text-danger mt-2"><?= htmlspecialchars($dbError) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card status-card <?= $stripeConfigured ? 'border-success' : 'border-warning' ?>">
                        <div class="card-body text-center">
                            <i class="fas fa-credit-card feature-icon"></i>
                            <h5>Stripe Payment</h5>
                            <?php if ($stripeConfigured): ?>
                                <span class="badge bg-success">✅ ATTIVO</span>
                                <p class="text-muted mt-2">Pagamenti sicuri abilitati</p>
                            <?php else: ?>
                                <span class="badge bg-warning">⚠️ CONFIGURA</span>
                                <p class="text-warning mt-2"><?= $stripeError ?: 'Configura le chiavi Stripe' ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($dbConnected): ?>
            <!-- Main Content -->
            <div class="row">
                <!-- Products Column -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-box-open me-2"></i>Prodotti Disponibili</h2>
                        <span class="badge bg-primary"><?= count($products) ?> prodotti</span>
                    </div>
                    
                    <div id="products-container" class="row g-4">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="card product-card">
                                        <div class="card-body text-center">
                                            <div class="mb-3">
                                                <i class="fas fa-shopping-bag fa-3x text-primary"></i>
                                            </div>
                                            <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                            <p class="card-text text-muted">
                                                <?= htmlspecialchars($product['description'] ?? 'Prodotto di qualità') ?>
                                            </p>
                                            <p class="h4 text-primary fw-bold">
                                                €<?= number_format($product['price'], 2) ?>
                                            </p>
                                            <button class="btn btn-primary w-100 add-to-cart" 
                                                    data-id="<?= $product['id'] ?>"
                                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                                    data-price="<?= $product['price'] ?>"
                                                    data-description="<?= htmlspecialchars($product['description'] ?? '') ?>">
                                                <i class="fas fa-cart-plus me-2"></i>Aggiungi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nessun prodotto disponibile nel database
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cart Column -->
                <div class="col-lg-4">
                    <div class="cart-card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Il Tuo Carrello</h4>
                        </div>
                        <div class="card-body">
                            <div id="cart-empty" class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Il carrello è vuoto</p>
                                <small class="text-muted">Aggiungi alcuni prodotti per iniziare</small>
                            </div>
                            
                            <div id="cart-content" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table">
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
                                                <td id="cart-total" class="fw-bold">€0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Checkout Form -->
                                <div id="checkout-section" class="mt-4">
                                    <div class="mb-3">
                                        <label for="customer-email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email per la ricevuta
                                        </label>
                                        <input type="email" class="form-control" id="customer-email" 
                                               placeholder="nome@esempio.com" required>
                                        <div class="form-text">Ti invieremo la conferma d'ordine</div>
                                    </div>
                                    
                                    <button id="checkout-btn" class="btn btn-success w-100" 
                                            <?= !$stripeConfigured ? 'disabled' : '' ?>>
                                        <?php if ($stripeConfigured): ?>
                                            <i class="fas fa-lock me-2"></i>PAGAMENTO SICURO
                                        <?php else: ?>
                                            <i class="fas fa-cog me-2"></i>CONFIGURA STRIPE
                                        <?php endif; ?>
                                    </button>
                                    
                                    <?php if (!$stripeConfigured): ?>
                                        <div class="alert alert-warning mt-3">
                                            <small>
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Configura le variabili d'ambiente STRIPE_PUBLISHABLE_KEY e STRIPE_SECRET_KEY
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6><i class="fas fa-info-circle me-2 text-primary"></i>Come funziona</h6>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-check text-success me-2"></i>Aggiungi prodotti al carrello</li>
                                <li><i class="fas fa-check text-success me-2"></i>Inserisci la tua email</li>
                                <li><i class="fas fa-check text-success me-2"></i>Paga in modo sicuro con Stripe</li>
                                <li><i class="fas fa-check text-success me-2"></i>Ricevi la conferma via email</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
    $(document).ready(function() {
        const stripe = <?= $stripeConfigured ? "Stripe('{$stripeConfig['publishable_key']}')" : 'null' ?>;
        let cart = [];
        let products = <?= json_encode($products) ?>;

        // Initialize products
        function initProducts() {
            $('.add-to-cart').click(function() {
                const product = {
                    id: $(this).data('id'),
                    name: $(this).data('name'),
                    price: parseFloat($(this).data('price')),
                    description: $(this).data('description'),
                    quantity: 1
                };
                addToCart(product);
                
                // Feedback animation
                $(this).html('<i class="fas fa-check me-2"></i>Aggiunto!');
                $(this).removeClass('btn-primary').addClass('btn-success');
                setTimeout(() => {
                    $(this).html('<i class="fas fa-cart-plus me-2"></i>Aggiungi');
                    $(this).removeClass('btn-success').addClass('btn-primary');
                }, 1000);
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
                        <td>
                            <strong>${item.name}</strong>
                            ${item.description ? '<br><small class="text-muted">' + item.description + '</small>' : ''}
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 90px;">
                                <button class="btn btn-outline-secondary minus" data-id="${item.id}">-</button>
                                <input type="number" class="form-control text-center" 
                                       value="${item.quantity}" min="1" data-id="${item.id}" readonly>
                                <button class="btn btn-outline-secondary plus" data-id="${item.id}">+</button>
                            </div>
                        </td>
                        <td class="fw-bold">€${itemTotal.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger remove" data-id="${item.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            $total.text(`€${total.toFixed(2)}`);

            // Event listeners
            $('.plus').click(e => {
                const id = $(e.target).closest('.plus').data('id');
                const item = cart.find(item => item.id === id);
                if (item) item.quantity++;
                updateCart();
            });

            $('.minus').click(e => {
                const id = $(e.target).closest('.minus').data('id');
                const item = cart.find(item => item.id === id);
                if (item && item.quantity > 1) item.quantity--;
                updateCart();
            });

            $('.remove').click(e => {
                const id = $(e.target).closest('.remove').data('id');
                cart = cart.filter(item => item.id !== id);
                updateCart();
            });
        }

        // Checkout
        $('#checkout-btn').click(async function() {
            const email = $('#customer-email').val().trim();
            
            if (!email) {
                alert('❌ Inserisci la tua email');
                return;
            }

            if (!/\S+@\S+\.\S+/.test(email)) {
                alert('❌ Email non valida');
                return;
            }

            if (cart.length === 0) {
                alert('❌ Il carrello è vuoto');
                return;
            }

            const $btn = $(this);
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

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
                    $btn.html('<i class="fas fa-check me-2"></i>Reindirizzamento...');
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
                alert('❌ Errore: ' + error.message);
                $btn.prop('disabled', false).html(originalText);
            }
        });

        // Initialize
        initProducts();
    });
    </script>
</body>
</html>

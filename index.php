<?php
// ========================
// index.php - Carrello Stripe
// ========================

// Debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload Composer
require_once __DIR__ . '/vendor/autoload.php';

// -------------------
// Config DB
// -------------------
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 5432,
    'dbname' => getenv('DB_NAME') ?: 'postgres',
    'user' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
    'ssl_mode' => getenv('DB_SSLMODE') ?: 'require'
];

// -------------------
// Config Stripe
// -------------------
$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_your_publishable_key',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_secret_key'
];

$stripeInitialized = false;
if (!empty($stripeConfig['secret_key']) && $stripeConfig['secret_key'] !== 'sk_test_your_secret_key') {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    
    // Forza la versione principale dell'API Stripe
    \Stripe\Stripe::setApiVersion('2025-02-24');
    
    $stripeInitialized = true;
}

// -------------------
// Connessione DB con diagnostica avanzata
// -------------------
function getDBConnection($config) {
    try {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};sslmode={$config['ssl_mode']}";
        
        // Opzioni aggiuntive per SSL su Render
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_PERSISTENT => false
        ];
        
        // Aggiungi opzioni SSL se necessario
        if ($config['ssl_mode'] === 'require') {
            $options[PDO::PGSQL_ATTR_SSL_MODE] = PDO::PGSQL_SSL_REQUIRE;
        }
        
        $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
        
        // Test della connessione e della tabella
        $pdo->query("SELECT 1 FROM pg_tables WHERE tablename = 'products'")->fetch();
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Errore connessione DB: " . $e->getMessage());
    }
}

// -------------------
// Creazione tabella products se non esiste
// -------------------
function ensureProductsTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Inserisce alcuni prodotti di esempio se la tabella è vuota
        $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        if ($count == 0) {
            $sampleProducts = [
                ['name' => 'Prodotto 1', 'description' => 'Descrizione prodotto 1', 'price' => 19.99],
                ['name' => 'Prodotto 2', 'description' => 'Descrizione prodotto 2', 'price' => 29.99],
                ['name' => 'Prodotto 3', 'description' => 'Descrizione prodotto 3', 'price' => 39.99]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price) VALUES (?, ?, ?)");
            foreach ($sampleProducts as $product) {
                $stmt->execute([$product['name'], $product['description'], $product['price']]);
            }
        }
    } catch (PDOException $e) {
        throw new Exception("Errore nella creazione della tabella: " . $e->getMessage());
    }
}

// -------------------
// AJAX
// -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection($dbConfig);
        ensureProductsTable($pdo);
        
        switch ($_POST['action']) {
            case 'get_products':
                $products = $pdo->query("SELECT * FROM products")->fetchAll();
                echo json_encode(['status' => 'success', 'products' => $products]);
                break;

            case 'create_checkout_session':
                if (!$stripeInitialized) {
                    throw new Exception("Stripe non configurato correttamente. Verifica le chiavi API.");
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
                            'product_data' => ['name' => $item['name']],
                            'unit_amount' => (int)($item['price'] * 100),
                        ],
                        'quantity' => $item['quantity']
                    ];
                }

                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
                
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $baseUrl . '/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $baseUrl . '/index.php',
                    'customer_email' => $_POST['email'] ?? ''
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

// -------------------
// Frontend
// -------------------
try {
    $pdo = getDBConnection($dbConfig);
    ensureProductsTable($pdo);
    $dbConnected = true;
    $dbError = null;
} catch (Exception $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']) && 
                   $stripeConfig['publishable_key'] !== 'pk_test_your_publishable_key';

// Debug info
$currentStripeVersion = class_exists('\Stripe\Stripe') ? (\Stripe\Stripe::getApiVersion() ?? 'default') : 'not-set';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Carrello Stripe</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    .debug-info {
        font-size: 0.85rem;
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
    }
    .status-badge {
        position: absolute;
        top: 10px;
        right: 10px;
    }
</style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4 text-center">🛒 Carrello Stripe</h1>

    <!-- Debug Information -->
    <div class="debug-info">
        <strong>Informazioni di Debug:</strong><br>
        - Stripe API Version: <?php echo htmlspecialchars($currentStripeVersion); ?><br>
        - DB Host: <?php echo htmlspecialchars($dbConfig['host']); ?><br>
        - DB Name: <?php echo htmlspecialchars($dbConfig['dbname']); ?><br>
        - DB User: <?php echo htmlspecialchars($dbConfig['user']); ?><br>
        - SSL Mode: <?php echo htmlspecialchars($dbConfig['ssl_mode']); ?><br>
        - Stripe Configurato: <?php echo $stripeConfigured ? 'Sì' : 'No'; ?><br>
        - Render Environment: <?php echo getenv('RENDER') ? 'Sì' : 'No'; ?>
    </div>

    <div class="alert <?= $dbConnected ? 'alert-success' : 'alert-danger' ?>">
        <?= $dbConnected ? '✅ Database connesso correttamente' : '❌ Errore DB: ' . htmlspecialchars($dbError) ?>
    </div>
    
    <div class="alert <?= $stripeConfigured ? 'alert-success' : 'alert-warning' ?>">
        <?= $stripeConfigured ? '✅ Stripe configurato correttamente' : '⚠️ Stripe non configurato - Verifica le variabili d\'ambiente STRIPE_PUBLISHABLE_KEY e STRIPE_SECRET_KEY' ?>
    </div>

    <?php if ($dbConnected): ?>
    <div class="row">
        <div class="col-lg-8">
            <h2>Prodotti</h2>
            <div id="products-container" class="row g-3">
                <div class="col-12">
                    <div class="alert alert-info">Caricamento prodotti in corso...</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Il tuo carrello</h5>
                </div>
                <div class="card-body">
                    <div id="cart-empty" class="text-center py-3">Il carrello è vuoto</div>
                    <div id="cart-content" style="display:none;">
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
                                <tr>
                                    <td colspan="2"><strong>Totale:</strong></td>
                                    <td id="cart-total" class="fw-bold">€0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="mb-3">
                            <label for="customer-email" class="form-label">Email per la ricevuta</label>
                            <input type="email" id="customer-email" class="form-control" placeholder="inserisci@email.com" required>
                        </div>
                        
                        <button id="checkout-btn" class="btn btn-success w-100" <?= !$stripeConfigured ? 'disabled' : '' ?>>
                            <?= $stripeConfigured ? 'Vai al pagamento' : 'Configura Stripe prima' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <h4>Risoluzione problemi di connessione su Render:</h4>
        <ul>
            <li>Verifica che il database PostgreSQL su Render sia attivo</li>
            <li>Controlla che le variabili d'ambiente su Render siano corrette</li>
            <li>Assicurati che la connessione SSL sia configurata correttamente</li>
            <li>Riavvia il servizio su Render dopo aver modificato le variabili d'ambiente</li>
        </ul>
        <p class="mt-3">
            <strong>Configurazione Render consigliata:</strong><br>
            - DB_SSLMODE: require<br>
            - STRIPE_API_VERSION: 2025-02-24 (solo versione principale)
        </p>
    </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = <?= $stripeConfigured ? "Stripe('{$stripeConfig['publishable_key']}')" : 'null' ?>;
let cart = [];

// Carica prodotti
$(document).ready(function() {
    loadProducts();
});

function loadProducts() {
    $.post('', {action: 'get_products'}, function(res) {
        if (res.status === 'success') {
            const container = $('#products-container');
            container.empty();
            
            if (res.products.length === 0) {
                container.html('<div class="col-12"><div class="alert alert-warning">Nessun prodotto disponibile nel database</div></div>');
                return;
            }
            
            res.products.forEach(p => {
                container.append(`<div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${p.name}</h5>
                            <p class="card-text flex-grow-1">${p.description || ''}</p>
                            <p class="card-text fw-bold">€${parseFloat(p.price).toFixed(2)}</p>
                            <button class="btn btn-primary mt-auto add-to-cart" 
                                data-id="${p.id}" 
                                data-name="${p.name}" 
                                data-price="${p.price}">
                                Aggiungi al carrello
                            </button>
                        </div>
                    </div>
                </div>`);
            });
            
            $('.add-to-cart').click(function() {
                const product = {
                    id: $(this).data('id'),
                    name: $(this).data('name'),
                    price: parseFloat($(this).data('price')),
                    quantity: 1
                };
                
                const existingItem = cart.find(item => item.id === product.id);
                if (existingItem) {
                    existingItem.quantity++;
                } else {
                    cart.push(product);
                }
                updateCart();
            });
        } else {
            $('#products-container').html('<div class="col-12"><div class="alert alert-danger">Errore nel caricamento prodotti: ' + res.message + '</div></div>');
        }
    }).fail(function(xhr, status, error) {
        $('#products-container').html('<div class="col-12"><div class="alert alert-danger">Errore di connessione: ' + error + '</div></div>');
    });
}

function updateCart() {
    const $items = $('#cart-items');
    const $total = $('#cart-total');
    $items.empty();
    
    if (cart.length === 0) { 
        $('#cart-empty').show(); 
        $('#cart-content').hide(); 
        return; 
    }
    
    $('#cart-empty').hide(); 
    $('#cart-content').show();
    
    let total = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        $items.append(`<tr>
            <td>${item.name}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary decrease-qty" data-id="${item.id}">-</button>
                    <span class="btn btn-outline-light disabled">${item.quantity}</span>
                    <button class="btn btn-outline-secondary increase-qty" data-id="${item.id}">+</button>
                </div>
            </td>
            <td>€${itemTotal.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger remove-item" data-id="${item.id}">&times;</button></td>
        </tr>`);
    });
    
    $total.text(`€${total.toFixed(2)}`);
    
    $('.remove-item').click(function() {
        cart = cart.filter(item => item.id !== $(this).data('id'));
        updateCart();
    });
    
    $('.decrease-qty').click(function() {
        const item = cart.find(i => i.id === $(this).data('id'));
        if (item && item.quantity > 1) {
            item.quantity--;
            updateCart();
        }
    });
    
    $('.increase-qty').click(function() {
        const item = cart.find(i => i.id === $(this).data('id'));
        if (item) {
            item.quantity++;
            updateCart();
        }
    });
}

$('#checkout-btn').click(function() {
    const email = $('#customer-email').val().trim();
    
    if (!email) {
        alert('Inserisci un indirizzo email valido');
        return;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Inserisci un indirizzo email valido');
        return;
    }
    
    if (cart.length === 0) {
        alert('Il carrello è vuoto');
        return;
    }
    
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');
    
    $.post('', {
        action: 'create_checkout_session',
        cart: JSON.stringify(cart),
        email: email
    }, function(res) {
        if (res.status === 'success') {
            stripe.redirectToCheckout({ sessionId: res.sessionId })
                .then(function(result) {
                    if (result.error) {
                        alert('Errore: ' + result.error.message);
                        $btn.prop('disabled', false).text('Vai al pagamento');
                    }
                });
        } else {
            alert('Errore: ' + res.message);
            $btn.prop('disabled', false).text('Vai al pagamento');
        }
    }).fail(function(xhr, status, error) {
        alert('Errore di connessione: ' + error);
        $btn.prop('disabled', false).text('Vai al pagamento');
    });
});
</script>
</body>
</html>

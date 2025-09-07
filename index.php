<?php
// index.php - Carrello Stripe + Debug completo
// Versione: 2025 (riscrittura completa)

// --- Config PHP / Debug ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("===== Avvio index.php (debug) =====");

// --- Autoload Composer ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log('Composer autoload non trovato');
}

// -------------------
// Configurazioni DB/Stripe
// -------------------
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: '',
    'ssl_mode' => getenv('DB_SSL_MODE') ?: ''
];

$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: ''
];

// Log mascherato
$logStripe = [
    'publishable_key' => $stripeConfig['publishable_key'] ? substr($stripeConfig['publishable_key'],0,8).'...' : '(vuoto)',
    'secret_key'      => $stripeConfig['secret_key'] ? substr($stripeConfig['secret_key'],0,8).'...' : '(vuoto)'
];
error_log('DB Config: ' . json_encode(array_merge($dbConfig, ['password' => $dbConfig['password'] ? '***' : '(vuota)'])));
error_log('Stripe Config (mascherato): ' . json_encode($logStripe));

// -------------------
// Inizializza Stripe
// -------------------
$stripeInitialized = false;
if (!empty($stripeConfig['secret_key'])) {
    try {
        \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
        $stripeInitialized = true;
        error_log('Stripe inizializzato correttamente');
    } catch (Exception $e) {
        error_log('Errore inizializzazione Stripe: ' . $e->getMessage());
    }
} else {
    error_log('STRIPE_SECRET_KEY vuota: Stripe non inizializzato');
}

// -------------------
// ECHO CHIAVI STRIPE (PER DEBUG) - RIMUOVERE IN PRODUZIONE
// -------------------
echo 'Stripe publishable key: ' . $stripeConfig['publishable_key'] . '<br>';
echo 'Stripe secret key: ' . ($stripeConfig['secret_key'] ? substr($stripeConfig['secret_key'], 0, 8) . '...' : 'non impostata') . '<br>';

// -------------------
// Funzioni DB
// -------------------
function getDBConnection(array $config) {
    try {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        if (!empty($config['ssl_mode'])) {
            $dsn .= ";sslmode={$config['ssl_mode']}";
        }
        error_log("Tentativo connessione DB con DSN: {$dsn}");
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->query('SELECT 1')->fetch();
        error_log('Connessione DB OK');
        return $pdo;
    } catch (PDOException $e) {
        error_log('PDOException: ' . $e->getMessage());
        throw new Exception('Errore connessione DB: ' . $e->getMessage());
    }
}

function checkProductsTable(PDO $pdo): bool {
    try {
        $sql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema='public' AND table_name='products')";
        return (bool)$pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        error_log('Errore checkProductsTable: ' . $e->getMessage());
        return false;
    }
}

function ensureProductsTable(PDO $pdo): void {
    try {
        $create = "CREATE TABLE IF NOT EXISTS products (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($create);
        $count = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($count === 0) {
            $sample = [
                ['Prodotto 1','Descrizione 1',19.99],
                ['Prodotto 2','Descrizione 2',29.99],
                ['Prodotto 3','Descrizione 3',9.99]
            ];
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
            foreach($sample as $p){ $stmt->execute($p); }
        }
    } catch (PDOException $e) {
        error_log('Errore ensureProductsTable: ' . $e->getMessage());
        throw new Exception('Errore gestione tabella products: ' . $e->getMessage());
    }
}

// -------------------
// Helpers JSON
// -------------------
function sendJson($data,int $status=200){
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function errorJson(Exception $e,array $context=[]){
    sendJson([
        'status'=>'error',
        'message'=>$e->getMessage(),
        'trace'=>$e->getTraceAsString(),
        'context'=>$context
    ],400);
}

// -------------------
// Gestione AJAX POST
// -------------------
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    try{
        $pdo = getDBConnection($dbConfig);
        $action = $_POST['action'] ?? '';

        switch($action){
            case 'get_products':
                if(!checkProductsTable($pdo)) ensureProductsTable($pdo);
                $stmt = $pdo->query('SELECT id,name,description,price,created_at FROM products ORDER BY id');
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJson(['status'=>'success','products'=>$products],200);
                break;

            case 'create_checkout_session':
                if(!$stripeInitialized) throw new Exception('Stripe non configurato');
                $cart = json_decode($_POST['cart'] ?? '[]',true);
                $email = trim($_POST['email'] ?? '');
                if(empty($cart) || !is_array($cart)) throw new Exception('Carrello vuoto o malformato');

                $lineItems = [];
                foreach($cart as $item){
                    if(empty($item['name']) || !isset($item['price']) || !isset($item['quantity']))
                        throw new Exception('Elemento carrello malformato');
                    $lineItems[]=[
                        'price_data'=>[
                            'currency'=>'eur',
                            'product_data'=>['name'=>$item['name']],
                            'unit_amount'=>(int)round($item['price']*100)
                        ],
                        'quantity'=>(int)$item['quantity']
                    ];
                }

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
                $baseUrl = $protocol.'://'.$_SERVER['HTTP_HOST'];

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types'=>['card'],
                    'line_items'=>$lineItems,
                    'mode'=>'payment',
                    'success_url'=>$baseUrl.'/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'=>$baseUrl.'/index.php',
                    'customer_email'=>$email
                ]);

                sendJson(['status'=>'success','sessionId'=>$session->id],200);
                break;

            default:
                throw new Exception('Azione non valida: '.$action);
        }
    } catch(Exception $e){
        errorJson($e,['post'=>$_POST,'dbHost'=>$dbConfig['host'],'stripeInitialized'=>$stripeInitialized]);
    }
}

// -------------------
// FRONTEND HTML
// -------------------
$dbConnected=false; $tableExists=false; $dbError=null;
try{
    $pdo=getDBConnection($dbConfig);
    $dbConnected=true;
    $tableExists=checkProductsTable($pdo);
}catch(Exception $e){ $dbConnected=false; $tableExists=false; $dbError=$e->getMessage(); }

$stripeConfigured=$stripeInitialized && !empty($stripeConfig['publishable_key']);
$currentStripeVersion=(class_exists('\\Stripe\\Stripe') && method_exists('\\Stripe\\Stripe','getApiVersion'))?\Stripe\Stripe::getApiVersion():'not-set';
$pdoExtensionLoaded=extension_loaded('pdo');
$pdoPgSqlExtensionLoaded=extension_loaded('pdo_pgsql');
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Carrello Stripe - Debug</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.debug-info{font-size:.9rem;background:#f8f9fa;padding:10px;border-radius:6px;margin-bottom:15px}
.cart-item { border-bottom: 1px solid #eee; padding: 10px 0; }
.cart-total { font-weight: bold; font-size: 1.2rem; margin-top: 15px; }
.product-card { transition: all 0.3s; }
.product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
</style>
</head>
<body>
<div class="container py-4">
<h1 class="mb-4 text-center">🛒 Carrello Stripe - Debug</h1>
<div class="debug-info">
<strong>Debug Info:</strong><br>
- Stripe API Version: <?=htmlspecialchars($currentStripeVersion)?><br>
- DB Host: <?=htmlspecialchars($dbConfig['host'])?><br>
- DB Name: <?=htmlspecialchars($dbConfig['dbname'])?><br>
- DB User: <?=htmlspecialchars($dbConfig['user'])?><br>
- SSL Mode: <?=htmlspecialchars($dbConfig['ssl_mode'])?><br>
- PDO: <?=$pdoExtensionLoaded?'Caricata ✅':'Mancante ❌'?><br>
- PDO_PGSQL: <?=$pdoPgSqlExtensionLoaded?'Caricata ✅':'Mancante ❌'?><br>
- Tabella Products: <?=$tableExists?'Presente ✅':'Assente ❌'?><br>
- Stripe Configurato: <?=$stripeConfigured?'Sì ✅':'No ❌'?><br>
<?php if($dbError): ?><div class="mt-2 alert alert-danger">Errore DB: <?=htmlspecialchars($dbError)?></div><?php endif; ?>
</div>

<div class="row">
<div class="col-md-8">
<h2>Prodotti disponibili</h2>
<div id="products-list" class="row row-cols-1 row-cols-md-2 g-4 mb-4">
<div class="col">
<div class="card h-100">
<div class="card-body">
<h5 class="card-title">Caricamento prodotti...</h5>
</div>
</div>
</div>
</div>
</div>

<div class="col-md-4">
<div class="sticky-top" style="top: 20px;">
<div class="card">
<div class="card-header bg-primary text-white">
<h5 class="card-title mb-0"><i class="bi bi-cart"></i> Il tuo carrello</h5>
</div>
<div class="card-body">
<div id="cart-items">
<p class="text-muted">Il carrello è vuoto</p>
</div>
<div id="cart-total" class="cart-total text-end mb-3 d-none">
Totale: €<span id="cart-total-amount">0.00</span>
</div>
<form id="checkout-form" class="d-none">
<div class="mb-3">
<label for="email" class="form-label">Email</label>
<input type="email" class="form-control" id="email" placeholder="La tua email" required>
</div>
<button type="submit" class="btn btn-success w-100" id="checkout-button">
Checkout con Stripe
</button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script>
$(document).ready(function() {
    let cart = [];
    const stripe = Stripe('<?php echo $stripeConfig['publishable_key']; ?>');
    
    // Carica i prodotti
    function loadProducts() {
        $.post('index.php', {action: 'get_products'}, function(response) {
            if (response.status === 'success') {
                renderProducts(response.products);
            } else {
                alert('Errore nel caricamento prodotti: ' + response.message);
            }
        }).fail(function(xhr, status, error) {
            alert('Errore di connessione: ' + error);
        });
    }
    
    // Renderizza i prodotti
    function renderProducts(products) {
        const $productsList = $('#products-list');
        $productsList.empty();
        
        products.forEach(product => {
            const productCard = `
            <div class="col">
                <div class="card h-100 product-card">
                    <div class="card-body">
                        <h5 class="card-title">${product.name}</h5>
                        <p class="card-text">${product.description}</p>
                        <p class="card-text"><strong>Prezzo: €${product.price}</strong></p>
                        <button class="btn btn-primary add-to-cart" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}">
                            <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                        </button>
                    </div>
                </div>
            </div>`;
            $productsList.append(productCard);
        });
        
        // Aggiungi event listener per i pulsanti
        $('.add-to-cart').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const price = $(this).data('price');
            addToCart(id, name, price);
        });
    }
    
    // Aggiungi prodotto al carrello
    function addToCart(id, name, price) {
        const existingItem = cart.find(item => item.id === id);
        
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                quantity: 1
            });
        }
        
        updateCart();
    }
    
    // Rimuovi prodotto dal carrello
    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        updateCart();
    }
    
    // Aggiorna quantità prodotto
    function updateQuantity(id, quantity) {
        const item = cart.find(item => item.id === id);
        if (item) {
            item.quantity = parseInt(quantity);
            if (item.quantity <= 0) {
                removeFromCart(id);
            } else {
                updateCart();
            }
        }
    }
    
    // Aggiorna visualizzazione carrello
    function updateCart() {
        const $cartItems = $('#cart-items');
        const $cartTotal = $('#cart-total');
        const $checkoutForm = $('#checkout-form');
        
        if (cart.length === 0) {
            $cartItems.html('<p class="text-muted">Il carrello è vuoto</p>');
            $cartTotal.addClass('d-none');
            $checkoutForm.addClass('d-none');
            return;
        }
        
        let cartHtml = '';
        let total = 0;
        
        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            cartHtml += `
            <div class="cart-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">${item.name}</h6>
                        <small class="text-muted">€${item.price} x ${item.quantity}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <input type="number" min="1" value="${item.quantity}" 
                               class="form-control form-control-sm me-2 quantity-input" 
                               style="width: 60px;" 
                               data-id="${item.id}">
                        <button class="btn btn-sm btn-danger remove-item" data-id="${item.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end">
                    <strong>€${itemTotal.toFixed(2)}</strong>
                </div>
            </div>`;
        });
        
        $cartItems.html(cartHtml);
        $('#cart-total-amount').text(total.toFixed(2));
        $cartTotal.removeClass('d-none');
        $checkoutForm.removeClass('d-none');
        
        // Aggiungi event listener per i pulsanti di rimozione
        $('.remove-item').on('click', function() {
            const id = $(this).data('id');
            removeFromCart(id);
        });
        
        // Aggiungi event listener per i campi quantità
        $('.quantity-input').on('change', function() {
            const id = $(this).data('id');
            const quantity = $(this).val();
            updateQuantity(id, quantity);
        });
    }
    
    // Gestione checkout
    $('#checkout-form').on('submit', function(e) {
        e.preventDefault();
        
        const email = $('#email').val().trim();
        if (!email) {
            alert('Inserisci un indirizzo email valido');
            return;
        }
        
        $('#checkout-button').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        
        $.post('index.php', {
            action: 'create_checkout_session',
            cart: JSON.stringify(cart),
            email: email
        }, function(response) {
            if (response.status === 'success') {
                stripe.redirectToCheckout({ sessionId: response.sessionId })
                    .then(function(result) {
                        if (result.error) {
                            alert('Errore durante il redirect a Stripe: ' + result.error.message);
                            $('#checkout-button').prop('disabled', false).html('Checkout con Stripe');
                        }
                    });
            } else {
                alert('Errore nella creazione della sessione di checkout: ' + response.message);
                $('#checkout-button').prop('disabled', false).html('Checkout con Stripe');
            }
        }).fail(function(xhr, status, error) {
            alert('Errore di connessione: ' + error);
            $('#checkout-button').prop('disabled', false).html('Checkout con Stripe');
        });
    });
    
    // Inizializza la pagina
    loadProducts();
});
</script>
</body>
</html>


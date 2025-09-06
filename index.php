<?php
// index.php - Carrello Stripe (Debug completo, JSON pulito, fix Stripe-Version)
// Versione: 2025 (riscrittura completa)

// --- Config PHP / Debug ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("===== Avvio index.php (debug) =====");

// --- Autoload Composer (se presente) ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log('Composer autoload non trovato: ' . __DIR__ . '/vendor/autoload.php');
}

// -------------------
// Configurazioni (env con fallback)
// -------------------
$dbConfig = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 5432,
    'dbname'   => getenv('DB_NAME') ?: 'postgres',
    'user'     => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: '',
    'ssl_mode' => getenv('DB_SSLMODE') ?: ''
];

$stripeConfig = [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: ''
];

// Log (mascherato per la secret)
$logStripe = [
    'publishable_key' => $stripeConfig['publishable_key'] ? substr($stripeConfig['publishable_key'], 0, 8) . '...' : '(vuoto)',
    'secret_key'      => $stripeConfig['secret_key'] ? substr($stripeConfig['secret_key'], 0, 8) . '...' : '(vuoto)'
];
error_log('DB Config: ' . json_encode(array_merge($dbConfig, ['password' => $dbConfig['password'] ? '***' : '(vuota)'])));
error_log('Stripe Config (mascherato): ' . json_encode($logStripe));

// -------------------
// Inizializza Stripe (se è presente la secret key)
// -------------------
$stripeInitialized = false;
if (!empty($stripeConfig['secret_key'])) {
    try {
        \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
        // NON forzare qui una versione non valida: se vuoi forzare, usa una versione supportata.
        // Esempio (se vuoi realmente forzare una versione stabile): uncommentare la riga seguente con una versione valida
        // \Stripe\Stripe::setApiVersion('2024-06-20');
        $stripeInitialized = true;
        error_log('Stripe inizializzato senza versione forzata (usa default account).');
    } catch (Exception $e) {
        error_log('Errore inizializzazione Stripe: ' . $e->getMessage());
    }
} else {
    error_log('STRIPE_SECRET_KEY vuota: Stripe non inizializzato.');
}

// -------------------
// Funzioni DB
// -------------------
function getDBConnection(array $config) {
    try {
        $host = $config['host'];
        $port = intval($config['port']);
        $dbname = $config['dbname'];
        $user = $config['user'];
        $password = $config['password'];
        $sslmode = $config['ssl_mode'];

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        if (!empty($sslmode)) {
            $dsn .= ";sslmode={$sslmode}";
        }

        error_log("Tentativo connessione DB con DSN: {$dsn}");
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10,
            PDO::ATTR_PERSISTENT         => false
        ];

        $pdo = new PDO($dsn, $user, $password, $options);
        // test
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
        $sql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'products')";
        error_log('Eseguo check tabella products');
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
        error_log('CREATE TABLE IF NOT EXISTS products eseguita');

        $count = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        error_log('Products count: ' . $count);

        if ($count === 0) {
            $sample = [
                ['Prodotto 1', 'Descrizione prodotto 1', 19.99],
                ['Prodotto 2', 'Descrizione prodotto 2', 29.99],
                ['Prodotto 3', 'Descrizione prodotto 3', 9.99]
            ];
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price) VALUES (?, ?, ?)');
            foreach ($sample as $p) {
                $stmt->execute($p);
                error_log('Inserito sample product: ' . implode(' | ', $p));
            }
        }
    } catch (PDOException $e) {
        error_log('Errore ensureProductsTable: ' . $e->getMessage());
        throw new Exception('Errore gestione tabella products: ' . $e->getMessage());
    }
}

// -------------------
// Helpers JSON (puliti) - sendJson chiama exit per evitare output HTML dopo JSON
// -------------------
function sendJson($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log('Response JSON: ' . substr(json_encode($data), 0, 1000));
    exit;
}

function errorJson(Exception $e, array $context = []) {
    $payload = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'context' => $context
    ];
    sendJson($payload, 400);
}

// -------------------
// Gestione AJAX POST
// -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getDBConnection($dbConfig);
        $action = $_POST['action'] ?? '';
        error_log('AJAX action: ' . $action);

        switch ($action) {
            case 'get_products':
                if (!checkProductsTable($pdo)) {
                    ensureProductsTable($pdo);
                }
                $stmt = $pdo->query('SELECT id, name, description, price, created_at FROM products ORDER BY id');
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJson(['status' => 'success', 'products' => $products], 200);
                break;

            case 'create_checkout_session':
                if (!$stripeInitialized) {
                    throw new Exception('Stripe non configurato. Imposta STRIPE_SECRET_KEY.');
                }

                $cart = json_decode($_POST['cart'] ?? '[]', true);
                $email = trim($_POST['email'] ?? '');

                if (empty($cart) || !is_array($cart)) {
                    throw new Exception('Carrello vuoto o malformato');
                }

                $lineItems = [];
                foreach ($cart as $item) {
                    if (empty($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                        throw new Exception('Elemento carrello malformato');
                    }
                    $unit_amount = (int)round(floatval($item['price']) * 100);
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => ['name' => $item['name']],
                            'unit_amount' => $unit_amount
                        ],
                        'quantity' => (int)$item['quantity']
                    ];
                }

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $baseUrl . '/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => $baseUrl . '/index.php',
                    'customer_email' => $email
                ]);

                sendJson(['status' => 'success', 'sessionId' => $session->id], 200);
                break;

            default:
                throw new Exception('Azione non valida: ' . $action);
        }
    } catch (Exception $e) {
        error_log('Exception AJAX: ' . $e->getMessage());
        errorJson($e, ['post' => $_POST, 'dbHost' => $dbConfig['host'], 'stripeInitialized' => $stripeInitialized]);
    }
}

// -------------------
// FRONTEND HTML
// -------------------
// Pre-check DB per pannello debug
$dbConnected = false;
$tableExists = false;
$dbError = null;
try {
    $pdo = getDBConnection($dbConfig);
    $dbConnected = true;
    $tableExists = checkProductsTable($pdo);
} catch (Exception $e) {
    $dbConnected = false;
    $tableExists = false;
    $dbError = $e->getMessage();
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']);
$currentStripeVersion = (class_exists('\\Stripe\\Stripe') && method_exists('\\Stripe\\Stripe', 'getApiVersion')) ? \Stripe\Stripe::getApiVersion() : 'not-set';
$pdoExtensionLoaded = extension_loaded('pdo');
$pdoPgSqlExtensionLoaded = extension_loaded('pdo_pgsql');

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Carrello Stripe - Debug</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.debug-info{font-size:.9rem;background:#f8f9fa;padding:10px;border-radius:6px;margin-bottom:15px}
</style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4 text-center">🛒 Carrello Stripe - Debug</h1>

    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        - Stripe API Version: <?= htmlspecialchars($currentStripeVersion) ?><br>
        - DB Host: <?= htmlspecialchars($dbConfig['host']) ?><br>
        - DB Name: <?= htmlspecialchars($dbConfig['dbname']) ?><br>
        - DB User: <?= htmlspecialchars($dbConfig['user']) ?><br>
        - SSL Mode: <?= htmlspecialchars($dbConfig['ssl_mode']) ?><br>
        - PDO: <?= $pdoExtensionLoaded ? 'Caricata ✅' : 'Mancante ❌' ?><br>
        - PDO_PGSQL: <?= $pdoPgSqlExtensionLoaded ? 'Caricata ✅' : 'Mancante ❌' ?><br>
        - Tabella Products: <?= $tableExists ? 'Presente ✅' : 'Assente ❌' ?><br>
        - Stripe Configurato: <?= $stripeConfigured ? 'Sì ✅' : 'No ❌' ?><br>
        <?php if ($dbError): ?>
        <div class="mt-2 alert alert-danger">Errore DB: <?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <h2>Prodotti</h2>
            <div id="products-container" class="row g-3">
                <div class="col-12"><div class="alert alert-info">Caricamento prodotti in corso...</div></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card sticky-top" style="top:20px;">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Il tuo carrello</h5></div>
                <div class="card-body">
                    <div id="cart-empty" class="text-center py-3">Il carrello è vuoto</div>
                    <div id="cart-content" style="display:none;">
                        <table class="table table-sm">
                            <thead><tr><th>Prodotto</th><th>Q.tà</th><th>Prezzo</th><th></th></tr></thead>
                            <tbody id="cart-items"></tbody>
                            <tfoot><tr><td colspan="2"><strong>Totale:</strong></td><td id="cart-total" class="fw-bold">€0.00</td><td></td></tr></tfoot>
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

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<?php if ($stripeConfigured): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>const stripe = Stripe('<?= htmlspecialchars($stripeConfig['publishable_key']) ?>');</script>
<?php else: ?>
<script>const stripe = null;</script>
<?php endif; ?>

<script>
console.log('Client-side: avvio debug JS');
let cart = [];

function showErrorInProducts(msg) {
    $('#products-container').html('<div class="col-12"><div class="alert alert-danger">'+msg+'</div></div>');
}

function loadProducts() {
    console.log('AJAX get_products');
    $.post('', { action: 'get_products' }, function(res) {
        console.log('get_products response', res);
        if (res && res.status === 'success') {
            const container = $('#products-container'); container.empty();
            if (!res.products || res.products.length === 0) {
                container.html('<div class="col-12"><div class="alert alert-warning">Nessun prodotto disponibile</div></div>');
                return;
            }
            res.products.forEach(p => {
                container.append(`<div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${escapeHtml(p.name)}</h5>
                            <p class="card-text flex-grow-1">${escapeHtml(p.description || 'Nessuna descrizione')}</p>
                            <p class="card-text fw-bold">€${parseFloat(p.price).toFixed(2)}</p>
                            <button class="btn btn-primary mt-auto add-to-cart"
                                data-id="${p.id}" data-name="${escapeAttr(p.name)}" data-price="${p.price}">Aggiungi al carrello</button>
                        </div>
                    </div>
                </div>`);
            });
            $('.add-to-cart').off('click').on('click', function() {
                const product = { id: $(this).data('id'), name: $(this).data('name'), price: parseFloat($(this).data('price')), quantity: 1 };
                const existing = cart.find(i => i.id === product.id);
                if (existing) existing.quantity++; else cart.push(product);
                updateCart();
            });
        } else if (res) {
            showErrorInProducts('Errore caricamento prodotti: ' + (res.message || 'Risposta non valida'));
        } else {
            showErrorInProducts('Risposta non valida dal server.');
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX fail get_products', status, error, xhr.responseText);
        try {
            const parsed = JSON.parse(xhr.responseText);
            showErrorInProducts('Errore: ' + (parsed.message || JSON.stringify(parsed)));
        } catch (e) {
            showErrorInProducts('Errore di connessione: ' + error);
        }
    });
}

function updateCart() {
    const $items = $('#cart-items'); const $total = $('#cart-total'); $items.empty();
    if (cart.length === 0) { $('#cart-empty').show(); $('#cart-content').hide(); return; }
    $('#cart-empty').hide(); $('#cart-content').show();
    let total = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; total += itemTotal;
        $items.append(`<tr>
            <td>${escapeHtml(item.name)}</td>
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
    $total.text('€' + total.toFixed(2));
    $('.remove-item').off('click').on('click', function() { cart = cart.filter(i => i.id !== $(this).data('id')); updateCart(); });
    $('.decrease-qty').off('click').on('click', function() { const it = cart.find(i => i.id === $(this).data('id')); if (it && it.quantity > 1) it.quantity--; updateCart(); });
    $('.increase-qty').off('click').on('click', function() { const it = cart.find(i => i.id === $(this).data('id')); if (it) it.quantity++; updateCart(); });
}

$('#checkout-btn').on('click', function() {
    const email = $('#customer-email').val().trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { alert('Inserisci un indirizzo email valido'); return; }
    if (cart.length === 0) { alert('Il carrello è vuoto'); return; }
    const $btn = $(this); $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Processing...');
    $.post('', { action: 'create_checkout_session', cart: JSON.stringify(cart), email: email }, function(res) {
        console.log('create_checkout_session response', res);
        if (res && res.status === 'success') {
            if (!stripe) { alert('Stripe client non configurato'); $btn.prop('disabled', false).text('Vai al pagamento'); return; }
            stripe.redirectToCheckout({ sessionId: res.sessionId }).then(function(result) {
                if (result.error) { alert('Errore: ' + result.error.message); $btn.prop('disabled', false).text('Vai al pagamento'); }
            });
        } else if (res) {
            alert('Errore: ' + (res.message || JSON.stringify(res))); $btn.prop('disabled', false).text('Vai al pagamento');
        } else {
            alert('Risposta dal server non valida'); $btn.prop('disabled', false).text('Vai al pagamento');
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX fail create_checkout_session', status, error, xhr.responseText);
        try { const parsed = JSON.parse(xhr.responseText); alert('Errore: ' + (parsed.message || JSON.stringify(parsed))); }
        catch (e) { alert('Errore di connessione: ' + error); }
        $btn.prop('disabled', false).text('Vai al pagamento');
    });
});

// Utility
function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
function escapeAttr(s) { return String(s).replace(/"/g, '&quot;'); }

$(document).ready(function(){ loadProducts(); });
</script>
</body>
</html>

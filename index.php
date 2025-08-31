<?php
// ========================
// index.php - Carrello Stripe
// ========================

// Debug
ini_set('display_errors', 1);
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
    $stripeInitialized = true;
}

// -------------------
// Connessione DB
// -------------------
function getDBConnection($config){
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};sslmode={$config['ssl_mode']}";
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

// -------------------
// AJAX
// -------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $pdo = getDBConnection($dbConfig);
        switch ($_POST['action']) {
            case 'get_products':
                $products = $pdo->query("SELECT * FROM products")->fetchAll();
                echo json_encode(['status'=>'success','products'=>$products]);
                break;

            case 'create_checkout_session':
                if (!$stripeInitialized) throw new Exception("Stripe non configurato");

                $cart = json_decode($_POST['cart'], true);
                if (empty($cart)) throw new Exception("Carrello vuoto");

                $lineItems = [];
                foreach ($cart as $item){
                    $lineItems[] = [
                        'price_data'=>[
                            'currency'=>'eur',
                            'product_data'=>['name'=>$item['name']],
                            'unit_amount'=>(int)($item['price']*100),
                        ],
                        'quantity'=>$item['quantity']
                    ];
                }

                $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST'];
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types'=>['card'],
                    'line_items'=>$lineItems,
                    'mode'=>'payment',
                    'success_url'=>$baseUrl.'/success.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'=>$baseUrl.'/index.php',
                    'customer_email'=>$_POST['email'] ?? ''
                ]);

                echo json_encode(['status'=>'success','sessionId'=>$session->id]);
                break;

            default:
                throw new Exception("Azione non valida");
        }
    } catch (Exception $e){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

// -------------------
// Frontend
// -------------------
try{
    $pdo = getDBConnection($dbConfig);
    $dbConnected = true;
}catch(Exception $e){
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$stripeConfigured = $stripeInitialized && !empty($stripeConfig['publishable_key']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Carrello Stripe</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4 text-center">🛒 Carrello Stripe</h1>

    <div class="alert <?= $dbConnected ? 'alert-success' : 'alert-danger' ?>">
        <?= $dbConnected ? 'Database connesso' : 'Errore DB: '.htmlspecialchars($dbError) ?>
    </div>
    <div class="alert <?= $stripeConfigured ? 'alert-success' : 'alert-warning' ?>">
        <?= $stripeConfigured ? 'Stripe configurato' : 'Stripe non configurato' ?>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <h2>Prodotti</h2>
            <div id="products-container" class="row g-3"></div>
        </div>
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:20px;">
                <div class="card-header">Il tuo carrello</div>
                <div class="card-body">
                    <div id="cart-empty">Il carrello è vuoto</div>
                    <div id="cart-content" style="display:none;">
                        <table class="table table-sm">
                            <thead><tr><th>Prodotto</th><th>Q.tà</th><th>Prezzo</th><th></th></tr></thead>
                            <tbody id="cart-items"></tbody>
                            <tfoot>
                                <tr><td colspan="2">Totale:</td><td id="cart-total">€0.00</td><td></td></tr>
                            </tfoot>
                        </table>
                        <input type="email" id="customer-email" class="form-control mb-2" placeholder="Email">
                        <button id="checkout-btn" class="btn btn-success w-100" <?= !$stripeConfigured ? 'disabled' : '' ?>>Vai al pagamento</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = <?= $stripeConfigured ? "Stripe('{$stripeConfig['publishable_key']}')" : 'null' ?>;
let cart=[];

// Carica prodotti
$.post('', {action:'get_products'}, function(res){
    if(res.status==='success'){
        const container=$('#products-container');
        res.products.forEach(p=>{
            container.append(`<div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5>${p.name}</h5>
                        <p class="flex-grow-1">€${parseFloat(p.price).toFixed(2)}</p>
                        <button class="btn btn-primary mt-auto add-to-cart" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Aggiungi</button>
                    </div>
                </div>
            </div>`);
        });
        $('.add-to-cart').click(function(){
            const p={id:$(this).data('id'),name:$(this).data('name'),price:parseFloat($(this).data('price')),quantity:1};
            const e=cart.find(i=>i.id===p.id);
            if(e)e.quantity++; else cart.push(p);
            updateCart();
        });
    }
});

function updateCart(){
    const $items=$('#cart-items'),$total=$('#cart-total');
    $items.empty();
    if(cart.length===0){ $('#cart-empty').show(); $('#cart-content').hide(); return; }
    $('#cart-empty').hide(); $('#cart-content').show();
    let total=0;
    cart.forEach(i=>{
        const itemTotal=i.price*i.quantity; total+=itemTotal;
        $items.append(`<tr>
            <td>${i.name}</td>
            <td>${i.quantity}</td>
            <td>€${itemTotal.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger remove-item" data-id="${i.id}">&times;</button></td>
        </tr>`);
    });
    $total.text(`€${total.toFixed(2)}`);
    $('.remove-item').click(function(){ cart=cart.filter(i=>i.id!=$(this).data('id')); updateCart(); });
}

$('#checkout-btn').click(function(){
    const email=$('#customer-email').val().trim();
    if(!email){ alert('Inserisci email'); return; }
    if(cart.length===0){ alert('Carrello vuoto'); return; }
    const $btn=$(this); $btn.prop('disabled',true).text('Processing...');
    $.post('', {action:'create_checkout_session',cart:JSON.stringify(cart),email:email}, function(res){
        if(res.status==='success'){ stripe.redirectToCheckout({sessionId:res.sessionId}); }
        else{ alert(res.message); $btn.prop('disabled',false).text('Vai al pagamento'); }
    }).fail(function(){ alert('Errore di connessione'); $btn.prop('disabled',false).text('Vai al pagamento'); });
});
</script>
</body>
</html>

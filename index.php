<?php
require_once 'vendor/autoload.php';

// Configurazione Stripe
$stripeSecretKey = 'sk_test_51QtDji2X4PJWtjNB6TPNZV7grmjSKRJvAHzY0ZgxdydwCZPSdQSDYrOsvzaGrejOh9vriE0Di7LQeMajQxJmClWn00FLOQVe6Y';
$stripePublishableKey = 'pk_test_51QtDji2X4PJWtjNBd0aFegJrLo9xN8iRkoxgov4Q7d16ASNGlnBIVOcHc2JuaPrRLbBtd3p2ERzbhzMrYE14tixn00FSSWJjpv';
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Gestione della creazione della sessione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $input['productName'],
                        'description' => 'Descrizione del prodotto di esempio'
                    ],
                    'unit_amount' => $input['amount'],
                ],
                'quantity' => $input['quantity'],
            ]],
            'mode' => 'payment',
            'success_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/cancel.php',
            'customer_email' => $input['email'] ?? '',
            'submit_type' => 'pay',
            // RIMOSSO: 'billing_address_collection' => 'required',
        ]);

        echo json_encode(['id' => $session->id]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stripe Checkout</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 500px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product { 
            border-bottom: 1px solid #eee;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .buy-btn { 
            background: #5469d4; 
            color: white; 
            border: none; 
            padding: 15px 30px; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
            width: 100%;
            margin-top: 10px;
        }
        .buy-btn:hover { 
            background: #3a4fc4; 
        }
        .buy-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error {
            color: #e74c3c;
            margin-top: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Acquista Prodotto</h1>
        
        <div class="product">
            <h2>Prodotto Demo - €50.00</h2>
            <p>Descrizione del prodotto di esempio con tutte le caratteristiche principali.</p>
        </div>

        <form id="checkout-form">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required placeholder="la.tua@email.com">
                <div class="error" id="email-error"></div>
            </div>

            <button type="submit" class="buy-btn" id="buy-button">Acquista Ora - €50.00</button>
        </form>
    </div>

    <script>
        const stripe = Stripe('<?php echo $stripePublishableKey; ?>');
        const form = document.getElementById('checkout-form');
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('email-error');
        const buyButton = document.getElementById('buy-button');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Reset errori
            emailError.textContent = '';
            
            // Validazione email
            const email = emailInput.value.trim();
            if (!email) {
                emailError.textContent = 'Inserisci la tua email';
                return;
            }
            
            if (!/\S+@\S+\.\S+/.test(email)) {
                emailError.textContent = 'Inserisci un\'email valida';
                return;
            }

            buyButton.disabled = true;
            buyButton.textContent = 'Processing...';

            try {
                const response = await fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        productName: 'Prodotto Demo',
                        amount: 5000, // 50.00 EUR in cents
                        quantity: 1,
                        email: email
                    })
                });
                
                const data = await response.json();
                
                if (data.id) {
                    const result = await stripe.redirectToCheckout({ 
                        sessionId: data.id 
                    });
                    
                    if (result.error) {
                        alert('Errore checkout: ' + result.error.message);
                    }
                } else {
                    alert('Errore: ' + (data.error || 'Errore sconosciuto'));
                    console.error('Server error:', data);
                }
            } catch (error) {
                console.error('Network error:', error);
                alert('Si è verificato un errore di connessione');
            } finally {
                buyButton.disabled = false;
                buyButton.textContent = 'Acquista Ora - €50.00';
            }
        });
    </script>
</body>
</html>
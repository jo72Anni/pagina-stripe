<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrello Stripe - Soluzione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 10px 10px;
        }
        
        .debug-panel {
            background-color: #f8f9fa;
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .product-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        
        .cart-sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: fadeIn 0.5s, fadeOut 0.5s 2.5s forwards;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        
        .btn-success {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
        
        .solution-box {
            background-color: #eef2ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-cart-check"></i> Carrello Stripe - Soluzione</h1>
                    <p class="lead">Risoluzione dell'errore "Received unknown parameter: Stripe-Version"</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">API Version: 2025-02-24.acacia</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="solution-box">
                    <h4><i class="bi bi-lightbulb"></i> Soluzione all'errore</h4>
                    <p>L'errore "Received unknown parameter: Stripe-Version:2025-02-24.acacia" indica un conflitto tra la versione della libreria Stripe PHP e l'API.</p>
                    <p><strong>Possibili cause e soluzioni:</strong></p>
                    <ol>
                        <li>Aggiornare la libreria Stripe PHP all'ultima versione</li>
                        <li>Forzare una versione specifica dell'API Stripe nel codice</li>
                        <li>Verificare la compatibilità tra la libreria e l'API</li>
                    </ol>
                </div>

                <h3>Prodotti disponibili</h3>
                <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
                    <div class="col">
                        <div class="card product-card">
                            <span class="badge bg-success status-badge">Disponibile</span>
                            <img src="https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=400" class="card-img-top product-image" alt="Smartphone">
                            <div class="card-body">
                                <h5 class="card-title">Smartphone XYZ</h5>
                                <p class="card-text">Telefono di ultima generazione con fotocamera avanzata e batteria a lunga durata.</p>
                                <p class="card-text"><strong>Prezzo: €599,99</strong></p>
                                <button class="btn btn-primary add-to-cart">
                                    <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card product-card">
                            <span class="badge bg-success status-badge">Disponibile</span>
                            <img src="https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=400" class="card-img-top product-image" alt="Laptop">
                            <div class="card-body">
                                <h5 class="card-title">Laptop Ultra</h5>
                                <p class="card-text">Potente laptop per lavoro e gaming con schermo 15" e GPU dedicata.</p>
                                <p class="card-text"><strong>Prezzo: €1299,99</strong></p>
                                <button class="btn btn-primary add-to-cart">
                                    <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card product-card">
                            <span class="badge bg-success status-badge">Disponibile</span>
                            <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400" class="card-img-top product-image" alt="Cuffie">
                            <div class="card-body">
                                <h5 class="card-title">Cuffie Wireless</h5>
                                <p class="card-text">Cuffie con cancellazione del rumore e batteria a lunga durata (30 ore).</p>
                                <p class="card-text"><strong>Prezzo: €199,99</strong></p>
                                <button class="btn btn-primary add-to-cart">
                                    <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card product-card">
                            <span class="badge bg-success status-badge">Disponibile</span>
                            <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400" class="card-img-top product-image" alt="Smartwatch">
                            <div class="card-body">
                                <h5 class="card-title">Smartwatch Pro</h5>
                                <p class="card-text">Monitora la tua salute e le notifiche del telefono con questo smartwatch avanzato.</p>
                                <p class="card-text"><strong>Prezzo: €249,99</strong></p>
                                <button class="btn btn-primary add-to-cart">
                                    <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="cart-sidebar sticky-top" style="top: 20px;">
                    <h4><i class="bi bi-cart"></i> Il tuo carrello</h4>
                    <div class="cart-items">
                        <p class="text-muted">Il carrello è vuoto</p>
                    </div>
                    <div class="cart-total text-end mb-3 d-none">
                        <h5>Totale: €<span class="total-amount">0,00</span></h5>
                    </div>
                    <form class="checkout-form d-none">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="la.tua.email@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-credit-card"></i> Checkout con Stripe
                        </button>
                    </form>
                </div>

                <div class="debug-panel mt-4">
                    <h5>Informazioni di Debug</h5>
                    <p><strong>Problema riscontrato:</strong> Errore "Received unknown parameter: Stripe-Version:2025-02-24.acacia"</p>
                    <p><strong>Soluzione applicata:</strong> Forzare la versione API corretta nel codice PHP</p>
                    <div class="code-snippet bg-dark text-light p-3 rounded mt-2">
                        <code>
// Aggiungere nel codice PHP, dopo l'inclusione di Stripe<br>
\Stripe\Stripe::setApiVersion('2025-02-24.acacia');<br>
\Stripe\Stripe::setApiKey($stripeSecretKey);
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Carrello Stripe</h5>
                    <p>Una soluzione completa per integrare pagamenti Stripe nel tuo e-commerce.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>© 2025 - Tutti i diritti riservati</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let cart = [];
            
            // Aggiungi prodotto al carrello
            $('.add-to-cart').on('click', function() {
                const productCard = $(this).closest('.product-card');
                const name = productCard.find('.card-title').text();
                const price = parseFloat(productCard.find('.card-text strong').text().replace('Prezzo: €', '').replace(',', '.'));
                
                addToCart(name, price);
                showNotification(`${name} aggiunto al carrello!`);
            });
            
            // Funzione per aggiungere al carrello
            function addToCart(name, price) {
                const existingItem = cart.find(item => item.name === name);
                
                if (existingItem) {
                    existingItem.quantity++;
                } else {
                    cart.push({
                        name: name,
                        price: price,
                        quantity: 1
                    });
                }
                
                updateCart();
            }
            
            // Aggiorna visualizzazione carrello
            function updateCart() {
                const $cartItems = $('.cart-items');
                const $cartTotal = $('.cart-total');
                const $checkoutForm = $('.checkout-form');
                
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
                                <small class="text-muted">€${item.price.toFixed(2)} x ${item.quantity}</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="number" min="1" value="${item.quantity}" 
                                       class="form-control form-control-sm me-2 quantity-input" 
                                       style="width: 70px;" 
                                       data-name="${item.name}">
                                <button class="btn btn-sm btn-danger remove-item" data-name="${item.name}">
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
                $('.total-amount').text(total.toFixed(2));
                $cartTotal.removeClass('d-none');
                $checkoutForm.removeClass('d-none');
                
                // Aggiungi event listener per i pulsanti di rimozione
                $('.remove-item').on('click', function() {
                    const name = $(this).data('name');
                    removeFromCart(name);
                });
                
                // Aggiungi event listener per i campi quantità
                $('.quantity-input').on('change', function() {
                    const name = $(this).data('name');
                    const quantity = $(this).val();
                    updateQuantity(name, quantity);
                });
            }
            
            // Rimuovi prodotto dal carrello
            function removeFromCart(name) {
                const item = cart.find(item => item.name === name);
                cart = cart.filter(item => item.name !== name);
                updateCart();
                
                if (item) {
                    showNotification(`${item.name} rimosso dal carrello!`, 'warning');
                }
            }
            
            // Aggiorna quantità prodotto
            function updateQuantity(name, quantity) {
                const item = cart.find(item => item.name === name);
                if (item) {
                    item.quantity = parseInt(quantity);
                    if (item.quantity <= 0) {
                        removeFromCart(name);
                    } else {
                        updateCart();
                    }
                }
            }
            
            // Gestione checkout
            $('.checkout-form').on('submit', function(e) {
                e.preventDefault();
                
                const email = $('#email').val().trim();
                if (!email) {
                    showNotification('Inserisci un indirizzo email valido', 'error');
                    return;
                }
                
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    showNotification('Inserisci un indirizzo email valido', 'error');
                    return;
                }
                
                // Simulazione di successo (sostituire con chiamata API reale)
                showNotification('Reindirizzamento a Stripe...', 'info');
                
                // Simulazione di reindirizzamento
                setTimeout(() => {
                    showNotification('Checkout completato con successo!', 'success');
                }, 2000);
            });
            
            // Mostra notifica
            function showNotification(message, type = 'success') {
                // Rimuovi notifiche precedenti
                $('.notification').remove();
                
                const bgColor = type === 'error' ? 'danger' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success'));
                
                const notification = $(`
                    <div class="notification alert alert-${bgColor} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
                
                $('body').append(notification);
                
                // Rimuovi automaticamente dopo 3 secondi
                setTimeout(() => {
                    notification.alert('close');
                }, 3000);
            }
            
            // Inizializza il carrello
            updateCart();
        });
    </script>
</body>
</html>

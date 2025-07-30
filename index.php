<?php
session_start();

// ==============================================
// CONFIGURAZIONE AUTOMATICA DA VARIABILI D'AMBIENTE
// ==============================================

// Leggi le credenziali dall'ambiente del server
$DB_CONFIG = [
    'host'     => getenv('DB_HOST'),
    'port'     => (int)(getenv('DB_PORT') ?: 5432),
    'dbname'   => getenv('DB_NAME'),
    'user'     => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'ssl_mode' => getenv('DB_SSL_MODE') ?: 'require'
];

$ADMIN_CREDENTIALS = [
    'username' => getenv('ADMIN_USER') ?: 'admin',
    'password' => getenv('ADMIN_PASSWORD') ? password_hash(getenv('ADMIN_PASSWORD'), PASSWORD_DEFAULT) 
                  : password_hash('admin123', PASSWORD_DEFAULT)
];

// Verifica credenziali minime
if (empty($DB_CONFIG['host']) || empty($DB_CONFIG['dbname']) || empty($DB_CONFIG['user']) || empty($DB_CONFIG['password'])) {
    die("Configurazione database incompleta. Imposta le variabili d'ambiente richieste.");
}

// ==============================================
// GESTIONE AUTENTICAZIONE
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_CREDENTIALS['username'] && 
        password_verify($_POST['password'], $ADMIN_CREDENTIALS['password'])) {
        $_SESSION['authenticated'] = true;
        header("Location: ?");
        exit;
    } else {
        $login_error = "Credenziali non valide";
    }
}

// ==============================================
// INTERFACCIA UTENTE (simile alle versioni precedenti)
// ==============================================

if (!($_SESSION['authenticated'] ?? false)) {
    // Mostra solo il form di login
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title text-center">Accesso</h4>
                            <?php if (isset($login_error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Accedi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// RESTANTE CODICE IDENTICO ALLE VERSIONI PRECEDENTI...
// (gestione DB, visualizzazione tabelle, ecc.)

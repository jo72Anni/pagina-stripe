
<?php
// ==============================================
// ESPLORATORE DATABASE POSTGRESQL (ENV VARS NATIVI)
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Blocca accesso diretto
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Accesso negato');
}

// Caricamento manuale da .env (senza librerie esterne)
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Ignora commenti
        
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Configurazione da ENV (con fallback a getenv() per hosting senza supporto .env)
$db_config = [
    'host'     => $_ENV['DB_HOST'] ?? getenv('DB_HOST'),
    'port'     => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 5432,
    'dbname'   => $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
    'user'     => $_ENV['DB_USER'] ?? getenv('DB_USER'),
    'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD'),
    'ssl_mode' => $_ENV['DB_SSL_MODE'] ?? getenv('DB_SSL_MODE') ?? 'require'
];

// Verifica credenziali
$required_vars = ['host', 'dbname', 'user', 'password'];
foreach ($required_vars as $var) {
    if (empty($db_config[$var])) {
        die("<div class='error'>Errore: Configurazione database incompleta (manca $var)</div>");
    }
}

// ... [resto del codice identico alla versione precedente] ...

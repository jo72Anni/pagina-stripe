<<?php
require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$host = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$port = getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 5432;
$db   = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'postgres';
$user = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'postgres';
$pass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$ssl  = getenv('DB_SSL_MODE') ?: $_ENV['DB_SSL_MODE'] ?? 'prefer';

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Errore connessione DB: " . $e->getMessage());
}

return $pdo;

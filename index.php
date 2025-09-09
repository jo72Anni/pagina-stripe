<?php
require 'vendor/autoload.php';

// Mostra la versione PHP
echo "<h2>PHP Version:</h2>";
echo phpversion();

// Mostra versione cURL
echo "<h2>cURL Version:</h2>";
$curl_info = curl_version();
echo "cURL: " . $curl_info['version'] . "<br>";
echo "SSL: " . $curl_info['ssl_version'] . "<br>";

// Mostra versione Stripe PHP
echo "<h2>Stripe PHP Version:</h2>";
echo \Stripe\Stripe::VERSION;

// Mostra environment variables (senza stampare le chiavi segrete!)
echo "<h2>Environment Variables:</h2>";
echo "STRIPE_SECRET_KEY? " . (getenv('STRIPE_SECRET_KEY') ? 'Set' : 'Non impostata') . "<br>";
echo "STRIPE_PUBLISHABLE_KEY? " . (getenv('STRIPE_PUBLISHABLE_KEY') ? 'Set' : 'Non impostata') . "<br>";
echo "STRIPE_WEBHOOK_SECRET? " . (getenv('STRIPE_WEBHOOK_SECRET') ? 'Set' : 'Non impostata') . "<br>";
?>

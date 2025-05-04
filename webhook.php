<?php
require __DIR__ . '/vendor/autoload.php';

// 1. Recupera le variabili d'ambiente da Render.com
$senderEmail = getenv('SENDER_EMAIL'); // o $_ENV['SENDER_EMAIL']
$brevoApiKey = getenv('BREVO_API_KEY');
$testEmail = getenv('TEST_EMAIL');

// 2. Configura Brevo
$config = Brevo\Client\Configuration::getDefaultConfiguration()
    ->setApiKey('api-key', $brevoApiKey);

$apiInstance = new Brevo\Client\Api\TransactionalEmailsApi(
    new GuzzleHttp\Client(),
    $config
);

// 3. Crea e invia l'email
$email = new Brevo\Client\Model\SendSmtpEmail([
    'sender' => new Brevo\Client\Model\SendSmtpEmailSender([
        'email' => $senderEmail,
        'name' => 'Gianni'
    ]),
    'to' => [
        new Brevo\Client\Model\SendSmtpEmailTo([
            'email' => $testEmail,
            'name' => 'Test Render'
        ])
    ],
    'subject' => 'Conferma funzionamento da Render',
    'htmlContent' => '<h1>Test riuscito! 🎉</h1><p>Questa email prova che Render e Brevo sono configurati correttamente.</p>'
]);

// 4. Debug dell'invio
try {
    $result = $apiInstance->sendTransacEmail($email);
    echo "Email inviata a: " . $testEmail . "<br>";
    echo "ID Brevo: " . $result->getMessageId();
} catch (Exception $e) {
    echo "Errore Brevo: " . $e->getMessage() . "<br><br>";
    echo "Variabili usate:<br>";
    echo "SENDER_EMAIL: " . $senderEmail . "<br>";
    echo "TEST_EMAIL: " . $testEmail . "<br>";
    echo "BREVO_API_KEY: " . substr($brevoApiKey, 0, 5) . "..."; // Non mostrare tutta la chiave!
}
?>

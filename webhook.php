<?php
require __DIR__ . '/vendor/autoload.php';

use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;

// 1. Caricamento variabili d'ambiente
$config = [
    'api_key' => getenv('BREVO_API_KEY'),
    'sender' => getenv('SENDER_EMAIL'),
    'recipient' => getenv('TEST_EMAIL')
];

// 2. Verifica configurazione
if (empty($config['api_key']) || empty($config['sender']) || empty($config['recipient'])) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'error' => 'Configurazione mancante',
        'details' => [
            'BREVO_API_KEY' => empty($config['api_key']) ? 'Mancante' : 'Presente',
            'SENDER_EMAIL' => empty($config['sender']) ? 'Mancante' : $config['sender'],
            'TEST_EMAIL' => empty($config['recipient']) ? 'Mancante' : $config['recipient']
        ]
    ]));
}

// 3. Configurazione Brevo
$brevoConfig = Configuration::getDefaultConfiguration()
    ->setApiKey('api-key', $config['api_key']);

$apiInstance = new TransactionalEmailsApi(
    new GuzzleHttp\Client(['timeout' => 20]),
    $brevoConfig
);

// 4. Creazione email
$email = [
    'sender' => ['email' => $config['sender'], 'name' => 'Test Render'],
    'to' => [['email' => $config['recipient'], 'name' => 'Destinatario']],
    'subject' => 'Test funzionamento da Render - ' . date('d/m H:i'),
    'htmlContent' => '
        <h1>Test riuscito!</h1>
        <p>Questa email prova che:</p>
        <ul>
            <li>Il server Render.com è configurato correttamente</li>
            <li>L\'API Brevo è operativa</li>
            <li>Le variabili d\'ambiente sono caricate</li>
        </ul>
        <p>Data invio: '.date('d/m/Y H:i:s').'</p>
    ',
    'tags' => ['render_test']
];

// 5. Invio e output JSON
header('Content-Type: application/json');

try {
    $result = $apiInstance->sendTransacEmail(new \Brevo\Client\Model\SendSmtpEmail($email));
    
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('c'),
        'brevo_response' => [
            'message_id' => $result->getMessageId(),
            'accepted_at' => $result->getMessageId() ? date('c') : null
        ],
        'email_details' => [
            'from' => $config['sender'],
            'to' => $config['recipient'],
            'subject' => $email['subject']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'debug_info' => [
            'api_key_prefix' => substr($config['api_key'], 0, 10) . '...',
            'sender_status' => 'Verifica in https://app.brevo.com/settings/senders',
            'ip_restrictions' => 'Controlla in https://app.brevo.com/settings/keys/api'
        ]
    ]);
}
?>

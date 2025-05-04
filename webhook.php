<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Configurazione
$senderEmail = getenv('SENDER_EMAIL'); // dolcissimogianni@gmail.com
$testEmail = getenv('TEST_EMAIL'); // perdifumo72@libero.it
$smtpPassword = getenv('BREVO_SMTP_PASSWORD'); // xsmtp-... (creala in Brevo)

// 2. Istanza PHPMailer
$mail = new PHPMailer(true); // 'true' abilita le eccezioni

try {
    // Impostazioni SMTP Brevo
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->Port = 587; // Porta TLS
    $mail->SMTPAuth = true;
    $mail->Username = $senderEmail; // Deve essere verificato in Brevo
    $mail->Password = $smtpPassword; // Password SMTP (NON l'API key)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // Destinatari
    $mail->setFrom($senderEmail, 'Gianni');
    $mail->addAddress($testEmail, 'Destinatario Test');
    
    // Contenuto
    $mail->Subject = 'Test SMTP da Render.com ✅';
    $mail->Body = '<h1>Funziona!</h1><p>Questa email prova che lo SMTP Brevo è configurato correttamente.</p>';
    $mail->isHTML(true);

    // 3. Invio e debug
    $mail->send();
    echo "Email inviata con successo a: " . $testEmail;

} catch (Exception $e) {
    echo "<strong>Errore nell'invio:</strong><br>";
    echo $e->getMessage() . "<br><br>";
    
    echo "<strong>Controlla:</strong><br>";
    echo "1. La password SMTP (<code>xsmtp-...</code>) è corretta?<br>";
    echo "2. Il mittente (<code>$senderEmail</code>) è verificato in <a href='https://app.brevo.com/settings/senders' target='_blank'>Brevo > Senders</a>?<br>";
    echo "3. Render.com permette connessioni in uscita sulla porta 587?<br>";
    echo "4. Hai abilitato l'opzione <strong>SMTP</strong> in <a href='https://app.brevo.com/settings/keys/api' target='_blank'>Brevo > API Keys</a>?";
}
?>

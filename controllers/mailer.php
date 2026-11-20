
<?php
// Import PHPMailer classes

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Load mail configuration if present. Create mail_config.php returning array with keys:
// ['host','username','password','port','from_email','from_name','secure']
$configFile = __DIR__ . '/mail_config.php';
$config = [];
if (is_readable($configFile)) {
    $cfg = include $configFile;
    if (is_array($cfg)) $config = $cfg;
}

function send_email($email, $name, $message, $alt_message, $subject)
{
    global $config;
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = isset($config['host']) ? $config['host'] : 'smtp.gmail.com';
        $mail->Username = isset($config['username']) ? trim($config['username']) : 'bnhsenrollmentsystem@gmail.com';
        $mail->Password = isset($config['password']) ? trim($config['password']) : '';
        $secure = isset($config['secure']) ? $config['secure'] : 'tls';
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = isset($config['port']) ? (int)$config['port'] : 587;
        $mail->CharSet = 'UTF-8';

        // Helpful for local environments with self-signed certs
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Recipients
        $fromEmail = isset($config['from_email']) ? $config['from_email'] : $mail->Username;
        $fromName = isset($config['from_name']) ? $config['from_name'] : 'BNHS Enrollment System';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->addReplyTo($fromEmail, $fromName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = $alt_message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error instead of echoing to user
        $err = '[' . date('Y-m-d H:i:s') . '] Mailer Error: ' . $mail->ErrorInfo . " - Exception: " . $e->getMessage() . PHP_EOL;
        @file_put_contents(__DIR__ . '/mail_error.log', $err, FILE_APPEND | LOCK_EX);
        return false;
    }
}
/*
Test code

send_email(
    'daverhandon@gmail.com',
    'Sample',
    '
    <h3>Good day, Sample</h3>
    <p>Here is your OTP: <b>123456</b></p>
    <p>Thanks for using our service.</p>
    <p>EZTrack System</p>
    ',
    '
    Good day, Sample
    Here is your OTP: 123456
    Thanks for using our service.
    EZTrack System
    ',
    'EZTrack System OTP'
);
*/

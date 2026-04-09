<?php
require_once 'includes/config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress('destino@ejemplo.com'); // ← Cambia por tu correo personal
    $mail->Subject = 'Prueba SMTP';
    $mail->Body = 'Si ves esto, todo funciona correctamente.';

    $mail->send();
    echo "✅ Correo enviado correctamente.";
} catch (Exception $e) {
    echo "❌ Error: " . $mail->ErrorInfo;
}
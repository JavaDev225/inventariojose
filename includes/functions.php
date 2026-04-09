<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Cargar PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function redirigirSiLogueado() {
    if (isset($_SESSION['usuario_id'])) {
        header('Location: ' . SITE_URL . 'dashboard.php');
        exit;
    }
}

function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . SITE_URL . 'index.php');
        exit;
    }
}

function generarToken() {
    return bin2hex(random_bytes(32));
}

// Genera un código numérico de 6 dígitos
function generarCodigoRecuperacion() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Guarda el código y su expiración para un email
function guardarCodigoRecuperacion($email, $codigo, $expira) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE usuarios SET recovery_code = ?, recovery_code_expira = ? WHERE email = ?");
    return $stmt->execute([$codigo, $expira, $email]);
}

// Verifica que el código sea correcto y no haya expirado
function verificarCodigoRecuperacion($email, $codigo) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND recovery_code = ? AND recovery_code_expira > NOW()");
    $stmt->execute([$email, $codigo]);
    return $stmt->fetch() !== false;
}

// Limpia el código después de usarlo
function limpiarCodigoRecuperacion($email) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE usuarios SET recovery_code = NULL, recovery_code_expira = NULL WHERE email = ?");
    return $stmt->execute([$email]);
}

// Función real para enviar código por correo usando PHPMailer
function enviarCodigoRecuperacion($email, $codigo) {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Código de verificación - ' . SITE_NAME;
        $mail->Body    = "
            <h2>Recuperacion de contrasenia</h2>
            <p>Hemos recibido una solicitud para restablecer tu contrasenia.</p>
            <p>Tu código de verificacion es: <strong style='font-size: 1.2rem;'>$codigo</strong></p>
            <p>Este codigo expira en 2 minutos.</p>
            <p>Si no solicitaste esto, ignora este mensaje.</p>
        ";
        $mail->AltBody = "Tu código de verificación es: $codigo. Expira en 2 minutos.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Registrar error en el log del servidor
        error_log("Error al enviar correo a $email: " . $mail->ErrorInfo);
        return false;
    }
}

// NOTA: Las siguientes funciones (enviarCorreo, enviarCorreoRecuperacion, restablecerPassword) 
// ya no se usan en el nuevo flujo de código de 6 dígitos, pero las conservamos por si acaso.
// Puedes eliminarlas si no las necesitas.

function enviarCorreo($destinatario, $asunto, $mensaje) {
    // Deprecated: usar enviarCodigoRecuperacion
    return true;
}

function enviarCorreoRecuperacion($destinatario, $token) {
    // Deprecated: usar enviarCodigoRecuperacion
    return true;
}

function restablecerPassword($token, $nuevaPassword) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        return false;
    }
    $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
    return $stmt->execute([$hash, $usuario['id']]);
}
?>
<?php
require_once '../includes/functions.php';
redirigirSiLogueado();

if (!isset($_SESSION['email_recuperacion'])) {
    header('Location: recuperar.php');
    exit;
}
$email = $_SESSION['email_recuperacion'];

$error = '';

// Obtener expiración del código
$stmt = $pdo->prepare("SELECT recovery_code_expira FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$data = $stmt->fetch();
$expiracion = $data ? $data['recovery_code_expira'] : null;

if (!$expiracion || strtotime($expiracion) < time()) {
    unset($_SESSION['email_recuperacion']);
    header('Location: recuperar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');

    if (empty($codigo)) {
        $error = 'Por favor, ingresa el código.';
    } elseif (!preg_match('/^\d{6}$/', $codigo)) {
        $error = 'El código debe tener 6 dígitos numéricos.';
    } else {
        if (verificarCodigoRecuperacion($email, $codigo)) {
            $token = generarToken();
            $expiraToken = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ?, recovery_code = NULL, recovery_code_expira = NULL WHERE email = ?");
            if ($stmt->execute([$token, $expiraToken, $email])) {
                unset($_SESSION['email_recuperacion']);
                header('Location: restablecer.php?token=' . $token);
                exit;
            } else {
                $error = 'Error al procesar la solicitud. Intenta de nuevo.';
            }
        } else {
            $error = 'Código inválido o expirado.';
        }
    }
}

$segundosRestantes = max(0, strtotime($expiracion) - time());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verify-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
            width: 100%;
            max-width: 450px;
        }
        .verify-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .verify-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .verify-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .verify-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 30px;
            border: 1px solid #e0e0e0;
            padding: 0.75rem 1rem 0.75rem 2.8rem;
            text-align: center;
            letter-spacing: 2px;
            font-size: 1.2rem;
        }
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,0.25);
        }
        .input-group-icon {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-group-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1rem;
        }
        .btn-verify {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        .links a {
            color: #2a5298;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .alert-custom {
            border-radius: 30px;
            border: none;
            font-size: 0.9rem;
        }
        .timer {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2a5298;
            text-align: center;
            margin-bottom: 1rem;
        }
        .logo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: white;
        }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="verify-header">
        <div class="logo-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h3>📧 Verificar Código</h3>
        <p>Hemos enviado un código a tu correo</p>
    </div>
    <div class="verify-body">
        <div class="timer" id="timer">Cargando...</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="codeForm">
            <div class="input-group-icon">
                <i class="fas fa-key"></i>
                <input type="text" name="codigo" class="form-control" placeholder="Código de 6 dígitos" maxlength="6" pattern="\d{6}" required>
            </div>
            <button type="submit" class="btn btn-verify btn-primary w-100">
                <i class="fas fa-check-circle me-2"></i> Verificar
            </button>
        </form>
        <div class="links">
            <a href="recuperar.php"><i class="fas fa-redo-alt me-1"></i> Solicitar nuevo código</a>
        </div>
    </div>
</div>

<script>
    let segundos = <?= $segundosRestantes ?>;
    const timerElement = document.getElementById('timer');
    const form = document.getElementById('codeForm');
    const input = document.querySelector('input[name="codigo"]');

    function actualizarTimer() {
        if (segundos <= 0) {
            timerElement.innerHTML = '⏰ Tiempo expirado. Redirigiendo...';
            setTimeout(() => {
                window.location.href = 'recuperar.php';
            }, 2000);
            if (form) {
                input.disabled = true;
                document.querySelector('button[type="submit"]').disabled = true;
            }
        } else {
            const minutos = Math.floor(segundos / 60);
            const seg = segundos % 60;
            timerElement.innerHTML = `⏳ Tiempo restante: ${minutos}:${seg < 10 ? '0' : ''}${seg}`;
            segundos--;
            setTimeout(actualizarTimer, 1000);
        }
    }

    if (segundos > 0) {
        actualizarTimer();
    } else {
        timerElement.innerHTML = '⏰ Tiempo expirado. Redirigiendo...';
        setTimeout(() => {
            window.location.href = 'recuperar.php';
        }, 2000);
        if (form) {
            input.disabled = true;
            document.querySelector('button[type="submit"]').disabled = true;
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
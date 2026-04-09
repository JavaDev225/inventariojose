<?php
require_once '../includes/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Si no hay token, redirigir a recuperar
if (empty($token)) {
    header('Location: recuperar.php');
    exit;
}

// Verificar que el token existe y no ha expirado
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()");
$stmt->execute([$token]);
$usuario = $stmt->fetch();
if (!$usuario) {
    $error = 'El enlace de recuperación no es válido o ha expirado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm)) {
        $error = 'Debes ingresar una nueva contraseña.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        if (restablecerPassword($token, $password)) {
            $success = 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.';
            // Limpiar token de la URL para evitar reenvío
            $token = '';
        } else {
            $error = 'Error al actualizar la contraseña. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Sistema de Inventario</title>
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
        .reset-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
            width: 100%;
            max-width: 450px;
        }
        .reset-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .reset-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .reset-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .reset-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 30px;
            border: 1px solid #e0e0e0;
            padding: 0.75rem 1rem 0.75rem 2.8rem;
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
        .btn-reset {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-reset:hover {
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
        .logo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: white;
        }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="reset-header">
        <div class="logo-icon">
            <i class="fas fa-lock-open"></i>
        </div>
        <h3>🔄 Restablecer Contraseña</h3>
        <p>Ingresa tu nueva contraseña</p>
    </div>
    <div class="reset-body">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <a href="index.php" class="alert-link">Iniciar sesión</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$error && !$success): ?>
        <form method="POST">
            <div class="input-group-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Nueva contraseña (mínimo 6 caracteres)" required>
            </div>
            <div class="input-group-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirmar nueva contraseña" required>
            </div>
            <button type="submit" class="btn btn-reset btn-primary w-100">
                <i class="fas fa-save me-2"></i> Guardar nueva contraseña
            </button>
        </form>
        <?php endif; ?>

        <div class="links">
            <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../includes/functions.php';
redirigirSiLogueado();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Buscar usuario por nombre de usuario
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Verificar si está bloqueado
            $bloqueado = false;
            $mensajeBloqueo = '';
            if ($usuario['blocked_until'] && strtotime($usuario['blocked_until']) > time()) {
                $bloqueado = true;
                $restante = ceil((strtotime($usuario['blocked_until']) - time()) / 60);
                $mensajeBloqueo = "Cuenta bloqueada. Intenta nuevamente en $restante minutos.";
            } elseif ($usuario['blocked_until'] && strtotime($usuario['blocked_until']) <= time()) {
                // Si ya pasó el tiempo, reiniciar intentos y limpiar bloqueo
                $stmtReset = $pdo->prepare("UPDATE usuarios SET failed_attempts = 0, blocked_until = NULL WHERE id = ?");
                $stmtReset->execute([$usuario['id']]);
                $usuario['failed_attempts'] = 0;
                $usuario['blocked_until'] = null;
            }

            if ($bloqueado) {
                $error = $mensajeBloqueo;
            } else {
                // Verificar contraseña
                if (password_verify($password, $usuario['password'])) {
                    // Login exitoso: reiniciar contador de intentos
                    $stmtReset = $pdo->prepare("UPDATE usuarios SET failed_attempts = 0, blocked_until = NULL WHERE id = ?");
                    $stmtReset->execute([$usuario['id']]);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['username'];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Incrementar intentos fallidos
                    $nuevosIntentos = $usuario['failed_attempts'] + 1;
                    if ($nuevosIntentos >= 5) {
                        // Bloquear por 2 horas (7200 segundos)
                        $bloqueoHasta = date('Y-m-d H:i:s', strtotime('+2 hours'));
                        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET failed_attempts = ?, blocked_until = ? WHERE id = ?");
                        $stmtUpdate->execute([$nuevosIntentos, $bloqueoHasta, $usuario['id']]);
                        $error = 'Has superado los 5 intentos fallidos. Tu cuenta ha sido bloqueada por 2 horas.';
                    } else {
                        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET failed_attempts = ? WHERE id = ?");
                        $stmtUpdate->execute([$nuevosIntentos, $usuario['id']]);
                        $error = 'Usuario o contraseña incorrectos.';
                        // Opcional: mostrar intentos restantes
                        $restantes = 5 - $nuevosIntentos;
                        if ($restantes > 0) {
                            $error .= " Te quedan $restantes intentos.";
                        }
                    }
                }
            }
        } else {
            // Usuario no existe: igual incrementamos intentos? Para no dar pistas, se puede mostrar error genérico
            $error = 'Usuario o contraseña incorrectos.';
            // Opcional: llevar un registro global de intentos por IP (no lo hacemos aquí)
        }
    } else {
        $error = 'Completa todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Tus estilos actuales (no cambian) */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        .login-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .login-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .login-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .login-body {
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
            font-size: 1.1rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-login:hover {
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
            margin: 0 0.5rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .alert-custom {
            border-radius: 30px;
            border: none;
            background-color: #f8d7da;
            color: #721c24;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .logo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: white;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="logo-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Sistema de Inventario</h3>
        <p>Accede a tu panel de gestión</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="input-group-icon">
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Nombre de usuario" required>
            </div>
            <div class="input-group-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn btn-login btn-primary w-100">
                <i class="fas fa-sign-in-alt me-2"></i> Ingresar
            </button>
        </form>
        <div class="links">
            <a href="registro.php"><i class="fas fa-user-plus me-1"></i> ¿No tienes cuenta? Regístrate</a><br>
            <a href="recuperar.php"><i class="fas fa-key me-1"></i> ¿Olvidaste tu contraseña?</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../includes/functions.php';
redirigirSiLogueado();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Por favor, ingresa tu correo electrónico.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo electrónico no válido.';
    } else {
        // Verificar si el email existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            $error = 'No existe una cuenta con ese correo electrónico.';
        } else {
            // Generar código y guardarlo con expiración (2 minutos)
            $codigo = generarCodigoRecuperacion();
            $expira = date('Y-m-d H:i:s', strtotime('+2 minutes'));
            if (guardarCodigoRecuperacion($email, $codigo, $expira)) {
                if (enviarCodigoRecuperacion($email, $codigo)) {
                    $_SESSION['email_recuperacion'] = $email;
                    header('Location: verificar_codigo.php');
                    exit;
                } else {
                    $error = 'No se pudo enviar el correo. Intente de nuevo más tarde.';
                }
            } else {
                $error = 'Error al generar la solicitud. Intenta de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Sistema de Inventario</title>
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
        .recover-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
            width: 100%;
            max-width: 450px;
        }
        .recover-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .recover-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .recover-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .recover-body {
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
        .btn-recover {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-recover:hover {
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

<div class="recover-card">
    <div class="recover-header">
        <div class="logo-icon">
            <i class="fas fa-key"></i>
        </div>
        <h3>Recuperar Contraseña</h3>
        <p>Ingresa tu correo y te enviaremos un código</p>
    </div>
    <div class="recover-body">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="input-group-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
            </div>
            <button type="submit" class="btn btn-recover btn-primary w-100">
                <i class="fas fa-paper-plane me-2"></i> Enviar código
            </button>
        </form>
        <div class="links">
            <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../includes/functions.php';
redirigirSiLogueado();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $pregunta = trim($_POST['pregunta_seguridad'] ?? '');
    $respuesta = trim($_POST['respuesta_seguridad'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Todos los campos obligatorios deben ser llenados.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo electrónico no válido.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'El nombre de usuario o correo ya está registrado.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $hashedRespuesta = !empty($respuesta) ? password_hash($respuesta, PASSWORD_DEFAULT) : null;

            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, email, telefono, pregunta_seguridad, respuesta_seguridad) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $hashedPassword, $email, $telefono, $pregunta, $hashedRespuesta])) {
                $success = 'Cuenta creada exitosamente. Ahora puedes iniciar sesión.';
                // Limpiar campos opcionales
                $_POST = [];
            } else {
                $error = 'Error al registrar. Intenta de nuevo.';
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
    <title>Registro - Sistema de Inventario</title>
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
        .register-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
            width: 100%;
            max-width: 550px;
        }
        .register-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .register-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .register-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control, .form-select {
            border-radius: 30px;
            border: 1px solid #e0e0e0;
            padding: 0.75rem 1rem 0.75rem 2.8rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,0.25);
        }
        .input-group-icon {
            position: relative;
            margin-bottom: 1.2rem;
        }
        .input-group-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1rem;
        }
        .btn-register {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-register:hover {
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
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: white;
        }
        .row-custom {
            margin: 0 -0.5rem;
        }
        .col-custom {
            padding: 0 0.5rem;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="register-header">
        <div class="logo-icon">
            <i class="fas fa-user-plus"></i>
        </div>
        <h3>Crear Cuenta Nueva</h3>
        <p>Completa todos los campos para registrarte</p>
    </div>
    <div class="register-body">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <a href="index.php" class="alert-link">Inicia sesión aquí</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row row-custom">
                <div class="col-md-6 col-custom">
                    <div class="input-group-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-control" placeholder="Nombre de usuario *" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6 col-custom">
                    <div class="input-group-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Correo electrónico *" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row row-custom">
                <div class="col-md-6 col-custom">
                    <div class="input-group-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Contraseña * (mínimo 6 caracteres)" required>
                    </div>
                </div>
                <div class="col-md-6 col-custom">
                    <div class="input-group-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirmar contraseña *" required>
                    </div>
                </div>
            </div>

            <div class="input-group-icon">
                <i class="fas fa-phone"></i>
                <input type="text" name="telefono" class="form-control" placeholder="Teléfono (opcional)" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
            </div>

            <div class="input-group-icon">
                <i class="fas fa-question-circle"></i>
                <select name="pregunta_seguridad" class="form-select">
                    <option value="">Selecciona una pregunta de seguridad</option>
                    <option value="¿Cuál es el nombre de tu primera mascota?" <?= (isset($_POST['pregunta_seguridad']) && $_POST['pregunta_seguridad'] == '¿Cuál es el nombre de tu primera mascota?') ? 'selected' : '' ?>>¿Cuál es el nombre de tu primera mascota?</option>
                    <option value="¿En qué ciudad naciste?" <?= (isset($_POST['pregunta_seguridad']) && $_POST['pregunta_seguridad'] == '¿En qué ciudad naciste?') ? 'selected' : '' ?>>¿En qué ciudad naciste?</option>
                    <option value="¿Cuál es el nombre de tu madre?" <?= (isset($_POST['pregunta_seguridad']) && $_POST['pregunta_seguridad'] == '¿Cuál es el nombre de tu madre?') ? 'selected' : '' ?>>¿Cuál es el nombre de tu madre?</option>
                    <option value="¿Cuál es tu comida favorita?" <?= (isset($_POST['pregunta_seguridad']) && $_POST['pregunta_seguridad'] == '¿Cuál es tu comida favorita?') ? 'selected' : '' ?>>¿Cuál es tu comida favorita?</option>
                </select>
            </div>

            <div class="input-group-icon">
                <i class="fas fa-shield-alt"></i>
                <input type="text" name="respuesta_seguridad" class="form-control" placeholder="Respuesta de seguridad (opcional)" value="<?= htmlspecialchars($_POST['respuesta_seguridad'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-register btn-primary w-100 mt-2">
                <i class="fas fa-user-check me-2"></i> Crear Cuenta
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
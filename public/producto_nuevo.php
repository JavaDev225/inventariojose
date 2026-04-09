<?php
require_once '../includes/functions.php';
verificarSesion();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $precio = (float) ($_POST['precio'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');

    if (empty($nombre) || empty($categoria) || $precio <= 0) {
        $error = 'Nombre, categoría y precio son obligatorios y deben ser válidos.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, cantidad, precio, categoria) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nombre, $descripcion, $cantidad, $precio, $categoria])) {
            $success = 'Producto agregado exitosamente.';
            $_POST = [];
        } else {
            $error = 'Error al guardar el producto.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .form-card-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .form-card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .form-card-body {
            padding: 2rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42,82,152,0.25);
        }
        .btn-custom {
            border-radius: 30px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
        }
        .input-group-icon {
            position: relative;
        }
        .input-group-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .input-group-icon input, .input-group-icon textarea, .input-group-icon select {
            padding-left: 40px;
        }
        .alert-custom {
            border-radius: 12px;
            border: none;
            background-color: #e9ecef;
            color: #1e3c72;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card form-card">
                <div class="form-card-header">
                    <h4><i class="fas fa-box-open me-2"></i> Agregar Nuevo Producto</h4>
                </div>
                <div class="form-card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                            <a href="dashboard.php" class="alert-link">Ir al dashboard</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3 input-group-icon">
                            <i class="fas fa-tag"></i>
                            <input type="text" name="nombre" class="form-control" placeholder="Nombre del producto *" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                        </div>

                        <div class="mb-3 input-group-icon">
                            <i class="fas fa-align-left"></i>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Descripción (opcional)"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 input-group-icon">
                                <i class="fas fa-boxes"></i>
                                <input type="number" name="cantidad" class="form-control" placeholder="Cantidad" value="<?= htmlspecialchars($_POST['cantidad'] ?? 0) ?>" min="0">
                            </div>
                            <div class="col-md-6 mb-3 input-group-icon">
                                <i class="fas fa-dollar-sign"></i>
                                <input type="number" step="0.01" name="precio" class="form-control" placeholder="Precio *" required value="<?= htmlspecialchars($_POST['precio'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4 input-group-icon">
                            <i class="fas fa-tags"></i>
                            <input type="text" name="categoria" class="form-control" placeholder="Categoría *" required value="<?= htmlspecialchars($_POST['categoria'] ?? '') ?>">
                        </div>

                        <div class="d-flex justify-content-between gap-2">
                            <button type="submit" class="btn btn-primary btn-custom flex-grow-1">
                                <i class="fas fa-save me-2"></i> Guardar Producto
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-custom">
                                <i class="fas fa-arrow-left me-2"></i> Regresar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
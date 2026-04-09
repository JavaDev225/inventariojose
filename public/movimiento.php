<?php
require_once '../includes/functions.php';
verificarSesion();

$error = '';
$success = '';
$producto = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Cargar datos del producto
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch();
if (!$producto) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');

    if (!in_array($tipo, ['entrada', 'salida'])) {
        $error = 'Tipo de movimiento no válido.';
    } elseif ($cantidad <= 0) {
        $error = 'La cantidad debe ser mayor a cero.';
    } elseif ($tipo === 'salida' && $producto['cantidad'] < $cantidad) {
        $error = 'Stock insuficiente para realizar la salida.';
    } else {
        // Iniciar transacción
        $pdo->beginTransaction();
        try {
            // Actualizar stock del producto
            if ($tipo === 'entrada') {
                $nuevaCantidad = $producto['cantidad'] + $cantidad;
            } else {
                $nuevaCantidad = $producto['cantidad'] - $cantidad;
            }
            $stmt = $pdo->prepare("UPDATE productos SET cantidad = ? WHERE id = ?");
            $stmt->execute([$nuevaCantidad, $id]);

            // Registrar movimiento
            $stmt = $pdo->prepare("INSERT INTO movimientos (producto_id, tipo, cantidad, motivo, fecha) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$id, $tipo, $cantidad, $motivo]);

            $pdo->commit();
            $success = 'Movimiento registrado exitosamente. Stock actualizado.';
            // Recargar datos del producto
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            $producto = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al registrar el movimiento: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Movimiento - Sistema de Inventario</title>
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
        .product-info {
            background-color: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card form-card">
                <div class="form-card-header">
                    <h4><i class="fas fa-exchange-alt me-2"></i> Registrar Movimiento</h4>
                </div>
                <div class="form-card-body">
                    <div class="product-info">
                        <h5><i class="fas fa-box"></i> <?= htmlspecialchars($producto['nombre']) ?></h5>
                        <p class="mb-1"><strong>Stock actual:</strong> <?= $producto['cantidad'] ?> unidades</p>
                        <p class="mb-0"><strong>Categoría:</strong> <?= htmlspecialchars($producto['categoria']) ?> | <strong>Precio:</strong> Bs <?= number_format($producto['precio'], 2) ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                            <a href="dashboard.php" class="alert-link">Volver al dashboard</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Tipo de movimiento *</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" value="entrada" id="entrada" required>
                                    <label class="form-check-label" for="entrada">
                                        <i class="fas fa-arrow-down text-success"></i> Entrada (Compra)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" value="salida" id="salida" required>
                                    <label class="form-check-label" for="salida">
                                        <i class="fas fa-arrow-up text-danger"></i> Salida (Venta)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 input-group-icon">
                            <i class="fas fa-calculator"></i>
                            <input type="number" name="cantidad" class="form-control" placeholder="Cantidad *" required min="1">
                        </div>

                        <div class="mb-4 input-group-icon">
                            <i class="fas fa-comment"></i>
                            <input type="text" name="motivo" class="form-control" placeholder="Motivo (opcional, ej: compra proveedor, venta cliente)">
                        </div>

                        <div class="d-flex justify-content-between gap-2">
                            <button type="submit" class="btn btn-primary btn-custom flex-grow-1">
                                <i class="fas fa-save me-2"></i> Registrar Movimiento
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-custom">
                                <i class="fas fa-arrow-left me-2"></i> Cancelar
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
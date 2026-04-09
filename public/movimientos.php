<?php
require_once '../includes/functions.php';
verificarSesion();

// Obtener lista de ventas (con nombre de usuario)
$stmt = $pdo->prepare("
    SELECT v.*, u.username 
    FROM ventas v 
    JOIN usuarios u ON v.usuario_id = u.id 
    ORDER BY v.fecha DESC
");
$stmt->execute();
$ventas = $stmt->fetchAll();

// Obtener detalles de cada venta (lo haremos en la vista con consultas por separado o en un loop)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link, .navbar-custom .navbar-text {
            color: white !important;
        }
        .card-shadow {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .table-custom {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .table-custom thead th {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            font-weight: 500;
            border: none;
        }
        .btn-details {
            background-color: #17a2b8;
            color: white;
        }
        .btn-details:hover {
            background-color: #138496;
        }
        .detail-row {
            background-color: #f8f9fa;
        }
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .accordion-button:focus {
            box-shadow: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-boxes me-2"></i>Inventario General
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                
                
                
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-history me-2"></i>Historial de Ventas</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
        </a>
    </div>

    <?php if (empty($ventas)): ?>
        <div class="alert alert-info text-center">No hay ventas registradas aún.</div>
    <?php else: ?>
        <div class="accordion" id="accordionVentas">
            <?php foreach ($ventas as $index => $venta): ?>
                <div class="accordion-item mb-3 border-0 shadow-sm">
                    <h2 class="accordion-header" id="heading<?= $index ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false" aria-controls="collapse<?= $index ?>">
                            <div class="d-flex justify-content-between w-100 me-3">
                                <span><i class="fas fa-tag me-2"></i> <?= htmlspecialchars($venta['identificador']) ?></span>
                                <span><i class="fas fa-user me-2"></i> <?= htmlspecialchars($venta['username']) ?></span>
                                <span><i class="far fa-calendar-alt me-2"></i> <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></span>
                                <span><i class="fas fa-dollar-sign me-1"></i> <?= number_format($venta['total_usd'], 2) ?> USD / Bs <?= number_format($venta['total_bs'], 2) ?></span>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#accordionVentas">
                        <div class="accordion-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario (USD)</th>
                                            <th>Subtotal (USD)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Obtener detalles de esta venta
                                        $stmtDet = $pdo->prepare("
                                            SELECT d.*, p.nombre 
                                            FROM venta_detalles d 
                                            JOIN productos p ON d.producto_id = p.id 
                                            WHERE d.venta_id = ?
                                        ");
                                        $stmtDet->execute([$venta['id']]);
                                        $detalles = $stmtDet->fetchAll();
                                        foreach ($detalles as $det):
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($det['nombre']) ?></td>
                                            <td><?= $det['cantidad'] ?></td>
                                            <td>$ <?= number_format($det['precio_unitario_usd'], 2) ?></td>
                                            <td>$ <?= number_format($det['subtotal_usd'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <td colspan="3" class="text-end fw-bold">Total USD:</td>
                                            <td><strong>$ <?= number_format($venta['total_usd'], 2) ?></strong></td>
                                        </tr>
                                        <tr class="table-active">
                                            <td colspan="3" class="text-end fw-bold">Total Bs:</td>
                                            <td><strong>Bs <?= number_format($venta['total_bs'], 2) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../includes/functions.php';
verificarSesion();

// Inicializar carrito en sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

$error = '';
$success = '';

// Obtener tasa del dólar
$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
$stmt->execute();
$tasaDolar = $stmt->fetchColumn();
if ($tasaDolar === false) {
    $tasaDolar = '0.00';
}
$tasaDolar = (float)$tasaDolar;

// Procesar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $id = (int)($_POST['producto_id'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 1);
        if ($id > 0 && $cantidad > 0) {
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND cantidad >= ?");
            $stmt->execute([$id, $cantidad]);
            $producto = $stmt->fetch();
            if ($producto) {
                if (isset($_SESSION['carrito'][$id])) {
                    $nuevaCantidad = $_SESSION['carrito'][$id]['cantidad'] + $cantidad;
                    if ($nuevaCantidad <= $producto['cantidad']) {
                        $_SESSION['carrito'][$id]['cantidad'] = $nuevaCantidad;
                        $success = 'Producto actualizado en el carrito.';
                    } else {
                        $error = 'No hay suficiente stock para la cantidad solicitada.';
                    }
                } else {
                    $_SESSION['carrito'][$id] = [
                        'nombre' => $producto['nombre'],
                        'precio' => $producto['precio'],
                        'cantidad' => $cantidad
                    ];
                    $success = 'Producto agregado al carrito.';
                }
            } else {
                $error = 'Producto no encontrado o stock insuficiente.';
            }
        } else {
            $error = 'Datos de producto inválidos.';
        }
    } elseif ($accion === 'actualizar') {
        $id = (int)($_POST['producto_id'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        if ($id > 0 && isset($_SESSION['carrito'][$id])) {
            if ($cantidad <= 0) {
                unset($_SESSION['carrito'][$id]);
                $success = 'Producto eliminado del carrito.';
            } else {
                $stmt = $pdo->prepare("SELECT cantidad FROM productos WHERE id = ?");
                $stmt->execute([$id]);
                $stock = $stmt->fetchColumn();
                if ($cantidad <= $stock) {
                    $_SESSION['carrito'][$id]['cantidad'] = $cantidad;
                    $success = 'Cantidad actualizada.';
                } else {
                    $error = 'No hay suficiente stock. Máximo disponible: ' . $stock;
                }
            }
        } else {
            $error = 'Producto no encontrado en el carrito.';
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['producto_id'] ?? 0);
        if (isset($_SESSION['carrito'][$id])) {
            unset($_SESSION['carrito'][$id]);
            $success = 'Producto eliminado del carrito.';
        } else {
            $error = 'Producto no encontrado.';
        }
    } elseif ($accion === 'confirmar_compra') {
        if (empty($_SESSION['carrito'])) {
            $error = 'El carrito está vacío.';
        } else {
            $pdo->beginTransaction();
            try {
                $totalUSD = 0;
                $items = [];

                foreach ($_SESSION['carrito'] as $id => $item) {
                    // Verificar stock con bloqueo
                    $stmt = $pdo->prepare("SELECT cantidad FROM productos WHERE id = ? FOR UPDATE");
                    $stmt->execute([$id]);
                    $stockActual = $stmt->fetchColumn();
                    if ($item['cantidad'] > $stockActual) {
                        throw new Exception("Stock insuficiente para {$item['nombre']}. Disponible: $stockActual");
                    }
                    $nuevoStock = $stockActual - $item['cantidad'];
                    $stmt = $pdo->prepare("UPDATE productos SET cantidad = ? WHERE id = ?");
                    $stmt->execute([$nuevoStock, $id]);

                    // Registrar movimiento en la tabla movimientos (opcional)
                    $stmt = $pdo->prepare("INSERT INTO movimientos (producto_id, tipo, cantidad, motivo, fecha) VALUES (?, 'salida', ?, 'venta', NOW())");
                    $stmt->execute([$id, $item['cantidad']]);

                    // Calcular subtotal
                    $subtotal = $item['precio'] * $item['cantidad'];
                    $totalUSD += $subtotal;
                    $items[] = [
                        'producto_id' => $id,
                        'cantidad' => $item['cantidad'],
                        'precio' => $item['precio'],
                        'subtotal' => $subtotal
                    ];
                }

                $totalBS = $totalUSD * $tasaDolar;
                $identificador = 'VENTA-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
                $usuario_id = $_SESSION['usuario_id'];

                // Insertar cabecera de venta
                $stmt = $pdo->prepare("INSERT INTO ventas (identificador, usuario_id, fecha, total_usd, total_bs) VALUES (?, ?, NOW(), ?, ?)");
                $stmt->execute([$identificador, $usuario_id, $totalUSD, $totalBS]);
                $venta_id = $pdo->lastInsertId();

                // Insertar detalles de venta
                $stmtDetalle = $pdo->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario_usd, subtotal_usd) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $det) {
                    $stmtDetalle->execute([$venta_id, $det['producto_id'], $det['cantidad'], $det['precio'], $det['subtotal']]);
                }

                $pdo->commit();
                $_SESSION['carrito'] = [];
                $_SESSION['mensaje'] = "Compra realizada con éxito. ID: $identificador";
                header('Location: dashboard.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error al procesar la compra: ' . $e->getMessage();
            }
        }
    }
}

// Obtener letra de filtro
$letra = isset($_GET['letra']) && preg_match('/^[A-Z]$/', $_GET['letra']) ? $_GET['letra'] : '';

// Consulta de productos con stock > 0
$sql = "SELECT * FROM productos WHERE cantidad > 0";
$params = [];
if (!empty($letra)) {
    $sql .= " AND nombre LIKE ?";
    $params[] = $letra . '%';
}
$sql .= " ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productosDisponibles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - Sistema de Inventario</title>
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
        .gradient-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            transition: all 0.2s;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            border: none;
            color: white;
        }
        .btn-back:hover {
            background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
            transform: translateY(-2px);
        }
        .card-shadow {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .card-shadow:hover {
            transform: translateY(-3px);
        }
        .producto-card {
            border: none;
            border-radius: 15px;
            background: white;
            transition: all 0.2s;
            height: 100%;
        }
        .producto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .table-cart {
            border-radius: 12px;
            overflow: hidden;
        }
        .table-cart thead th {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            font-weight: 500;
            border: none;
        }
        .table-cart tbody tr:hover {
            background-color: #f8f9fa;
        }
        .total-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2a5298;
        }
        .total-bs {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        .quantity-input {
            width: 80px;
            text-align: center;
        }
        .badge-cart-count {
            background-color: #ffc107;
            color: #212529;
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
            border-radius: 30px;
            margin-left: 8px;
        }
        .header-section {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e0e0e0;
        }
        .product-grid {
            max-height: 65vh;
            overflow-y: auto;
            padding-right: 5px;
        }
        .letter-filter {
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }
        .letter-btn {
            min-width: 45px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            color: #495057;
            transition: all 0.2s;
        }
        .letter-btn.active {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-color: transparent;
        }
        .letter-btn:hover:not(.active) {
            background: #dee2e6;
        }
        .letter-btn-all {
            background: #6c757d;
            color: white;
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
                    <span class="navbar-text me-3">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?>
                    </span>
                </li>
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
    <div class="d-flex justify-content-between align-items-center header-section">
        <div>
            <h2><i class="fas fa-receipt me-2"></i>Facturación</h2>
            <small class="text-muted">Tasa del día: <strong>$1 = Bs <?= number_format($tasaDolar, 2) ?></strong></small>
        </div>
        <a href="dashboard.php" class="btn btn-back">
            <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
        </a>
    </div>

    <div class="row g-4">
        <!-- Columna izquierda: Productos disponibles con filtro alfabético -->
        <div class="col-lg-6">
            <div class="card card-shadow">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Productos en Stock</h5>
                </div>
                <div class="card-body">
                    <div class="letter-filter">
                        <a href="facturacion.php" class="btn btn-sm letter-btn letter-btn-all <?= empty($letra) ? 'active' : '' ?>">
                            Todos
                        </a>
                        <?php for ($i = 65; $i <= 90; $i++): $chr = chr($i); ?>
                            <a href="facturacion.php?letra=<?= $chr ?>" class="btn btn-sm letter-btn <?= ($letra === $chr) ? 'active' : '' ?>">
                                <?= $chr ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <div class="product-grid">
                        <?php if (empty($productosDisponibles)): ?>
                            <div class="alert alert-info text-center">
                                <?= !empty($letra) ? "No hay productos que comiencen con la letra '$letra'." : "No hay productos con stock disponible en este momento." ?>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($productosDisponibles as $prod): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card producto-card h-100" data-id="<?= $prod['id'] ?>" data-nombre="<?= htmlspecialchars($prod['nombre']) ?>" data-precio="<?= $prod['precio'] ?>" data-stock="<?= $prod['cantidad'] ?>">
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                                <p class="card-text small text-muted"><?= htmlspecialchars($prod['categoria']) ?></p>
                                                <p class="card-text mt-2">Stock: <?= $prod['cantidad'] ?> | Precio: $ <?= number_format($prod['precio'], 2) ?></p>
                                                <div class="d-flex gap-2 mt-auto">
                                                    <input type="number" class="form-control form-control-sm quantity-select" value="1" min="1" max="<?= $prod['cantidad'] ?>" style="width: 70px;">
                                                    <button class="btn btn-sm btn-gradient btn-agregar" data-id="<?= $prod['id'] ?>" data-nombre="<?= htmlspecialchars($prod['nombre']) ?>" data-precio="<?= $prod['precio'] ?>">Agregar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Carrito de compras -->
        <div class="col-lg-6">
            <div class="card card-shadow">
                <div class="card-header gradient-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Carrito de Compras</h5>
                    <?php if (!empty($_SESSION['carrito'])): ?>
                        <span class="badge-cart-count">
                            <i class="fas fa-box"></i> <?= array_sum(array_column($_SESSION['carrito'], 'cantidad')) ?> productos
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <?php if (empty($_SESSION['carrito'])): ?>
                        <p class="text-center text-muted py-4">El carrito está vacío. Agrega productos desde la izquierda.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-cart table-sm align-middle">
                                <thead>
                                
                                        <th>Producto</th>
                                        <th>Precio</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $totalUSD = 0; ?>
                                    <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                                        <?php $subtotal = $item['precio'] * $item['cantidad']; $totalUSD += $subtotal; ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                                            <td>$ <?= number_format($item['precio'], 2) ?></td>
                                            <td>
                                                <form method="POST" class="d-flex gap-1">
                                                    <input type="hidden" name="accion" value="actualizar">
                                                    <input type="hidden" name="producto_id" value="<?= $id ?>">
                                                    <input type="number" name="cantidad" class="form-control form-control-sm quantity-input" value="<?= $item['cantidad'] ?>" min="1" step="1">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i></button>
                                                </form>
                                            </td>
                                            <td>$ <?= number_format($subtotal, 2) ?></td>
                                            <td>
                                                <form method="POST">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="producto_id" value="<?= $id ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <td colspan="3" class="text-end fw-bold">Total (USD):</td>
                                        <td colspan="2" class="total-amount">$ <?= number_format($totalUSD, 2) ?></td>
                                    </tr>
                                    <?php if ($tasaDolar > 0): ?>
                                    <tr class="table-active">
                                        <td colspan="3" class="text-end fw-bold">Total (VES):</td>
                                        <td colspan="2" class="total-bs">Bs <?= number_format($totalUSD * $tasaDolar, 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="accion" value="confirmar_compra">
                            <button type="submit" class="btn btn-gradient w-100 btn-lg" onclick="return confirm('¿Confirmar la compra? Se descontará el stock.')">
                                <i class="fas fa-check-circle me-2"></i> Confirmar Compra
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.btn-agregar').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const id = this.dataset.id;
            const cantidad = this.closest('.producto-card').querySelector('.quantity-select').value;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.style.display = 'none';

            const inputAccion = document.createElement('input');
            inputAccion.name = 'accion';
            inputAccion.value = 'agregar';
            form.appendChild(inputAccion);

            const inputId = document.createElement('input');
            inputId.name = 'producto_id';
            inputId.value = id;
            form.appendChild(inputId);

            const inputCant = document.createElement('input');
            inputCant.name = 'cantidad';
            inputCant.value = cantidad;
            form.appendChild(inputCant);

            document.body.appendChild(form);
            form.submit();
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
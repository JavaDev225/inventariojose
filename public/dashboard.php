<?php
require_once '../includes/functions.php';
verificarSesion();

// Mostrar mensajes de éxito o error
if (isset($_SESSION['mensaje'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> ' . htmlspecialchars($_SESSION['mensaje']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['mensaje_error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> ' . htmlspecialchars($_SESSION['mensaje_error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['mensaje_error']);
}

// Estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
$totalProductos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(cantidad * precio) as total_valor FROM productos");
$valorTotal = $stmt->fetch()['total_valor'];
$valorTotal = $valorTotal ? number_format($valorTotal, 2) : '0.00';

$stmt = $pdo->query("SELECT COUNT(*) as bajo FROM productos WHERE cantidad < 5");
$stockBajo = $stmt->fetch()['bajo'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT categoria) as categorias FROM productos");
$totalCategorias = $stmt->fetch()['categorias'];

// Tasa del dólar
$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'");
$stmt->execute();
$tasaDolar = $stmt->fetchColumn();
if ($tasaDolar === false) {
    $tasaDolar = '0.00';
}

// Procesar actualización de tasa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_tasa') {
    $nuevaTasa = floatval($_POST['tasa'] ?? 0);
    if ($nuevaTasa > 0) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_dolar'");
        if ($stmt->execute([$nuevaTasa])) {
            $_SESSION['mensaje'] = 'Tasa actualizada correctamente.';
        } else {
            $_SESSION['mensaje_error'] = 'Error al actualizar la tasa.';
        }
    } else {
        $_SESSION['mensaje_error'] = 'Ingrese un valor válido mayor que cero.';
    }
    header('Location: dashboard.php');
    exit;
}

// Lista de productos
$stmt = $pdo->query("SELECT * FROM productos ORDER BY id DESC");
$productos = $stmt->fetchAll();

// Cantidad de productos en el carrito (para badge)
$carritoTotal = 0;
if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $carritoTotal += $item['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', 'Poppins', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 10% 20%, rgba(255,255,255,0.15) 1.5px, transparent 1.5px);
            background-size: 25px 25px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            backdrop-filter: blur(5px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .navbar-custom .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 1.6rem;
            background: linear-gradient(120deg, #fff, #ffd89b);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }

        .stat-card {
            border-radius: 24px;
            border: none;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            font-size: 2.8rem;
            opacity: 0.8;
        }

        .tasa-card {
            background: linear-gradient(145deg, #ffd89b, #ffb347);
            border: none;
        }

        .btn-facturar {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            transition: all 0.2s;
        }

        .btn-facturar:hover {
            transform: scale(1.02);
            filter: brightness(1.05);
        }

        .table-custom {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 24px;
            overflow: hidden;
            backdrop-filter: blur(5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .table-custom thead th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px 12px;
        }

        .table-custom tbody td {
            vertical-align: middle;
            padding: 12px;
        }

        .btn-action {
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
            border-radius: 30px;
            margin: 0 2px;
        }

        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-movimientos, .btn-warning {
            border-radius: 30px;
            font-weight: 500;
            padding: 0.4rem 1rem;
        }

        .search-box {
            background: rgba(255,255,255,0.85);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            border: none;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
        }

        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            width: 200px;
        }

        .badge-cart {
            font-size: 0.85rem;
            background: #ffc107;
            color: #212529;
            padding: 0.4rem 0.9rem;
            border-radius: 40px;
            font-weight: 600;
        }

        .catalogo-title {
            background: rgba(0,0,0,0.25);
            backdrop-filter: blur(4px);
            padding: 8px 16px;
            border-radius: 50px;
            display: inline-block;
            font-weight: 700;
            color: white;
            margin-bottom: 0;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .btn-new-product {
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
        }

        @media (max-width: 768px) {
            .stat-card .stat-icon {
                font-size: 2rem;
            }
            .search-box input {
                width: 140px;
            }
            .header-actions {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            .header-actions .d-flex {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            🚀 InventarioPro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item me-2">
                    <a href="movimientos.php" class="btn btn-outline-light btn-sm rounded-pill">
                        <i class="fas fa-history me-1"></i> Movimientos
                    </a>
                </li>
                <li class="nav-item me-2">
                    <a href="../cerrar_turno.php" class="btn btn-warning btn-sm rounded-pill">
                        <i class="fas fa-file-pdf me-1"></i> Cerrar turno
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill">
                        <i class="fas fa-sign-out-alt me-1"></i> Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- Tarjetas de estadísticas -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-uppercase small fw-bold">Productos</h6>
                            <h2 class="mb-0 fw-bold"><?= $totalProductos ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-cubes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-uppercase small fw-bold">Valor inventario</h6>
                            <h2 class="mb-0 fw-bold">$ <?= $valorTotal ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-uppercase small fw-bold">Stock bajo</h6>
                            <h2 class="mb-0 fw-bold"><?= $stockBajo ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-uppercase small fw-bold">Categorías</h6>
                            <h2 class="mb-0 fw-bold"><?= $totalCategorias ?></h2>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de tasa y facturación en una fila de dos columnas -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card stat-card tasa-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1"><i class="fas fa-dollar-sign me-2"></i>Tasa del Dólar (USD)</h6>
                        <h3 id="tasa-valor" class="mb-0 fw-bold">$ <?= number_format($tasaDolar, 2) ?></h3>
                        <small>Actualización: <?= date('d/m/Y H:i') ?></small>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#modalTasa">
                            <i class="fas fa-sync-alt me-1"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <a href="facturacion.php" class="card stat-card btn-facturar text-white text-decoration-none">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-receipt me-2"></i>Facturación</h5>
                        <p class="small mt-2 mb-0">Registra ventas y gestiona el carrito</p>
                    </div>
                    <div>
                        <?php if ($carritoTotal > 0): ?>
                            <span class="badge-cart">
                                <i class="fas fa-shopping-cart me-1"></i> <?= $carritoTotal ?> producto(s)
                            </span>
                        <?php else: ?>
                            <span class="badge-cart bg-secondary text-white">
                                <i class="fas fa-shopping-cart me-1"></i> Vacío
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Lista de productos con título y buscador alineados -->
    <div class="header-actions">
        <h3 class="catalogo-title">
            <i class="fas fa-cubes me-2"></i> Catálogo de Productos
        </h3>
        <div class="d-flex gap-3">
            <div class="search-box">
                <i class="fas fa-search me-2 text-secondary"></i>
                <input type="text" id="buscarProducto" placeholder="Buscar producto..." onkeyup="filtrarProductos()">
            </div>
            <a href="producto_nuevo.php" class="btn btn-primary btn-new-product">
                <i class="fas fa-plus me-1"></i> Nuevo Producto
            </a>
        </div>
    </div>

    <?php if (empty($productos)): ?>
        <div class="alert alert-info text-center">No hay productos registrados. ¡Agrega el primero!</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle" id="tablaProductos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio ($)</th>
                        <th>Categoría</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($p['descripcion']) ?></td>
                        <td>
                            <?= $p['cantidad'] ?>
                            <?php if ($p['cantidad'] < 5): ?>
                                <span class="badge-low-stock ms-2">Bajo stock</span>
                            <?php endif; ?>
                        </td>
                        <td>$ <?= number_format($p['precio'], 2) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($p['categoria']) ?></span></td>
                        <td>
                            <a href="producto_editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-action btn-edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="producto_eliminar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-action btn-delete" title="Eliminar" onclick="return confirm('¿Eliminar este producto?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para actualizar tasa -->
<div class="modal fade" id="modalTasa" tabindex="-1" aria-labelledby="modalTasaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header gradient-header text-white">
                <h5 class="modal-title" id="modalTasaLabel"><i class="fas fa-dollar-sign me-2"></i>Actualizar Tasa del Dólar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="actualizar_tasa">
                    <div class="mb-3">
                        <label for="tasa" class="form-label">Nueva tasa (USD)</label>
                        <input type="number" step="0.01" class="form-control" id="tasa" name="tasa" placeholder="Ejemplo: 42.50" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Filtro de productos en tiempo real
    function filtrarProductos() {
        let input = document.getElementById('buscarProducto');
        let filter = input.value.toLowerCase();
        let table = document.getElementById('tablaProductos');
        let rows = table.getElementsByTagName('tr');

        for (let i = 1; i < rows.length; i++) {
            let cells = rows[i].getElementsByTagName('td');
            if (cells.length > 0) {
                let nombre = cells[1].innerText.toLowerCase();
                let categoria = cells[5].innerText.toLowerCase();
                if (nombre.includes(filter) || categoria.includes(filter)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once '../includes/functions.php';
verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Verificar que el producto existe
$stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    header('Location: dashboard.php');
    exit;
}

// Eliminar el producto (los movimientos se eliminarán por ON DELETE CASCADE)
$stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
if ($stmt->execute([$id])) {
    $_SESSION['mensaje'] = 'Producto eliminado exitosamente.';
} else {
    $_SESSION['mensaje_error'] = 'Error al eliminar el producto.';
}

header('Location: dashboard.php');
exit;
<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . '/includes/db.php';

// Incluir Dompdf
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Iniciar sesión para mensajes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fecha del reporte: hoy
$fecha = date('Y-m-d');
$fechaLegible = date('d/m/Y');

// 1. Obtener productos actuales (stock final)
$stmt = $pdo->query("SELECT * FROM productos ORDER BY nombre");
$productos = $stmt->fetchAll();

// 2. Obtener movimientos del día (entradas y salidas)
$stmt = $pdo->prepare("SELECT producto_id, tipo, SUM(cantidad) as total FROM movimientos WHERE DATE(fecha) = ? GROUP BY producto_id, tipo");
$stmt->execute([$fecha]);
$movimientos = $stmt->fetchAll();

// 3. Obtener ventas del día
$stmt = $pdo->prepare("SELECT * FROM ventas WHERE DATE(fecha) = ? ORDER BY fecha");
$stmt->execute([$fecha]);
$ventas = $stmt->fetchAll();

// Totales
$totalVentasUSD = 0;
$totalVentasBS = 0;
$totalEntradas = 0;
$totalSalidas = 0;

$entradasPorProducto = [];
$salidasPorProducto = [];
foreach ($movimientos as $m) {
    if ($m['tipo'] == 'entrada') {
        $entradasPorProducto[$m['producto_id']] = $m['total'];
        $totalEntradas += $m['total'];
    } else {
        $salidasPorProducto[$m['producto_id']] = $m['total'];
        $totalSalidas += $m['total'];
    }
}

foreach ($ventas as $v) {
    $totalVentasUSD += $v['total_usd'];
    $totalVentasBS += $v['total_bs'];
}

// Construir HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Turno - ' . $fechaLegible . '</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #1e3c72; }
        .header { text-align: center; margin-bottom: 30px; }
        .fecha { color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1e3c72; color: white; }
        .totales { margin-top: 20px; font-weight: bold; }
        .resumen { background-color: #f8f9fa; padding: 10px; margin-bottom: 20px; }
        .resumen p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Turno</h1>
        <p class="fecha">Fecha: ' . $fechaLegible . '</p>
    </div>

    <div class="resumen">
        <p><strong>Resumen del turno:</strong></p>
        <p>Total de ventas: $ ' . number_format($totalVentasUSD, 2) . ' USD / Bs ' . number_format($totalVentasBS, 2) . '</p>
        <p>Productos ingresados: ' . $totalEntradas . ' unidades</p>
        <p>Productos egresados: ' . $totalSalidas . ' unidades</p>
        <p>Total productos en stock: ' . count($productos) . '</p>
    </div>

    <h2>Inventario de Productos</h2>
     <table>
        <thead>
             <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Stock Inicial*</th>
                <th>Entradas</th>
                <th>Salidas</th>
                <th>Stock Final</th>
                <th>Precio (USD)</th>
             </tr>
        </thead>
        <tbody>';

foreach ($productos as $p) {
    $id = $p['id'];
    $entradasHoy = $entradasPorProducto[$id] ?? 0;
    $salidasHoy = $salidasPorProducto[$id] ?? 0;
    $stockFinal = $p['cantidad'];
    $stockInicial = $stockFinal - ($entradasHoy - $salidasHoy);

    $html .= '<tr>
        <td>' . $id . '</td>
        <td>' . htmlspecialchars($p['nombre']) . '</td>
        <td>' . htmlspecialchars($p['categoria']) . '</td>
        <td>' . $stockInicial . '</td>
        <td>' . $entradasHoy . '</td>
        <td>' . $salidasHoy . '</td>
        <td>' . $stockFinal . '</td>
        <td>$ ' . number_format($p['precio'], 2) . '</td>
    </tr>';
}

$html .= '
        </tbody>
     </table>';

if (!empty($ventas)) {
    $html .= '<h2>Ventas del turno</h2>
     <table>
        <thead>
             <tr>
                <th>ID Venta</th>
                <th>Hora</th>
                <th>Total USD</th>
                <th>Total Bs</th>
             </tr>
        </thead>
        <tbody>';
    foreach ($ventas as $v) {
        $hora = date('H:i', strtotime($v['fecha']));
        $html .= '<tr>
            <td>' . htmlspecialchars($v['identificador']) . '</td>
            <td>' . $hora . '</td>
            <td>$ ' . number_format($v['total_usd'], 2) . '</td>
            <td>Bs ' . number_format($v['total_bs'], 2) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '
    <div class="totales">
        <p>Total ventas del turno: $ ' . number_format($totalVentasUSD, 2) . ' USD / Bs ' . number_format($totalVentasBS, 2) . '</p>
        <p>Productos ingresados total: ' . $totalEntradas . ' | Productos egresados total: ' . $totalSalidas . '</p>
        <p>Reporte generado el ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

// Configurar Dompdf
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Guardar el PDF
$nombreArchivo = 'reporte_turno_' . date('Ymd_His') . '.pdf';
$rutaArchivo = __DIR__ . '/reportes/' . $nombreArchivo;
file_put_contents($rutaArchivo, $dompdf->output());

// Guardar mensaje en sesión
$_SESSION['mensaje'] = 'Reporte de turno generado correctamente.';
$_SESSION['reporte_descarga'] = 'reportes/' . $nombreArchivo;

// Redirigir al dashboard
header('Location: public/dashboard.php');
exit;
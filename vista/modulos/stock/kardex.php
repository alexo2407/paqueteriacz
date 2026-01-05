<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener filtro de usuario (proveedores solo ven sus productos)
$filtroUsuario = getIdUsuarioCreadorFilter();

$productoId = $_GET['producto'] ?? '';
$producto = null;

// Obtener productos con filtro de usuario
$productos = ProductoModel::listarConInventario($filtroUsuario);

// Si hay producto seleccionado, verificar que tenga permiso
if ($productoId) {
    $producto = ProductoModel::obtenerPorId($productoId);
    // Verificar permiso de acceso
    if ($producto && !canViewProduct($producto)) {
        $producto = null; // No tiene permiso
    }
}

// Obtener fechas del filtro o usar valores por defecto (mes actual)
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

$kardexData = [
    'saldo_inicial' => 0,
    'movimientos' => [],
    'saldo_final' => 0
];

if ($producto) {
    // Si hay producto, obtener reporte kardex
    require_once __DIR__ . '/../../../modelo/stock.php';
    $kardexData = StockModel::generarReporteKardex($producto['id'], $fechaInicio, $fechaFin);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Kardex - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.kardex-header {
    background: linear-gradient(135deg, #093028 0%, #237A57 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(17, 153, 142, 0.2);
}
.product-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    background: white;
    overflow: hidden;
}
.info-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    height: 100%;
    transition: transform 0.2s;
    border-left: 4px solid #11998e;
}
.info-box:hover {
    transform: translateY(-2px);
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.info-label {
    text-transform: uppercase;
    font-size: 0.75rem;
    font-weight: 700;
    color: #6c757d;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}
.info-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}
.search-card {
    background: white; 
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: none;
}
.table-kardex th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}
.badge-mov {
    font-weight: 500;
    font-size: 0.8em;
    padding: 0.4em 0.8em;
    border-radius: 4px;
}
.badge-entrada { background-color: #198754; color: white; }
.badge-salida { background-color: #dc3545; color: white; }
.badge-ajuste { background-color: #ffc107; color: #212529; }

@media print {
    .no-print { display: none !important; }
    .kardex-header { background: white; color: black; padding: 0; box-shadow: none; margin-bottom: 1rem; }
    .kardex-header h2 { color: black; }
    .card { border: 1px solid #ddd; box-shadow: none; }
    .info-box { border: 1px solid #eee; }
    .table-responsive { overflow: visible; }
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="kardex-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-file-earmark-text me-2"></i> Reporte Kardex</h2>
            <p class="mb-0 opacity-75">Historial detallado de movimientos por producto</p>
        </div>
        <div class="no-print">
            <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-light border-2 text-white fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Volver lista
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card search-card mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold text-muted small"><i class="bi bi-search me-1"></i> PRODUCTO</label>
                    <select class="form-select select2-searchable" name="producto" required data-placeholder="Selecciona un producto...">
                        <option value="">Selecciona un producto...</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($productoId == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                                - Stock: <?php echo (int)($p['stock_total'] ?? 0); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small"><i class="bi bi-calendar-event me-1"></i> DESDE</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo $fechaInicio; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small"><i class="bi bi-calendar-event me-1"></i> HASTA</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo $fechaFin; ?>" required>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm" style="background: #11998e; border-color: #11998e;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($producto): ?>
        <!-- Información del Producto -->
        <div class="card product-card mb-4">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-box-seam me-2 text-success"></i> <?php echo htmlspecialchars($producto['nombre']); ?>
                </h4>
                <div class="d-flex align-items-center gap-3 no-print">
                     <a href="<?php echo RUTA_URL; ?>stock/crear?producto=<?php echo $productoId; ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Nuevo Movimiento
                    </a>
                    <button class="btn btn-light btn-sm border" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimir
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <div class="info-label">Código SKU</div>
                            <div class="info-value text-break"><?php echo htmlspecialchars($producto['sku'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box" style="border-left-color: #38ef7d;">
                            <div class="info-label">Precio Unitario</div>
                            <div class="info-value text-success">$<?php echo number_format($producto['precio_usd'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box" style="border-left-color: #ffc107;">
                            <div class="info-label">Saldo Inicial Período</div>
                            <div class="info-value">
                                <?php echo number_format($kardexData['saldo_inicial']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box" style="border-left-color: #0dcaf0;">
                            <div class="info-label">Stock Actual</div>
                            <div class="info-value">
                                <span class="badge bg-primary text-white fs-5 px-3 rounded-pill"><?php echo (int)($producto['stock_total'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Historial -->
        <div class="card product-card">
            <div class="card-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-muted text-uppercase"><i class="bi bi-clock-history me-1"></i> Historial de Movimientos</h6>
                <small class="text-muted">Del <?php echo date('d/m/Y', strtotime($fechaInicio)); ?> al <?php echo date('d/m/Y', strtotime($fechaFin)); ?></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-kardex mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Tipo</th>
                                <th>Referencia</th>
                                <th>Motivo / Descripción</th>
                                <th class="text-center">Entrada</th>
                                <th class="text-center">Salida</th>
                                <th class="text-end pe-4">Saldo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Saldo Inicial Row -->
                            <tr class="table-light">
                                <td colspan="6" class="ps-4 text-end fw-bold text-muted fst-italic">Saldo al iniciar el período:</td>
                                <td class="text-end pe-4 fw-bold"><?php echo number_format($kardexData['saldo_inicial']); ?></td>
                                <td></td>
                            </tr>

                            <?php if (empty($kardexData['movimientos'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-25"></i>
                                        No hay movimientos registrados en este rango de fechas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kardexData['movimientos'] as $mov): 
                                    $cantidad = (int)$mov['cantidad'];
                                    $isEntrada = $cantidad > 0;
                                    $tipoClass = $isEntrada ? 'badge-entrada' : 'badge-salida';
                                    if ($mov['tipo_movimiento'] === 'ajuste') $tipoClass = 'badge-ajuste';
                                ?>
                                <tr>
                                    <td class="ps-4 text-nowrap"><?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?></td>
                                    <td>
                                        <span class="badge-mov <?php echo $tipoClass; ?> text-uppercase">
                                            <?php echo htmlspecialchars($mov['tipo_movimiento']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($mov['referencia_tipo'])): ?>
                                            <small class="d-block fw-bold text-secondary"><?php echo htmlspecialchars(ucfirst($mov['referencia_tipo'])); ?></small>
                                            <small class="text-muted">#<?php echo htmlspecialchars($mov['referencia_id'] ?? '-'); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['motivo'] ?? '-'); ?></td>
                                    
                                    <!-- Entrada -->
                                    <td class="text-center text-success fw-bold bg-light bg-opacity-10">
                                        <?php echo $isEntrada ? '+' . number_format($cantidad) : ''; ?>
                                    </td>
                                    
                                    <!-- Salida -->
                                    <td class="text-center text-danger fw-bold bg-light bg-opacity-10">
                                        <?php echo !$isEntrada ? number_format(abs($cantidad)) : ''; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 fw-bold text-dark">
                                        <?php echo number_format($mov['saldo']); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle text-center me-2" style="width:25px; height:25px; line-height:25px;">
                                                <i class="bi bi-person-fill text-secondary small"></i>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($mov['usuario'] ?? 'Sistema'); ?></small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($kardexData['movimientos'])): ?>
                        <tfoot>
                            <tr class="table-light border-top">
                                <td colspan="6" class="ps-4 text-end fw-bold text-dark">Saldo Final del período:</td>
                                <td class="text-end pe-4 fw-bold fs-6 text-primary"><?php echo number_format($kardexData['saldo_final']); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Estado Vacío -->
        <div class="card border-0 shadow-sm rounded-4 py-5 text-center mt-5">
            <div class="card-body">
                <div class="mb-4 text-muted opacity-25">
                    <i class="bi bi-clipboard-data" style="font-size: 5rem;"></i>
                </div>
                <h3 class="fw-bold text-dark">Consulta de Kardex</h3>
                <p class="text-muted col-md-6 mx-auto">
                    Selecciona un producto y un rango de fechas para visualizar su historial detallado de movimientos.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2-searchable').select2({
            theme: "bootstrap-5",
            width: '100%',
            language: {
                noResults: function() {
                    return "No se encontraron resultados";
                },
                searching: function() {
                    return "Buscando...";
                }
            }
        });
    });
</script>
</body>
</html>

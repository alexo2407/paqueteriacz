<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/stock.php';
require_once __DIR__ . '/../../../modelo/producto.php';

start_secure_session();
require_login();

// Obtener filtros
$tipoFiltro = $_GET['tipo'] ?? '';

// Por defecto: mes en curso
$primerDiaMes = date('Y-m-01');
$hoy = date('Y-m-d');

$fechaInicio = $_GET['fecha_inicio'] ?? $primerDiaMes;
$fechaFin = $_GET['fecha_fin'] ?? $hoy;

// Obtener movimientos filtrados por fecha (siempre aplicar filtro de mes en curso)
if ($tipoFiltro) {
    // Si hay filtro de tipo, obtener por tipo y luego filtrar por fecha
    $movimientos = StockModel::obtenerMovimientosPorFecha($fechaInicio, $fechaFin, ['tipo_movimiento' => $tipoFiltro]);
} else {
    $movimientos = StockModel::obtenerMovimientosPorFecha($fechaInicio, $fechaFin);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Stock - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-arrow-down-up"></i> Movimientos de Stock</h2>
            <p class="text-muted mb-0">Historial de entradas, salidas y ajustes de inventario</p>
        </div>
        <div>
            <a href="<?php echo RUTA_URL; ?>stock/kardex" class="btn btn-outline-info me-2">
                <i class="bi bi-file-earmark-text"></i> Reporte Kardex
            </a>
            <a href="<?php echo RUTA_URL; ?>stock/crear" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Registrar Movimiento
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Tipo de Movimiento</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <option value="entrada" <?php echo $tipoFiltro === 'entrada' ? 'selected' : ''; ?>>
                                Entradas
                            </option>
                            <option value="salida" <?php echo $tipoFiltro === 'salida' ? 'selected' : ''; ?>>
                                Salidas
                            </option>
                            <option value="ajuste" <?php echo $tipoFiltro === 'ajuste' ? 'selected' : ''; ?>>
                                Ajustes
                            </option>
                            <option value="devolucion" <?php echo $tipoFiltro === 'devolucion' ? 'selected' : ''; ?>>
                                Devoluciones
                            </option>
                            <option value="transferencia" <?php echo $tipoFiltro === 'transferencia' ? 'selected' : ''; ?>>
                                Transferencias
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel"></i> Filtrar
                            </button>
                            <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Leyenda de Tipos -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3"><i class="bi bi-info-circle"></i> Leyenda de Tipos de Movimiento</h6>
            <div class="row">
                <div class="col-md-2">
                    <span class="badge bg-success"><i class="bi bi-arrow-down-circle"></i> Entrada</span> Compra/Recepción
                </div>
                <div class="col-md-2">
                    <span class="badge bg-danger"><i class="bi bi-arrow-up-circle"></i> Salida</span> Venta/Despacho
                </div>
                <div class="col-md-2">
                    <span class="badge bg-warning text-dark"><i class="bi bi-wrench"></i> Ajuste</span> Corrección
                </div>
                <div class="col-md-3">
                    <span class="badge bg-info"><i class="bi bi-arrow-counterclockwise"></i> Devolución</span> Retorno
                </div>
                <div class="col-md-3">
                    <span class="badge bg-primary"><i class="bi bi-arrow-left-right"></i> Transferencia</span> Entre ubicaciones
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Movimientos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaStock" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Ubicación</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movimientos)): ?>
                            <?php foreach ($movimientos as $mov): 
                                // Obtener producto - usar nombre del join si está disponible
                                $nombreProducto = !empty($mov['producto']) 
                                    ? htmlspecialchars($mov['producto']) 
                                    : 'Producto N/A';
                                
                                // Determinar tipo basado en tipo_movimiento o signo de cantidad
                                $tipoMov = $mov['tipo_movimiento'] ?? null;
                                if (!$tipoMov) {
                                    // Para registros antiguos sin tipo_movimiento, inferir del signo
                                    $tipoMov = ($mov['cantidad'] >= 0) ? 'entrada' : 'salida';
                                }
                                
                                // Badge de tipo
                                $badges = [
                                    'entrada' => '<span class="badge bg-success"><i class="bi bi-arrow-down-circle"></i> Entrada</span>',
                                    'salida' => '<span class="badge bg-danger"><i class="bi bi-arrow-up-circle"></i> Salida</span>',
                                    'ajuste' => '<span class="badge bg-warning text-dark"><i class="bi bi-wrench"></i> Ajuste</span>',
                                    'devolucion' => '<span class="badge bg-info"><i class="bi bi-arrow-counterclockwise"></i> Devolución</span>',
                                    'transferencia' => '<span class="badge bg-primary"><i class="bi bi-arrow-left-right"></i> Transferencia</span>'
                                ];
                                $tipoBadge = $badges[$tipoMov] ?? '<span class="badge bg-secondary">Otro</span>';
                                
                                // Ubicación
                                $ubicacion = '';
                                if ($tipoMov === 'transferencia') {
                                    $ubicacion = htmlspecialchars($mov['ubicacion_origen'] ?? '') . ' → ' . htmlspecialchars($mov['ubicacion_destino'] ?? '');
                                } else {
                                    $ubicacion = htmlspecialchars($mov['ubicacion_origen'] ?? $mov['ubicacion_destino'] ?? 'N/A');
                                }
                                
                                // Cantidad - mostrar valor absoluto con color según signo real
                                $cantidadReal = (int)$mov['cantidad'];
                                $cantidadAbs = abs($cantidadReal);
                                $cantidadClass = $cantidadReal >= 0 ? 'text-success' : 'text-danger';
                                $cantidadSigno = $cantidadReal >= 0 ? '+' : '';
                                
                                // Fecha - manejar fechas nulas o epoch
                                $fechaRaw = $mov['created_at'] ?? $mov['updated_at'] ?? null;
                                if ($fechaRaw && strtotime($fechaRaw) > 86400) {
                                    $fechaFormateada = date('d/m/Y H:i', strtotime($fechaRaw));
                                } else {
                                    $fechaFormateada = '<span class="text-muted">Sin fecha</span>';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <?php echo $fechaFormateada; ?>
                                    </td>
                                    <td><?php echo $tipoBadge; ?></td>
                                    <td><strong><?php echo $nombreProducto; ?></strong></td>
                                    <td class="<?php echo $cantidadClass; ?> fw-bold">
                                        <?php echo $cantidadSigno . $cantidadReal; ?>
                                    </td>
                                    <td><small><?php echo $ubicacion; ?></small></td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($mov['motivo'] ?? 'N/A', 0, 50)); ?>
                                        <?php if (strlen($mov['motivo'] ?? '') > 50): ?>...<?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($mov['referencia_tipo']) && !empty($mov['referencia_id'])): ?>
                                            <small class="badge bg-secondary">
                                                <?php echo htmlspecialchars($mov['referencia_tipo']); ?> #<?php echo $mov['referencia_id']; ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tablaStock').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[0, 'desc']], // Ordenar por fecha descendente
            pageLength: 25,
            responsive: true
        });
    });
</script>
</body>
</html>

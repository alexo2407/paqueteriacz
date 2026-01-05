<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/stock.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/usuario.php';

start_secure_session();
require_login();

// Verificar si es administrador
$esAdmin = isSuperAdmin();

// Obtener filtro de usuario 
// Para admin: puede filtrar por cualquier proveedor via GET, o ver todos
// Para proveedor: solo ve sus propios movimientos
$filtroUsuario = getIdUsuarioCreadorFilter();

// Si es admin y hay un filtro de proveedor en GET, usarlo
$proveedorFiltro = $_GET['proveedor'] ?? '';
if ($esAdmin && $proveedorFiltro !== '') {
    $filtroUsuario = (int)$proveedorFiltro;
}

// Obtener lista de proveedores para el dropdown (solo para admin)
$proveedores = [];
if ($esAdmin) {
    $usuarioModel = new UsuarioModel();
    $proveedores = $usuarioModel->obtenerUsuariosPorRolNombre(ROL_NOMBRE_PROVEEDOR);
}

// Obtener filtros de la URL
$tipoFiltro = $_GET['tipo'] ?? '';

// Por defecto: mes en curso
$primerDiaMes = date('Y-m-01');
$hoy = date('Y-m-d');

$fechaInicio = $_GET['fecha_inicio'] ?? $primerDiaMes;
$fechaFin = $_GET['fecha_fin'] ?? $hoy;

// Construir filtros
$filtros = [];
if ($tipoFiltro) {
    $filtros['tipo_movimiento'] = $tipoFiltro;
}
// Aplicar filtro de usuario
if ($filtroUsuario !== null) {
    $filtros['id_usuario'] = $filtroUsuario;
}

// Obtener movimientos filtrados
$movimientos = StockModel::obtenerMovimientosPorFecha($fechaInicio, $fechaFin, $filtros);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Stock - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.stock-header {
    background: linear-gradient(135deg, #093028 0%, #237A57 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(17, 153, 142, 0.2);
}
.filter-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    background: white;
}
.table-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    overflow: hidden;
}
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding: 1rem;
}
.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}
.badge-movement {
    padding: 0.5em 0.8em;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.85rem;
}
.btn-action-light {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}
.btn-action-light:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6c757d;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="stock-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-arrow-down-up me-2"></i> Movimientos de Stock</h2>
            <p class="mb-0 opacity-75">Historial completo de entradas, salidas y ajustes de inventario</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo RUTA_URL; ?>stock/kardex" class="btn btn-action-light">
                <i class="bi bi-file-earmark-text me-1"></i> Reporte Kardex
            </a>
            <a href="<?php echo RUTA_URL; ?>stock/crear" class="btn btn-light text-success fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Movimiento
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card mb-4">
        <div class="card-body p-4">
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <?php if ($esAdmin && !empty($proveedores)): ?>
                    <!-- Filtro por Proveedor (solo Admin) -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">
                            <i class="bi bi-person-badge"></i> Proveedor
                        </label>
                        <select name="proveedor" class="form-select select2-searchable" data-placeholder="Filtrar por proveedor...">
                            <option value="">Todos los proveedores</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id']; ?>" <?php echo $proveedorFiltro == $prov['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-<?php echo $esAdmin ? '2' : '3'; ?>">
                        <label class="form-label fw-bold small text-muted"><i class="bi bi-filter"></i> Tipo</label>
                        <select name="tipo" class="form-select select2-searchable" data-placeholder="Tipo de movimiento...">
                            <option value="">Todos los tipos</option>
                            <option value="entrada" <?php echo $tipoFiltro === 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                            <option value="salida" <?php echo $tipoFiltro === 'salida' ? 'selected' : ''; ?>>Salidas</option>
                            <option value="ajuste" <?php echo $tipoFiltro === 'ajuste' ? 'selected' : ''; ?>>Ajustes</option>
                            <option value="devolucion" <?php echo $tipoFiltro === 'devolucion' ? 'selected' : ''; ?>>Devoluciones</option>
                            <option value="transferencia" <?php echo $tipoFiltro === 'transferencia' ? 'selected' : ''; ?>>Transferencias</option>
                        </select>
                    </div>

                    <div class="col-md-<?php echo $esAdmin ? '2' : '3'; ?>">
                        <label class="form-label fw-bold small text-muted"><i class="bi bi-calendar-event"></i> Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>

                    <div class="col-md-<?php echo $esAdmin ? '2' : '3'; ?>">
                        <label class="form-label fw-bold small text-muted"><i class="bi bi-calendar-event"></i> Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>

                    <div class="col-md-<?php echo $esAdmin ? '3' : '3'; ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill fw-bold">
                                <i class="bi bi-funnel me-1"></i> Filtrar
                            </button>
                            <a href="<?php echo RUTA_URL; ?>stock/listar" class="btn btn-outline-secondary" title="Limpiar Filtros">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($esAdmin && $proveedorFiltro): ?>
                <div class="alert alert-primary mt-3 mb-0 py-2 border-0 bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2"></i> 
                    <span>Mostrando movimientos del proveedor: <strong><?php 
                        foreach ($proveedores as $p) {
                            if ($p['id'] == $proveedorFiltro) {
                                echo htmlspecialchars($p['nombre']);
                                break;
                            }
                        }
                    ?></strong></span>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Leyenda Rápida -->
    <div class="d-flex flex-wrap gap-4 mb-3 px-2">
        <div class="legend-item"><span class="badge bg-success rounded-circle p-1"> </span> Entrada</div>
        <div class="legend-item"><span class="badge bg-danger rounded-circle p-1"> </span> Salida</div>
        <div class="legend-item"><span class="badge bg-warning rounded-circle p-1"> </span> Ajuste</div>
        <div class="legend-item"><span class="badge bg-info rounded-circle p-1"> </span> Devolución</div>
        <div class="legend-item"><span class="badge bg-primary rounded-circle p-1"> </span> Transferencia</div>
    </div>

    <!-- Tabla de Movimientos -->
    <div class="card table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaStock" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Fecha/Hora</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th class="text-end">Cantidad</th>
                            <th>Ubicación</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movimientos)): ?>
                            <?php foreach ($movimientos as $mov): 
                                $nombreProducto = !empty($mov['producto']) ? htmlspecialchars($mov['producto']) : 'Producto N/A';
                                
                                $tipoMov = $mov['tipo_movimiento'] ?? null;
                                if (!$tipoMov) $tipoMov = ($mov['cantidad'] >= 0) ? 'entrada' : 'salida';
                                
                                $badges = [
                                    'entrada' => '<span class="badge bg-success text-white badge-movement"><i class="bi bi-arrow-down-circle me-1"></i> Entrada</span>',
                                    'salida' => '<span class="badge bg-danger text-white badge-movement"><i class="bi bi-arrow-up-circle me-1"></i> Salida</span>',
                                    'ajuste' => '<span class="badge bg-warning text-dark badge-movement"><i class="bi bi-wrench me-1"></i> Ajuste</span>',
                                    'devolucion' => '<span class="badge bg-info text-white badge-movement"><i class="bi bi-arrow-counterclockwise me-1"></i> Devolución</span>',
                                    'transferencia' => '<span class="badge bg-primary text-white badge-movement"><i class="bi bi-arrow-left-right me-1"></i> Transf.</span>'
                                ];
                                $tipoBadge = $badges[$tipoMov] ?? '<span class="badge bg-secondary badge-movement">Otro</span>';
                                
                                $ubicacion = '';
                                if ($tipoMov === 'transferencia') {
                                    $ubicacion = htmlspecialchars($mov['ubicacion_origen'] ?? '') . ' → ' . htmlspecialchars($mov['ubicacion_destino'] ?? '');
                                } else {
                                    $ubicacion = htmlspecialchars($mov['ubicacion_origen'] ?? $mov['ubicacion_destino'] ?? 'Principal');
                                }
                                
                                $cantidadReal = (int)$mov['cantidad'];
                                $cantidadClass = $cantidadReal >= 0 ? 'text-success' : 'text-danger';
                                $cantidadSigno = $cantidadReal >= 0 ? '+' : '';
                                
                                $fechaRaw = $mov['created_at'] ?? $mov['updated_at'] ?? null;
                                $fechaFormateada = ($fechaRaw && strtotime($fechaRaw) > 86400) ? date('d/m/Y H:i', strtotime($fechaRaw)) : '<span class="text-muted">-</span>';
                            ?>
                                <tr>
                                    <td class="ps-4 text-nowrap"><?php echo $fechaFormateada; ?></td>
                                    <td><?php echo $tipoBadge; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $nombreProducto; ?></div>
                                    </td>
                                    <td class="text-end <?php echo $cantidadClass; ?> fw-bold fs-6">
                                        <?php echo $cantidadSigno . $cantidadReal; ?>
                                    </td>
                                    <td><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo $ubicacion; ?></small></td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars(mb_strimwidth($mov['motivo'] ?? '', 0, 40, "...")); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle-sm bg-light text-secondary me-2 rounded-circle d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:10px;">
                                                <?php echo strtoupper(substr($mov['usuario_nombre'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <span class="small"><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($mov['referencia_tipo']) && !empty($mov['referencia_id'])): ?>
                                            <span class="badge bg-light text-secondary border">
                                                ID: <?php echo $mov['referencia_id']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
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
        // Inicializar Select2
        $('.select2-searchable').select2({
            theme: "bootstrap-5",
            width: '100%',
            selectionCssClass: "select2--small",
            dropdownCssClass: "select2--small"
        });

        // Inicializar DataTables
        $('#tablaStock').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[0, 'desc']], // Ordenar por fecha descendente
            pageLength: 25,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
        });
    });
</script>
</body>
</html>

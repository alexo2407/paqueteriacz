<?php
/**
 * vista/modulos/logistica_operativa/etiquetas/index.php
 *
 * Centro de Impresión Masiva de Etiquetas adhesivas de envío (4x6" / Código de barras).
 * Filtra EXCLUSIVAMENTE pedidos con estado "Pendiente recolección por mensajería" (Estado 11).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

$rolesSession = $_SESSION['roles_nombres'] ?? [];
$isCliente    = in_array(ROL_NOMBRE_CLIENTE, $rolesSession, true) || in_array('Cliente', $rolesSession, true);
$isProveedor  = in_array(ROL_NOMBRE_PROVEEDOR, $rolesSession, true) || in_array('Proveedor', $rolesSession, true);
$isAdmin      = in_array(ROL_NOMBRE_ADMIN, $rolesSession, true) || in_array('Administrador', $rolesSession, true);

$pedidos = [];
$errorMsg = null;

// Filtros recibidos
$filtroProveedor = isset($_GET['proveedor']) && $_GET['proveedor'] !== '' ? (int)$_GET['proveedor'] : 0;
$filtroCliente   = isset($_GET['cliente']) && $_GET['cliente'] !== '' ? (int)$_GET['cliente'] : 0;
$fechaDesde      = isset($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : '';
$fechaHasta      = isset($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : '';

// Si el usuario autenticado es Proveedor (y no admin), fijar automáticamente su ID de proveedor
if ($isProveedor && !$isAdmin) {
    $filtroProveedor = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
}
// Si el usuario autenticado es Cliente (y no admin/proveedor), fijar automáticamente su ID de cliente
if ($isCliente && !$isAdmin && !$isProveedor) {
    $filtroCliente = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
}

try {
    $db = (new Conexion())->conectar();
    
    // Consulta base: paquetes con datos del cliente emisor y proveedor asignado
    // EXCLUSIVAMENTE en estado 11: "Pendiente recolección por mensajería"
    $sql = "
        SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.direccion AS direccion_destino,
               p.precio_total_local AS monto_cod, 
               COALESCE(u.nombre, 'Sin cliente') AS cliente_nombre,
               COALESCE(up.nombre, 'Sin asignar') AS proveedor_nombre,
               COALESCE(p.fecha_ingreso, p.created_at, p.fecha_entrega) AS fecha_registro,
               p.id_estado, ep.nombre_estado
          FROM pedidos p
          JOIN usuarios u ON u.id = p.id_cliente
     LEFT JOIN usuarios up ON up.id = p.id_proveedor
          JOIN estados_pedidos ep ON ep.id = p.id_estado
         WHERE p.id_estado = 11
    ";
    $params = [];
    
    // Filtro por Proveedor asignado
    if ($filtroProveedor > 0) {
        $sql .= " AND p.id_proveedor = :filtroProveedor";
        $params[':filtroProveedor'] = $filtroProveedor;
    }
    
    // Filtro por Cliente Emisor
    if ($filtroCliente > 0) {
        $sql .= " AND p.id_cliente = :filtroCliente";
        $params[':filtroCliente'] = $filtroCliente;
    }
    
    // Filtro por Rango de Fechas (opcional)
    if (!empty($fechaDesde)) {
        $sql .= " AND DATE(COALESCE(p.fecha_ingreso, p.created_at, p.fecha_entrega)) >= :fechaDesde";
        $params[':fechaDesde'] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $sql .= " AND DATE(COALESCE(p.fecha_ingreso, p.created_at, p.fecha_entrega)) <= :fechaHasta";
        $params[':fechaHasta'] = $fechaHasta;
    }
    
    $sql .= " ORDER BY p.id DESC LIMIT 200";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 1. Obtener lista ÚNICAMENTE de PROVEEDORES que tienen pedidos en estado 11 (Pendiente recolección)
    if ($isProveedor && !$isAdmin) {
        $stmtProv = $db->prepare("SELECT id, nombre FROM usuarios WHERE id = :id");
        $stmtProv->execute([':id' => $filtroProveedor]);
        $proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtProv = $db->query("
            SELECT u.id, u.nombre, COUNT(p.id) AS pendientes
              FROM usuarios u
              JOIN pedidos p ON p.id_proveedor = u.id
             WHERE p.id_estado = 11
             GROUP BY u.id, u.nombre
             ORDER BY pendientes DESC, u.nombre ASC
        ");
        $proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Obtener lista ÚNICAMENTE de CLIENTES emisores que tienen pedidos en estado 11 (Pendiente recolección)
    if ($isCliente && !$isAdmin && !$isProveedor) {
        $stmtCli = $db->prepare("SELECT id, nombre FROM usuarios WHERE id = :id");
        $stmtCli->execute([':id' => $filtroCliente]);
        $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtCli = $db->query("
            SELECT u.id, u.nombre, COUNT(p.id) AS pendientes
              FROM usuarios u
              JOIN pedidos p ON p.id_cliente = u.id
             WHERE p.id_estado = 11
             GROUP BY u.id, u.nombre
             ORDER BY pendientes DESC, u.nombre ASC
        ");
        $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {
    error_log('[etiquetas/index] Error: ' . $e->getMessage());
    $errorMsg = 'Error al cargar los paquetes para impresión de etiquetas.';
}

$pageTitle = 'Impresión Masiva de Etiquetas — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3 no-print">
  <ol class="breadcrumb mb-0 small">
    <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item">Logística Operativa</li>
    <li class="breadcrumb-item active">Etiquetas</li>
  </ol>
</nav>

<style>
@media print {
    @page {
        size: letter portrait;
        margin: 8mm;
    }
    html, body {
        background: #fff !important;
        color: #000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    header, footer, nav, sidebar, .sidebar, .no-print, .btn, .breadcrumb, nav[aria-label="breadcrumb"], .main-header, .top-navbar, .page-header {
        display: none !important;
    }
    .container-fluid, .main-content, #content, .content-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .label-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 6mm !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .item-etiqueta {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .item-etiqueta:nth-child(4n) {
        page-break-after: always !important;
        break-after: page !important;
    }
    .sticker-card {
        border: 2px solid #000 !important;
        border-radius: 8px !important;
        padding: 10px !important;
        box-shadow: none !important;
        height: 122mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        background: #fff !important;
    }
    .qrcode-container img, .qrcode-container canvas {
        width: 75px !important;
        height: 75px !important;
    }
    .barcode-svg {
        height: 30px !important;
    }
}

.sticker-card {
    border: 2px solid #0f172a;
    border-radius: 12px;
    background: #fff;
    padding: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.barcode-svg {
    height: 50px;
    width: 100%;
}
</style>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-tag-fill me-2 text-warning"></i>Impresión Masiva de Etiquetas adhesivas (4×6")
        </h1>
        <small class="text-muted">Mostrando exclusivamente paquetes en estado <strong>Pendiente recolección por mensajería</strong></small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning fw-bold px-3 text-dark shadow-sm" onclick="window.print()" <?= empty($pedidos) ? 'disabled' : '' ?>>
            <i class="bi bi-printer-fill me-1"></i>Imprimir Etiquetas (<?= count($pedidos) ?>)
        </button>
    </div>
</div>

<!-- Filtros no-print -->
<div class="card border-0 shadow-sm mb-4 no-print bg-white rounded-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= RUTA_URL ?>index.php" class="row g-3 align-items-end" id="formFiltrosEtiquetas">
            <input type="hidden" name="enlace" value="logistica-operativa/etiquetas">

            <!-- Filtro Proveedor / Mensajería Asignada -->
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-truck me-1 text-primary"></i>Proveedor / Mensajería
                </label>
                <?php if ($isProveedor && !$isAdmin): ?>
                <select name="proveedor" class="form-select form-select-sm bg-light" disabled>
                    <?php foreach ($proveedores as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" selected>🚚 <?= htmlspecialchars($pr['nombre']) ?> (Mi Cuenta)</option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select name="proveedor" class="form-select form-select-sm">
                    <option value="">-- Todos los proveedores --</option>
                    <?php foreach ($proveedores as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>" <?= $filtroProveedor === (int)$pr['id'] ? 'selected' : '' ?>>
                        🚚 <?= htmlspecialchars($pr['nombre']) ?> (<?= (int)$pr['pendientes'] ?> pendiente<?= (int)$pr['pendientes'] !== 1 ? 's' : '' ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Filtro Cliente Emisor -->
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-building me-1 text-info"></i>Cliente Emisor (Remitente)
                </label>
                <?php if ($isCliente && !$isAdmin && !$isProveedor): ?>
                <select name="cliente" class="form-select form-select-sm bg-light" disabled>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" selected>🏢 <?= htmlspecialchars($c['nombre']) ?> (Mi Cuenta)</option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select name="cliente" class="form-select form-select-sm">
                    <option value="">-- Todos los clientes emisores --</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filtroCliente === (int)$c['id'] ? 'selected' : '' ?>>
                        🏢 <?= htmlspecialchars($c['nombre']) ?> (<?= (int)$c['pendientes'] ?> pendiente<?= (int)$c['pendientes'] !== 1 ? 's' : '' ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Filtro Fecha Desde -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i>Fecha Desde
                </label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($fechaDesde) ?>" id="inputFechaDesde" placeholder="Desde">
            </div>

            <!-- Filtro Fecha Hasta (Opcional) -->
            <div class="col-6 col-md-2 d-none d-md-block" style="display:none !important;">
                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>" id="inputFechaHasta">
            </div>

            <!-- Botones de Acción -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                    <i class="bi bi-filter me-1"></i>Filtrar
                </button>
                <a href="<?= RUTA_URL ?>logistica-operativa/etiquetas"
                   class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>

            <!-- Indicador y Switch -->
            <div class="col-12 d-flex align-items-center justify-content-between pt-2 border-top flex-wrap gap-2">
                <div class="small text-muted">
                    Mostrando <strong class="text-dark"><?= count($pedidos) ?></strong> paquete(s) en estado 
                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle fw-semibold">
                        <i class="bi bi-box-seam me-1"></i>Pendiente recolección por mensajería
                    </span>
                    <?php if (!empty($fechaDesde)): ?>
                        a partir del <span class="badge bg-light text-primary border"><?= date('d/m/Y', strtotime($fechaDesde)) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="chkTodos" checked onclick="toggleSeleccionarTodos(this)">
                    <label class="form-check-label fw-bold small text-muted cursor-pointer" for="chkTodos">
                        Seleccionar todos los paquetes
                    </label>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
<div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-4 no-print">
    <div class="py-4">
        <i class="bi bi-tags display-4 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="fw-bold text-dark mb-1">No hay paquetes pendientes de recolección</h5>
        <p class="text-muted small mb-3">No se encontraron pedidos con estado <em>Pendiente recolección por mensajería</em> para los filtros seleccionados.</p>
        <a href="<?= RUTA_URL ?>logistica-operativa/etiquetas" class="btn btn-outline-primary btn-sm px-3 rounded-pill">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer filtros
        </a>
    </div>
</div>
<?php else: ?>

<!-- Rejilla de Etiquetas (Stickers 4x6") -->
<div class="row g-4 label-grid" id="contenedorEtiquetas">
    <?php foreach ($pedidos as $p): ?>
    <div class="col-12 col-md-6 col-xl-4 item-etiqueta">
        <div class="sticker-card">
            <!-- Header Sticker -->
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                <div class="fw-bold fs-5 text-uppercase">RutaEx Express</div>
                <div class="badge bg-dark text-white font-monospace fs-6">COD: C$ <?= number_format((float)($p['monto_cod'] ?? 0), 2) ?></div>
            </div>

            <!-- Datos Cliente y Proveedor -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="small text-muted" style="font-size:0.75rem;">REMITENTE:</div>
                    <div class="fw-bold text-dark small">🏢 <?= htmlspecialchars((string)($p['cliente_nombre'] ?? '')) ?></div>
                </div>
                <div class="text-end">
                    <div class="small text-muted" style="font-size:0.75rem;">MENSAJERÍA:</div>
                    <div class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace small">🚚 <?= htmlspecialchars((string)($p['proveedor_nombre'] ?? '')) ?></div>
                </div>
            </div>

            <!-- Datos Destinatario -->
            <div class="bg-light p-2 rounded border mb-2">
                <div class="small text-muted fw-bold">DESTINATARIO:</div>
                <div class="fw-bold fs-6 text-primary"><?= htmlspecialchars((string)($p['destinatario'] ?? '')) ?></div>
                <div class="small font-monospace"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars((string)($p['telefono'] ?? '—')) ?></div>
                <div class="small text-dark mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)($p['direccion_destino'] ?? '—')) ?></div>
            </div>

            <!-- Código QR / Código de barras / N.º de orden -->
            <div class="text-center pt-2 border-top">
                <div class="font-monospace fw-bold fs-4 tracking-wide text-primary"><?= htmlspecialchars((string)($p['numero_orden'] ?? '#' . $p['id'])) ?></div>
                
                <div class="d-flex align-items-center justify-content-center gap-3 my-2">
                    <!-- QR Code -->
                    <div id="qrcode-<?= (int)$p['id'] ?>" class="qrcode-container"></div>
                </div>

                <!-- SVG Barcode visual -->
                <svg class="barcode-svg" id="barcode-<?= (int)$p['id'] ?>"></svg>
                <div class="small text-muted font-monospace">Escanea este código QR o de barras en Colectas</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($pedidos as $p): ?>
    try {
        // Generar Código QR
        new QRCode(document.getElementById("qrcode-<?= (int)$p['id'] ?>"), {
            text: "<?= htmlspecialchars((string)($p['numero_orden'] ?? $p['id'])) ?>",
            width: 90,
            height: 90,
            colorDark: "#0f172a",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Generar Código de Barras
        JsBarcode("#barcode-<?= (int)$p['id'] ?>", "<?= htmlspecialchars((string)($p['numero_orden'] ?? $p['id'])) ?>", {
            format: "CODE128",
            height: 35,
            displayValue: false
        });
    } catch(e) {}
    <?php endforeach; ?>
});

function toggleSeleccionarTodos(chk) {
    document.querySelectorAll('.item-etiqueta').forEach(el => {
        el.style.display = chk.checked ? 'block' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>

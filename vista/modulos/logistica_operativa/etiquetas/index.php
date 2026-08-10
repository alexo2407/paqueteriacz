<?php
/**
 * vista/modulos/logistica_operativa/etiquetas/index.php
 *
 * Centro de Impresión Masiva de Etiquetas adhesivas de envío (4x6" / Código de barras).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';

$rolesSession = $_SESSION['roles_nombres'] ?? [];
$isProveedor  = in_array(ROL_NOMBRE_PROVEEDOR, $rolesSession, true) || in_array('Proveedor', $rolesSession, true);
$isAdmin      = in_array(ROL_NOMBRE_ADMIN, $rolesSession, true) || in_array('Administrador', $rolesSession, true);

$pedidos = [];
$errorMsg = null;

try {
    $db = (new Conexion())->conectar();
    
    $filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;

    // Si es proveedor de logística y no admin, filtrar por su ID
    if ($isProveedor && !$isAdmin) {
        $filtroCliente = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
    }
    
    $sql = "
        SELECT p.id, p.numero_orden, p.destinatario, p.telefono, p.direccion AS direccion_destino,
               p.precio_total_local AS monto_cod, u.nombre AS cliente_nombre, p.fecha_ingreso
          FROM pedidos p
          JOIN usuarios u ON u.id = p.id_cliente
         WHERE 1=1
    ";
    
    if ($filtroCliente > 0) {
        $sql .= " AND (p.id_cliente = " . $filtroCliente . " OR p.id_proveedor = " . $filtroCliente . ")";
    }
    
    $sql .= " ORDER BY p.id DESC LIMIT 50";
    
    $stmt = $db->query($sql);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de clientes
    if ($isProveedor && !$isAdmin) {
        $stmtCli = $db->prepare("SELECT id, nombre FROM usuarios WHERE id = :id");
        $stmtCli->execute(['id' => $filtroCliente]);
        $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtCli = $db->query("SELECT id, nombre FROM usuarios WHERE id_estado = 1 ORDER BY nombre ASC");
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
        <small class="text-muted">Generación de guías con código de barras listas para impresoras térmicas (Zebra / Xprinter)</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning fw-bold px-3 text-dark shadow-sm" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i>Imprimir Etiquetas Seleccionadas
        </button>
    </div>
</div>

<!-- Filtros no-print -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= RUTA_URL ?>index.php" class="row g-3">
            <input type="hidden" name="enlace" value="logistica-operativa/etiquetas">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold text-muted mb-1">Cliente remisor</label>
                <?php if (($isCliente || $isProveedor) && !$isAdmin): ?>
                <select name="cliente" class="form-select form-select-sm bg-light" disabled>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" selected>🏢 <?= htmlspecialchars($c['nombre']) ?> (Mi Cuenta)</option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select name="cliente" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Todos los clientes --</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filtroCliente === (int)$c['id'] ? 'selected' : '' ?>>
                        🏢 <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end justify-content-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="chkTodos" checked onclick="toggleSeleccionarTodos(this)">
                    <label class="form-check-label fw-bold small text-muted" for="chkTodos">Seleccionar todos los paquetes</label>
                </div>
            </div>
        </form>
    </div>
</div>

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

            <!-- Datos Cliente -->
            <div class="small text-muted mb-1">REMITENTE:</div>
            <div class="fw-bold text-dark mb-2">🏢 <?= htmlspecialchars((string)($p['cliente_nombre'] ?? '')) ?></div>

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

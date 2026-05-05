<?php include("vista/includes/header.php") ?>

<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../modelo/conexion.php';
require_once __DIR__ . '/../../../modelo/forwarding.php';

// Filtros
$filtroOrden  = trim($_GET['orden']  ?? '');
$filtroEstado = (int)($_GET['estado'] ?? 0);
$filtroDesde  = trim($_GET['desde']  ?? '');
$filtroHasta  = trim($_GET['hasta']  ?? '');

// Construir WHERE
$where  = ["h.observaciones LIKE 'LogisPro [%]%'"];
$params = [];

if ($filtroOrden) {
    $where[]            = "p.numero_orden LIKE :orden";
    $params[':orden']   = "%$filtroOrden%";
}
if ($filtroEstado) {
    $where[]             = "h.id_estado_nuevo = :estado";
    $params[':estado']   = $filtroEstado;
}
if ($filtroDesde) {
    $where[]             = "DATE(h.created_at) >= :desde";
    $params[':desde']    = $filtroDesde;
}
if ($filtroHasta) {
    $where[]             = "DATE(h.created_at) <= :hasta";
    $params[':hasta']    = $filtroHasta;
}

$whereSQL = implode(' AND ', $where);

try {
    $db   = (new Conexion())->conectar();
    $sql  = "
        SELECT h.id, h.id_pedido, h.id_estado_anterior, h.id_estado_nuevo,
               h.observaciones, h.created_at,
               p.numero_orden,
               ea.nombre AS estado_anterior_nombre,
               en.nombre AS estado_nuevo_nombre
        FROM pedidos_historial_estados h
        LEFT JOIN pedidos p        ON p.id  = h.id_pedido
        LEFT JOIN estados_pedidos ea ON ea.id = h.id_estado_anterior
        LEFT JOIN estados_pedidos en ON en.id = h.id_estado_nuevo
        WHERE $whereSQL
        ORDER BY h.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para el filtro de estado
    $estados = $db->query("SELECT id, nombre FROM estados_pedidos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    // KPIs rápidos
    $totalHoy  = $db->query("SELECT COUNT(*) FROM pedidos_historial_estados WHERE observaciones LIKE 'LogisPro [%]%' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $totalSem  = $db->query("SELECT COUNT(*) FROM pedidos_historial_estados WHERE observaciones LIKE 'LogisPro [%]%' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $totalAll  = $db->query("SELECT COUNT(*) FROM pedidos_historial_estados WHERE observaciones LIKE 'LogisPro [%]%'")->fetchColumn();
} catch (Exception $e) {
    $registros = [];
    $estados   = [];
    $totalHoy = $totalSem = $totalAll = 0;
}
?>

<style>
.fwd-card   { border:none; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.08); overflow:hidden; }
.fwd-header { background:linear-gradient(135deg,#f97316 0%,#ea580c 100%); color:white; padding:1.75rem 2rem; }
.fwd-header h3 { margin:0; font-weight:600; }
.kpi-card   { background:#fff; border-radius:14px; padding:1.25rem; box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #f0f0f5; }
.kpi-icon   { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
.kpi-value  { font-size:1.75rem; font-weight:700; line-height:1; }
.kpi-label  { font-size:0.8rem; color:#6b7280; font-weight:500; margin-top:2px; }
.badge-estado { background:#fff3e0; color:#e65100; font-weight:600; padding:.35em .7em; border-radius:8px; font-size:0.78rem; }
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-7 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-arrow-down-circle fs-3"></i>
                        </div>
                        <div>
                            <h3>Webhooks Recibidos</h3>
                            <p class="mb-0 opacity-75">Actualizaciones de estado enviadas por LogisPro</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-md-end gap-2">
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#fff3e0;color:#e65100;"><i class="bi bi-calendar-day"></i></div>
                    <div>
                        <div class="kpi-value"><?= $totalHoy ?></div>
                        <div class="kpi-label">Hoy</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-calendar-week"></i></div>
                    <div>
                        <div class="kpi-value"><?= $totalSem ?></div>
                        <div class="kpi-label">Últimos 7 días</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#d1fae5;color:#059669;"><i class="bi bi-collection"></i></div>
                    <div>
                        <div class="kpi-value"><?= $totalAll ?></div>
                        <div class="kpi-label">Total histórico</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card fwd-card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Nº Orden</label>
                    <input type="text" name="orden" class="form-control" value="<?= htmlspecialchars($filtroOrden) ?>" placeholder="Buscar orden...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Estado nuevo</label>
                    <select name="estado" class="form-select">
                        <option value="">— Todos —</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filtroEstado == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($filtroDesde) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($filtroHasta) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
                    <a href="<?= RUTA_URL ?>forwarding/webhooks" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card fwd-card">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-list-ul me-2"></i><?= count($registros) ?> registro(s) encontrado(s)
                </h5>
            </div>

            <?php if (empty($registros)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-3 opacity-25 d-block mb-3"></i>
                    <h5>Sin webhooks recibidos</h5>
                    <p>Cuando LogisPro envíe actualizaciones de estado, aparecerán aquí.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tblWebhooks" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Fecha / Hora</th>
                            <th>Nº Orden</th>
                            <th>Estado anterior</th>
                            <th>Estado nuevo</th>
                            <th>Usuario LogisPro</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $r):
                        // Extraer auditUser de la observación
                        preg_match('/LogisPro \[([^\]]+)\]/', $r['observaciones'] ?? '', $mAudit);
                        $auditUser = $mAudit[1] ?? '-';
                    ?>
                        <tr>
                            <td>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small><br>
                                <strong><?= date('H:i:s', strtotime($r['created_at'])) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-semibold">#<?= htmlspecialchars($r['numero_orden'] ?? '-') ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($r['estado_anterior_nombre'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge-estado"><?= htmlspecialchars($r['estado_nuevo_nombre'] ?? '-') ?></span>
                            </td>
                            <td>
                                <code class="text-dark" style="font-size:0.8rem;"><?= htmlspecialchars($auditUser) ?></code>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($r['observaciones'] ?? '') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
$(document).ready(function() {
    if ($('#tblWebhooks').length) {
        $('#tblWebhooks').DataTable({
            responsive: true,
            language: { url: '//cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [{ orderable: false, targets: [5] }]
        });
    }
});
</script>

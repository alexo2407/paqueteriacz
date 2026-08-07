<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../utils/csrf.php';
require_once __DIR__ . '/../../../modelo/webhook.php';
require_once __DIR__ . '/../../../modelo/usuario.php';

start_secure_session();
require_login();

if (!isSuperAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── Acciones POST ──────────────────────────────────────────────────
$mensaje = $tipoMensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    try {
        switch ($_POST['accion']) {
            case 'crear':
                WebhookModel::crear($_POST);
                $mensaje = 'Webhook creado correctamente.'; $tipoMensaje = 'success'; break;
            case 'editar':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) { WebhookModel::actualizar($id, $_POST); $mensaje = 'Webhook actualizado.'; $tipoMensaje = 'success'; }
                break;
            case 'eliminar':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) { WebhookModel::eliminar($id); $mensaje = 'Webhook eliminado.'; $tipoMensaje = 'warning'; }
                break;
            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) { WebhookModel::toggleActivo($id); $mensaje = 'Estado actualizado.'; $tipoMensaje = 'info'; }
                break;
        }
    } catch (Exception $e) { $mensaje = 'Error: ' . $e->getMessage(); $tipoMensaje = 'danger'; }

    if ($mensaje) {
        $_SESSION['flash_msg']  = $mensaje;
        $_SESSION['flash_type'] = $tipoMensaje;
        header('Location: ' . RUTA_URL . 'webhooks'); exit;
    }
}

if (isset($_SESSION['flash_msg'])) {
    $mensaje     = $_SESSION['flash_msg'];
    $tipoMensaje = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// ── Datos para la página ────────────────────────────────────────────
$configs  = WebhookModel::listarConfigs();
$um       = new UsuarioModel();
$usuarios = $um->mostrarUsuarios();

// Filtros activos (para resaltar pills y pasar al DataTable)
$filtroDesde   = trim($_GET['fecha_desde'] ?? '');
$filtroHasta   = trim($_GET['fecha_hasta'] ?? '');
$filtroWebhook = (int)($_GET['id_webhook'] ?? 0);
$filtroStatus  = trim($_GET['status']      ?? '');
if ($filtroDesde  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroDesde))  $filtroDesde  = '';
if ($filtroHasta  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroHasta))  $filtroHasta  = '';
if (!in_array($filtroStatus, ['', 'ok', 'error', 'pending']))              $filtroStatus = '';
$hayFiltros = ($filtroDesde || $filtroHasta || $filtroWebhook || $filtroStatus);
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.wh-stat-card{border-radius:14px;padding:1.15rem 1.4rem;display:flex;align-items:center;gap:1rem;box-shadow:0 2px 10px rgba(0,0,0,.07);transition:transform .18s,box-shadow .18s}
.wh-stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.wh-stat-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.wh-stat-label{font-size:.78rem;color:#6c757d;text-transform:uppercase;letter-spacing:.5px}
.wh-stat-value{font-size:1.8rem;font-weight:800;line-height:1}
.wh-cfg-card{border-radius:14px;border:none;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;transition:transform .18s,box-shadow .18s}
.wh-cfg-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.13)}
.wh-cfg-header{padding:.85rem 1.1rem;display:flex;align-items:center;justify-content:space-between}
.wh-cfg-body{padding:1rem 1.1rem}
.wh-cfg-footer{padding:.55rem 1.1rem;font-size:.78rem;color:#6c757d;border-top:1px solid rgba(0,0,0,.06);background:#fafafa}
.badge-status{font-size:.72rem;padding:.3em .6em;border-radius:20px;font-weight:600;letter-spacing:.3px}
.wh-filter-panel{background:#f8f9fa;border:1px solid #e9ecef;border-radius:14px;padding:1.1rem 1.4rem}
.wh-filter-panel label{font-size:.82rem;font-weight:600;color:#495057}
#tablaWebhooks thead th{font-size:.82rem;font-weight:700;white-space:nowrap;background:#f8f9fa}
#tablaWebhooks tbody td{vertical-align:middle;font-size:.85rem}
#btnsFechaRapida{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.65rem}
#btnsFechaRapida .btn{font-size:.75rem;padding:.2rem .55rem}
/* Spinner de carga DataTable */
.dt-processing{font-weight:600;color:#0B4EA2}
</style>

<div class="container-fluid py-4">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>Webhooks</h2>
      <p class="text-muted mb-0 small">Configuración y monitoreo de notificaciones a APIs externas</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalFormWebhook" onclick="limpiarFormulario()">
        <i class="bi bi-plus-circle me-1"></i> Nuevo Webhook
      </button>
      <a href="<?= RUTA_URL ?>dashboard" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>

  <?php if ($mensaje): ?>
  <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show">
    <i class="bi bi-<?= $tipoMensaje === 'success' ? 'check-circle' : ($tipoMensaje === 'danger' ? 'x-circle' : 'info-circle') ?>"></i>
    <?= htmlspecialchars($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Stats cards (se actualizan via JS después de carga DT) -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="wh-stat-card" style="background:#e8f5e9">
        <div class="wh-stat-icon" style="background:#4caf50;color:#fff"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="wh-stat-label">Exitosos</div><div class="wh-stat-value text-success" id="statOk">—</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wh-stat-card" style="background:#fce4ec">
        <div class="wh-stat-icon" style="background:#e53935;color:#fff"><i class="bi bi-x-circle-fill"></i></div>
        <div><div class="wh-stat-label">Errores</div><div class="wh-stat-value text-danger" id="statError">—</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wh-stat-card" style="background:#fff8e1">
        <div class="wh-stat-icon" style="background:#f9a825;color:#fff"><i class="bi bi-clock-fill"></i></div>
        <div><div class="wh-stat-label">Pendientes</div><div class="wh-stat-value text-warning" id="statPending">—</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wh-stat-card" style="background:#e3f2fd">
        <div class="wh-stat-icon" style="background:#1e88e5;color:#fff"><i class="bi bi-list-ul"></i></div>
        <div><div class="wh-stat-label">Total logs</div><div class="wh-stat-value text-primary" id="statTotal">—</div></div>
      </div>
    </div>
  </div>

  <!-- Cards Webhooks configurados -->
  <div class="row mb-4">
    <?php if (empty($configs)): ?>
    <div class="col-12">
      <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-info-circle fs-5"></i>
        No hay webhooks configurados. Haz clic en <strong class="mx-1">"Nuevo Webhook"</strong> para agregar uno.
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($configs as $cfg): ?>
    <div class="col-md-6 col-lg-4 mb-3">
      <div class="wh-cfg-card card h-100">
        <div class="wh-cfg-header <?= $cfg['activo'] ? 'bg-success' : 'bg-secondary' ?> text-white">
          <strong><i class="bi bi-broadcast me-1"></i><?= htmlspecialchars($cfg['nombre']) ?></strong>
          <div class="d-flex gap-1">
            <form method="POST" class="d-inline"><?= csrf_field() ?>
              <input type="hidden" name="accion" value="toggle">
              <input type="hidden" name="id" value="<?= $cfg['id'] ?>">
              <button type="submit" class="btn btn-sm btn-<?= $cfg['activo'] ? 'light' : 'warning' ?>" title="<?= $cfg['activo'] ? 'Desactivar' : 'Activar' ?>">
                <i class="bi bi-<?= $cfg['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
              </button>
            </form>
            <button class="btn btn-sm btn-light" onclick="editarWebhook(<?= htmlspecialchars(json_encode($cfg)) ?>)" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-info text-white" title="Ver solo logs de este webhook"
                    onclick="filtrarPorWebhook(<?= $cfg['id'] ?>, '<?= addslashes(htmlspecialchars($cfg['nombre'])) ?>')">
              <i class="bi bi-funnel"></i>
            </button>
            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este webhook y todos sus logs?')"><?= csrf_field() ?>
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= $cfg['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <div class="wh-cfg-body">
          <div class="mb-2"><small class="text-muted">Cliente:</small><br><strong><?= htmlspecialchars($cfg['cliente_nombre'] ?? 'ID: ' . $cfg['id_cliente']) ?></strong></div>
          <div class="mb-2"><small class="text-muted">URL Login:</small><br><code class="small text-break"><?= htmlspecialchars($cfg['url_login']) ?></code></div>
          <div class="mb-2"><small class="text-muted">URL Webhook:</small><br><code class="small text-break"><?= htmlspecialchars($cfg['url_webhook']) ?></code></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><small class="text-muted">Auth User:</small><br><code><?= htmlspecialchars($cfg['auth_user']) ?></code></div>
            <div class="col-6"><small class="text-muted">CustomersId:</small><br><code><?= $cfg['customers_id'] ?? 'N/A' ?></code></div>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between">
            <span class="badge bg-success"><i class="bi bi-check-circle"></i> <?= $cfg['total_ok'] ?> OK</span>
            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> <?= $cfg['total_error'] ?> Err</span>
            <span class="badge bg-primary"><i class="bi bi-list"></i> <?= $cfg['total_logs'] ?> Total</span>
          </div>
        </div>
        <div class="wh-cfg-footer"><i class="bi bi-calendar3 me-1"></i>Creado: <?= date('d/m/Y H:i', strtotime($cfg['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Panel Filtros -->
  <div class="wh-filter-panel mb-3">
    <h6 class="mb-2 fw-bold"><i class="bi bi-funnel-fill me-1 text-primary"></i>Filtrar Log de Webhooks</h6>
    <div class="row g-2 align-items-end">
      <div class="col-sm-6 col-md-3">
        <label for="fDesde">Fecha desde</label>
        <input type="date" id="fDesde" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filtroDesde) ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label for="fHasta">Fecha hasta</label>
        <input type="date" id="fHasta" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filtroHasta) ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label for="fWebhook">Webhook</label>
        <select id="fWebhook" class="form-select form-select-sm">
          <option value="">— Todos los webhooks —</option>
          <?php foreach ($configs as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filtroWebhook === (int)$c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-2">
        <label for="fStatus">Estado</label>
        <select id="fStatus" class="form-select form-select-sm">
          <option value="">— Todos —</option>
          <option value="ok"      <?= $filtroStatus === 'ok'      ? 'selected' : '' ?>>✅ OK</option>
          <option value="error"   <?= $filtroStatus === 'error'   ? 'selected' : '' ?>>❌ Error</option>
          <option value="pending" <?= $filtroStatus === 'pending' ? 'selected' : '' ?>>⏳ Pendiente</option>
        </select>
      </div>
      <div class="col-auto d-flex gap-1 align-items-end">
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="aplicarFiltros()">
          <i class="bi bi-search me-1"></i>Buscar
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltros" onclick="limpiarFiltros()"
                style="<?= $hayFiltros ? '' : 'display:none' ?>">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    <!-- Atajos fecha -->
    <div id="btnsFechaRapida">
      <small class="text-muted align-self-center me-1">Atajos:</small>
      <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('hoy')">Hoy</button>
      <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('ayer')">Ayer</button>
      <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('7d')">Últimos 7 días</button>
      <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('30d')">Últimos 30 días</button>
      <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('mes')">Este mes</button>
    </div>

    <!-- Pills filtros activos -->
    <div id="pillsFiltros" class="mt-2 d-flex flex-wrap gap-1 align-items-center" style="<?= $hayFiltros ? '' : 'display:none!important' ?>">
      <small class="text-muted me-1">Filtros activos:</small>
      <span id="pillDesde"   class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="<?= $filtroDesde   ? '' : 'display:none' ?>">Desde: <?= $filtroDesde   ? date('d/m/Y', strtotime($filtroDesde))  : '' ?></span>
      <span id="pillHasta"   class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="<?= $filtroHasta   ? '' : 'display:none' ?>">Hasta: <?= $filtroHasta   ? date('d/m/Y', strtotime($filtroHasta))  : '' ?></span>
      <span id="pillWebhook" class="badge bg-info    bg-opacity-10 text-info    border border-info    border-opacity-25" style="<?= $filtroWebhook ? '' : 'display:none' ?>">Webhook: <?= $filtroWebhook ? (array_column($configs,'nombre','id')[$filtroWebhook] ?? "ID $filtroWebhook") : '' ?></span>
      <span id="pillStatus"  class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="<?= $filtroStatus  ? '' : 'display:none' ?>">Estado: <?= $filtroStatus ? strtoupper($filtroStatus) : '' ?></span>
      <span id="pillTotal"   class="badge bg-secondary bg-opacity-10 text-secondary ms-1"></span>
    </div>
  </div>

  <!-- Tabla (server-side) -->
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#061C4C,#0B4EA2);">
      <h5 class="mb-0 text-white"><i class="bi bi-journal-text me-2"></i>Log de Webhooks</h5>
      <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-light text-primary" id="badgeTotal">Cargando…</span>
        <span class="badge bg-warning text-dark" id="badgeFiltrado" style="<?= $hayFiltros ? '' : 'display:none' ?>">
          <i class="bi bi-funnel-fill me-1"></i>Filtrado
        </span>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="tablaWebhooks" class="table table-hover table-sm mb-0 w-100">
          <thead>
            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Webhook</th>
              <th>Pedido</th>
              <th>Estado Enviado</th>
              <th>Resultado</th>
              <th>HTTP</th>
              <th class="text-center no-sort">Ver</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /container-fluid -->

<!-- ══ Modal detalle log (reutilizable, llenado por JS) ══ -->
<div class="modal fade" id="modalLogDetalle" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" id="modalLogHeader">
        <h5 class="modal-title" id="modalLogTitle"><i class="bi bi-broadcast me-1"></i>Detalle Webhook</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalLogBody">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer text-muted small" id="modalLogFooter"></div>
    </div>
  </div>
</div>

<!-- ══ Modal Crear/Editar Webhook ══ -->
<div class="modal fade" id="modalFormWebhook" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="formWebhook">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" id="formAccion" value="crear">
        <input type="hidden" name="id"     id="formId"     value="">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalTitulo"><i class="bi bi-plus-circle me-1"></i> Nuevo Webhook</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
              <input type="text" name="nombre" id="fNombre" class="form-control" required placeholder="Ej: LogisPro Guatemala">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Cliente <span class="text-danger">*</span></label>
              <select name="id_cliente" id="fIdCliente" class="form-select" required>
                <option value="">Seleccionar cliente...</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?> (ID: <?= $u['id'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">URL Login <span class="text-danger">*</span></label>
              <input type="url" name="url_login" id="fUrlLogin" class="form-control" required placeholder="https://api.ejemplo.com/login">
            </div>
            <div class="col-12">
              <label class="form-label fw-bold">URL Webhook <span class="text-danger">*</span></label>
              <input type="url" name="url_webhook" id="fUrlWebhook" class="form-control" required placeholder="https://api.ejemplo.com/orders/update">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Auth User <span class="text-danger">*</span></label>
              <input type="text" name="auth_user" id="fAuthUser" class="form-control" required placeholder="usuario.api">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Auth Password <span class="text-danger">*</span></label>
              <input type="text" name="auth_password" id="fAuthPassword" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">CustomersId</label>
              <input type="number" name="customers_id" id="fCustomersId" class="form-control" placeholder="ID externo">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" id="fActivo" checked>
                <label class="form-check-label" for="fActivo">Activo</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
// ── Configuración base ─────────────────────────────────────────────
const WH_AJAX_URL = '<?= RUTA_URL ?>ajax/webhook_logs.php';

// Estado de filtros activos (en memoria, sin recargar página)
let filtrosActivos = {
    fecha_desde:  '<?= $filtroDesde ?>',
    fecha_hasta:  '<?= $filtroHasta ?>',
    id_webhook:   '<?= $filtroWebhook ?: '' ?>',
    status:       '<?= $filtroStatus ?>'
};

// ── DataTable server-side ──────────────────────────────────────────
let dt;
$(document).ready(function () {
    dt = $('#tablaWebhooks').DataTable({
        processing:  true,
        serverSide:  true,
        ajax: {
            url:  WH_AJAX_URL,
            type: 'GET',
            data: function (d) {
                // Agregar filtros personalizados a cada request de DT
                d.fecha_desde = filtrosActivos.fecha_desde;
                d.fecha_hasta = filtrosActivos.fecha_hasta;
                d.id_webhook  = filtrosActivos.id_webhook;
                d.status      = filtrosActivos.status;
            },
            dataSrc: function (json) {
                // Actualizar stats desde la respuesta
                actualizarStats(json);
                return json.data;
            },
            error: function (xhr, err, thrown) {
                console.error('[DataTable] Error AJAX:', err, thrown);
                $('#tablaWebhooks tbody').html(
                    '<tr><td colspan="8" class="text-center text-danger py-3">' +
                    '<i class="bi bi-exclamation-triangle me-1"></i>Error cargando datos. ' +
                    '<button class="btn btn-sm btn-outline-danger ms-2" onclick="dt.ajax.reload()">Reintentar</button>' +
                    '</td></tr>'
                );
            }
        },
        order: [[1, 'desc']],
        pageLength: 50,
        lengthMenu: [25, 50, 100, 250, 500],
        language: { url: 'https://cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.7/i18n/es-ES.json' },
        responsive: true,
        columns: [
            // 0: id
            {
                data: 'id',
                render: d => `<span class="text-muted small">${d}</span>`
            },
            // 1: fecha
            {
                data: 'created_at',
                render: d => `<small>${formatFecha(d)}</small>`
            },
            // 2: webhook / cliente_nombre
            {
                data: 'cliente_nombre',
                render: d => `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:.73rem">${esc(d)}</span>`
            },
            // 3: pedido
            {
                data: 'numero_orden',
                render: (d, type, row) =>
                    `<a href="${RUTA_URL}logistica/ver/${row.id_pedido}" class="text-decoration-none fw-semibold">#${esc(d)}</a>`
            },
            // 4: estado enviado
            {
                data: 'estado_enviado',
                render: d => `<span class="badge bg-secondary bg-opacity-75 badge-status">${esc(d)}</span>`
            },
            // 5: status (resultado)
            {
                data: 'status',
                render: d => {
                    const map = {
                        ok:      { cls: 'bg-success',              icon: 'bi-check-circle-fill' },
                        error:   { cls: 'bg-danger',               icon: 'bi-x-circle-fill'     },
                        pending: { cls: 'bg-warning text-dark',    icon: 'bi-clock-fill'        }
                    };
                    const v = map[d] || { cls: 'bg-secondary', icon: 'bi-question-circle' };
                    return `<span class="badge badge-status ${v.cls}"><i class="bi ${v.icon}"></i> ${d.toUpperCase()}</span>`;
                }
            },
            // 6: HTTP code
            {
                data: 'response_code',
                render: d => {
                    if (!d) return '<span class="text-muted">—</span>';
                    const cls = (d >= 200 && d < 300) ? 'text-success' : 'text-danger';
                    return `<code class="${cls} fw-bold">${d}</code>`;
                }
            },
            // 7: Acción Ver (no sortable)
            {
                data: 'id',
                orderable: false,
                className: 'text-center',
                render: d =>
                    `<button class="btn btn-sm btn-outline-info btn-ver-log" data-id="${d}" title="Ver detalles">
                        <i class="bi bi-eye"></i>
                    </button>`
            }
        ],
        drawCallback: function (settings) {
            // Actualizar badge del header de la tabla
            const info = this.api().page.info();
            document.getElementById('badgeTotal').textContent =
                info.recordsFiltered.toLocaleString() + ' registros';
        }
    });

    // Delegar clic en botón Ver (generado dinámicamente por DT)
    $('#tablaWebhooks tbody').on('click', '.btn-ver-log', function () {
        const id = $(this).data('id');
        abrirModalLog(id);
    });
});

// ── Actualizar cards de estadísticas ──────────────────────────────
function actualizarStats(json) {
    // Contamos sobre los datos de la página actual si no hay agregados
    // Para stats exactas hacemos una segunda llamada ligera (sin paginación)
    fetch(WH_AJAX_URL + '?action=stats'
        + '&fecha_desde=' + encodeURIComponent(filtrosActivos.fecha_desde)
        + '&fecha_hasta=' + encodeURIComponent(filtrosActivos.fecha_hasta)
        + '&id_webhook='  + encodeURIComponent(filtrosActivos.id_webhook)
        + '&status='      + encodeURIComponent(filtrosActivos.status)
    ).then(r => r.json()).then(data => {
        if (data.stats) {
            document.getElementById('statOk').textContent      = (data.stats.ok      || 0).toLocaleString();
            document.getElementById('statError').textContent   = (data.stats.error   || 0).toLocaleString();
            document.getElementById('statPending').textContent = (data.stats.pending || 0).toLocaleString();
            document.getElementById('statTotal').textContent   = (data.stats.total   || 0).toLocaleString();
            document.getElementById('pillTotal').textContent   = (data.stats.total   || 0).toLocaleString() + ' resultado(s)';
        }
    }).catch(() => {
        // Fallback: usar recordsFiltered del response DT
        const total = json.recordsFiltered || 0;
        document.getElementById('statTotal').textContent = total.toLocaleString();
    });
}

// ── Modal de detalle (carga por AJAX, sin modales duplicados en DOM) ─
function abrirModalLog(id) {
    const header = document.getElementById('modalLogHeader');
    const title  = document.getElementById('modalLogTitle');
    const body   = document.getElementById('modalLogBody');
    const footer = document.getElementById('modalLogFooter');

    // Reset
    header.className = 'modal-header bg-secondary text-white';
    title.innerHTML  = '<i class="bi bi-broadcast me-1"></i>Cargando…';
    body.innerHTML   = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    footer.innerHTML = '';

    new bootstrap.Modal(document.getElementById('modalLogDetalle')).show();

    fetch(WH_AJAX_URL + '?action=detail&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) { body.innerHTML = `<div class="alert alert-danger">${esc(data.error)}</div>`; return; }
            const log = data.log;
            const isOk = log.status === 'ok';
            header.className = `modal-header ${isOk ? 'bg-success' : 'bg-danger'} text-white`;
            title.innerHTML  = `<i class="bi bi-broadcast me-1"></i>Webhook #${log.id} — Pedido #${esc(log.numero_orden)}`;

            const httpColor = (log.response_code >= 200 && log.response_code < 300) ? 'text-success' : 'text-danger';

            let reqJson = '{}', resJson = '{}';
            try { reqJson = JSON.stringify(JSON.parse(log.request_body  || '{}'), null, 2); } catch(e){}
            try { resJson = JSON.stringify(JSON.parse(log.response_body || '{}'), null, 2); } catch(e){}

            body.innerHTML = `
              <div class="row g-2 mb-3">
                <div class="col-sm-4">
                  <div class="p-2 rounded bg-light">
                    <small class="text-muted d-block">Webhook</small>
                    <strong class="small">${esc(log.cliente_nombre || '—')}</strong>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="p-2 rounded bg-light">
                    <small class="text-muted d-block">Estado enviado</small>
                    <strong class="small">${esc(log.estado_enviado)}</strong>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="p-2 rounded bg-light">
                    <small class="text-muted d-block">Resultado HTTP</small>
                    <code class="${httpColor}">${log.response_code || 'N/A'}</code>
                  </div>
                </div>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <h6 class="small fw-bold text-uppercase text-muted mb-1">Request Body</h6>
                  <pre class="bg-dark text-light p-3 rounded" style="max-height:280px;overflow-y:auto;font-size:.78rem">${escHtml(reqJson)}</pre>
                </div>
                <div class="col-md-6">
                  <h6 class="small fw-bold text-uppercase text-muted mb-1">Response</h6>
                  <pre class="bg-dark text-light p-3 rounded" style="max-height:280px;overflow-y:auto;font-size:.78rem">${escHtml(resJson)}</pre>
                </div>
              </div>
              ${log.error_message ? `<div class="alert alert-danger mt-3 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i><strong>Error:</strong> ${esc(log.error_message)}</div>` : ''}
            `;
            footer.innerHTML = `
              <span><i class="bi bi-arrow-repeat me-1"></i>Intentos: ${log.intentos}</span>
              <span class="mx-2">|</span>
              <span><i class="bi bi-send me-1"></i>Enviado: ${log.enviado_at ? formatFecha(log.enviado_at) : 'N/A'}</span>
            `;
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger">Error de conexión: ${esc(String(err))}</div>`;
        });
}

// ── Filtros ────────────────────────────────────────────────────────
function aplicarFiltros() {
    filtrosActivos.fecha_desde = document.getElementById('fDesde').value;
    filtrosActivos.fecha_hasta = document.getElementById('fHasta').value;
    filtrosActivos.id_webhook  = document.getElementById('fWebhook').value;
    filtrosActivos.status      = document.getElementById('fStatus').value;

    actualizarPills();
    if (dt) dt.ajax.reload();
}

function limpiarFiltros() {
    document.getElementById('fDesde').value    = '';
    document.getElementById('fHasta').value    = '';
    document.getElementById('fWebhook').value  = '';
    document.getElementById('fStatus').value   = '';
    filtrosActivos = { fecha_desde:'', fecha_hasta:'', id_webhook:'', status:'' };
    actualizarPills();
    if (dt) dt.ajax.reload();
}

function filtrarPorWebhook(id, nombre) {
    document.getElementById('fWebhook').value = id;
    filtrosActivos.id_webhook = String(id);
    actualizarPills();
    if (dt) dt.ajax.reload();
    document.getElementById('tablaWebhooks').scrollIntoView({ behavior: 'smooth' });
}

function actualizarPills() {
    const f    = filtrosActivos;
    const hay  = (f.fecha_desde || f.fecha_hasta || f.id_webhook || f.status);
    const pane = document.getElementById('pillsFiltros');
    const btnX = document.getElementById('btnLimpiarFiltros');
    const bFlt = document.getElementById('badgeFiltrado');

    pane.style.display = hay ? '' : 'none';
    btnX.style.display = hay ? '' : 'none';
    bFlt.style.display = hay ? '' : 'none';

    setPill('pillDesde',   f.fecha_desde ? 'Desde: ' + formatFechaCorta(f.fecha_desde) : '');
    setPill('pillHasta',   f.fecha_hasta ? 'Hasta: ' + formatFechaCorta(f.fecha_hasta) : '');
    setPill('pillWebhook', f.id_webhook  ? 'Webhook: ' + (document.getElementById('fWebhook').selectedOptions[0]?.text || f.id_webhook) : '');
    setPill('pillStatus',  f.status      ? 'Estado: ' + f.status.toUpperCase() : '');
}

function setPill(id, text) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent  = text;
    el.style.display = text ? '' : 'none';
}

function setDateRange(tipo) {
    const today = new Date();
    const fmt   = d => d.toISOString().slice(0,10);
    let desde, hasta = fmt(today);
    if      (tipo === 'hoy')  { desde = fmt(today); }
    else if (tipo === 'ayer') { const y = new Date(today); y.setDate(y.getDate()-1); desde = hasta = fmt(y); }
    else if (tipo === '7d')   { const s = new Date(today); s.setDate(s.getDate()-6); desde = fmt(s); }
    else if (tipo === '30d')  { const m = new Date(today); m.setDate(m.getDate()-29); desde = fmt(m); }
    else if (tipo === 'mes')  { desde = fmt(new Date(today.getFullYear(), today.getMonth(), 1)); }
    document.getElementById('fDesde').value = desde;
    document.getElementById('fHasta').value = hasta;
    aplicarFiltros();
}

// ── Formulario CRUD Webhook ────────────────────────────────────────
function limpiarFormulario() {
    ['fNombre','fUrlLogin','fUrlWebhook','fAuthUser','fAuthPassword','fCustomersId']
        .forEach(id => document.getElementById(id).value = '');
    document.getElementById('formAccion').value = 'crear';
    document.getElementById('formId').value     = '';
    document.getElementById('fIdCliente').value = '';
    document.getElementById('fActivo').checked  = true;
    document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-plus-circle me-1"></i> Nuevo Webhook';
}

function editarWebhook(cfg) {
    document.getElementById('formAccion').value    = 'editar';
    document.getElementById('formId').value        = cfg.id;
    document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-pencil me-1"></i> Editar Webhook';
    document.getElementById('fNombre').value       = cfg.nombre;
    document.getElementById('fIdCliente').value    = cfg.id_cliente;
    document.getElementById('fUrlLogin').value     = cfg.url_login;
    document.getElementById('fUrlWebhook').value   = cfg.url_webhook;
    document.getElementById('fAuthUser').value     = cfg.auth_user;
    document.getElementById('fAuthPassword').value = cfg.auth_password;
    document.getElementById('fCustomersId').value  = cfg.customers_id || '';
    document.getElementById('fActivo').checked     = cfg.activo == 1;
    new bootstrap.Modal(document.getElementById('modalFormWebhook')).show();
}

// ── Helpers ────────────────────────────────────────────────────────
function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escHtml(s) { return esc(s); }

function formatFecha(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ','T'));
    if (isNaN(d)) return s;
    return d.toLocaleDateString('es-GT', { day:'2-digit', month:'2-digit', year:'numeric' })
        + ' ' + d.toLocaleTimeString('es-GT', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}
function formatFechaCorta(s) {
    if (!s) return '';
    const [y,m,d] = s.split('-');
    return `${d}/${m}/${y}`;
}
</script>

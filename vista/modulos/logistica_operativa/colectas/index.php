<?php
/**
 * vista/modulos/logistica_operativa/colectas/index.php
 *
 * Vista principal de colectas.
 * Muestra la tabla de colectas con filtros y el botón "Abrir colecta".
 *
 * Datos: se cargan mediante el endpoint resumen y colectas API (AJAX).
 * La lista inicial se construye con una consulta directa via ColectaModel
 * para mantener el patrón existente de las vistas del sistema.
 *
 * Seguridad:
 *   - El archivo solo se incluye desde rutas/logistica_operativa.php
 *     que ya verificó sesión, permiso y feature flag.
 *   - id_operador se obtiene de la sesión, nunca del formulario.
 *   - CSRF token generado en sesión para el modal de apertura.
 */

declare(strict_types=1);

// ── Dependencias ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../modelo/conexion.php';
require_once __DIR__ . '/../../../../utils/permissions.php';
require_once __DIR__ . '/../../../../modelo/logistica_operativa/ColectaModel.php';
require_once __DIR__ . '/../../../../services/logistica_operativa/ColectaService.php';

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token_colectas'])) {
    $_SESSION['csrf_token_colectas'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token_colectas'];

// ── Filtros ────────────────────────────────────────────────────────────────────
$filtroFecha   = trim($_GET['fecha']    ?? '');
$filtroCliente = trim($_GET['cliente']  ?? '');
$filtroTurno   = strtoupper(trim($_GET['turno']  ?? ''));
$filtroEstado  = strtoupper(trim($_GET['estado'] ?? ''));

// ── Rol y permisos ──────────────────────────────────────────────────────────────
$rolesSession = $_SESSION['roles_nombres'] ?? [];
$isProveedor  = in_array(ROL_NOMBRE_PROVEEDOR, $rolesSession, true) || in_array('Proveedor', $rolesSession, true);
$isAdmin      = in_array(ROL_NOMBRE_ADMIN, $rolesSession, true) || in_array('Administrador', $rolesSession, true);

// ── Lista de colectas desde BD ────────────────────────────────────────────────
$colectas        = [];
$clientes        = [];
$proveedores     = [];
$errorCarga      = null;
$countAbiertas   = 0;
$countConciliadas = 0;
$totalEsperados  = 0;
$totalFaltantes  = 0;

try {
    $db    = (new Conexion())->conectar();
    $colModel = new ColectaModel($db);

    $filtrosQuery = [
        'fecha'   => $filtroFecha   ?: null,
        'turno'   => $filtroTurno   ?: null,
        'estado'  => $filtroEstado  ?: null,
        'cliente' => $filtroCliente ?: null,
    ];

    // Si es proveedor de logística y no admin, filtrar por su id_proveedor
    $currentUserId = getCurrentUserId();
    if ($isProveedor && !$isAdmin && $currentUserId !== null) {
        $filtrosQuery['id_proveedor'] = (int)$currentUserId;
    }

    // Obtener colectas con filtros
    $colectas = $colModel->listarConFiltros($filtrosQuery);

    // Obtener clientes (Rol 4) disponibles con pedidos en estado 11 (Pendiente recolección)
    if ($isProveedor && !$isAdmin && $currentUserId !== null) {
        $stmtClientes = $db->prepare(
            "SELECT u.id, u.nombre
               FROM usuarios u
               JOIN usuarios_roles ur ON ur.id_usuario = u.id
               JOIN pedidos p ON p.id_cliente = u.id
              WHERE ur.id_rol = 4
                AND p.id_proveedor = :id_proveedor
                AND p.id_estado = 11
              GROUP BY u.id, u.nombre
              ORDER BY u.nombre ASC"
        );
        $stmtClientes->execute([':id_proveedor' => $currentUserId]);
        $clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtClientes = $db->query(
            "SELECT u.id, u.nombre
               FROM usuarios u
               JOIN usuarios_roles ur ON ur.id_usuario = u.id
               JOIN pedidos p ON p.id_cliente = u.id
              WHERE ur.id_rol = 4
                AND p.id_estado = 11
              GROUP BY u.id, u.nombre
              ORDER BY u.nombre ASC"
        );
        $clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener lista de proveedores (Rol 5) para el desplegable del Admin
    $proveedores = [];
    if ($isAdmin) {
        $stmtProveedores = $db->query(
            "SELECT u.id, u.nombre
               FROM usuarios u
               JOIN usuarios_roles ur ON ur.id_usuario = u.id
              WHERE ur.id_rol = 5
                AND u.activo = 1
              ORDER BY u.nombre ASC"
        );
        $proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calcular contadores KPI para el header
    foreach ($colectas as $c) {
        if (($c['estado'] ?? '') === 'ABIERTA') {
            $countAbiertas++;
            $totalEsperados += (int)($c['cantidad_esperada'] ?? 0);
            $totalFaltantes += (int)($c['cantidad_faltante'] ?? 0);
        } elseif (($c['estado'] ?? '') === 'CONCILIADA') {
            $countConciliadas++;
        }
    }

} catch (Throwable $e) {
    error_log('[colectas/index] Error al cargar datos: ' . $e->getMessage());
    $errorCarga = 'No se pudo cargar la lista de colectas.';
}

// ── Helpers de badge ──────────────────────────────────────────────────────────
if (!function_exists('badgeEstado')) {
    function badgeEstado(string $estado): string
    {
        return match ($estado) {
            'ABIERTA'     => '<span class="badge badge-outline-success">ABIERTA</span>',
            'CONCILIADA'  => '<span class="badge badge-outline-secondary">✓ CONCILIADA</span>',
            default       => '<span class="badge bg-light text-dark">' . htmlspecialchars($estado) . '</span>',
        };
    }
}

if (!function_exists('badgeTurno')) {
    function badgeTurno(string $turno): string
    {
        return match ($turno) {
            'MANANA' => '<span class="badge badge-turno-manana"><i class="bi bi-sun me-1"></i>Mañana</span>',
            'TARDE'  => '<span class="badge badge-turno-tarde"><i class="bi bi-moon me-1"></i>Tarde</span>',
            default  => '<span class="badge bg-light text-dark">' . htmlspecialchars($turno) . '</span>',
        };
    }
}

$pageTitle = 'Colectas — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-folder-fill me-2 text-warning"></i>Colectas
        </h1>
        <small class="text-muted">Logística Operativa &mdash; Modo sombra activo <i class="bi bi-info-circle ms-1" title="Registra sin modificar pedidos, inventario ni stock"></i></small>
    </div>
    <button class="btn btn-warning fw-bold px-3 shadow-sm text-dark"
            data-bs-toggle="modal"
            data-bs-target="#modalAbrirColecta"
            id="btnAbrirColecta">
        <i class="bi bi-plus-circle me-1"></i>Abrir colecta
    </button>
</div>

<!-- ═══ 4 Tarjetas KPI Superiores ═══ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-blue me-3">
                    <i class="bi bi-inbox"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= $countAbiertas ?></div>
                    <div class="fw-semibold text-primary small">Abiertas</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Colectas activas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-green me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= $countConciliadas ?></div>
                    <div class="fw-semibold text-success small">Conciliadas</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Completadas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-purple me-3">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold"><?= number_format($totalEsperados) ?></div>
                    <div class="fw-semibold text-purple small" style="color:#9333ea;">Paquetes esperados</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Total en colectas abiertas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-kpi p-3">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-circle kpi-icon-red me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="h3 mb-0 fw-bold text-danger"><?= $totalFaltantes ?></div>
                    <div class="fw-semibold text-danger small">Faltantes</div>
                    <div class="text-muted small" style="font-size:0.75rem;">En colectas abiertas</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($errorCarga): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorCarga) ?></div>
<?php endif; ?>

<!-- ═══ Filtros ═══ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <input type="hidden" name="enlace" value="logistica-operativa/colectas">
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold mb-1">Fecha</label>
                <input type="date" name="fecha" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filtroFecha) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold mb-1">Turno</label>
                <select name="turno" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="MANANA" <?= $filtroTurno === 'MANANA' ? 'selected' : '' ?>>Mañana</option>
                    <option value="TARDE"  <?= $filtroTurno === 'TARDE'  ? 'selected' : '' ?>>Tarde</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="ABIERTA"    <?= $filtroEstado === 'ABIERTA'    ? 'selected' : '' ?>>Abierta</option>
                    <option value="CONCILIADA" <?= $filtroEstado === 'CONCILIADA' ? 'selected' : '' ?>>Conciliada</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Cliente</label>
                <input type="text" name="cliente" class="form-control form-control-sm"
                       placeholder="Nombre o ID..."
                       value="<?= htmlspecialchars($filtroCliente) ?>">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                    <i class="bi bi-search me-1"></i>Buscar
                </button>
                <a href="?enlace=logistica-operativa/colectas" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Tabla de colectas ═══ -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-navy align-middle mb-0" id="tablaColectas">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Fecha <i class="bi bi-arrow-down-up ms-1 small opacity-50"></i></th>
                        <th>Turno</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th class="text-center">Esperados</th>
                        <th class="text-center">Escaneados</th>
                        <th class="text-center">Faltantes</th>
                        <th>Operador</th>
                        <th>Apertura</th>
                        <th style="width:80px" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($colectas)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-collection display-6 opacity-25 d-block mb-2"></i>
                            No hay colectas<?= ($filtroFecha || $filtroTurno || $filtroEstado || $filtroCliente) ? ' con los filtros seleccionados.' : ' registradas aún.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($colectas as $c): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$c['id'] ?></td>
                        <td><?= htmlspecialchars((string)($c['fecha'] ?? '')) ?></td>
                        <td><?= badgeTurno($c['turno'] ?? '') ?></td>
                        <td><?= htmlspecialchars((string)($c['cliente_nombre'] ?? $c['id_cliente'] ?? '—')) ?></td>
                        <td><?= badgeEstado($c['estado'] ?? '') ?></td>
                        <td class="text-center fw-semibold"><?= (int)($c['cantidad_esperada']  ?? 0) ?></td>
                        <td class="text-center text-success fw-semibold"><?= (int)($c['cantidad_escaneada'] ?? 0) ?></td>
                        <td class="text-center text-danger fw-semibold"><?= (int)($c['cantidad_faltante']  ?? 0) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars((string)($c['operador_nombre'] ?? '—')) ?></td>
                        <td class="small text-muted"><?= isset($c['created_at']) ? date('d/m/y H:i', strtotime($c['created_at'])) : '—' ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>logistica-operativa/colectas/ver/<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══ Modal: Abrir colecta ═══ -->
<?php require_once __DIR__ . '/partials/modal_abrir.php'; ?>

<!-- ═══ Scripts ═══ -->
<script>
const CSRF_TOKEN_COLECTAS = '<?= $csrfToken ?>';
</script>
<script src="<?= RUTA_URL ?>vista/modulos/logistica_operativa/colectas/js/colectas.js?v=<?= filemtime(__FILE__) ?>"></script>

<?php require_once __DIR__ . '/../../../../vista/includes/footer.php'; ?>

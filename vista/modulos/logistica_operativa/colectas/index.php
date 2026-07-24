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

// ── Lista de colectas desde BD ────────────────────────────────────────────────
$colectas    = [];
$clientes    = [];
$errorCarga  = null;

try {
    $db    = (new Conexion())->conectar();
    $colModel = new ColectaModel($db);

    // Obtener colectas con filtros básicos
    $colectas = $colModel->listarConFiltros([
        'fecha'   => $filtroFecha   ?: null,
        'turno'   => $filtroTurno   ?: null,
        'estado'  => $filtroEstado  ?: null,
        'cliente' => $filtroCliente ?: null,
    ]);

    // Obtener clientes disponibles para el select del modal
    $stmtClientes = $db->query(
        "SELECT u.id, u.nombre
           FROM usuarios u
          INNER JOIN pedidos p ON p.id_cliente = u.id
                              AND p.id_estado = 11
          GROUP BY u.id, u.nombre
          ORDER BY u.nombre ASC
         LIMIT 200"
    );
    $clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[colectas/index] Error al cargar datos: ' . $e->getMessage());
    $errorCarga = 'No se pudo cargar la lista de colectas.';
}

// ── Helpers de badge ──────────────────────────────────────────────────────────
function badgeEstado(string $estado): string
{
    return match ($estado) {
        'ABIERTA'     => '<span class="badge bg-success">ABIERTA</span>',
        'CONCILIADA'  => '<span class="badge bg-secondary">CONCILIADA</span>',
        default       => '<span class="badge bg-light text-dark">' . htmlspecialchars($estado) . '</span>',
    };
}

function badgeTurno(string $turno): string
{
    return match ($turno) {
        'MANANA' => '<span class="badge" style="background:#FF8A00;color:#fff"><i class="bi bi-sun me-1"></i>Mañana</span>',
        'TARDE'  => '<span class="badge" style="background:#0B4EA2;color:#fff"><i class="bi bi-moon me-1"></i>Tarde</span>',
        default  => '<span class="badge bg-light text-dark">' . htmlspecialchars($turno) . '</span>',
    };
}

$pageTitle = 'Colectas — Logística Operativa';
?>
<?php require_once __DIR__ . '/../../../../vista/includes/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-collection me-2 text-warning"></i>Colectas
        </h1>
        <small class="text-muted">Logística Operativa &mdash; Modo sombra activo</small>
    </div>
    <button class="btn btn-warning fw-semibold"
            data-bs-toggle="modal"
            data-bs-target="#modalAbrirColecta"
            id="btnAbrirColecta">
        <i class="bi bi-plus-circle me-1"></i>Abrir colecta
    </button>
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
                <label class="form-label form-label-sm fw-semibold">Fecha</label>
                <input type="date" name="fecha" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filtroFecha) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold">Turno</label>
                <select name="turno" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="MANANA" <?= $filtroTurno === 'MANANA' ? 'selected' : '' ?>>Mañana</option>
                    <option value="TARDE"  <?= $filtroTurno === 'TARDE'  ? 'selected' : '' ?>>Tarde</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="ABIERTA"    <?= $filtroEstado === 'ABIERTA'    ? 'selected' : '' ?>>Abierta</option>
                    <option value="CONCILIADA" <?= $filtroEstado === 'CONCILIADA' ? 'selected' : '' ?>>Conciliada</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm fw-semibold">Cliente</label>
                <input type="text" name="cliente" class="form-control form-control-sm"
                       placeholder="Nombre..."
                       value="<?= htmlspecialchars($filtroCliente) ?>">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-search"></i>
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
            <table class="table table-hover align-middle mb-0" id="tablaColectas">
                <thead class="table-dark">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th class="text-center">Esperados</th>
                        <th class="text-center">Escaneados</th>
                        <th class="text-center">Faltantes</th>
                        <th>Operador</th>
                        <th>Apertura</th>
                        <th style="width:80px"></th>
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
                        <td><?= htmlspecialchars($c['fecha'] ?? '') ?></td>
                        <td><?= badgeTurno($c['turno'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cliente_nombre'] ?? $c['id_cliente'] ?? '—') ?></td>
                        <td><?= badgeEstado($c['estado'] ?? '') ?></td>
                        <td class="text-center fw-semibold"><?= (int)($c['cantidad_esperada']  ?? 0) ?></td>
                        <td class="text-center text-success fw-semibold"><?= (int)($c['cantidad_escaneada'] ?? 0) ?></td>
                        <td class="text-center text-danger fw-semibold"><?= (int)($c['cantidad_faltante']  ?? 0) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($c['operador_nombre'] ?? '—') ?></td>
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

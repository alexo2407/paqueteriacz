<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/auditoria.php';

start_secure_session();
require_login();

// Determinar rol del usuario
// NOTA LEGACY: ROL_NOMBRE_PROVEEDOR='Cliente', ROL_NOMBRE_CLIENTE='Proveedor' (invertidos en BD)
// isCliente() = TRUE para quien CREA pedidos (id_cliente en pedidos) = "Proveedor logístico"
// ROL_NOMBRE_PROVEEDOR en sesión = distribuidor (id_proveedor en pedidos)
$esAdmin     = isSuperAdmin();
$esProveedorLogistico = !$esAdmin && isCliente(); // Quien crea pedidos (id_cliente)
$esDistribuidor       = !$esAdmin && !$esProveedorLogistico && in_array(ROL_NOMBRE_PROVEEDOR, $_SESSION['roles_nombres'] ?? [], true);

// Si no es admin, ni proveedor logístico, ni distribuidor → denegar
if (!$esAdmin && !$esProveedorLogistico && !$esDistribuidor) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// Para no-admins: configurar filtro por propietario
$userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
$filtroPropietario = null; // null = sin restricción (admin)

if ($esProveedorLogistico) {
    // isCliente()=TRUE = rol "Proveedor" en BD = distribuidor = id_proveedor en pedidos
    $filtroPropietario = ['campo' => 'id_proveedor', 'uid' => (int)$userId];
} elseif ($esDistribuidor) {
    $filtroPropietario = ['campo' => 'id_cliente', 'uid' => (int)$userId];
}

// Obtener filtros
$tablaFiltro = $_GET['tabla'] ?? '';
$accionFiltro = $_GET['accion'] ?? '';
$usuarioFiltro = $_GET['usuario'] ?? '';
$pedidoFiltro = isset($_GET['pedido']) ? trim($_GET['pedido']) : '';

// Resolver numero_orden -> id interno del pedido
$pedidoIdInterno = null;
$pedidoNoEncontrado = false;
if ($pedidoFiltro !== '') {
    try {
        require_once __DIR__ . '/../../../modelo/conexion.php';
        $dbRes = (new Conexion())->conectar();
        
        // Buscar pedido verificando que pertenece al usuario
        if ($filtroPropietario) {
            $campo = $filtroPropietario['campo'];
            $stmtPed = $dbRes->prepare("SELECT id FROM pedidos WHERE numero_orden = :n AND $campo = :uid LIMIT 1");
            $stmtPed->execute([':n' => (int)$pedidoFiltro, ':uid' => $filtroPropietario['uid']]);
        } else {
            $stmtPed = $dbRes->prepare('SELECT id FROM pedidos WHERE numero_orden = :n LIMIT 1');
            $stmtPed->execute([':n' => (int)$pedidoFiltro]);
        }
        
        $rowPed = $stmtPed->fetch(PDO::FETCH_ASSOC);
        if ($rowPed) {
            $pedidoIdInterno = (int)$rowPed['id'];
        } else {
            $pedidoNoEncontrado = true;
        }
    } catch (Exception $e) {
        $pedidoNoEncontrado = true;
    }
}

// Por defecto: últimos 7 días
$fechaHoy = date('Y-m-d');
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaFin = $_GET['fecha_fin'] ?? $fechaHoy;

// Construir filtros
$filtros = [];

// Si se filtra por pedido: buscar en todas las tablas y sin restricción de fechas
if ($pedidoFiltro !== '' && $pedidoIdInterno !== null) {
    $filtros['id_registro'] = $pedidoIdInterno;
    $filtros['tabla'] = 'pedidos';
    // Aplicar fechas solo si el usuario las cambió manualmente
    if (isset($_GET['fecha_inicio'])) $filtros['fecha_inicio'] = $fechaInicio;
    if (isset($_GET['fecha_fin']))   $filtros['fecha_fin']    = $fechaFin;
} else {
    $filtros['fecha_inicio'] = $fechaInicio;
    $filtros['fecha_fin']    = $fechaFin;
}

if ($tablaFiltro && $pedidoFiltro === '') $filtros['tabla'] = $tablaFiltro;
if ($accionFiltro) $filtros['accion'] = $accionFiltro;
if ($usuarioFiltro) $filtros['id_usuario'] = (int)$usuarioFiltro;

// Para no-admins: forzar tabla=pedidos y filtrar solo sus pedidos via subquery
if ($filtroPropietario !== null) {
    $filtros['tabla'] = 'pedidos';
    $filtros['propietario'] = $filtroPropietario;
}

// Obtener registros de auditoría
$registros = AuditoriaModel::listar($filtros, 500);

// Obtener tablas y usuarios únicos para filtros
$tablasUnicas = AuditoriaModel::obtenerTablasUnicas();
$usuarios = AuditoriaModel::obtenerUsuariosConAuditoria();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Auditoría - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        /* ── Audit modal styles ── */
        .badge-crear     { background-color: #198754; }
        .badge-actualizar{ background-color: #0d6efd; }
        .badge-eliminar  { background-color: #dc3545; }

        /* Country hero banner */
        .audit-country-hero {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .audit-country-hero.known   { background:#f0f7ff; border-color:#2d84e8; }
        .audit-country-hero.local   { background:#f5f5f7; border-color:#8e8ea0; }
        .audit-country-hero.unknown { background:#fffbeb; border-color:#f0a500; }
        .audit-country-hero .hero-icon { font-size:2rem; line-height:1; flex-shrink:0; }
        .audit-country-hero .hero-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .audit-country-hero .hero-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1.2;
        }
        .audit-country-hero .hero-ip {
            margin-left: auto;
            text-align: right;
            flex-shrink: 0;
        }
        .audit-country-hero .hero-ip .hero-label { text-align:right; }
        .audit-country-hero .hero-ip code {
            font-size: .78rem;
            background: rgba(0,0,0,.06);
            padding: 2px 7px;
            border-radius: 4px;
            color: #444;
        }

        /* Metadata info pills */
        .audit-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .audit-info-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 13px;
            border: 1px solid #e9ecef;
        }
        .audit-info-item .ai-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .audit-info-item .ai-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #212529;
        }

        /* JSON diff blocks */
        .audit-diff-block {
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 14px;
            border: 1px solid #e9ecef;
        }
        .audit-diff-header {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .audit-diff-header.antes  { background:#fff1f1; color:#c0392b; border-bottom:1px solid #fad4d4; }
        .audit-diff-header.despues{ background:#f0fff4; color:#1a7f4b; border-bottom:1px solid #c3f0d4; }
        .audit-diff-body {
            background: #fafafa;
            padding: 12px 14px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 12px;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            color: #2d3748;
        }
        .audit-ua-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 9px 13px;
            font-size: .75rem;
            color: #6c757d;
            border: 1px solid #e9ecef;
            word-break: break-all;
        }
        /* Modal header accent */
        .modal-header.audit-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            border-radius: 0;
        }
        .modal-header.audit-header .btn-close { filter: invert(1) grayscale(1) brightness(2); }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-clock-history"></i> Historial de Auditoría</h2>
            <p class="text-muted mb-0">
                <?php if ($esAdmin): ?>
                    Registro de cambios realizados en el sistema
                <?php else: ?>
                    Registro de cambios en tus pedidos
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo RUTA_URL; ?><?= $esAdmin ? 'dashboard' : 'logistica/dashboard' ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($pedidoNoEncontrado): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <span>No se encontró ningún pedido con número de orden <strong><?php echo htmlspecialchars($pedidoFiltro); ?></strong>. Verifica el número e intenta de nuevo.</span>
    </div>
    <?php elseif ($pedidoFiltro !== '' && $pedidoIdInterno !== null): ?>
    <div class="alert alert-info d-flex align-items-center mb-3">
        <i class="bi bi-info-circle-fill me-2"></i>
        <span>Mostrando historial del pedido <strong>#<?php echo htmlspecialchars($pedidoFiltro); ?></strong> &nbsp;<span class="badge bg-secondary">ID interno: <?php echo $pedidoIdInterno; ?></span></span>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">

                    <!-- Búsqueda rápida por pedido -->
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-primary"><i class="bi bi-box-seam"></i> Pedido #</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="number" name="pedido" class="form-control" 
                                   placeholder="ej: 1234"
                                   value="<?php echo htmlspecialchars($pedidoFiltro); ?>"
                                   min="1">
                        </div>
                        <div class="form-text">Filtra por número de pedido</div>
                    </div>

                    <?php if ($esAdmin): ?>
                    <div class="col-md-2">
                        <label class="form-label small">Tabla</label>
                        <select name="tabla" class="form-select select2-searchable" data-placeholder="Seleccionar tabla...">
                            <option value="">Todas las tablas</option>
                            <?php foreach ($tablasUnicas as $tabla): ?>
                                <option value="<?php echo htmlspecialchars($tabla); ?>" <?php echo $tablaFiltro === $tabla ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tabla); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Acción</label>
                        <select name="accion" class="form-select select2-searchable" data-placeholder="Seleccionar acción...">
                            <option value="">Todas las acciones</option>
                            <option value="crear" <?php echo $accionFiltro === 'crear' ? 'selected' : ''; ?>>Crear</option>
                            <option value="actualizar" <?php echo $accionFiltro === 'actualizar' ? 'selected' : ''; ?>>Actualizar</option>
                            <option value="eliminar" <?php echo $accionFiltro === 'eliminar' ? 'selected' : ''; ?>>Eliminar</option>
                        </select>
                    </div>

                    <?php if ($esAdmin): ?>
                    <div class="col-md-2">
                        <label class="form-label small">Usuario</label>
                        <select name="usuario" class="form-select select2-searchable" data-placeholder="Buscar usuario...">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $usuarioFiltro == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="<?php echo RUTA_URL; ?>auditoria/historial" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'crear')); ?></h3>
                    <small>Creaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'actualizar')); ?></h3>
                    <small>Actualizaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h3 class="text-danger"><?php echo count(array_filter($registros, fn($r) => $r['accion'] === 'eliminar')); ?></h3>
                    <small>Eliminaciones</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de registros -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaAuditoria" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <?php if ($esAdmin): ?><th>Tabla</th><?php endif; ?>
                            <th>ID Registro</th>
                            <th>Acción</th>
                            <th>Detalles</th>
                            <?php if ($esAdmin): ?><th>IP</th><th>País</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $reg):
                            $badgeClass = match($reg['accion']) {
                                'crear'      => 'badge-crear',
                                'actualizar' => 'badge-actualizar',
                                'eliminar'   => 'badge-eliminar',
                                default      => 'bg-secondary'
                            };
                            $iconClass = match($reg['accion']) {
                                'crear'      => 'bi-plus-circle',
                                'actualizar' => 'bi-pencil',
                                'eliminar'   => 'bi-trash',
                                default      => 'bi-circle'
                            };
                            // Datos del modal serializados en JSON para el atributo data-*
                            $paisVal   = $reg['pais_origen'] ?? null;
                            $modalData = json_encode([
                                'id'         => $reg['id'],
                                'accion'     => $reg['accion'],
                                'badgeClass' => $badgeClass,
                                'tabla'      => $reg['tabla'],
                                'id_registro'=> $reg['id_registro'],
                                'usuario'    => $reg['usuario_nombre'] ?? 'Sistema',
                                'fecha'      => date('d/m/Y H:i:s', strtotime($reg['created_at'])),
                                'ip'         => $reg['ip_address'] ?? 'N/A',
                                'pais'       => $paisVal,
                                'antes'      => $reg['datos_anteriores'] ? json_decode($reg['datos_anteriores'], true) : null,
                                'despues'    => $reg['datos_nuevos']     ? json_decode($reg['datos_nuevos'], true)     : null,
                                'ua'         => $reg['user_agent'] ?? null,
                            ], JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr>
                            <td><small><?php echo date('d/m/Y H:i:s', strtotime($reg['created_at'])); ?></small></td>
                            <td><strong><?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?></strong></td>
                            <?php if ($esAdmin): ?>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($reg['tabla']); ?></span></td>
                            <?php endif; ?>
                            <td><code>#<?php echo $reg['id_registro']; ?></code></td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <i class="bi <?php echo $iconClass; ?>"></i>
                                    <?php echo ucfirst($reg['accion']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info btn-ver-detalle" type="button"
                                        data-info='<?php echo htmlspecialchars($modalData, ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="bi bi-eye"></i> Ver
                                </button>
                            </td>
                            <?php if ($esAdmin): ?>
                            <td><small class="text-muted"><?php echo htmlspecialchars($reg['ip_address'] ?? 'N/A'); ?></small></td>
                            <td>
                                <?php
                                    $pais = $reg['pais_origen'] ?? null;
                                    if ($pais === 'Local')   echo '<span class="badge bg-secondary">🏠 Local</span>';
                                    elseif ($pais)           echo '<small>' . htmlspecialchars($pais) . '</small>';
                                    else                     echo '<small class="text-muted">—</small>';
                                ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div><!-- /container-fluid -->

<!-- ════════════════════════════════════════
     ÚNICO modal — se llena con JS al click
     ════════════════════════════════════════ -->
<div class="modal fade" id="auditModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.25)">
            <div class="modal-header audit-header py-3">
                <div>
                    <span style="font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;opacity:.6;font-weight:600">Registro de Auditoría</span>
                    <h5 class="modal-title mb-0" style="font-size:1rem;font-weight:700" id="amTitle">—</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Hero país -->
                <div id="amHero" class="audit-country-hero">
                    <span class="hero-icon" id="amIcon">🌍</span>
                    <div>
                        <div class="hero-label" id="amSub"></div>
                        <div class="hero-value" id="amPais"></div>
                    </div>
                    <div class="hero-ip">
                        <div class="hero-label">Dirección IP</div>
                        <code id="amIp"></code>
                    </div>
                </div>
                <!-- Grid metadata -->
                <div class="audit-info-grid">
                    <div class="audit-info-item">
                        <div class="ai-label"><i class="bi bi-person me-1"></i>Usuario</div>
                        <div class="ai-value" id="amUsuario"></div>
                    </div>
                    <div class="audit-info-item">
                        <div class="ai-label"><i class="bi bi-clock me-1"></i>Fecha y Hora</div>
                        <div class="ai-value" id="amFecha"></div>
                    </div>
                    <div class="audit-info-item">
                        <div class="ai-label"><i class="bi bi-table me-1"></i>Tabla afectada</div>
                        <div class="ai-value" id="amTabla"></div>
                    </div>
                    <div class="audit-info-item">
                        <div class="ai-label"><i class="bi bi-hash me-1"></i>ID Registro</div>
                        <div class="ai-value" id="amIdReg"></div>
                    </div>
                </div>
                <!-- Antes -->
                <div id="amBlockAntes" class="audit-diff-block" style="display:none">
                    <div class="audit-diff-header antes" id="amLabelAntes"><i class="bi bi-dash-circle-fill"></i> Antes del cambio</div>
                    <pre class="audit-diff-body" id="amAntes"></pre>
                </div>
                <!-- Después / Creado / Eliminado -->
                <div id="amBlockDespues" class="audit-diff-block" style="display:none">
                    <div class="audit-diff-header despues" id="amLabelDespues"><i class="bi bi-plus-circle-fill"></i> Después del cambio</div>
                    <pre class="audit-diff-body" id="amDespues"></pre>
                </div>
                <!-- UA -->
                <div id="amBlockUa" style="display:none;margin-top:8px">
                    <div class="ai-label mb-1"><i class="bi bi-laptop me-1"></i>Navegador / Cliente</div>
                    <div class="audit-ua-box" id="amUa"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px">
                <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function () {

    /* DataTables */
    $('#tablaAuditoria').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });

    /* Abrir modal único */
    $(document).on('click', '.btn-ver-detalle', function () {
        var d;
        try { d = JSON.parse(this.dataset.info); } catch(e) { return; }

        /* Título */
        $('#amTitle').html('#' + d.id + ' &mdash; <span class="badge ' + d.badgeClass + ' ms-1">' + cap(d.accion) + '</span>');

        /* Hero banner */
        var hClass, hIcon, hText, hSub;
        if (d.pais === 'Local') {
            hClass = 'local';   hIcon = '🏠'; hText = 'Acceso local';    hSub = 'Red interna';
        } else if (d.pais) {
            hClass = 'known';   hIcon = '🌍'; hText = d.pais;            hSub = 'País de origen del cambio';
        } else {
            hClass = 'unknown'; hIcon = '❓'; hText = 'País desconocido'; hSub = 'No se pudo determinar';
        }
        $('#amHero').removeClass('known local unknown').addClass(hClass);
        $('#amIcon').text(hIcon);
        $('#amPais').text(hText);
        $('#amSub').text(hSub);
        $('#amIp').text(d.ip);

        /* Metadata */
        $('#amUsuario').text(d.usuario);
        $('#amFecha').text(d.fecha);
        $('#amTabla').text(d.tabla);
        $('#amIdReg').text('#' + d.id_registro);

        /* Diffs — etiqueta según tipo de acción */
        var labelAntes, labelDespues, iconAntes, iconDespues;
        switch (d.accion) {
            case 'crear':
                labelAntes   = null;
                iconDespues  = 'bi-plus-circle-fill';
                labelDespues = 'Datos del registro creado';
                break;
            case 'eliminar':
                iconAntes    = 'bi-trash-fill';
                labelAntes   = 'Datos eliminados';
                labelDespues = null;
                break;
            default: // actualizar
                iconAntes    = 'bi-dash-circle-fill';
                labelAntes   = 'Antes del cambio';
                iconDespues  = 'bi-plus-circle-fill';
                labelDespues = 'Después del cambio';
        }
        if (d.antes && labelAntes) {
            $('#amLabelAntes').html('<i class="bi ' + iconAntes + '"></i> ' + labelAntes);
            $('#amAntes').text(JSON.stringify(d.antes, null, 2));
            $('#amBlockAntes').show();
        } else { $('#amBlockAntes').hide(); }

        if (d.despues && labelDespues) {
            $('#amLabelDespues').html('<i class="bi ' + iconDespues + '"></i> ' + labelDespues);
            $('#amDespues').text(JSON.stringify(d.despues, null, 2));
            $('#amBlockDespues').show();
        } else { $('#amBlockDespues').hide(); }

        /* UA */
        if (d.ua) { $('#amUa').text(d.ua); $('#amBlockUa').show(); }
        else      { $('#amBlockUa').hide(); }

        new bootstrap.Modal(document.getElementById('auditModal')).show();
    });

    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
});
</script>
</body>
</html>

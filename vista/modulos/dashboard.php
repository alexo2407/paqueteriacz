<?php 

start_secure_session();
require_once __DIR__ . '/../../utils/permissions.php';

if(!isset($_SESSION['registrado']))
{
    header('location:'.RUTA_URL.'login');
    die();
}
else
{

$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
$isClienteCRM = in_array(ROL_NOMBRE_CLIENTE_CRM, $rolesNombres, true);
$isProveedorCRM = in_array(ROL_NOMBRE_PROVEEDOR_CRM, $rolesNombres, true);

if ($isRepartidor && !$isAdmin) { header('Location: '.RUTA_URL.'seguimiento/listar'); exit; }
if (($isClienteCRM || $isProveedorCRM) && !$isAdmin) { header('Location: '.RUTA_URL.'crm/notificaciones'); exit; }
$isClienteLogistica = in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true);
if ($isClienteLogistica && !$isAdmin) { header('Location: '.RUTA_URL.'pedidos/listar'); exit; }

require_once __DIR__ . '/../../controlador/dashboard.php';
include("vista/includes/header_materialize.php");

$dashboard = new DashboardController();
$datos = $dashboard->obtenerDatosDashboard();

$kpis                 = $datos['kpis'];
$comparativa          = $datos['comparativa'];
$acumulada            = $datos['acumulada'];
$topProductos         = $datos['topProductos'];
$topProductosDetalle  = $datos['topProductosDetalle'];
$recomendacion        = $datos['recomendacion'];
$fechaDesde           = $datos['fechaDesde'];
$fechaHasta           = $datos['fechaHasta'];
$efectividadPaises    = $datos['efectividadPaises'] ?? [];
$clientes             = $datos['clientes'] ?? [];
$paises               = $datos['paises'] ?? [];
$proveedoresMensajeria= $datos['proveedoresMensajeria'] ?? [];
$clienteIdFiltro      = $datos['clienteIdFiltro'] ?? null;

// Nombre del cliente filtrado (para mostrar en header)
$clienteNombreFiltro = '';
if ($clienteIdFiltro && !empty($clientes)) {
    foreach ($clientes as $c) {
        if ((int)$c['id'] === $clienteIdFiltro) {
            $clienteNombreFiltro = $c['nombre'];
            break;
        }
    }
}

$fechaDesdeFormateada = date('d/m/Y', strtotime($fechaDesde));
$fechaHastaFormateada = date('d/m/Y', strtotime($fechaHasta));
$periodoTexto = "del $fechaDesdeFormateada al $fechaHastaFormateada";

$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$primerNombre = explode(' ', $nombreUsuario)[0];
$hora = (int)date('H');
if ($hora < 12)       { $saludo = 'Buenos días';   $saludoIcon = '☀️'; }
elseif ($hora < 19)   { $saludo = 'Buenas tardes'; $saludoIcon = '🌤️'; }
else                  { $saludo = 'Buenas noches';  $saludoIcon = '🌙'; }
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px; padding: 2rem; color: white;
    margin-bottom: 1.5rem; box-shadow: 0 10px 40px rgba(102,126,234,0.3);
}
.dashboard-header h2 { font-weight: 700; margin-bottom: 0.25rem; }
.dashboard-header p  { opacity: 0.9; margin-bottom: 0; }
.filter-card {
    background: white; border-radius: 16px; padding: 1rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: none; margin-bottom: 1.5rem;
}
.filter-card .form-control { border-radius: 10px; border: 2px solid #e9ecef; padding: .5rem 1rem; }
.filter-card .form-control:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.15); }
.filter-card .btn-primary  { background:linear-gradient(135deg,#667eea,#764ba2); border:none; border-radius:10px; padding:.5rem 1.5rem; }
.filter-card .btn-secondary{ background:#f8f9fa; border:2px solid #e9ecef; color:#6c757d; border-radius:10px; padding:.5rem 1.5rem; }
.kpi-card {
    border:none; border-radius:16px; padding:1.5rem; color:white;
    box-shadow:0 8px 25px rgba(0,0,0,.1); transition:all .3s ease; height:100%;
}
.kpi-card:hover { transform:translateY(-5px); box-shadow:0 15px 35px rgba(0,0,0,.15); }
.kpi-card .kpi-icon  { font-size:2.5rem; opacity:.8; margin-bottom:.5rem; }
.kpi-card .kpi-value { font-size:2rem; font-weight:700; margin-bottom:.25rem; }
.kpi-card .kpi-label { font-size:.9rem; opacity:.9; margin-bottom:.5rem; }
.kpi-card .kpi-desc  { font-size:.8rem; opacity:.75; }
.chart-card {
    background:white; border:none; border-radius:16px;
    box-shadow:0 4px 20px rgba(0,0,0,.06); overflow:hidden; margin-bottom:1.5rem;
}
.chart-card .chart-header { padding:1.25rem 1.5rem; border-bottom:1px solid #f0f0f0; }
.chart-card .chart-header h5 { margin:0; font-weight:600; color:#1a1a2e; display:flex; align-items:center; gap:.5rem; }
.chart-card .chart-header small { color:#6c757d; display:block; margin-top:.25rem; }
.chart-card .chart-body { padding:1.5rem; }
.period-badge {
    background:rgba(255,255,255,.2); padding:.5rem 1rem;
    border-radius:50px; font-size:.85rem; display:inline-flex; align-items:center; gap:.5rem;
}
.efectividad-card {
    background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);
    border-radius:12px; padding:1.5rem; text-align:center; height:100%;
}
.efectividad-value { font-size:2.5rem; font-weight:700; color:#11998e; }
.stat-item { display:flex; align-items:center; justify-content:center; gap:.5rem; margin:.5rem 0; font-size:.9rem; }
.proveedor-bi-card {
    border:none; border-radius:14px; padding:1.25rem 1.5rem;
    box-shadow:0 4px 16px rgba(0,0,0,.07); background:white;
    transition:transform .25s ease, box-shadow .25s ease;
}
.proveedor-bi-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.12); }
.proveedor-bi-card .prov-name  { font-weight:700; font-size:1rem; color:#1a1a2e; }
.proveedor-bi-card .prov-badge { font-size:1.7rem; font-weight:800; }
.efectividad-bar { height:8px; border-radius:50px; background:#f0f0f0; overflow:hidden; }
.efectividad-bar-inner { height:100%; border-radius:50px; transition:width .8s ease; }
.ef-green  { background:linear-gradient(90deg,#11998e,#38ef7d); }
.ef-yellow { background:linear-gradient(90deg,#f7971e,#ffd200); }
.ef-red    { background:linear-gradient(90deg,#f5576c,#f093fb); }
.recomendacion-badge { display:inline-block; padding:.3rem .85rem; border-radius:50px; font-size:.78rem; font-weight:600; }
.recomendacion-badge.best  { background:rgba(17,153,142,.12); color:#11998e; }
.recomendacion-badge.worst { background:rgba(245,87,108,.12); color:#f5576c; }
.top-prod-table td, .top-prod-table th { vertical-align:middle; padding:.65rem .75rem; }
.top-prod-table th { background:#f8f9fa; font-size:.8rem; text-transform:uppercase; color:#8a94a6; }
.rank-badge {
    width:28px; height:28px; border-radius:50%; display:inline-flex;
    align-items:center; justify-content:center; font-weight:700; font-size:.8rem; color:white;
}
.rank-1 { background:linear-gradient(135deg,#f7971e,#ffd200); }
.rank-2 { background:linear-gradient(135deg,#667eea,#764ba2); }
.rank-3 { background:linear-gradient(135deg,#4facfe,#00f2fe); }
.rank-n { background:#ced4da; }
.insight-card {
    background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%);
    border-radius:16px; padding:1.5rem 2rem; color:white;
    box-shadow:0 8px 32px rgba(15,52,96,.35); position:relative; overflow:hidden;
}
.insight-card::before {
    content:''; position:absolute; top:-40px; right:-40px;
    width:160px; height:160px; border-radius:50%; background:rgba(255,255,255,.04);
}
.insight-card .insight-icon  { font-size:2.8rem; margin-bottom:.5rem; }
.insight-card .insight-title { font-size:.85rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em; }
.insight-card .insight-value { font-size:1.6rem; font-weight:800; margin:.25rem 0; }
.insight-card .insight-tip   { font-size:.88rem; opacity:.8; margin-top:.75rem; }
.insight-chip {
    display:inline-block; padding:.25rem .7rem; border-radius:50px;
    background:rgba(255,255,255,.12); font-size:.78rem; margin-top:.5rem;
}
</style>

<div class="container-fluid py-3">

    <!-- Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><?= $saludoIcon ?> <?= $saludo ?>, <?= htmlspecialchars($primerNombre) ?></h2>
                <p>Aquí tienes un resumen de tu negocio
                <?php if ($clienteNombreFiltro): ?>
                    &nbsp;·&nbsp;
                    <span style="background:rgba(255,255,255,0.25);padding:.2rem .75rem;border-radius:50px;font-size:.9rem;">
                        👤 Viendo: <strong><?= htmlspecialchars($clienteNombreFiltro) ?></strong>
                    </span>
                <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="period-badge"><i class="bi bi-calendar3"></i> <?= $periodoTexto ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-card">
        <form method="GET" action="<?= RUTA_URL ?>dashboard" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="fecha_desde" class="form-label small text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= $fechaDesde ?>" required>
            </div>
            <div class="col-md-3">
                <label for="fecha_hasta" class="form-label small text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= $fechaHasta ?>" required>
            </div>
            <?php if ($isAdmin && !empty($clientes)): ?>
            <div class="col-md-3">
                <label for="cliente_id" class="form-label small text-muted mb-1"><i class="bi bi-person me-1"></i>Cliente</label>
                <select id="cliente_id" name="cliente_id" class="form-control" style="border-radius:10px;border:2px solid <?= $clienteIdFiltro ? '#667eea' : '#e9ecef' ?>;">
                    <option value="">— Todos los clientes —</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $clienteIdFiltro ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
            <?php else: ?>
            <div class="col-md-6 d-flex gap-2">
            <?php endif; ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="<?= RUTA_URL ?>dashboard" class="btn btn-secondary"><i class="bi bi-x-lg me-1"></i>Limpiar</a>
            </div>
        </form>
    </div>

    <!-- KPIs -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                <div class="kpi-icon">📊</div>
                <div class="kpi-value"><?= number_format($kpis['efectividad_global'],1) ?>%</div>
                <div class="kpi-label">Efectividad Global</div>
                <div class="kpi-desc">Entregas exitosas</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);">
                <div class="kpi-icon">✅</div>
                <div class="kpi-value"><?= number_format($kpis['total_entregados']) ?></div>
                <div class="kpi-label">Entregas Exitosas</div>
                <div class="kpi-desc">Pedidos entregados</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);">
                <div class="kpi-icon">🚚</div>
                <div class="kpi-value"><?= number_format($kpis['en_proceso']) ?></div>
                <div class="kpi-label">En Proceso</div>
                <div class="kpi-desc">Pedidos en tránsito</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#fa709a 0%,#fee140 100%);">
                <div class="kpi-icon">↩️</div>
                <div class="kpi-value"><?= number_format($kpis['tasa_devolucion'],1) ?>%</div>
                <div class="kpi-label">Tasa de Devolución</div>
                <div class="kpi-desc">Pedidos devueltos</div>
            </div>
        </div>
    </div>

    <!-- Efectividad por País -->
    <?php if (!empty($efectividadPaises)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-globe text-success"></i> Efectividad por País</h5>
                    <small>Rendimiento de entregas por ubicación</small>
                </div>
                <div class="chart-body">
                    <div class="row">
                        <?php foreach ($efectividadPaises as $pais): ?>
                        <div class="col-md-4 mb-3">
                            <div class="efectividad-card">
                                <h6><?= htmlspecialchars($pais['pais_nombre']) ?></h6>
                                <div class="my-2"><span class="efectividad-value"><?= number_format($pais['efectividad'],1) ?>%</span></div>
                                <div class="stat-item"><i class="bi bi-check-circle text-success"></i><span><?= $pais['entregados'] ?> Entregados</span></div>
                                <div class="stat-item"><i class="bi bi-arrow-return-left text-warning"></i><span><?= $pais['devueltos'] ?> Devueltos</span></div>
                                <div class="stat-item"><i class="bi bi-hourglass-split text-info"></i><span><?= $pais['en_proceso'] ?> En Proceso</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && !empty($efectividadTemporal)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-graph-up-arrow text-primary"></i> Efectividad Temporal</h5>
                    <small>Tendencia de entregas exitosas en el tiempo</small>
                </div>
                <div class="chart-body">
                    <canvas id="efectividadTemporalChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- BI: TOP PRODUCTOS – TABLA DETALLADA                                -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php if (!empty($topProductosDetalle)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="bi bi-boxes text-primary"></i> Productos Más Movidos</h5>
                        <small>Ranking por unidades pedidas · <?= $periodoTexto ?></small>
                    </div>
                    <?php if ($recomendacion): ?>
                    <div class="d-none d-md-block">
                        <span class="badge" style="background:linear-gradient(135deg,#667eea,#764ba2);font-size:.85rem;padding:.5rem 1rem;border-radius:50px;">
                            ⭐ Estrella del período: <?= htmlspecialchars($recomendacion['nombre']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="chart-body p-0">
                    <div class="table-responsive">
                        <table class="table top-prod-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>Producto</th>
                                    <th class="text-center">Unidades</th>
                                    <th class="text-center">Pedidos</th>
                                    <th>Efectividad de entrega</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topProductosDetalle as $i => $prod):
                                $ef = (float)$prod['efectividad'];
                                $efColor = $ef >= 70 ? '#11998e' : ($ef >= 50 ? '#f7971e' : '#f5576c');
                                $rankClass = match($i) { 0 => 'rank-1', 1 => 'rank-2', 2 => 'rank-3', default => 'rank-n' };
                            ?>
                            <tr>
                                <td><span class="rank-badge <?= $rankClass ?>"><?= $i+1 ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($prod['nombre']) ?></div>
                                    <?php if ($i === 0): ?><small class="text-warning">⭐ Más pedido</small>
                                    <?php elseif ($ef >= 70): ?><small class="text-success">✅ Alta efectividad</small>
                                    <?php elseif ($ef < 40 && $ef > 0): ?><small class="text-danger">⚠️ Baja efectividad</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold fs-6"><?= number_format($prod['total_unidades']) ?></span>
                                    <div class="small text-muted">uds.</div>
                                </td>
                                <td class="text-center"><span><?= number_format($prod['total_pedidos']) ?></span></td>
                                <td style="min-width:180px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="efectividad-bar flex-grow-1">
                                            <div class="efectividad-bar-inner <?= $ef >= 70 ? 'ef-green' : ($ef >= 50 ? 'ef-yellow' : 'ef-red') ?>" style="width:<?= min($ef,100) ?>%"></div>
                                        </div>
                                        <span class="fw-bold" style="color:<?= $efColor ?>;min-width:42px;"><?= $ef ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- BI: PROVEEDOR DE MENSAJERÍA POR EFECTIVIDAD                       -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php if (!empty($proveedoresMensajeria)):
        $mejorProveedor = $proveedoresMensajeria[0];
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-truck text-info"></i> Proveedor de Mensajería – Efectividad de Entrega</h5>
                    <small>Rendimiento por proveedor · filtrado por <?= $periodoTexto ?></small>
                </div>
                <div class="chart-body">
                    <div class="alert border-0 mb-4" style="background:linear-gradient(135deg,rgba(17,153,142,0.08),rgba(56,239,125,0.08));border-left:4px solid #11998e !important;border-radius:12px;">
                        <div class="d-flex align-items-center gap-3">
                            <span style="font-size:1.8rem;">🏆</span>
                            <div>
                                <div class="fw-bold text-success">Recomendación: Priorizar a <strong><?= htmlspecialchars($mejorProveedor['proveedor_nombre']) ?></strong></div>
                                <div class="small text-muted"><?= $mejorProveedor['efectividad'] ?>% de efectividad · <?= $mejorProveedor['entregados'] ?> de <?= $mejorProveedor['total_pedidos'] ?> pedidos entregados</div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                    <?php foreach ($proveedoresMensajeria as $idx => $prov):
                        $ef        = (float)$prov['efectividad'];
                        $barClass  = $ef >= 70 ? 'ef-green' : ($ef >= 50 ? 'ef-yellow' : 'ef-red');
                        $badgeColor= $ef >= 70 ? '#11998e'  : ($ef >= 50 ? '#f7971e'  : '#f5576c');
                        $stars     = $ef >= 80 ? '⭐⭐⭐' : ($ef >= 60 ? '⭐⭐' : '⭐');
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="proveedor-bi-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="prov-name"><?= htmlspecialchars($prov['proveedor_nombre']) ?></div>
                                    <div class="small text-muted mt-1"><?= $stars ?></div>
                                </div>
                                <div class="prov-badge" style="color:<?= $badgeColor ?>"><?= $ef ?>%</div>
                            </div>
                            <div class="efectividad-bar mb-3">
                                <div class="efectividad-bar-inner <?= $barClass ?>" style="width:<?= min($ef,100) ?>%"></div>
                            </div>
                            <div class="row g-2 text-center">
                                <div class="col-4"><div class="small text-muted">Total</div><div class="fw-bold"><?= $prov['total_pedidos'] ?></div></div>
                                <div class="col-4"><div class="small text-success">Entregados</div><div class="fw-bold text-success"><?= $prov['entregados'] ?></div></div>
                                <div class="col-4"><div class="small text-danger">Devueltos</div><div class="fw-bold text-danger"><?= $prov['devueltos'] ?></div></div>
                            </div>
                            <?php if ($prov['en_proceso'] > 0): ?>
                            <div class="mt-2 text-center">
                                <span class="badge bg-light text-secondary" style="font-size:.78rem;"><i class="bi bi-hourglass-split"></i> <?= $prov['en_proceso'] ?> en proceso</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($idx === 0 && count($proveedoresMensajeria) > 1): ?>
                            <div class="mt-2"><span class="recomendacion-badge best">✔ Mejor rendimiento</span></div>
                            <?php elseif ($idx === count($proveedoresMensajeria) - 1 && count($proveedoresMensajeria) > 1): ?>
                            <div class="mt-2"><span class="recomendacion-badge worst">⚠ Pendiente de mejora</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- BI: RECOMENDACIÓN DE PRODUCTOS                                     -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($recomendacion): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="insight-card mb-3">
                <div class="insight-icon">🔥</div>
                <div class="insight-title">Producto Estrella del Período</div>
                <div class="insight-value"><?= htmlspecialchars($recomendacion['nombre']) ?></div>
                <div class="d-flex gap-3 mt-1">
                    <span class="insight-chip">📦 <?= $recomendacion['total_unidades'] ?> unidades</span>
                    <span class="insight-chip">🛒 <?= $recomendacion['total_pedidos'] ?> pedidos</span>
                </div>
                <div class="insight-tip">💡 <em>Asegúrate de mantener stock suficiente de <strong><?= htmlspecialchars($recomendacion['nombre']) ?></strong> antes del próximo período.</em></div>
            </div>
        </div>
        <?php
        $conEfectividad = array_filter($topProductosDetalle, fn($p) => (float)$p['efectividad'] > 0);
        if (!empty($conEfectividad)) {
            usort($conEfectividad, fn($a, $b) => (float)$a['efectividad'] <=> (float)$b['efectividad']);
            $peorProd = reset($conEfectividad);
        } else { $peorProd = null; }
        ?>
        <?php if ($peorProd): ?>
        <div class="col-md-6">
            <div class="insight-card" style="background:linear-gradient(135deg,#2d1b69 0%,#1a1a2e 100%);">
                <div class="insight-icon">⚠️</div>
                <div class="insight-title">Producto con Baja Efectividad</div>
                <div class="insight-value"><?= htmlspecialchars($peorProd['nombre']) ?></div>
                <div class="d-flex gap-3 mt-1">
                    <span class="insight-chip">📊 <?= $peorProd['efectividad'] ?>% efectividad</span>
                    <span class="insight-chip">📦 <?= $peorProd['total_unidades'] ?> uds.</span>
                </div>
                <div class="insight-tip">🔍 <em>Revisa las causas de devolución de <strong><?= htmlspecialchars($peorProd['nombre']) ?></strong> para mejorar la efectividad.</em></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- Comparativa + Top Productos (charts)                               -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-graph-up text-primary"></i> Comparativa de Efectividad</h5>
                    <small>% de entregas exitosas: período actual vs anterior</small>
                </div>
                <div class="chart-body">
                    <canvas id="comparativaChart" height="130"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-trophy text-warning"></i> Top 5 Productos</h5>
                    <small>Productos más pedidos en el período</small>
                </div>
                <div class="chart-body">
                    <canvas id="productosChart" height="280"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Entregas Acumuladas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h5><i class="bi bi-bar-chart-line text-success"></i> Entregas Acumuladas</h5>
                    <small>Evolución de entregas exitosas <?= $periodoTexto ?></small>
                </div>
                <div class="chart-body">
                    <canvas id="acumuladoChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dashboardData = {
    comparativa: <?= json_encode($comparativa) ?>,
    topProductos: {
        nombres:    <?= json_encode($topProductos['nombres']) ?>,
        cantidades: <?= json_encode($topProductos['cantidades']) ?>
    },
    acumulada: <?= json_encode($acumulada) ?>
};

<?php if ($isAdmin && !empty($efectividadTemporal)): ?>
new Chart(document.getElementById('efectividadTemporalChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($efectividadTemporal, 'fecha')) ?>,
        datasets: [{
            label: '% Efectividad',
            data: <?= json_encode(array_column($efectividadTemporal, 'efectividad')) ?>,
            borderColor: '#11998e',
            backgroundColor: 'rgba(17,153,142,0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#11998e'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
    }
});
<?php endif; ?>

</script>
<script src="js/dashboard.js?v=<?= time() ?>"></script>
<?php

include("vista/includes/footer_materialize.php");

}

?>

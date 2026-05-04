<?php include("vista/includes/header.php") ?>

<?php
require_once __DIR__ . '/../../../modelo/forwarding.php';
$stats = ForwardingModel::obtenerEstadisticas();
$proveedores = ForwardingModel::obtenerProveedores(true);
$reglasRecientes = ForwardingModel::obtenerTodasLasReglas();
$logsRecientes = ForwardingModel::obtenerLogs([], 5, 0);
?>

<style>
.fwd-card { border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
.fwd-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.75rem 2rem; }
.fwd-header h3 { margin: 0; font-weight: 600; }
.kpi-card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #f0f0f5; transition: transform 0.2s, box-shadow 0.2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
.kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
.kpi-value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
.kpi-label { font-size: 0.8rem; color: #6b7280; font-weight: 500; margin-top: 2px; }
.quick-link { display: flex; align-items: center; gap: 12px; padding: 1rem 1.25rem; border-radius: 12px; text-decoration: none; color: #1a1a2e; transition: all 0.2s; border: 1px solid #e5e7eb; background: #fff; }
.quick-link:hover { background: #f8f9ff; border-color: #667eea; transform: translateX(4px); color: #667eea; }
.quick-link i { font-size: 1.3rem; }
.log-status { font-size: 0.75rem; padding: 0.25em 0.6em; border-radius: 6px; font-weight: 600; }
.log-status.success { background: #d1fae5; color: #065f46; }
.log-status.failed { background: #fee2e2; color: #991b1b; }
.log-status.pending { background: #fef3c7; color: #92400e; }
</style>

<div class="container-fluid py-3">
    <!-- Header Card -->
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-7 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-arrow-left-right fs-3"></i>
                        </div>
                        <div>
                            <h3>Forwarding de Pedidos</h3>
                            <p class="mb-0 opacity-75">Gestión de reenvío de pedidos a proveedores externos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-md-end justify-content-center gap-2">
                        <span class="badge bg-white bg-opacity-25 p-2 px-3 fs-6">
                            <i class="bi bi-activity me-1"></i>
                            <?= defined('FORWARDING_ENABLED') && FORWARDING_ENABLED ? 'Sistema Activo' : 'Sistema Inactivo' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #ede9fe; color: #7c3aed;">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <div class="kpi-value"><?= $stats['proveedores_activos'] ?></div>
                        <div class="kpi-label">Proveedores Activos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #dbeafe; color: #2563eb;">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <div>
                        <div class="kpi-value"><?= $stats['reglas_activas'] ?></div>
                        <div class="kpi-label">Reglas Activas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #d1fae5; color: #059669;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="kpi-value"><?= $stats['exitos_hoy'] ?></div>
                        <div class="kpi-label">Exitosos Hoy</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #fee2e2; color: #dc2626;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="kpi-value"><?= $stats['fallos_hoy'] ?></div>
                        <div class="kpi-label">Fallos Hoy</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Links -->
        <div class="col-md-4">
            <div class="card fwd-card h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-lightning me-2"></i>Acceso Rápido</h5>
                    <div class="d-flex flex-column gap-2">
                        <a href="<?= RUTA_URL ?>forwarding/proveedores" class="quick-link">
                            <i class="bi bi-building text-purple"></i>
                            <div>
                                <div class="fw-semibold">Proveedores</div>
                                <small class="text-muted">Gestionar proveedores externos</small>
                            </div>
                        </a>
                        <a href="<?= RUTA_URL ?>forwarding/reglas" class="quick-link">
                            <i class="bi bi-diagram-3 text-primary"></i>
                            <div>
                                <div class="fw-semibold">Reglas de Clientes</div>
                                <small class="text-muted">Asignar clientes a proveedores</small>
                            </div>
                        </a>
                        <a href="<?= RUTA_URL ?>forwarding/logs" class="quick-link">
                            <i class="bi bi-journal-text text-success"></i>
                            <div>
                                <div class="fw-semibold">Historial de Envíos</div>
                                <small class="text-muted">Ver logs de forwarding</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Logs -->
        <div class="col-md-8">
            <div class="card fwd-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Últimos Envíos</h5>
                        <a href="<?= RUTA_URL ?>forwarding/logs" class="btn btn-sm btn-outline-primary">Ver todos</a>
                    </div>
                    <?php if (empty($logsRecientes)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-4 opacity-25 d-block mb-2"></i>
                            <p>No hay envíos registrados aún</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Nº Orden</th>
                                    <th>Proveedor</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($logsRecientes as $log): ?>
                                <tr>
                                    <td><small><?= date('d/m H:i', strtotime($log['created_at'])) ?></small></td>
                                    <td><span class="badge bg-light text-dark border">#<?= htmlspecialchars($log['numero_orden'] ?? '-') ?></span></td>
                                    <td><?= htmlspecialchars($log['provider_nombre'] ?? '-') ?></td>
                                    <td><span class="log-status <?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

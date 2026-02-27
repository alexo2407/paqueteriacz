<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$datos = $crmController->monitor();
$inboxPending = $datos['inbox']['pendientes'] ?? [];
$outboxFailed = $datos['outbox']['fallidos'] ?? [];

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-activity"></i> Monitor del Worker</h2>
        <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Estado de Workers -->
    <div class="row g-3 mb-4">
        <?php foreach ($datos['worker_status'] as $name => $status): ?>
        <div class="col-md-4">
            <div class="card <?= $status['status'] === 'running' ? 'border-success' : 'border-danger' ?> shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-uppercase fw-bold"><?= str_replace(['_', 'worker'], [' ', ''], $name) ?> WORKER</h6>
                        <small class="text-muted">
                            <?php if ($status['last_beat']): ?>
                                <i class="bi bi-clock"></i> <?= date('H:i:s', strtotime($status['last_beat'])) ?>
                            <?php else: ?>
                                <i class="bi bi-exclamation-circle"></i> Inactivo
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($status['status'] === 'running'): ?>
                        <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle"></i> RUNNING</span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill"><i class="bi bi-x-circle"></i> STOPPED</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['inbox_pending'] ?></h3>
                    <p class="mb-0">Inbox Pendientes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['inbox_processed'] ?></h3>
                    <p class="mb-0">Inbox Procesados</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['outbox_pending'] ?></h3>
                    <p class="mb-0">Outbox Pendientes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['outbox_failed'] ?></h3>
                    <p class="mb-0">Webhooks Fallidos</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Colas -->
    <div class="row">
        <div class="col-lg-6">
            <!-- Inbox Pendiente -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Inbox Pendiente/Fallido</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fuente</th>
                                <th>Estado</th>
                                <th>Recibido</th>
                                <th>Error</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inboxPending as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= $msg['source'] ?? 'N/A' ?></td>
                                <td><span class="badge bg-<?= ($msg['status'] ?? '') == 'failed' ? 'danger' : 'warning' ?>"><?= $msg['status'] ?? 'N/A' ?></span></td>
                                <td><?= $msg['received_at'] ?? 'N/A' ?></td>
                                <td class="text-danger small"><?= substr($msg['last_error'] ?? '', 0, 50) ?>...</td>
                                <td>
                                    <?php if(($msg['status'] ?? '') === 'failed'): ?>
                                    <form action="<?= RUTA_URL ?>crm/reintentarInbox/<?= $msg['id'] ?>" method="POST" class="d-inline">
                                        <button class="btn btn-sm btn-outline-primary" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <form action="<?= RUTA_URL ?>crm/eliminarInbox/<?= $msg['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Eliminar mensaje?');">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Outbox Fallido -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Outbox Fallido</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Lead ID</th>
                                <th>Intentos</th>
                                <th>Error</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outboxFailed as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= $msg['event_type'] ?? 'N/A' ?></td>
                                <td><?= $msg['lead_id'] ?? 'N/A' ?></td>
                                <td><?= $msg['attempts'] ?? 'N/A' ?></td>
                                <td class="text-danger small"><?= substr($msg['last_error'] ?? '', 0, 50) ?>...</td>
                                <td>
                                    <form action="<?= RUTA_URL ?>crm/reintentarOutbox/<?= $msg['id'] ?>" method="POST" class="d-inline">
                                        <button class="btn btn-sm btn-outline-primary" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                    </form>
                                    <form action="<?= RUTA_URL ?>crm/eliminarOutbox/<?= $msg['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Eliminar mensaje?');">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Limpieza de Datos -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-trash3"></i> Limpieza de Datos</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Información:</strong> 
                        Elimina registros antiguos que ya no son necesarios para liberar espacio en la base de datos.
                        Usa "Vista Previa" para ver qué se eliminará antes de ejecutar.
                    </div>

                    <!-- Estadísticas de Limpieza -->
                    <div class="row g-3 mb-4" id="cleanup-stats">
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h4 class="mb-0" id="stat-inbox">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </h4>
                                    <small class="text-muted">Inbox Procesado (>90d)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <h4 class="mb-0" id="stat-outbox">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </h4>
                                    <small class="text-muted">Outbox Enviado (>90d)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h4 class="mb-0" id="stat-jobs">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </h4>
                                    <small class="text-muted">Jobs Completados (>30d)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-secondary">
                                <div class="card-body text-center">
                                    <h4 class="mb-0" id="stat-notifications">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </h4>
                                    <small class="text-muted">Notificaciones Leídas (>60d)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Controles de Limpieza -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dryRunToggle" checked>
                                    <label class="form-check-label" for="dryRunToggle">
                                        <strong>Modo Vista Previa</strong> (no elimina datos)
                                    </label>
                                </div>
                                <button class="btn btn-primary" onclick="refreshCleanupStats()">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar Estadísticas
                                </button>
                            </div>

                            <div class="btn-group w-100 mb-3" role="group">
                                <button type="button" class="btn btn-outline-warning" onclick="executeCleanup('inbox')">
                                    <i class="bi bi-inbox"></i> Limpiar Inbox
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="executeCleanup('outbox')">
                                    <i class="bi bi-send"></i> Limpiar Outbox
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="executeCleanup('jobs')">
                                    <i class="bi bi-gear"></i> Limpiar Jobs
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="executeCleanup('notifications')">
                                    <i class="bi bi-bell"></i> Limpiar Notificaciones
                                </button>
                            </div>

                            <button type="button" class="btn btn-danger w-100" onclick="executeCleanup('all')">
                                <i class="bi bi-trash3"></i> Ejecutar Limpieza Completa
                            </button>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div id="cleanup-results" class="mt-4" style="display: none;">
                        <hr>
                        <h6><i class="bi bi-check-circle"></i> Resultados de Limpieza</h6>
                        <div id="cleanup-results-content"></div>
                    </div>

                    <!-- Progress -->
                    <div id="cleanup-progress" class="mt-4" style="display: none;">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border text-primary me-3" role="status">
                                <span class="visually-hidden">Procesando...</span>
                            </div>
                            <div>
                                <strong>Procesando limpieza...</strong>
                                <p class="mb-0 text-muted small">Por favor espera</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Refrescar estadísticas de limpieza
function refreshCleanupStats() {
    // Ejecutar vista previa de cada tipo para obtener conteos
    ['inbox', 'outbox', 'jobs', 'notifications'].forEach(type => {
        fetch('<?= RUTA_URL ?>api/crm/cleanup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                dry_run: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.results.count || 0;
                document.getElementById(`stat-${type}`).textContent = count.toLocaleString();
            }
        })
        .catch(error => {
            console.error(`Error fetching ${type} stats:`, error);
            document.getElementById(`stat-${type}`).textContent = 'Error';
        });
    });
}

// Ejecutar limpieza
async function executeCleanup(type) {
    const dryRun = document.getElementById('dryRunToggle').checked;
    const actionText = dryRun ? 'vista previa' : 'eliminación';
    const typeNames = {
        'inbox': 'Inbox',
        'outbox': 'Outbox',
        'jobs': 'Jobs',
        'notifications': 'Notificaciones',
        'all': 'Limpieza Completa'
    };

    // Confirmación con SweetAlert2 si no es dry-run
    if (!dryRun) {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            html: `Esta acción eliminará datos de <strong>${typeNames[type]}</strong>.<br>Esta operación <strong>no se puede deshacer</strong>.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) {
            return;
        }
    }

    // Mostrar progreso
    document.getElementById('cleanup-progress').style.display = 'block';
    document.getElementById('cleanup-results').style.display = 'none';

    try {
        const response = await fetch('<?= RUTA_URL ?>api/crm/cleanup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                dry_run: dryRun
            })
        });

        const data = await response.json();
        document.getElementById('cleanup-progress').style.display = 'none';
        
        if (data.success) {
            showResults(data);
            
            // Mostrar notificación de éxito
            if (!dryRun) {
                const totalDeleted = data.type === 'all' 
                    ? Object.values(data.results).reduce((sum, r) => sum + r.deleted, 0)
                    : data.results.deleted;
                
                Swal.fire({
                    title: '¡Limpieza Completada!',
                    html: `Se eliminaron <strong>${totalDeleted.toLocaleString()}</strong> registros en <strong>${data.execution_time_ms}ms</strong>`,
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
                
                // Refrescar estadísticas después de limpieza
                setTimeout(refreshCleanupStats, 1000);
            } else {
                // Modo vista previa
                Swal.fire({
                    title: 'Vista Previa',
                    html: `Se encontraron <strong>${data.results.count?.toLocaleString() || 'varios'}</strong> registros que se eliminarían.<br><small class="text-muted">Desactiva "Vista Previa" para eliminar.</small>`,
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error || 'Error desconocido al ejecutar limpieza',
                icon: 'error',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        document.getElementById('cleanup-progress').style.display = 'none';
        Swal.fire({
            title: 'Error de Conexión',
            text: 'No se pudo conectar con el servidor: ' + error.message,
            icon: 'error',
            confirmButtonColor: '#0d6efd'
        });
        console.error('Error:', error);
    }
}

// Mostrar resultados
function showResults(data) {
    const resultsDiv = document.getElementById('cleanup-results');
    const contentDiv = document.getElementById('cleanup-results-content');
    
    let html = '';
    
    if (data.type === 'all') {
        // Resultados de limpieza completa
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="table-light"><tr><th>Tabla</th><th>Descripción</th><th>Registros Encontrados</th><th>Registros Eliminados</th></tr></thead>';
        html += '<tbody>';
        
        Object.values(data.results).forEach(result => {
            html += `<tr>
                <td><code>${result.table}</code></td>
                <td>${result.description}</td>
                <td class="text-end">${result.count.toLocaleString()}</td>
                <td class="text-end ${result.deleted > 0 ? 'text-success fw-bold' : ''}">${result.deleted.toLocaleString()}</td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
    } else {
        // Resultado individual
        const result = data.results;
        html += `<div class="alert alert-${data.dry_run ? 'info' : 'success'}">`;
        html += `<h6>${result.description}</h6>`;
        html += `<p class="mb-0">`;
        html += `<strong>Registros encontrados:</strong> ${result.count.toLocaleString()}<br>`;
        if (!data.dry_run) {
            html += `<strong>Registros eliminados:</strong> ${result.deleted.toLocaleString()}<br>`;
        }
        html += `</p></div>`;
    }
    
    html += `<p class="text-muted small mb-0">`;
    html += `<i class="bi bi-clock"></i> Tiempo de ejecución: ${data.execution_time_ms}ms`;
    if (data.dry_run) {
        html += ` | <i class="bi bi-eye"></i> Modo Vista Previa (no se eliminó nada)`;
    }
    html += `</p>`;
    
    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

// Cargar estadísticas al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    refreshCleanupStats();
});
</script>

<?php include("vista/includes/footer.php"); ?>

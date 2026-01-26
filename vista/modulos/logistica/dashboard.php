<?php
/**
 * Dashboard Logística (Cliente) - Redesign
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['registrado'])) {
    header('location:' . RUTA_URL . 'login');
    exit;
}

require_once __DIR__ . '/../../../utils/permissions.php';

// Incluir controlador
require_once "controlador/logistica.php";

$controller = new LogisticaController();
// Obtener datos (filtros se aplican solo al historial)
$data = $controller->dashboard();

$notificaciones = $data['notificaciones'];
$historialTotal = $data['historial']; // Esto trae TODO según filtros básica
$filtros = $data['filtros'];
$estadosDisponibles = $data['estados'] ?? [];

// --- LÓGICA DE FILTRADO PARA TABS ---
// Separamos "Activos" de "Historial" (Entregados/Finalizados)
$pedidosActivos = [];
$estadosFinales = ['ENTREGADO', 'CANCELADO', 'LIQUIDADO', 'DEVOLUCION COMPLETA'];

foreach ($historialTotal as $pedido) {
    if (!in_array(strtoupper($pedido['estado']), $estadosFinales)) {
        $pedidosActivos[] = $pedido;
    }
}

// Mapa de Colores Estandarizado y Vibrante
$estadoColores = [
    'EN BODEGA'           => 'primary',           
    'EN RUTA'             => 'info text-dark',    
    'ENTREGADO'           => 'success',           
    'CANCELADO'           => 'danger',            
    'LIQUIDADO'           => 'dark',              
    'DEVOLUCION'          => 'warning text-dark', 
    'DEVOLUCION COMPLETA' => 'warning text-dark', 
    'EN_ESPERA'           => 'secondary',         
    'PENDIENTE'           => 'warning text-dark', 
    'VENDIDO'             => 'success',           
    'RECHAZADO'           => 'danger',            
    'DOMICILIO'           => 'warning text-dark', 
    'DEVUELTO'            => 'danger',            
    'TRANSITO'            => 'info text-dark'     
];

function getBadgeColor($estado, $map) {
    if (empty($estado)) return 'secondary';
    $estadoUpper = strtoupper($estado);
    foreach ($map as $key => $val) {
        if (strpos($estadoUpper, $key) !== false) return $val;
    }
    return 'secondary';
}

// Función Helper para Renderizar Card de Notificación (estilo CRM)
function renderNotificationCard($notif) {
    $time = date('d/m H:i', strtotime($notif['created_at']));
    
    // Determinar cambios
    $cambios = [];
    $datosNuevos = $notif['datos_nuevos'] ?? [];
    $datosViejos = $notif['datos_anteriores'] ?? [];
    
    if (isset($datosNuevos['id_estado']) && isset($datosViejos['id_estado'])) {
        if ($datosNuevos['id_estado'] != $datosViejos['id_estado']) {
            $cambios[] = "Estado actualizado";
        }
    }
    
    $accion = $notif['accion'];
    if ($accion === 'crear') {
        $title = "Nuevo Pedido #" . htmlspecialchars($notif['numero_orden']);
        $icon = '<i class="bi bi-box-seam"></i>';
        $iconClass = 'bg-soft-success';
        $typeClass = 'type-lead';
        $subtitle = "Pedido creado para " . htmlspecialchars($notif['destinatario']);
    } else {
        $title = "Actualización Pedido #" . htmlspecialchars($notif['numero_orden']);
        $icon = '<i class="bi bi-arrow-repeat"></i>';
        $iconClass = 'bg-soft-info';
        $typeClass = 'type-update';
        
        $detalles = empty($cambios) ? "Actualización de datos" : implode(", ", $cambios);
        $subtitle = "$detalles - " . htmlspecialchars($notif['destinatario']);
    }

    ?>
    <div class="col-12">
        <div class="card notif-card <?= $typeClass ?> p-3 h-100">
            <div class="d-flex align-items-start">
                <div class="notif-icon <?= $iconClass ?> flex-shrink-0 me-3">
                    <?= $icon ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1 fw-bold text-dark"><?= $title ?></h6>
                        <small class="text-muted ms-2"><?= $time ?></small>
                    </div>
                    <p class="mb-2 text-muted small"><?= $subtitle ?></p>
                    <a href="<?= RUTA_URL ?>logistica/ver/<?= $notif['pedido_id'] ?>" class="btn btn-sm btn-light border">Ver Detalles</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

include "vista/includes/header.php";
?>

<style>
    .crm-inbox-header {
        background: white;
        border-bottom: 1px solid #e9ecef;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        margin-top: -1.5rem;
    }
    
    #pills-tab {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        padding: 0.5rem !important;
        border-radius: 12px !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        gap: 0.5rem;
    }
    
    .nav-pills .nav-link {
        color: #495057;
        padding: 0.75rem 1.25rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .nav-pills .nav-link:hover:not(.active) {
        background: rgba(255, 255, 255, 0.8);
        color: #0d6efd;
    }
    
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: white;
        font-weight: 600;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }

    .notif-card {
        transition: all 0.2s;
        border: 1px solid #e9ecef;
        border-left: 4px solid transparent; 
        border-radius: 8px;
        background: white;
        margin-bottom: 0.75rem;
    }
    .notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .notif-card.type-lead { border-left-color: #198754; } 
    .notif-card.type-update { border-left-color: #0dcaf0; }
    
    .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .bg-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .bg-soft-info { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
    .btn-detalle {
        border-radius: 50px;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
</style>

<!-- Header de Logística -->
<div class="container-fluid crm-inbox-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-truck me-2 text-primary"></i>Portal Logística
                </h4>
                <p class="mb-0 text-muted small">
                    Gestiona tus pedidos y mantente al día con las actualizaciones.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    
    <?php
    $activeTab = $_GET['tab'] ?? 'pedidos';
    
    $showPedidos = ($activeTab === 'pedidos') ? 'active' : '';
    $showUpdates = ($activeTab === 'updates') ? 'active' : '';
    $showAll = ($activeTab === 'all') ? 'active' : '';
    
    $panePedidos = ($activeTab === 'pedidos') ? 'show active' : '';
    $paneUpdates = ($activeTab === 'updates') ? 'show active' : '';
    $paneAll = ($activeTab === 'all') ? 'show active' : '';
    
    $countActivos = count($pedidosActivos);
    ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4 bg-light p-2 rounded border" id="pills-tab" role="tablist">
        <!-- Tab: Pedidos Activos -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showPedidos ?> d-flex align-items-center gap-2" 
                    id="pills-pedidos-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-pedidos" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=pedidos')">
                <i class="bi bi-box-seam-fill text-primary"></i>
                <span>En Proceso</span>
                <?php if($countActivos > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?= $countActivos ?></span>
                <?php endif; ?>
            </button>
        </li>
        
        <!-- Tab: Actualizaciones -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showUpdates ?> d-flex align-items-center gap-2" 
                    id="pills-updates-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-updates" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=updates')">
                <i class="bi bi-bell-fill text-info"></i>
                <span>Actualizaciones</span>
            </button>
        </li>

        <!-- Tab: Historial -->
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $showAll ?> d-flex align-items-center gap-2" 
                    id="pills-all-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-all" 
                    type="button"
                    onclick="history.pushState(null, '', '?tab=all')">
                <i class="bi bi-archive-fill"></i>
                <span>Historial Completo</span>
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="pills-tabContent">
        
        <!-- TAB: PEDIDOS ACTIVOS (GRID DE CARDS) -->
        <div class="tab-pane fade <?= $panePedidos ?>" id="pills-pedidos" role="tabpanel">
            <?php if (empty($pedidosActivos)): ?>
                <div class="text-center py-5 border rounded bg-light">
                    <i class="bi bi-box2 display-1 text-muted opacity-25"></i>
                    <h5 class="mt-3 text-muted">Sin pedidos activos</h5>
                    <p class="text-muted">No tienes pedidos en tránsito o pendientes por el momento.</p>
                </div>
            <?php else: ?>
                
                <!-- Buscador simple JS -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchActivos" class="form-control" placeholder="Buscar pedido activo (Orden, nombre)...">
                        </div>
                    </div>
                </div>

                <div class="row" id="gridActivos">
                <?php foreach ($pedidosActivos as $p): 
                     $color = getBadgeColor($p['estado'], $estadoColores);
                ?>
                    <div class="col-md-6 col-lg-4 mb-4 card-item">
                        <div class="card h-100 shadow-sm border-0 position-relative" style="transition: transform 0.2s;">
                             <div class="card-body">
                                 <div class="d-flex justify-content-between mb-3">
                                     <span class="badge bg-light text-dark border">
                                         #<?= htmlspecialchars($p['numero_orden']) ?>
                                     </span>
                                     <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($p['estado']) ?></span>
                                 </div>
                                 
                                 <h5 class="card-title fw-bold text-dark mb-1">
                                     <?= htmlspecialchars($p['destinatario']) ?>
                                 </h5>
                                 <div class="d-flex align-items-center text-muted small mb-3">
                                     <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($p['telefono']) ?>
                                 </div>

                                 <hr class="my-3 opacity-25">

                                 <div class="d-flex justify-content-between align-items-end">
                                     <div>
                                         <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Total</small>
                                         <span class="fw-bold fs-5 text-dark">
                                             <?= htmlspecialchars($p['moneda'] ?? 'GTQ') ?> 
                                             <?= number_format($p['precio_total_local'] ?? 0, 2) ?>
                                         </span>
                                     </div>
                                     <a href="<?= RUTA_URL ?>logistica/ver/<?= $p['id'] ?>" class="btn btn-outline-primary rounded-circle" title="Ver Detalles">
                                         <i class="bi bi-arrow-right"></i>
                                     </a>
                                 </div>
                             </div>
                             <div class="card-footer bg-light border-top-0 py-2">
                                 <small class="text-muted">
                                     <i class="bi bi-calendar-event me-1"></i> Ingreso: <?= date('d/m/Y', strtotime($p['fecha_ingreso'])) ?>
                                 </small>
                             </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: ACTUALIZACIONES -->
        <div class="tab-pane fade <?= $paneUpdates ?>" id="pills-updates" role="tabpanel">
            <div class="row">
                <?php if (empty($notificaciones)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-bell-slash display-4 opacity-25"></i>
                        <p class="mt-3">No hay actualizaciones recientes.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $uniqueNotifs = [];
                    foreach ($notificaciones as $notif) {
                        // Como vienen ordenadas por fecha DESC, la primera que encontremos es la más reciente
                        if (!isset($uniqueNotifs[$notif['pedido_id']])) {
                            $uniqueNotifs[$notif['pedido_id']] = $notif;
                        }
                    }
                    
                    foreach ($uniqueNotifs as $notif): 
                        renderNotificationCard($notif);
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: HISTORIAL COMPLETO -->
        <div class="tab-pane fade <?= $paneAll ?>" id="pills-all" role="tabpanel">
            
            <!-- Filtros (Visible solo en historial) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body bg-light rounded">
                    <form method="GET" action="<?= RUTA_URL ?>logistica/dashboard" class="row g-3">
                        <input type="hidden" name="tab" value="all">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Buscar</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Orden, cliente..." value="<?= htmlspecialchars($filtros['search']) ?>">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border" id="tablaHistorial">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>Destinatario</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historialTotal)): ?>
                            <tr><td colspan="6" class="text-center py-4">No se encontraron registros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($historialTotal as $p): 
                                $color = getBadgeColor($p['estado'], $estadoColores);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['numero_orden']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($p['destinatario']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($p['direccion']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($p['fecha_ingreso'])) ?></td>
                                    <td><?= htmlspecialchars($p['moneda']) ?> <?= number_format($p['precio_total_local'], 2) ?></td>
                                    <td><span class="badge bg-<?= $color ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Acciones
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="<?= RUTA_URL ?>logistica/ver/<?= $p['id'] ?>"><i class="bi bi-eye me-2"></i>Ver Detalles</a></li>
                                                <?php if (!in_array(strtoupper($p['estado']), ['ENTREGADO', 'CANCELADO', 'DEVOLUCION COMPLETA', 'LIQUIDADO'])): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-warning" href="#" onclick="event.preventDefault(); openStatusModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['numero_orden']) ?>')">
                                                        <i class="bi bi-arrow-repeat me-2"></i>Cambiar Estado
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Cambiando estado de orden seleccionada.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="">Seleccione un estado...</option>
                            <?php foreach ($estadosDisponibles as $est): ?>
                                <option value="<?= htmlspecialchars($est['nombre_estado']) ?>">
                                    <?= htmlspecialchars($est['nombre_estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Seleccione el nuevo estado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Razón del cambio..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include "vista/includes/footer.php"; ?>

<script>
    // Búsqueda simple en grid activos (cliente side)
    const searchInput = document.getElementById('searchActivos');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const cards = document.querySelectorAll('#gridActivos .card-item');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }
</script>

<script>
    function openStatusModal(id, orden) {
        const form = document.querySelector('#cambiarEstadoModal form');
        form.action = '<?= RUTA_URL ?>logistica/cambiarEstado/' + id;
        
        // Reset form
        form.reset();
        
        const modalEl = document.getElementById('cambiarEstadoModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Manejar el submit del formulario con AJAX
        form.onsubmit = function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            const formData = new FormData(form);
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Error parseando JSON. Respuesta del servidor:', text);
                    throw new Error('La respuesta del servidor no es un JSON válido.');
                }
            })
            .then(data => {
                if (data.success) {
                    modal.hide();
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.message || 'El estado ha sido actualizado.',
                            confirmButtonColor: '#0d6efd'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(data.message || 'Actualizado correctamente');
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo actualizar el estado.',
                            confirmButtonColor: '#0d6efd'
                        });
                    } else {
                        alert(data.message || 'Error al actualizar');
                    }
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'Hubo un error en la comunicación con el servidor.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert('Error en la comunicación con el servidor');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        };
    }
</script>


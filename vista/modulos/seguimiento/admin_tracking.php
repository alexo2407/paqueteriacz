<?php
/**
 * vista/modulos/seguimiento/admin_tracking.php
 * Vista de Seguimiento Administrativo con la interfaz nativa del sistema (Bootstrap 5) y paginación.
 */
include("vista/includes/header.php");

// Verificación de seguridad (Admin o Cliente únicamente)
require_once __DIR__ . '/../../../utils/permissions.php';
$rolesNames = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNames, true);
$sessionRol = $_SESSION['rol'] ?? 0;
$isCliente = isCliente() || $sessionRol == 4 || in_array('Cliente', $rolesNames) || in_array('cliente', $rolesNames);
$currentUserId = getCurrentUserId();

if (!$isAdmin && !$isCliente) {
    echo "<div class='container py-5 text-center'>
            <div class='card shadow-sm border-0 p-5 mx-auto' style='max-width: 600px; border-radius: 20px;'>
                <i class='bi bi-lock-fill text-danger mb-3' style='font-size: 4rem;'></i>
                <h3 class='fw-bold'>Acceso Denegado</h3>
                <p class='text-muted'>Esta sección es exclusiva para usuarios autorizados.</p>
                <div class='mt-4'>
                    <a href='".RUTA_URL."' class='btn btn-primary rounded-pill px-4'>
                        <i class='bi bi-house-door me-1'></i> Volver al Inicio
                    </a>
                </div>
            </div>
          </div>";
    include("vista/includes/footer.php");
    exit;
}

$pedidoController = new PedidosController();
if ($isAdmin) {
    $clientes = $pedidoController->obtenerClientes();
    $proveedores = $pedidoController->obtenerProveedores();
} else {
    $clientes = []; // No se listan otros clientes
    // Solo proveedores que tienen pedidos asignados este cliente
    $proveedores = PedidosModel::obtenerProveedoresPorCliente($currentUserId);
}
?>

<style>
/* Estilos alineados con dashboard.php */
.tracking-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border-radius: 20px; padding: 2.25rem 2.5rem; color: white;
    margin-bottom: 2rem; box-shadow: 0 10px 35px rgba(30,60,114,0.18);
    position: relative; overflow: hidden;
}
.tracking-header::after {
    content: ''; position: absolute; top: -50px; right: -50px;
    width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;
}
.tracking-header h2 { font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.5px; }
.tracking-header p { opacity: 0.85; margin-bottom: 0; font-size: 1.05rem; }

.search-card {
    background: white; border: none; border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 1.75rem;
    margin-bottom: 2.5rem;
}
.search-card label { font-weight: 700; color: #495057; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 0.6rem; display: block; }
.search-card .form-control, .search-card .form-select {
    border-radius: 12px; border: 2px solid #f1f3f5; padding: 0.75rem 1rem;
    font-size: 0.95rem; transition: all 0.2s;
}
.search-card .form-control:focus, .search-card .form-select:focus {
    border-color: #2a5298; box-shadow: 0 0 0 4px rgba(42,82,152,0.08); background: #fff;
}

/* Timeline Robusto Estilo Listado de Pedidos */
.timeline-wrapper { position: relative; padding: 1.5rem 0; }
.timeline-wrapper::before {
    content: ''; position: absolute; left: 1.55rem; top: 0; bottom: 0;
    width: 3px; background: #eaedf0; z-index: 1; border-radius: 10px;
}

.timeline-item { position: relative; margin-bottom: 1.75rem; padding-left: 4.8rem; }
.timeline-dot {
    position: absolute; left: 0.5rem; top: 0.4rem; width: 2.2rem; height: 2.2rem;
    border: 4px solid #fff; border-radius: 50%; z-index: 2;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.9rem;
}
.timeline-dot i { line-height: 1; }

.timeline-card {
    background: #ffffff; border: 1px solid #f1f3f5; border-radius: 18px;
    padding: 1.5rem; box-shadow: 0 3px 12px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}
.timeline-card:hover { transform: translateX(5px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); border-color: #dee2e6; }

.order-group { margin-bottom: 3.5rem; }
.order-group-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #eaedf0;
}
.order-group-header h5 { margin: 0; font-weight: 800; color: #1e3c72; font-size: 1.2rem; }

.status-badge {
    padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 800; font-size: 0.72rem;
    text-transform: uppercase; letter-spacing: 0.8px; display: inline-flex; align-items: center; gap: 0.5rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1); color: white !important; transition: transform 0.2s;
}
.status-badge:hover { transform: scale(1.05); }
.status-badge i { font-size: 0.9rem; }

/* Verde Sólido - Éxito */
.badge-delivered   { background: #10b981; border: 1px solid #059669; }
/* Azul Sólido - En Movimiento */
.badge-shipping    { background: #3b82f6; border: 1px solid #2563eb; }
/* Naranja Sólido - Pendiente/Atención */
.badge-pending     { background: #f59e0b; border: 1px solid #d97706; }
/* Rojo Sólido - Problemas/Cancelado/Devuelto */
.badge-canceled    { background: #ef4444; border: 1px solid #dc2626; }
/* Púrpura Sólido - Reintentos/Reprogramado */
.badge-retry       { background: #8b5cf6; border: 1px solid #7c3aed; }
/* Gris Sólido - Otros/Sistema */
.badge-default     { background: #64748b; border: 1px solid #475569; }

.obs-bubble {
    background: #fffcf0; padding: 1.25rem; border-radius: 14px; margin-top: 1.25rem;
    border-left: 5px solid #ffca28; font-style: italic; font-size: 0.92rem;
    color: #5d4037; box-shadow: inset 0 0 10px rgba(0,0,0,0.01);
}

.btn-search {
    height: 54px; border-radius: 14px; font-weight: 700; background: #1e3c72; 
    border: none; transition: transform 0.2s, background 0.2s;
}
.btn-search:hover { background: #162e58; transform: scale(1.02); }
.btn-search:active { transform: scale(0.98); }

/* Paginación Estilizada */
.pagination { gap: 0.4rem; }
.page-link {
    border-radius: 8px !important; border: 1px solid #e2e8f0; color: #475569;
    padding: 0.6rem 1rem; font-weight: 600; transition: all 0.2s;
}
.page-link:hover { background: #f8fafc; color: #1e3c72; border-color: #cbd5e1; }
.page-item.active .page-link { background: #1e3c72; border-color: #1e3c72; color: #fff; box-shadow: 0 4px 10px rgba(30,60,114,0.2); }
.page-item.disabled .page-link { background: #f1f5f9; color: #94a3b8; }

/* Controles Compactos */
.compact-input { 
    height: 42px !important; border-radius: 10px !important; 
    font-size: 0.88rem !important; border: 1px solid #e2e8f0 !important;
    background-color: #fcfdfe !important;
}
.compact-input:focus { border-color: #1e3c72 !important; box-shadow: 0 0 0 3px rgba(30,60,114,0.1) !important; background-color: #fff !important; }
.compact-btn { 
    height: 42px !important; border-radius: 10px !important; 
    transition: all 0.2s; font-weight: 700;
}
.compact-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="tracking-header">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2><i class="bi bi-geo-fill me-3"></i>Tracking de Estados de Pedido</h2>
                <p class="opacity-75">Visualiza y audita cada movimiento, cambio de estado y motivo del historial logístico.</p>
            </div>
            <div class="col-lg-4 text-md-end pt-3 pt-lg-0">
                <div class="d-inline-flex bg-white rounded-pill p-1 align-items-center shadow-sm border border-white border-opacity-10">
                    <span class="px-3 py-1 fw-bold small text-uppercase ls-1" style="color: #1e3c72 !important;">Seguimiento Administrativo</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros de Búsqueda -->
    <div class="search-card">
        <form id="formTrackingSearch" class="row g-3 align-items-end">
            <!-- Referencia -->
            <div class="<?= $isAdmin ? 'col-xl-3' : 'col-xl-4' ?> col-lg-4 col-md-12">
                <label class="form-label mb-1 text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;"><i class="bi bi-search me-1"></i> REFERENCIA DE PEDIDO</label>
                <input type="text" name="numero_orden" class="form-control compact-input" placeholder="Ej: 100456">
            </div>
            
            <!-- Rango de Fechas -->
            <div class="col-xl-2 col-lg-4 col-md-6">
                <label class="form-label mb-1 text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;"><i class="bi bi-calendar-event me-1"></i> DESDE</label>
                <input type="date" name="fecha_desde" class="form-control compact-input">
            </div>
            <div class="col-xl-2 col-lg-4 col-md-6">
                <label class="form-label mb-1 text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;"><i class="bi bi-calendar-check me-1"></i> HASTA</label>
                <input type="date" name="fecha_hasta" class="form-control compact-input">
            </div>

            <!-- Selects Compuestos -->
            <?php if ($isAdmin): ?>
            <div class="col-xl-2 col-lg-6 col-md-6">
                <label class="form-label mb-1 text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;"><i class="bi bi-person-circle me-1"></i> CLIENTE</label>
                <select name="id_cliente" class="form-select compact-input">
                    <option value="">— Todos —</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="id_cliente" value="<?= (int)$currentUserId ?>">
            <?php endif; ?>

            <div class="<?= $isAdmin ? 'col-xl-2' : 'col-xl-3' ?> col-lg-6 col-md-6">
                <label class="form-label mb-1 text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;"><i class="bi bi-truck me-1"></i> PROVEEDOR</label>
                <select name="id_proveedor" class="form-select compact-input">
                    <option value="">— Todos —</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Botones de Acción -->
            <div class="col-xl-1 col-lg-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary compact-btn flex-grow-1 shadow-sm" title="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                    <button type="button" id="btnResetFilters" class="btn btn-outline-secondary compact-btn shadow-sm" style="width: 50px;" title="Limpiar Filtros">
                        <i class="bi bi-eraser-fill"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Pantalla de Carga -->
    <div id="loader" class="text-center py-5 d-none">
        <div class="spinner-grow text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-4 text-secondary fw-bold fs-5">Consultando registros...</p>
    </div>

    <!-- Resultados -->
    <div id="resultsWrapper" class="px-2 d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;"><i class="bi bi-list-stars me-2 text-primary"></i>Historial de Movimientos</h4>
            <span id="resultsCount" class="badge rounded-pill bg-primary px-3 py-2 fw-bold" style="font-size: 0.85rem;">0</span>
        </div>
        
        <div id="resultsBody">
            <!-- Los resultados se inyectan dinámicamente aquí -->
        </div>

        <!-- Paginación -->
        <nav aria-label="Navegación de seguimiento" class="mt-5 pb-5">
            <ul id="paginationContainer" class="pagination justify-content-center">
                <!-- Se inyecta dinámicamente -->
            </ul>
        </nav>
    </div>

    <!-- Estado Vacío -->
    <div id="emptyState" class="text-center py-5 rounded-4 bg-light border border-dashed" style="border-width: 3px !important;">
        <div class="py-5">
            <i class="bi bi-search text-secondary opacity-25 mb-3" style="font-size: 6rem;"></i>
            <h4 class="text-secondary fw-bold">Inicia una nueva búsqueda</h4>
            <p class="text-secondary opacity-75">Ingresa criterios en los filtros superiores para ver la trazabilidad de los pedidos.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formTrackingSearch');
    const btnReset = document.getElementById('btnResetFilters');
    const resultsWrapper = document.getElementById('resultsWrapper');
    const resultsBody = document.getElementById('resultsBody');
    const resultsCount = document.getElementById('resultsCount');
    const loader = document.getElementById('loader');
    const emptyState = document.getElementById('emptyState');
    const paginationContainer = document.getElementById('paginationContainer');

    let currentPage = 1;
    const itemsPerPage = 20; // Paginación de 20 registros

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1; 
        fetchTrackingData();
    });

    // Botón de Limpiar Filtros
    btnReset.addEventListener('click', function() {
        form.reset();
        resultsWrapper.classList.add('d-none');
        emptyState.classList.remove('d-none');
        paginationContainer.innerHTML = '';
        currentPage = 1;
    });

    function fetchTrackingData() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.set('page', currentPage);
        params.set('limit', itemsPerPage);
        
        loader.classList.remove('d-none');
        resultsWrapper.classList.add('d-none');
        emptyState.classList.add('d-none');
        resultsBody.innerHTML = '';

        fetch(`${RUTA_URL}pedidos/adminTrackingSearch?${params.toString()}`)
            .then(r => r.json())
            .then(res => {
                loader.classList.add('d-none');
                
                if (res.success && res.data && res.data.length > 0) {
                    renderTracking(res.data);
                    renderPagination(res.pagination);
                    resultsWrapper.classList.remove('d-none');
                    resultsCount.textContent = `${res.pagination.total} registros encontrados`;
                } else {
                    emptyState.classList.remove('d-none');
                    emptyState.innerHTML = `
                        <div class='py-5'>
                            <i class="bi bi-search-heart text-secondary opacity-25 mb-3" style="font-size: 6rem;"></i>
                            <h4 class='text-secondary fw-bold'>Sin movimientos registrados</h4>
                            <p class='text-secondary opacity-75'>No se encontraron registros para los filtros seleccionados.</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                loader.classList.add('d-none');
                alert('Ocurrió un error al consultar el servidor.');
                console.error(err);
            });
    }

    function renderTracking(data) {
        // Agrupar por pedido para mejor visualización
        const orders = {};
        data.forEach(item => {
            if (!orders[item.id_pedido]) {
                orders[item.id_pedido] = {
                    id: item.id_pedido,
                    numero_orden: item.numero_orden,
                    history: []
                };
            }
            orders[item.id_pedido].history.push(item);
        });

        let html = '';
        Object.values(orders).forEach(order => {
            html += `
                <div class="order-group">
                    <div class="order-group-header">
                        <h5><i class="bi bi-box-seam me-2"></i>Pedido # ${order.numero_orden}</h5>
                        <a href="${RUTA_URL}pedidos/editar/${order.id}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-4 fw-bold text-primary">
                            Ver Detalles <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                    <div class="timeline-wrapper">
            `;

            order.history.forEach(h => {
                const statusInfo = getStatusInfo(h.estado_nuevo);
                const fechaPretty = formatFriendlyLabel(h.fecha_cambio);
                const uRealizo = h.realizado_por || 'Sistema';
                
                const prevLabel = h.estado_anterior 
                    ? `<span class="text-muted small text-decoration-line-through me-2" style="opacity: 0.6;">${h.estado_anterior}</span><i class="bi bi-arrow-right text-muted small me-2"></i>` 
                    : '';

                const motivoHtml = h.comentario 
                    ? `<div class="mt-2 text-dark"><i class="bi bi-chat-left-text me-2 text-secondary"></i><strong>Motivo:</strong> ${h.comentario}</div>` 
                    : `<div class="mt-2 text-muted small"><i class="bi bi-chat-left me-2"></i>Sin motivo registrado</div>`;

                html += `
                    <div class="timeline-item">
                        <div class="timeline-dot bg-${statusInfo.class === 'badge-delivered' ? 'success' : (statusInfo.class === 'badge-shipping' ? 'primary' : (statusInfo.class === 'badge-canceled' ? 'danger' : 'secondary'))}">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="timeline-card">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div class="fw-bold text-dark fs-6">
                                    Cambio de Estado: <span class="ms-1 text-primary">${h.estado_nuevo}</span>
                                </div>
                                <div class="text-end">
                                    <small class="text-secondary fw-bold"><i class="bi bi-calendar3 me-1"></i>${fechaPretty}</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                ${prevLabel}
                                <span class="status-badge ${statusInfo.class} mt-2">
                                    <i class="bi ${statusInfo.icon}"></i> ${h.estado_nuevo}
                                </span>
                            </div>

                            ${motivoHtml}

                            <div class="mt-3 pt-2 border-top">
                                <small class="text-secondary fst-italic">
                                    <i class="bi bi-person-circle me-1"></i>Por: <span class="text-dark fw-bold">${uRealizo}</span>
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        resultsBody.innerHTML = html;
    }

    function renderPagination(pagination) {
        paginationContainer.innerHTML = '';
        const { page, totalPages } = pagination;
        
        if (totalPages <= 1) return;

        // Botón Anterior
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a>`;
        if (page > 1) {
            prevLi.querySelector('a').addEventListener('click', (e) => {
                e.preventDefault();
                currentPage--;
                fetchTrackingData();
                window.scrollTo({ top: resultsWrapper.offsetTop - 100, behavior: 'smooth' });
            });
        }
        paginationContainer.appendChild(prevLi);

        // Números de Página (Lógica simplificada para mostrar cerca de la actual)
        const range = 2; // Mostrar 2 antes y 2 después
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - range && i <= page + range)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === page ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                li.querySelector('a').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage !== i) {
                        currentPage = i;
                        fetchTrackingData();
                        window.scrollTo({ top: resultsWrapper.offsetTop - 100, behavior: 'smooth' });
                    }
                });
                paginationContainer.appendChild(li);
            } else if (i === page - range - 1 || i === page + range + 1) {
                const ellLi = document.createElement('li');
                ellLi.className = 'page-item disabled';
                ellLi.innerHTML = `<span class="page-link">...</span>`;
                paginationContainer.appendChild(ellLi);
            }
        }

        // Botón Siguiente
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${page === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a>`;
        if (page < totalPages) {
            nextLi.querySelector('a').addEventListener('click', (e) => {
                e.preventDefault();
                currentPage++;
                fetchTrackingData();
                window.scrollTo({ top: resultsWrapper.offsetTop - 100, behavior: 'smooth' });
            });
        }
        paginationContainer.appendChild(nextLi);
    }

    function formatFriendlyLabel(dateStr) {
                    if (!dateStr) return '';
                    let dtStr = dateStr;
                    if (!dtStr.includes('Z')) {
                        dtStr = dtStr.replace(' ', 'T') + 'Z';
                    }
                    const d = new Date(dtStr);
                    const now = new Date();
                    const diffMs = now - d;
                    const diffSec = Math.floor(diffMs / 1000);
                    
                    const tz = 'America/Managua';
                    let mesStr = d.toLocaleDateString('es-ES', { timeZone: tz, month: 'short' }).replace('.', '');
                    let dDia = d.toLocaleDateString('es-ES', { timeZone: tz, day: 'numeric' });
                    let dYear = d.toLocaleDateString('es-ES', { timeZone: tz, year: 'numeric' });
                    let nowYear = now.toLocaleDateString('es-ES', { timeZone: tz, year: 'numeric' });
                    
                    let yearAppend = dYear !== nowYear ? ' ' + dYear : '';
                    const dateFormatted = `${dDia} de ${mesStr}${yearAppend}, ` + d.toLocaleTimeString('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();

        if (diffSec < 0) return dateFormatted;
        if (diffSec < 60) return `hace unos segundos (${dateFormatted})`;
        
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return `hace ${diffMin} minuto${diffMin === 1 ? '' : 's'} (${dateFormatted})`;
        
        const diffHour = Math.floor(diffMin / 60);
        if (diffHour < 24) return `hace ${diffHour} hora${diffHour === 1 ? '' : 's'} (${dateFormatted})`;
        
        const diffDay = Math.floor(diffHour / 24);
        if (diffDay === 1) return `ayer (${dateFormatted})`;
        if (diffDay < 7) return `hace ${diffDay} días (${dateFormatted})`;
        
        return dateFormatted;
    }

    function getStatusInfo(status) {
        if (!status) return { class: 'badge-default', icon: 'bi-question-circle' };
        const s = status.toUpperCase();
        
        if (s.includes('ENTREGADO')) 
            return { class: 'badge-delivered', icon: 'bi-check-all' };
        
        if (s.includes('RUTA') || s.includes('TRANSITO') || s.includes('CAMINO')) 
            return { class: 'badge-shipping', icon: 'bi-truck' };
        
        if (s.includes('CANCELADO') || s.includes('RECHAZADO') || s.includes('RECHAZO') || s.includes('FALLA') || s.includes('PERDIDO') || s.includes('DEVUELTO')) 
            return { class: 'badge-canceled', icon: 'bi-x-circle' };
        
        if (s.includes('PENDIENTE') || s.includes('NUEVO') || s.includes('INGRESADO') || s.includes('RECOLECTAR')) 
            return { class: 'badge-pending', icon: 'bi-hourglass-split' };

        if (s.includes('REINTENTO') || s.includes('REPROGRAMADO') || s.includes('VUELTA')) 
            return { class: 'badge-retry', icon: 'bi-arrow-repeat' };
            
        return { class: 'badge-default', icon: 'bi-info-circle' };
    }
});
</script>

<?php include("vista/includes/footer.php"); ?>

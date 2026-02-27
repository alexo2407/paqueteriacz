<?php 
$usaDataTables = true;

start_secure_session();

if(!isset($_SESSION['registrado'])) {
    header('location:'.RUTA_URL.'login');
    die();
}

require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

include("vista/includes/header.php");

require_once __DIR__ . '/../../../controlador/crm.php';
require_once __DIR__ . '/../../../utils/crm_status.php';
$crmController = new CrmController();
$resultado = $crmController->listar();
$filtros = $resultado['filtros'] ?? [];
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-list-ul"></i> Leads CRM</h2>
        <div>
            <a href="<?= RUTA_URL ?>crm/crear" class="btn btn-primary me-2">
                <i class="bi bi-plus-lg"></i> Nuevo Lead
            </a>
            <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filtrosForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="EN_ESPERA">En Espera</option>
                        <option value="APROBADO">Aprobado</option>
                        <option value="CONFIRMADO">Confirmado</option>
                        <option value="EN_TRANSITO">En Tránsito</option>
                        <option value="EN_BODEGA">En Bodega</option>
                        <option value="CANCELADO">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Proveedor</label>
                    <select name="proveedor_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($resultado['proveedores'] ?? []) as $prov): ?>
                            <option value="<?= $prov['id'] ?>" <?= ($filtros['proveedor_id'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($resultado['clientes'] ?? []) as $cli): ?>
                            <option value="<?= $cli['id'] ?>" <?= ($filtros['cliente_id'] ?? '') == $cli['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cli['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busqueda" class="form-control" placeholder="ID, nombre...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <button type="button" id="limpiarFiltros" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Limpiar
                    </button>
                    <button type="button" class="btn btn-success ms-2" onclick="exportarCSV()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Leads -->
    <div class="card">
        <div class="card-body">
            <table id="leadsTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proveedor Lead ID</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Proveedor</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Cargado vía AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
function exportarCSV() {
    const params = new URLSearchParams({
        estado: $('[name="estado"]').val(),
        proveedor_id: $('[name="proveedor_id"]').val(),
        cliente_id: $('[name="cliente_id"]').val(),
        fecha_desde: $('[name="fecha_desde"]').val(),
        fecha_hasta: $('[name="fecha_hasta"]').val(),
        busqueda: $('[name="busqueda"]').val()
    });
    window.location.href = '<?= RUTA_URL ?>crm/exportar?' + params.toString();
}

$(document).ready(function() {
    const apiUrl = '<?= RUTA_URL ?>vista/modulos/crm/ajax_datatable.php';
    
    const table = $('#leadsTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false, // Desactivar búsqueda nativa de DataTables
        ajax: {
            url: apiUrl,
            type: 'POST',
            data: function(d) {
                d.estado = $('[name="estado"]').val();
                d.proveedor_id = $('[name="proveedor_id"]').val();
                d.cliente_id = $('[name="cliente_id"]').val();
                d.fecha_desde = $('[name="fecha_desde"]').val();
                d.fecha_hasta = $('[name="fecha_hasta"]').val();
                d.busqueda = $('[name="busqueda"]').val();
            },
            error: function(xhr, error, code) {
                console.error('DataTables AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    code: code
                });
            }
        },
        columns: [
            { data: 'id' },
            { data: 'proveedor_lead_id', render: data => `<code>${data}</code>` },
            { data: 'nombre' },
            { data: 'telefono' },
            { 
                data: 'estado_actual',
                render: function(data) {
                    const params = JSON.parse('<?= json_encode(getEstadoMeta('EN_ESPERA')) ?>'); // Dummy to init if needed, though getEstadoMeta is PHP
                    // We will use the badge logic from server side or simple JS map
                    // Since I cannot call PHP function here easily without rewriting ajax to return metadata, 
                    // I will keep the existing JS map but add a note or try to inject canonical colors if possible.
                    // For now, keeping existing map to avoid breaking JS is safer unless I refactor AJAX response.
                    
                    const badges = {
                        'EN_ESPERA': 'warning',
                        'APROBADO': 'success',
                        'CONFIRMADO': 'primary',
                        'EN_TRANSITO': 'info',
                        'EN_BODEGA': 'secondary',
                        'CANCELADO': 'danger'
                    };
                    return `<span class="badge bg-${badges[data] || 'secondary'}">${data}</span>`;
                }
            },
            { data: 'proveedor_nombre' },
            { data: 'cliente_nombre' },
            { 
                data: 'created_at',
                render: data => new Date(data).toLocaleString('es-GT')
            },
            {
                data: null,
                render: function(data) {
                    return `
                        <a href="<?= RUTA_URL ?>crm/ver/${data.id}" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    `;
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        }
    });

    // Enviar formulario normalmente (submit con botón o Enter)
    $('#filtrosForm').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // Recargar inmediatamente cuando cambian los selectores
    $('[name="estado"], [name="proveedor_id"], [name="cliente_id"], [name="fecha_desde"], [name="fecha_hasta"]').on('change', function() {
        table.ajax.reload();
    });

    // Limpiar filtros
    $('#limpiarFiltros').on('click', function() {
        $('#filtrosForm')[0].reset();
        // Establecer valores por defecto de fechas
        $('[name="fecha_desde"]').val('<?= date('Y-m-01') ?>');
        $('[name="fecha_hasta"]').val('<?= date('Y-m-d') ?>');
        table.ajax.reload();
    });
});
</script>

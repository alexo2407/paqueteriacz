<?php

include("vista/includes/header.php");


?>

<?php
// Mostrar errores de importación guardados en sesión (si existen)
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) start_secure_session();
}
if (!empty($_SESSION['import_errors'])): ?>
    <div class="row mt-2">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5>Errores en la importación (<?= count($_SESSION['import_errors']) ?>)</h5>
                <pre id="importErrorsPre" style="white-space:pre-wrap; text-align:left;"><?= htmlspecialchars(implode("\n", $_SESSION['import_errors'])) ?></pre>
                <button id="downloadErrorsBtn" class="btn btn-sm btn-outline-secondary mt-2">Descargar errores</button>
            </div>
        </div>
    </div>
<?php
    // Limpiar errores de sesión después de mostrarlos
    unset($_SESSION['import_errors']);
endif;
?>



<!-- Botón para abrir modal de importación -->
<div class="mb-4">
    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalImportCSV">
        <i class="bi bi-file-earmark-arrow-up"></i> Importar Pedidos desde CSV
    </button>
    <div class="d-inline-block ms-3">
        <div class="dropdown d-inline-block">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download"></i> Descargar Plantillas
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=basico" download>
                    <i class="bi bi-file-earmark"></i> Plantilla Básica
                </a></li>
                <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=avanzado" download>
                    <i class="bi bi-file-earmark-text"></i> Plantilla Avanzada
                </a></li>
                <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=ejemplo" download>
                    <i class="bi bi-file-earmark-check"></i> Plantilla con Ejemplos
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_export_current.php" download>
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exportar Pedidos Actuales
                </a></li>
            </ul>
        </div>
        <a href="<?= RUTA_URL ?>Pedidos/referencia" class="btn btn-outline-info" >
            <i class="bi bi-book"></i> Ver Referencia de Valores
        </a>
    </div>
</div>

<!-- Modal de Importación CSV -->
<div class="modal fade" id="modalImportCSV" tabindex="-1" aria-labelledby="modalImportCSVLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalImportCSVLabel">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importar Pedidos desde CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario de importación -->
                <form id="formImportCSV" action="<?= RUTA_URL ?>pedidos/importar" method="POST" enctype="multipart/form-data">
                    
                    <!-- Selector de archivo -->
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-bold">Seleccionar archivo CSV</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" class="form-control" required>
                        <div class="form-text">Formatos aceptados: .csv, .txt (máximo 10MB)</div>
                    </div>

                    <!-- Opciones Avanzadas (Colapsable) -->
                    <div class="accordion mb-3" id="accordionAdvanced">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingAdvanced">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                                    <i class="bi bi-gear me-2"></i> Opciones Avanzadas
                                </button>
                            </h2>
                            <div id="collapseAdvanced" class="accordion-collapse collapse" aria-labelledby="headingAdvanced" data-bs-parent="#accordionAdvanced">
                                <div class="accordion-body">
                                    <div class="row">
                                        <!-- Valores por defecto -->
                                        <div class="col-md-6 mb-3">
                                            <label for="default_estado" class="form-label">Estado por defecto</label>
                                            <select name="default_estado" id="default_estado" class="form-select">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $ctrl = new PedidosController();
                                                $estados = $ctrl->obtenerEstados();
                                                foreach ($estados as $e):
                                                ?>
                                                    <option value="<?= $e['id'] ?>" <?= $e['id'] == 1 ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre_estado']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Se aplicará si la fila no especifica estado</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_proveedor" class="form-label">Proveedor por defecto</label>
                                            <select name="default_proveedor" id="default_proveedor" class="form-select">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $proveedores = $ctrl->obtenerProveedores();
                                                foreach ($proveedores as $p):
                                                ?>
                                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_moneda" class="form-label">Moneda por defecto</label>
                                            <select name="default_moneda" id="default_moneda" class="form-select">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $monedas = $ctrl->obtenerMonedas();
                                                foreach ($monedas as $m):
                                                ?>
                                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?> (<?= $m['codigo'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_vendedor" class="form-label">Vendedor/Repartidor por defecto</label>
                                            <select name="default_vendedor" id="default_vendedor" class="form-select">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $vendedores = $ctrl->obtenerRepartidores();
                                                foreach ($vendedores as $v):
                                                ?>
                                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Opciones adicionales -->
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="auto_create_products" name="auto_create_products" value="1" checked>
                                                <label class="form-check-label" for="auto_create_products">
                                                    Crear productos automáticamente si no existen
                                                </label>
                                                <div class="form-text">Si desactivas esta opción, las filas con productos inexistentes serán rechazadas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de progreso -->
                    <div id="uploadProgress" class="progress mt-2 d-none" style="height: 24px;">
                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                    <div id="uploadStatus" class="small text-muted mt-1 d-none"></div>

                    <!-- Información -->
                    <div class="alert alert-info mt-3">
                        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Instrucciones</h6>
                        <ul class="mb-0 small">
                            <li><strong>Columnas mínimas:</strong> numero_orden, latitud, longitud</li>
                            <li><strong>Columnas opcionales:</strong> destinatario, telefono, id_producto o producto_nombre, cantidad, direccion, etc.</li>
                            <li>Usa la <strong>Vista Previa</strong> para validar antes de importar</li>
                            <li>Si hay errores, se generará un CSV descargable con las filas problemáticas</li>
                        </ul>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="button" id="btnPreview" class="btn btn-outline-primary">
                    <i class="bi bi-eye"></i> Vista Previa
                </button>
                <button type="submit" form="formImportCSV" id="btnImport" class="btn btn-success">
                    <i class="bi bi-upload"></i> Importar Ahora
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Vista Previa -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-labelledby="modalPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPreviewLabel">Vista Previa de Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Resumen de validación -->
                <div id="previewSummary" class="mb-3"></div>
                
                <!-- Advertencias -->
                <div id="previewWarnings" class="mb-3"></div>
                
                <!-- Errores -->
                <div id="previewErrors" class="mb-3"></div>
                
                <!-- Productos a crear -->
                <div id="previewProductos" class="mb-3"></div>
                
                <!-- Tabla con primeras filas -->
                <div id="previewTable" class="table-responsive"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmImport" class="btn btn-primary" disabled>
                    <i class="bi bi-check-circle"></i> Confirmar e Importar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Resultados -->
<div class="modal fade" id="modalResults" tabindex="-1" aria-labelledby="modalResultsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResultsLabel">Resultados de Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="resultsContent">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>






 <div class="row">
    <div class="col-sm-6">
        <h3>Lista de Pedidos</h3>
    </div>
  <div class="col-sm-4 offset-sm-8 text-end">
        <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="btn btn-success">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Pedido
        </a>
    </div>
</div>

    <div class="row mt-2 caja">
    <div class="col-sm-12">
        

        <div class="table-responsive">
            <table id="tblPedidos" class="table table-striped">
                <thead>
                    <tr>
                        <th>Número de Orden</th>
                        <th>Cliente</th>
                        <th>Comentario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $listarPedidos = new PedidosController();
                    $estados = $listarPedidos->obtenerEstados(); // Obtener lista de estados
                    $pedidos = $listarPedidos->listarPedidosExtendidos();

                    // Determinar si el usuario es proveedor (y no admin) para deshabilitar cambios
                    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
                    $isProveedorOnly = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true) && !in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
                    $disabledAttr = $isProveedorOnly ? 'disabled' : '';

                    foreach ($pedidos as $pedido): ?>
                        <tr data-id="<?= $pedido['ID_Pedido'] ?>">
                            <td><?= htmlspecialchars($pedido['Numero_Orden']) ?></td>
                            <td><?= htmlspecialchars($pedido['Cliente']) ?></td>
                            <td><?= htmlspecialchars($pedido['Comentario']) ?></td>

                            <!-- Celda Editable para Estado -->
                            <td class="editable" data-campo="estado">
                                <select class="form-select actualizarEstado" data-id="<?= $pedido['ID_Pedido']; ?>" <?= $disabledAttr ?>>
                                    <?php foreach ($estados as $estado): ?>
                                        <option value="<?= $estado['id']; ?>" <?= $pedido['Estado'] == $estado['nombre_estado'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($estado['nombre_estado']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <!-- <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-primary btn-sm">Ver</a> -->
                                <a href="<?= RUTA_URL ?>pedidos/editar/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                

                                <?php if (!empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $pedido['latitud'] ?>,<?= $pedido['longitud'] ?>&travelmode=driving"
                                        target="_blank" class="btn btn-success btn-sm">
                                        <i class="bi bi-geo-alt"></i> Ir a Ruta
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="bi bi-geo-alt"></i> Sin Coordenadas
                                    </button>
                                <?php endif; ?>


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

<?php include("vista/includes/footer.php"); ?>



<script>
    $(document).ready(function() {
    $('#tblPedidos').DataTable({
        responsive: true, // Activa la capacidad responsive
        dom: 'Bfrtip', // Controles de exportación
        buttons: [
            'excel', 'pdf', 'print'
        ],
        order: [
            [1, 'asc']
        ],
        language: {
            search: "Buscar por Número de Orden o Cliente:",
            lengthMenu: "Mostrar _MENU_ registros por página",
            zeroRecords: "No se encontraron resultados",
            info: "Mostrando página _PAGE_ de _PAGES_",
            infoEmpty: "No hay registros disponibles",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });

});

</script>

<script>
    $(document).ready(function() {
        $(".actualizarEstado").change(function() {
            let select = $(this); // Guardamos la referencia al select
            let idPedido = select.data("id");
            let nuevoEstado = select.val();
            let estadoAnterior = select.data("estado"); // Guarda el estado anterior

            // Deshabilita el select mientras se procesa la petición
            select.prop("disabled", true);

            $.ajax({
                url: "cambiarEstados",
                type: "POST",
                data: {
                    id_pedido: idPedido,
                    estado: nuevoEstado
                },
                dataType: "json",
                success: function(response) {
                    //console.log("Respuesta recibida:", response);

                    if (response.success) {
                        Swal.fire({
                            title: "¡Éxito!",
                            text: "Estado actualizado correctamente.",
                            icon: "success",
                            confirmButtonText: "OK"
                        });
                        select.data("estado", nuevoEstado);
                        select.val(nuevoEstado);
                    } else {
                        Swal.fire({
                            title: "Error",
                            text: response.message,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                        select.val(estadoAnterior);
                    }
                },

                error: function(xhr, status, error) {
                   // Mostrar mensaje más útil: si el servidor devolvió JSON, usar su campo message
                   var serverMsg = null;
                   try {
                       if (xhr.responseJSON && xhr.responseJSON.message) serverMsg = xhr.responseJSON.message;
                       else if (xhr.responseText) {
                           // Intentar parsear JSON en texto
                           var parsed = JSON.parse(xhr.responseText);
                           if (parsed && parsed.message) serverMsg = parsed.message;
                       }
                   } catch (e) {
                       // no hacer nada
                   }
                   var messageToShow = serverMsg || ('Error de conexión: ' + (error || status));
                   Swal.fire({
                            title: "Error",
                            text: messageToShow,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                    // alert("Error de conexión. Intenta nuevamente.");
                    select.val(estadoAnterior);
                },
                complete: function() {
                    select.prop("disabled", false);
                }
            });

        });
    });
</script>

<!-- JS para manejar CSV import con Vista Previa y Import mejorado -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('formImportCSV');
    const fileInput = document.getElementById('csv_file');
    const btnPreview = document.getElementById('btnPreview');
    const btnImport = document.getElementById('btnImport');
    const btnConfirmImport = document.getElementById('btnConfirmImport');
    const progress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const status = document.getElementById('uploadStatus');
    
    if (!form) return;
    
    let previewData = null; // Guardar datos de preview
    
    // ================== PREVIEW FLOW ==================
    btnPreview.addEventListener('click', function(){
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin archivo',
                text: 'Por favor selecciona un archivo CSV primero',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Preparar FormData con preview=1
        const fd = new FormData(form);
        fd.append('preview', '1');
        
        // Mostrar loading
        Swal.fire({
            title: 'Validando CSV...',
            text: 'Analizando el archivo',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Enviar petición AJAX
        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            
            if (!data.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al analizar CSV',
                    text: data.message || 'Error desconocido',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Guardar datos de preview
            previewData = data;
            
            // Llenar modal con resumen
            mostrarPreview(data);
            
            // Abrir modal
            const modalPreview = new bootstrap.Modal(document.getElementById('modalPreview'));
            modalPreview.show();
        })
        .catch(error => {
            Swal.close();
            console.error('Preview error:', error);
            const actionUrl = form.getAttribute('action');
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                html: `No se pudo comunicar con el servidor.<br>
                       <small class="text-muted">URL: ${actionUrl}</small><br>
                       <small class="text-danger">Detalle: ${error.message}</small>`,
                confirmButtonText: 'OK'
            });
        });
    });
    
    // ================== CONFIRMAR DESDE PREVIEW ==================
    btnConfirmImport.addEventListener('click', function(){
        // Cerrar modal preview
        bootstrap.Modal.getInstance(document.getElementById('modalPreview')).hide();
        
        // Ejecutar importación
        ejecutarImportacion(false); // preview=false
    });
    
    // ================== IMPORT DIRECTO ==================
    form.addEventListener('submit', function(e){
        e.preventDefault();
        
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin archivo',
                text: 'Por favor selecciona un archivo CSV',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        ejecutarImportacion(false);
    });
    
    // ================== FUNCIÓN EJECUTAR IMPORTACIÓN ==================
    function ejecutarImportacion(isPreview)  {
        btnImport.disabled = true;
        btnPreview.disabled = true;
        
        const fd = new FormData(form);
        if (isPreview) {
            fd.append('preview', '1');
        }
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        // Progreso de subida
        xhr.upload.addEventListener('progress', function(ev){
            if (ev.lengthComputable) {
                const percent = Math.round((ev.loaded / ev.total) * 100);
                progress.classList.remove('d-none');
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                status.classList.remove('d-none');
                status.textContent = 'Subiendo y procesando... ' + percent + '%';
            }
        });
        
        xhr.onreadystatechange = function(){
            if (xhr.readyState === 4) {
                btnImport.disabled = false;
                btnPreview.disabled = false;
                progress.classList.add('d-none');
                status.classList.add('d-none');
                
                let json = null;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (e) {
                    console.error('No JSON response', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de respuesta',
                        html: `El servidor devolvió una respuesta inválida.<br>
                               <small class="text-danger">Status: ${xhr.status}</small><br>
                               <small class="text-muted">Ver consola para detalles</small>`,
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                if (xhr.status >= 200 && xhr.status < 400) {
                    if (json && json.success) {
                        mostrarResultados(json);
                    } else if (json && !json.success) {
                        const errorList = json.errors_list || json.errores || [];
                        let errorHtml = '<p>' + (json.message || 'Error desconocido') + '</p>';
                        
                        if (errorList.length > 0) {
                            errorHtml += '<div class="alert alert-danger text-start small mt-2" style="max-height:200px;overflow:auto;"><ul class="mb-0 ps-3">';
                            errorList.slice(0, 10).forEach(err => {
                                errorHtml += '<li>' + err + '</li>';
                            });
                            if (errorList.length > 10) {
                                errorHtml += '<li>... y ' + (errorList.length - 10) + ' más</li>';
                            }
                            errorHtml += '</ul></div>';
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error en la importación',
                            html: errorHtml,
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // Fallback: recargar página
                        window.location.reload();
                    }
                } else {
                    console.error('Import failed:', xhr.status, xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error del servidor',
                        html: '<p>Código: ' + xhr.status + '</p>' +
                              '<pre class="small">' + (xhr.responseText || 'Sin respuesta').substring(0, 500) + '</pre>',
                        confirmButtonText: 'OK'
                    });
                }
            }
        };
        
        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.withCredentials = true;
        xhr.send(fd);
    }
    
    // ================== MOSTRAR PREVIEW ==================
    function mostrarPreview(data) {
        const resumen = data.resumen || {};
        
        // Resumen estadístico
        let summaryHtml = `
            <div class="alert alert-${resumen.puede_importar ? 'success' : 'danger'}">
                <h6 class="alert-heading mb-3">
                    <i class="bi bi-${resumen.puede_importar ? 'check-circle' : 'x-circle'}"></i>
                    Resumen de Validación
                </h6>
                <div class="row text-center">
                    <div class="col">
                        <div class="fs-4 fw-bold">${resumen.total || 0}</div>
                        <div class="small text-muted">Total Filas</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-success">${resumen.validas || 0}</div>
                        <div class="small text-muted">Válidas</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-danger">${resumen.con_errores || 0}</div>
                        <div class="small text-muted">Con Errores</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-warning">${resumen.con_advertencias || 0}</div>
                        <div class="small text-muted">Con Advertencias</div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('previewSummary').innerHTML = summaryHtml;
        
        // Advertencias
        if (resumen.advertencias && resumen.advertencias.length > 0) {
            let warningsHtml = `
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Advertencias (${resumen.advertencias.length})</h6>
                    <ul class="mb-0 small" style="max-height:200px;overflow:auto;">
                        ${resumen.advertencias.slice(0, 10).map(w => '<li>' + w + '</li>').join('')}
                    </ul>
                    ${resumen.advertencias.length > 10 ? '<p class="mb-0 small mt-2">...y ' + (resumen.advertencias.length - 10) + ' más</p>' : ''}
                </div>
            `;
            document.getElementById('previewWarnings').innerHTML = warningsHtml;
        } else {
            document.getElementById('previewWarnings').innerHTML = '';
        }
        
        // Errores
        if (resumen.errores && resumen.errores.length > 0) {
            let errorsHtml = `
                <div class="alert alert-danger">
                    <h6 class="alert-heading"><i class="bi bi-x-circle"></i> Errores (${resumen.errores.length})</h6>
                    <pre class="mb-0 small" style="max-height:200px;overflow:auto;">${resumen.errores.slice(0, 15).join('\n')}</pre>
                    ${resumen.errores.length > 15 ? '<p class="mb-0 small mt-2">...y ' + (resumen.errores.length - 15) + ' más errores</p>' : ''}
                </div>
            `;
            document.getElementById('previewErrors').innerHTML = errorsHtml;
        } else {
            document.getElementById('previewErrors').innerHTML = '';
        }
        
        // Vista previa de primeras filas
        if (data.primeras_filas && data.primeras_filas.length > 0) {
            const cols = Object.keys(data.primeras_filas[0]);
            let tableHtml = `
                <h6>Primeras ${data.primeras_filas.length} filas del CSV</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>${cols.map(c => '<th class="small">' + c + '</th>').join('')}</tr>
                    </thead>
                    <tbody>
                        ${data.primeras_filas.map(r => 
                            '<tr>' + cols.map(c => '<td class="small">' + (r[c] || '') + '</td>').join('') + '</tr>'
                        ).join('')}
                    </tbody>
                </table>
            `;
            document.getElementById('previewTable').innerHTML = tableHtml;
        }
        
        // Habilitar/deshabilitar botón de confirmar
        btnConfirmImport.disabled = !resumen.puede_importar;
    }
    
    // ================== MOSTRAR RESULTADOS ==================
    function mostrarResultados(data) {
        const stats = data.stats || {};
        const estado = data.estado || 'completado';
        const productosCreados = data.productos_creados || [];
        
        let iconType = estado === 'completado' ? 'success' : (estado === 'parcial' ? 'warning' : 'error');
        
        let html = `
            <div class="alert alert-${iconType === 'success' ? 'success' : (iconType === 'warning' ? 'warning' : 'danger')}">
                <h5 class="alert-heading">
                    <i class="bi bi-${iconType === 'success' ? 'check-circle' : (iconType === 'warning' ? 'exclamation-triangle' : 'x-circle')}"></i>
                    ${data.message}
                </h5>
            </div>
            
            <div class="row text-center mb-3">
                <div class="col">
                    <div class="card">
                        <div class="card-body">
                            <div class="fs-3 fw-bold">${stats.total || 0}</div>
                            <div class="small text-muted">Total Filas</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-success">${stats.inserted || 0}</div>
                            <div class="small text-muted">Importadas</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-danger">${stats.errors || 0}</div>
                            <div class="small text-muted">Errores</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-warning">${stats.warnings || 0}</div>
                            <div class="small text-muted">Advertencias</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <p class="small text-muted">Tiempo de procesamiento: ${stats.tiempo_segundos || 0} segundos</p>
        `;
        
        // Productos creados
        if (productosCreados.length > 0) {
            html += `
                <div class="alert alert-info mt-3">
                    <h6 class="alert-heading"><i class="bi bi-plus-circle"></i> Productos nuevos creados (${productosCreados.length})</h6>
                    <ul class="mb-0 small">
                        ${productosCreados.map(p => '<li>' + p + '</li>').join('')}
                    </ul>
                </div>
            `;
        }
        
        // Lista de errores (si hay)
        if (data.errors_list && data.errors_list.length > 0) {
            html += `
                <div class="alert alert-danger mt-3">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Errores detectados</h6>
                    <ul class="mb-0 small">
                        ${data.errors_list.map(e => '<li>' + e + '</li>').join('')}
                    </ul>
                </div>
            `;
        }
        
        // Enlace a CSV de errores
        if (data.error_file_url) {
            html += `
                <div class="mt-3">
                    <a href="<?= RUTA_URL ?>${data.error_file_url}" class="btn btn-danger btn-sm" download>
                        <i class="bi bi-download"></i> Descargar CSV con Filas Erróneas
                    </a>
                </div>
            `;
        }
        
        document.getElementById('resultsContent').innerHTML = html;
        
        // Abrir modal de resultados
        const modalResults = new bootstrap.Modal(document.getElementById('modalResults'));
        modalResults.show();
        
        // Al cerrar modal, recargar página
        document.getElementById('modalResults').addEventListener('hidden.bs.modal', function () {
            window.location.reload();
        }, { once: true });
    }
});
</script>

<script>
// Descargar errores mostrados en la página (legacy)
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('downloadErrorsBtn');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var pre = document.getElementById('importErrorsPre');
        if (!pre) return;
        var text = pre.textContent || pre.innerText;
        var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'import_errors.txt';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    });
});
</script>
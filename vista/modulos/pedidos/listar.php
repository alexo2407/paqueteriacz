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



 <div class="card">
   <img class="card-img-top" src="holder.js/100px180/" alt="">
   <div class="card-body">

            <div>
                <h2 class="text-muted">Importar Pedidos</h2>
            </div>

     <!-- Importar CSV: formulario -->
        <div class="custom-file mb-3">
            <form id="formImportCSV" action="<?= RUTA_URL ?>pedidos/importar" method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                <input type="file" name="csv_file" id="csv_file" accept=".csv" class="form-control-file">
                <button type="submit" class="btn btn-primary">Importar CSV</button>
            </form>

            <!-- Barra de progreso (oculta por defecto) -->
            <div id="uploadProgress" class="progress mt-2 d-none" style="height: 24px;">
                <div id="uploadProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
            <div id="uploadStatus" class="small text-muted mt-1 d-none"></div>
        </div>

         <div class="rowcol-12 mb-3">
            <div
                class="alert alert-primary d-flex justify-content-between align-items-center"
                role="alert"
            >
                <div class="col-8">
                    <small class="form-text text-muted">El CSV debe incluir cabeceras: numero_orden,destinatario,telefono,producto,cantidad,direccion,latitud,longitud. El formato de descarga ahora trae dos ejemplos listos como guía.</small>
                </div>
                <div class="col-4 text-end">
                    <!-- Botón para descargar el CSV de ejemplo / plantilla -->
                        <a href="<?= RUTA_URL ?>public/pedidos_template.php" class="btn btn-secondary btn-sm" download>
                        <i class="bi bi-download"></i> Descargar plantilla CSV
                    </a>
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

                    foreach ($pedidos as $pedido): ?>
                        <tr data-id="<?= $pedido['ID_Pedido'] ?>">
                            <td><?= htmlspecialchars($pedido['Numero_Orden']) ?></td>
                            <td><?= htmlspecialchars($pedido['Cliente']) ?></td>
                            <td><?= htmlspecialchars($pedido['Comentario']) ?></td>

                            <!-- Celda Editable para Estado -->
                            <td class="editable" data-campo="estado">
                                <select class="form-select actualizarEstado" data-id="<?= $pedido['ID_Pedido']; ?>">
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

<!-- JS para manejar el upload via AJAX y mostrar progreso -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('formImportCSV');
    var fileInput = document.getElementById('csv_file');
    var progress = document.getElementById('uploadProgress');
    var progressBar = document.getElementById('uploadProgressBar');
    var status = document.getElementById('uploadStatus');

    if (!form) return;

    form.addEventListener('submit', function(e){
        // Si no hay archivo seleccionado, permitir submit normal para que el servidor valide
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) return;

        e.preventDefault();
        var file = fileInput.files[0];
        var action = form.getAttribute('action');
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;

        var xhr = new XMLHttpRequest();
        var fd = new FormData();
        fd.append('csv_file', file, file.name);

        xhr.upload.addEventListener('progress', function(ev){
            if (ev.lengthComputable) {
                var percent = Math.round((ev.loaded / ev.total) * 100);
                progress.classList.remove('d-none');
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                status.classList.remove('d-none');
                status.textContent = 'Subiendo ' + percent + '%';
            }
        });

        xhr.onreadystatechange = function(){
            if (xhr.readyState === 4) {
                btn.disabled = false;
                // Intentar parsear JSON si el servidor lo devuelve
                var json = null;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (e) {
                    // no JSON
                }

                if (xhr.status >= 200 && xhr.status < 400) {
                    if (json && typeof json.success !== 'undefined') {
                        if (json.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Importación',
                                text: json.message || 'Importación completada.',
                                confirmButtonText: 'OK'
                            }).then(function(){
                                window.location = '<?= RUTA_URL ?>pedidos/listar';
                            });
                        } else {
                            // Mostrar errores detallados si vienen
                            var details = json.errors && json.errors.length ? json.errors.join('\n') : (json.message || 'Error al importar');
                            console.error('Import error response:', xhr.status, xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al importar',
                                html: '<pre style="text-align:left; white-space:pre-wrap;">' + details + '</pre>',
                                confirmButtonText: 'OK'
                            });
                        }
                    } else {
                        // No JSON: recargar para mostrar mensajes en sesión (compatibilidad)
                        window.location = '<?= RUTA_URL ?>pedidos/listar';
                    }
                } else {
                    // Mostrar información util para depuración
                    var body = xhr.responseText || '(sin cuerpo)';
                    console.error('Upload failed:', xhr.status, body);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al subir el archivo',
                        html: '<p>Estado: ' + xhr.status + '</p><pre style="text-align:left; white-space:pre-wrap;">' + body + '</pre>',
                        confirmButtonText: 'OK'
                    });
                    status.textContent = 'Error al subir el archivo. Intenta de nuevo.';
                }
            }
        };

        xhr.open('POST', action, true);
        // Marcar como AJAX para que el servidor devuelva JSON
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        // Enviar cookies/credenciales si son necesarias (misma-origin)
        xhr.withCredentials = true;
        xhr.send(fd);
    });
});
</script>

<script>
// Descargar errores mostrados en la página
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
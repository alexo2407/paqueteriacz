<?php include("vista/includes/header.php") ?>


<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Usuarios</h3>
    </div>   
    <div class="col-sm-4 offset-2">
        <a href="<?=RUTA_URL?>usuarios/crear" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo Usuario</a>
    </div>    
</div>
<div class="row mt-2 caja">
    <div class="col-sm-12">
            <table id="tblUsuarios" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Fecha de Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>

                    <?php 
                    
                     $listarUsuarios = new UsuariosController();
                    $resultadoUsuarios = $listarUsuarios->mostrarUsuariosController();

                    foreach($resultadoUsuarios as $usuarios) :
                        $timestampCreacion = !empty($usuarios['fecha_creacion']) ? strtotime($usuarios['fecha_creacion']) : null;
                        $fechaCreacion = $timestampCreacion ? date('d/m/Y H:i', $timestampCreacion) : '—';
                    ?>
              
                    <tr>
                        <td><?php echo $usuarios['id']; ?></td>
                        <td><?php echo $usuarios['nombre']; ?></td>
                        <td><?php echo $usuarios['email']; ?></td>
                        <td><?php echo $usuarios['rol_nombre']; ?></td>
                        <td><?php echo $fechaCreacion; ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>usuarios/editar/<?php echo $usuarios['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil-fill"></i></a>
                        </td>
                    </tr>

                    <?php endforeach; ?>
                                           
                </tbody>       
            </table>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
    $(document).ready( function () {
        $('#tblUsuarios').DataTable();
    });
</script>
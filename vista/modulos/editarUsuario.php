<?php include "vista/includes/header.php" ?>


<div class="row">
    <div class="col-sm-6">
        <h3>Editar Usuario</h3>
    </div>

    <?php


    // /* CREAMOS UN CONTROLLER PARA ACTUALIZAR */

    // Si se ha enviado el formulario, llamamos a actualizar
    if (isset($_POST['actualizarUsuario'])) {
        $actualizar = new UsuariosController();
        $actualizar->actualizarUsuariosController();
    }

    // Si se ha enviado el formulario, llamamos a actualizar
    if (isset($_POST['borrarUsuario'])) {
        $borrarUsuario = new UsuariosController();
        $borrarUsuario->borrarUsuariosController();
    }


    /* CREAR EL CONTTROLADOR PARA EDITAR USUARIOS */
    //enviaremos a consultar a traves del ID   

    $editarUsuarios = new UsuariosController();
    $repuesta = $editarUsuarios->editarUsuariosController();

    ?>


    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <strong>Dato correcto</strong>
    </div>







</div>
<div class="row">
    <div class="col-sm-6 offset-3">
        <form method="POST" action="">

            <input type="hidden" name="id" value="<?php echo $repuesta->id; ?>">

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre:</label>
                <input type="text" class="form-control" name="nombre" id="nombre" placeholder="Ingresa el nombre" value="<?php echo $repuesta->nombre; ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email:</label>
                <input type="email" class="form-control" name="email" id="email" placeholder="Ingresa el email" value="<?php echo $repuesta->email; ?>">
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol:</label>
                <select class="form-select" aria-label="Default select example" name="rol">
                    <option value="" <?php if ($repuesta->rol_id == "") {
                                            echo 'selected';
                                        } ?>>--Selecciona un rol--</option>
                    <option value="1" <?php if ($repuesta->rol_id == 1) {
                                            echo 'selected';
                                        } ?>>Administrador</option>
                    <option value="2" <?php if ($repuesta->rol_id == 2) {
                                            echo 'selected';
                                        } ?>>Registrado</option>

                </select>
            </div>

            <br />
            <button type="submit" name="actualizarUsuario" class="btn btn-success float-left"><i class="bi bi-person-bounding-box"></i> Actualizar Usuario</button>

            <button type="submit" name="borrarUsuario" class="btn btn-danger float-right"><i class="bi bi-person-bounding-box"></i> Borrar Usuario</button>
        </form>
    </div>
</div>
<?php include("vista/includes/footer.php") ?>
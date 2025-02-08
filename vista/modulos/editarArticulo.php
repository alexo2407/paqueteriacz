<?php include("vista/includes/header.php") ?>

<div class="row">

</div>

<div class="row">

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

    $editarArticulo = new ArticuloController();
    $repuesta = $editarArticulo->editarArticuloController();

     var_dump($repuesta);

    ?>



    <div class="col-sm-6">
        <h3>Editar Artículo</h3>
    </div>
</div>
<div class="row">
    <div class="col-sm-6 offset-3">
        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="id" value="<?=$repuesta->id?>">

            <div class="mb-3">
                <label for="titulo" class="form-label">Título:</label>
                <input type="text" class="form-control" name="titulo" id="titulo" value="<?php echo isset($repuesta->titulo) ? $repuesta->titulo : '' ; ?>">
            </div>

            <div class="mb-3">
                <img class="img-fluid img-thumbnail" src="<?php echo isset($repuesta->imagen) ? RUTA_BACK.$repuesta->imagen : '' ; ?>">
            </div>

            <div class="mb-3">
                <label for="imagen" class="form-label">Imagen:</label>
                <input type="file" class="form-control" name="imagen" id="imagen" placeholder="Selecciona una imagen">
            </div>
            <div class="mb-3">
                <label for="texto">Texto</label>
                <textarea class="form-control" placeholder="Escriba el texto de su artículo" name="texto" style="height: 200px">
                <?php echo isset($repuesta->texto) ? $repuesta->texto : '' ; ?>
                </textarea>
            </div>

            <br />
            <button type="submit" name="editarArticulo" class="btn btn-success float-left"><i class="bi bi-person-bounding-box"></i> Editar Artículo</button>

            <button type="submit" name="borrarArticulo" class="btn btn-danger float-right"><i class="bi bi-person-bounding-box"></i> Borrar Artículo</button>
        </form>
    </div>
</div>
<?php include("vista/includes/footer.php") ?>
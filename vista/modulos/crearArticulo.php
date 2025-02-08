<?php include("vista/includes/header.php") ?>

    <div class="row">
        
    </div>

    <div class="row">
        <div class="col-">
            <div
                class="alert alert-success alert-dismissible fade show" role="alert" >
                <button type="button"class="btn-close" data-bs-dismiss="alert"
                    aria-label="Close"></button>
                <strong>Articulo Creado</strong> satisfactoriamente
            </div>
        
         <?php 
         
         if (isset($_POST['crearArticulo']))
         {
                $crearAticulo = new ArticuloController();
                $crearAticulo->crearArticuloController();
         }
         
         ?>


            
        </div>
        <div class="col-sm-6">
            <h3>Crear un Nuevo Artículo</h3>
        </div>            
    </div>
    <div class="row">
        <div class="col-sm-6 offset-3">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="titulo" class="form-label">Título:</label>
                <input type="text" class="form-control" name="titulo" id="titulo" placeholder="Ingresa el título">               
            </div>
            <div class="mb-3">
                <label for="imagen" class="form-label">Imagen:</label>
                <input type="file" class="form-control" name="imagenArticulo" id="apellidos" placeholder="Selecciona una imagen">               
            </div>
            <div class="mb-3">
                <label for="texto">Texto</label>   
                <textarea class="form-control" placeholder="Escriba el texto de su artículo" name="texto" style="height: 200px"></textarea>              
            </div>          
        
            <br />
            <button type="submit" name="crearArticulo" class="btn btn-primary w-100"><i class="bi bi-person-bounding-box"></i> Crear Nuevo Artículo</button>
            </form>
        </div>
    </div>
<?php include("vista/includes/footer.php") ?>
       
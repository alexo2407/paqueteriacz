<?php

session_start();

if(!isset($_SESSION['registrado']))
{
    header('location:'.RUTA_FRONT.'login');
    die();
}


// var_dump($_SESSION);

include("vista/includes/header.php");


?>


<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Artículos</h3>
    </div> 


    <div class="col-sm-4 offset-2">
        <a href="<?=RUTA_BACK?>crearArticulo" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo Artículo</a>
    </div>    
</div>
<div class="row mt-2 caja">
    <div class="col-sm-12">
            <table id="tblArticulos" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titulo</th>
                        <th>Imagen</th> 
                        <th>Texto</th>
                        <th>Fecha de creación</th>              
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>

                <?php 
                    
                     $listarArticulos = new ArticuloController();
                    $resultadoArticulos = $listarArticulos->mostrarArticulosController();

                    foreach($resultadoArticulos as $articulo) :
                                       
                    ?>
             
                    <tr>
                        <td><?php echo $articulo["id"];?></td>
                        <td><?php echo $articulo["titulo"];?></td>
                        <td>
                            <img src="<?php echo RUTA_BACK.$articulo["imagen"];?>" style="width:80px;">
                        </td>
                        <td><?php echo acotarTexto($articulo["texto"],150);?></td>
                        <td><?php echo $articulo["fecha_creacion"];?></td>                      
                        <td>
                        <a href="<?=RUTA_BACK?>editarArticulo/<?= $articulo['id']?>" class="btn btn-warning"><i class="bi bi-pencil-fill"></i></a>                       
                        </td>
                    </tr>
                <?php 
                endforeach;
                ?>
               
                </tbody>       
            </table>
    </div>
</div>
<?php include("vista/includes/footer.php") ?>

<script>
    $(document).ready( function () {
        $('#tblArticulos').DataTable();
    });
</script>
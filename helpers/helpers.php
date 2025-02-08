<?php

/* Recortar texto, texto de introducción */
function textoCorto($texto, $maximochart = 10)
{

  $limpiarTexto = strip_tags($texto);
  //substr — Devuelve parte de una cadena
  $texto = mb_substr($limpiarTexto, 0, $maximochart, 'UTF-8') . "...";

  return $texto;
}


function mostrarNombreModulo()
{
  if (isset($_SERVER['REQUEST_URI'])) {
    // obtener el nombre del modulo
    $urlArray = explode('/', $_SERVER['REQUEST_URI']);
    # code...
    switch ($urlArray[2]) {
      case 'dashboard':
        echo '<h1 class="m-0"> Dashboard </h1>';
        break;
      case 'articulos':
        echo '<h1 class="m-0"> Listar Articulos </h1>';
        break;
      case 'crearArticulo':
        echo '<h1 class="m-0"> Crear Articulo</h1>';
        break;
      case 'editarArticulo':
        echo '<h1 class="m-0"> Editar Articulo</h1>';
        break;
      default:
        echo '<h1 class="m-0"> Desfault </h1>';
        break;
    }
  } else {
    echo '<h1 class="m-0">Mi sitio WEB</h1>';
  }
}

/**
 * FUNCION PARA CARGAR SOLO LOS CSS NECESARIOS
 */

function cargarCss()
{
  if (isset($_SERVER['REQUEST_URI'])) {
    // obtener el nombre del modulo
    $urlArray = explode('/', $_SERVER['REQUEST_URI']);
    # code...
    // var_dump($urlArray);

    switch ($urlArray[2]) {

      case 'articulos':
?>
        <!-- DataTables -->
        <link rel="stylesheet" href="views/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="views/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
        <link rel="stylesheet" href="views/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

      <?php
        break;
      case 'editarArticulo':
      ?>
        <!-- daterange picker -->
        <link rel="stylesheet" href="views/plugins/daterangepicker/daterangepicker.css">

        <!-- summernote -->
        <link rel="stylesheet" href="views/plugins/summernote/summernote-bs4.min.css">

      <?php
        break;
      case 'crearArticulo':
      ?>
        <!-- daterange picker -->
        <link rel="stylesheet" href="<?= RUTA_BACKEND ?>views/plugins/daterangepicker/daterangepicker.css">

        <!-- summernote -->
        <link rel="stylesheet" href="<?= RUTA_BACKEND ?>views/plugins/summernote/summernote-bs4.min.css">

      <?php
        break;
    }
  }
}



/**
 * FUNCION PARA CARGAR SOLAMENTE LOS SCRIPT NECESARIOS
 */
function cargarScript()
{
  if (isset($_SERVER['REQUEST_URI'])) {
    // obtener el nombre del modulo
    $urlArray = explode('/', $_SERVER['REQUEST_URI']);
    # code...
    // var_dump($urlArray);

    switch ($urlArray[2]) {

      case 'articulos':
      ?>
        <!-- DataTables  & Plugins -->
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/jszip/jszip.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/pdfmake/pdfmake.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/pdfmake/vfs_fonts.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-buttons/js/buttons.print.min.js"></script>
        <script src="<?= RUTA_BACKEND ?>views/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
        <script>
          $(document).ready(function() {
            $('#listarUsuarios').DataTable({
              responsive: true,
              lengthChange: false,
              autoWidth: false,
              language: {
                url: "views/js/spanishtable.json",
              },
              dom: "Bfrtip",
              buttons: ["copy", "csv", "excel", "pdf", "print"],

            });
          });
        </script>
      <?php
        break;
      case 'editarArticulo':
      ?>
        <!-- Editor Summernote -->
        <script async src="../views/plugins/summernote/summernote-bs4.min.js"></script>
        <!-- InputMask -->

        <script async src="<?= RUTA_BACKEND ?>views/plugins/moment/moment.min.js"></script>
        <script async src="<?= RUTA_BACKEND ?>views/plugins/inputmask/jquery.inputmask.min.js"></script>
        <!-- date-range-picker -->
        <script async src="<?= RUTA_BACKEND ?>views/plugins/daterangepicker/daterangepicker.js"></script>
  
      <?php
        break;
      case 'crearArticulo':
      ?>
        <!-- Editor Summernote -->
        <script src="views/plugins/summernote/summernote-bs4.min.js"></script>
        <!-- InputMask -->
        <script src="views/plugins/moment/moment.min.js"></script>
        <script src="views/plugins/inputmask/jquery.inputmask.min.js"></script>
        <!-- date-range-picker -->
        <script src="views/plugins/daterangepicker/daterangepicker.js"></script>
      
    <?php
        break;
    }
  }
}



?>
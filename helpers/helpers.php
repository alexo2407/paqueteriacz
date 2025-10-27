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


function cargarRecursos($pagina) {
  $recursos = [
      "global" => [
          '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">',
          '<link rel="stylesheet" href="' . RUTA_URL . 'vista/css/bootstrap-icons-1.2.1/font/bootstrap-icons.css">',
          '<link rel="stylesheet" href="' . RUTA_URL . 'vista/css/estilos.css">',
      ],
      "datatables" => [
          '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">',
          '<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">',
          '<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">'
      ],
      "ckeditor" => [
          '<script src="https://cdn.ckeditor.com/4.15.1/standard/ckeditor.js"></script>'
      ],
      "maps" => [
          '<script src="https://maps.googleapis.com/maps/api/js?key=' . API_MAP . '&callback=initMap" async defer></script>',
      ]
  ];

  // Detectar la página actual y cargar los recursos específicos
  echo implode("\n", $recursos["global"]);

  $modulosConDatatables = [
    "listar",
    "pedidos",
    "usuarios",
    "clientes",
    "proveedor",
    "stock"
  ];

  if (in_array($pagina, $modulosConDatatables, true)) {
      echo implode("\n", $recursos["datatables"]);
  }

  if ($pagina == "editar") {
      echo implode("\n", $recursos["ckeditor"]);
      echo implode("\n", $recursos["maps"]);
  }
}
?>
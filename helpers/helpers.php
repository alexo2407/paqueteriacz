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
          // Select2 para búsqueda en dropdowns
          '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">',
          '<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">',
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
    "stock",
    "clientes",
    "monedas",
    "paises",
    "departamentos",
    "municipios",
    "barrios",
    "productos",
    "seguimiento"
  ];

  if (in_array($pagina, $modulosConDatatables, true)) {
      echo implode("\n", $recursos["datatables"]);
  }

  if ($pagina == "editar") {
      echo implode("\n", $recursos["ckeditor"]);
      echo implode("\n", $recursos["maps"]);
  }
}

/**
 * Obtener el ID del usuario actual desde la sesión.
 * Útil para auditoría y registro de acciones.
 *
 * @return int|null ID del usuario logueado o null si no hay sesión
 */
function getIdUsuarioActual(): ?int
{
    if (session_status() === PHP_SESSION_NONE) {
        return null;
    }
    // Priorizar user_id (variable principal del sistema)
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    // Compatibilidad con ID_Usuario
    return isset($_SESSION['ID_Usuario']) ? (int)$_SESSION['ID_Usuario'] : null;
}

/**
 * Obtener la IP del cliente de forma segura.
 * Soporta proxies, Cloudflare y conexiones directas.
 *
 * @return string|null IP del cliente o null si no está disponible
 */
function getClientIp(): ?string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Proxies
        'HTTP_X_REAL_IP',            // Nginx
        'HTTP_CLIENT_IP',            // Proxy alternativo
        'REMOTE_ADDR'                // Conexión directa
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validar que sea una IP válida
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return null;
}
?>
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
  // ── Dual-layout support ──────────────────────────────────────────────────
  // Si la vista declara $usaMaterialize = true ANTES del include de template,
  // omitimos Bootstrap CSS y Select2 (Materialize los carga en su propio header).
  $esMaterialize = $GLOBALS['usaMaterialize'] ?? false;

  if (!$esMaterialize) {
      // ── Assets Bootstrap (layout por defecto) ─────────────────────────
      $recursosBootstrap = [
          '<link href="' . RUTA_URL . 'vista/css/bootstrap.min.css" rel="stylesheet">',
          '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">',
          '<link rel="stylesheet" href="' . RUTA_URL . 'vista/css/estilos.css">',
          '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">',
          '<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">',
      ];
      echo implode("\n", $recursosBootstrap);

      $modulosConDatatables = [
          "listar", "pedidos", "usuarios", "stock", "clientes", "monedas",
          "paises", "departamentos", "municipios", "barrios", "productos",
          "seguimiento", "crm"
      ];
      if (in_array($pagina, $modulosConDatatables, true)) {
          $recursosDatatables = [
              '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">',
              '<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">',
              '<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">',
          ];
          echo implode("\n", $recursosDatatables);
      }
      if ($pagina == "editar") {
          echo '<script src="https://cdn.ckeditor.com/4.15.1/standard/ckeditor.js"></script>';
          echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . API_MAP . '&callback=initMap" async defer></script>';
      }
  } else {
      // ── Assets Materialize (cargados aquí para que lleguen al <head>) ──
      // Solo cargamos el CSS de estilos globales propio que no depende de Bootstrap.
      // El CSS y JS de Materialize se cargan en header_materialize.php.
      echo '<link rel="stylesheet" href="' . RUTA_URL . 'vista/css/estilos_mz.css">' . "\n";
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

/**
 * Convierte una fecha/hora UTC de la base de datos a la hora local del usuario.
 *
 * Uso en vistas:
 *   <?= localDate($row['created_at']) ?>          → "20/03/2026 14:35"
 *   <?= localDate($row['created_at'], 'd/m/Y') ?> → "20/03/2026"
 *   <?= localDate($row['created_at'], 'H:i') ?>   → "08:35"
 *
 * La timezone se toma de $_SESSION['user_timezone'] (detectada automáticamente
 * por el navegador al hacer login). Si no existe, muestra la hora UTC.
 *
 * @param string|null $utcDatetime  Fecha en formato MySQL: 'Y-m-d H:i:s' (guardada en UTC)
 * @param string      $format       Formato de salida (default: 'd/m/Y H:i')
 * @param string|null $fallback     Texto cuando la fecha es null/vacía (default: '—')
 * @return string Fecha formateada en la timezone del usuario
 */
function localDate(?string $utcDatetime, string $format = 'd/m/Y H:i', string $fallback = '—'): string
{
    if (empty($utcDatetime) || $utcDatetime === '0000-00-00 00:00:00') {
        return $fallback;
    }

    // Leer la timezone del usuario desde la sesión
    $userTz = 'America/Managua';
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_timezone'])) {
        $userTz = $_SESSION['user_timezone'];
    }

    try {
        // La fecha ahora se captura y guarda en la timezone del sistema (America/Managua)
        $systemTz = date_default_timezone_get();
        $dt = new DateTime($utcDatetime, new DateTimeZone($systemTz));
        $dt->setTimezone(new DateTimeZone($userTz));
        return $dt->format($format);
    } catch (Exception $e) {
        return $utcDatetime; // fallback: mostrar tal cual
    }
}

/**
 * Devuelve la timezone activa del usuario (o 'UTC' por defecto).
 * Útil para mostrarla en la UI o pasarla a JS.
 *
 * @return string  Ej: 'America/Managua'
 */
function getUserTimezone(): string
{
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_timezone'])) {
        return $_SESSION['user_timezone'];
    }
    return 'America/Managua';
}

/**
 * Convierte una fecha/hora UTC a un formato relativo y humano en español.
 * Ej: "hace 5 minutos (14 de abr, 14:35)"
 *
 * @param string|null $utcDatetime Fecha en formato MySQL: 'Y-m-d H:i:s' (guardada en UTC)
 * @return string Fecha formateada relativa
 */
function humanizeDate(?string $utcDatetime): string
{
    if (empty($utcDatetime) || $utcDatetime === '0000-00-00 00:00:00') {
        return '—';
    }

    $userTz = 'America/Managua';
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_timezone'])) {
        $userTz = $_SESSION['user_timezone'];
    }

    try {
        $systemTz = date_default_timezone_get();
        $dt = new DateTime($utcDatetime, new DateTimeZone($systemTz));
        $now = new DateTime('now', new DateTimeZone($systemTz));
        $seconds = $now->getTimestamp() - $dt->getTimestamp();
        
        $localDt = new DateTime($utcDatetime, new DateTimeZone($systemTz));
        $localDt->setTimezone(new DateTimeZone($userTz));

        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes = $meses[(int)$localDt->format('n') - 1];
        
        // Evitamos mostrar el año si es el actual
        $yearAppend = '';
        if ($localDt->format('Y') !== (new DateTime('now', new DateTimeZone($userTz)))->format('Y')) {
            $yearAppend = ' ' . $localDt->format('Y');
        }
        
        $formattedDate = $localDt->format('j') . " de $mes$yearAppend, " . $localDt->format('g:i a');

        if ($seconds < 0) {
            return $formattedDate; // En un caso anómalo, retornamos localDate
        }
        if ($seconds < 60) {
            return "hace unos segundos ($formattedDate)";
        }
        if ($seconds < 3600) {
            $m = floor($seconds / 60);
            return "hace $m minuto" . ($m == 1 ? '' : 's') . " ($formattedDate)";
        }
        if ($seconds < 86400) {
            $h = floor($seconds / 3600);
            return "hace $h hora" . ($h == 1 ? '' : 's') . " ($formattedDate)";
        }
        if ($seconds < 172800) {
            return "ayer ($formattedDate)";
        }
        if ($seconds < 604800) {
            $d = floor($seconds / 86400);
            return "hace $d días ($formattedDate)";
        }
        
        return $formattedDate; // Más de 7 días, mostramos solo la fecha
    } catch (Exception $e) {
        return $utcDatetime; 
    }
}
?>
<?php 


$ruta = isset($_GET['enlace']) ? explode("/", $_GET['enlace']) : ['inicio'];

if ($ruta[0] === "api") {
    // Incluir el archivo API correspondiente sin cargar el template
    $archivoAPI = "api/" . (isset($ruta[1]) ? $ruta[1] : "index") . "/" . (isset($ruta[2]) ? $ruta[2] : "index") . ".php";
    
    if (file_exists($archivoAPI)) {
        include $archivoAPI;
    } else {
        // Manejar error si el archivo API no existe
        header("HTTP/1.0 404 Not Found");
        echo json_encode(["error" => "Endpoint not found"]);
    }
    exit; // Detener la ejecución
}

//config
require_once __DIR__ . "/config/config.php";

// Manejar salida (logout) antes de generar cualquier salida HTML
if ($ruta[0] === 'salir') {
    require_once __DIR__ . '/utils/session.php';
    logout();
    header('Location: ' . RUTA_URL . 'login');
    exit;
}

// Nota: el manejo de rutas POST (incluyendo login) se ha movido a `rutas/web.php`
// para mantener `index.php` como bootstrap mínimo.

// Nota: manejo de proveedores (POST) movido a `rutas/web.php`.

//helper
// Rutas específicas de la aplicación: delegamos el manejo de rutas POST/GET
// a un archivo dedicado para mantener `index.php` como bootstrap limpio.
require_once __DIR__ . '/rutas/web.php';

// Nota: manejo de stock (POST) movido a `rutas/web.php`.

// Nota: manejo de usuarios (POST) movido a `rutas/web.php` para mantener index.php limpio.

 require_once "helpers/helpers.php";

// Sesión segura y helpers
require_once __DIR__ . '/utils/session.php';
start_secure_session();

// Redirigir raíz según estado de sesión
$enlaceSolicitado = $_GET['enlace'] ?? null;
$primerSegmento = $ruta[0] ?? '';

if ($enlaceSolicitado === null || $enlaceSolicitado === '') {
    if (!empty($_SESSION['registrado'])) {
        header('Location: ' . RUTA_URL . 'dashboard');
    } else {
        header('Location: ' . RUTA_URL . 'login');
    }
    exit;
}

if ($primerSegmento === 'inicio' && !empty($_SESSION['registrado'])) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

//modelos
require_once "modelo/enlaces.php";




require_once "modelo/usuario.php";
require_once "modelo/cliente.php";
require_once "modelo/pedido.php";
require_once "modelo/stock.php";
require_once "modelo/producto.php";
require_once "modelo/moneda.php";
require_once "modelo/barrio.php";
require_once "modelo/departamento.php";
require_once "modelo/municipio.php";
require_once "modelo/pais.php"; 

//controladores
require_once "controlador/enlaces.php";
require_once "controlador/usuario.php";
require_once "controlador/cliente.php";
require_once "controlador/pedido.php";
require_once "controlador/stock.php";
require_once "controlador/producto.php";
require_once "controlador/moneda.php";
require_once "controlador/barrio.php";
require_once "controlador/departamento.php";
require_once "controlador/municipio.php";
require_once "controlador/pais.php";
require_once "controlador/dashboard.php";



require_once "controlador/template.php";

$plantilla = new templateController();
$plantilla->template();



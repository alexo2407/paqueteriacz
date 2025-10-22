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

// Manejo de login vía formulario (MVC): cuando se hace POST a ?enlace=login
if (isset($ruta[0]) && $ruta[0] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cargar dependencias necesarias
    require_once "config/config.php";
    require_once "modelo/usuario.php";
    require_once "controlador/usuario.php";

    // Llamar al controlador de usuarios para procesar el login
    $ctrl = new UsuariosController();
    $ctrl->login();
    exit;
}

// Manejo de crear/actualizar proveedor vía POST
if (isset($ruta[0]) && $ruta[0] === 'prooveedor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // acción puede ser guardar o actualizar
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once "config/config.php";
    require_once "controlador/proveedor.php";

    $ctrl = new ProveedorController();

    if ($accion === 'guardar') {
        $data = [
            'nombre' => $_POST['nombre'] ?? null,
            'email' => $_POST['email'] ?? null,
            'telefono' => $_POST['telefono'] ?? null,
        ];
        $ctrl->crearProveedor($data);
        header('Location: ' . RUTA_URL . 'prooveedor/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : null;
        $data = [
            'nombre' => $_POST['nombre'] ?? null,
            'email' => $_POST['email'] ?? null,
            'telefono' => $_POST['telefono'] ?? null,
        ];
        if ($id) {
            $ctrl->actualizarProveedor($id, $data);
        }
        header('Location: ' . RUTA_URL . 'prooveedor/listar');
        exit;
    }

}




//config

require "config/config.php";


//helper

 require_once "helpers/helpers.php";

// Sesión segura y helpers
require_once __DIR__ . '/utils/session.php';
start_secure_session();

//modelos
require_once "modelo/enlaces.php";




require_once "modelo/usuario.php";
require_once "modelo/cliente.php";
require_once "modelo/pedido.php";

//controladores
require_once "controlador/enlaces.php";
require_once "controlador/usuario.php";
require_once "controlador/cliente.php";
require_once "controlador/pedido.php";




require_once "controlador/template.php";

$plantilla = new templateController();
$plantilla->template();



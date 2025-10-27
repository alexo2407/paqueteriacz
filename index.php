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

// Manejo de login vía formulario (MVC): cuando se hace POST a ?enlace=login
if (isset($ruta[0]) && $ruta[0] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cargar dependencias necesarias
    require_once "modelo/usuario.php";
    require_once "controlador/usuario.php";

    // Llamar al controlador de usuarios para procesar el login
    $ctrl = new UsuariosController();
    $ctrl->login();
    exit;
}

// Manejo de crear/actualizar proveedor vía POST
if (isset($ruta[0]) && $ruta[0] === 'proveedor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once "controlador/proveedor.php";

    $ctrl = new ProveedorController();

    if ($accion === 'guardar') {
        $jsonData = [
            'nombre' => $_POST['nombre'] ?? null,
            'email' => $_POST['email'] ?? null,
            'telefono' => $_POST['telefono'] ?? null,
            'pais' => $_POST['pais'] ?? null,
            'contrasena' => $_POST['contrasena'] ?? null,
        ];
        $response = $ctrl->crearProveedorAPI($jsonData);
        require_once __DIR__ . '/utils/session.php';
        start_secure_session();
        if ($response['success']) {
            set_flash('success', $response['message']);
            if (!empty($response['data'])) {
                $_SESSION['last_created_provider_id'] = (int) $response['data'];
            }
            header('Location: ' . RUTA_URL . 'proveedor/listar');
            exit;
        }

        set_flash('error', $response['message']);
        header('Location: ' . RUTA_URL . 'proveedor/crear');
        exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : null;
        $data = [
            'nombre' => $_POST['nombre'] ?? null,
            'email' => $_POST['email'] ?? null,
            'telefono' => $_POST['telefono'] ?? null,
            'pais' => $_POST['pais'] ?? null,
            'contrasena' => $_POST['contrasena'] ?? null,
        ];
        if ($id) {
            $ok = $ctrl->actualizarProveedor($id, $data);
            require_once __DIR__ . '/utils/session.php';
            if ($ok) {
                set_flash('success', 'Proveedor actualizado correctamente.');
                header('Location: ' . RUTA_URL . 'proveedor/listar');
                exit;
            }

            set_flash('error', 'No se realizaron cambios en el proveedor.');
            header('Location: ' . RUTA_URL . 'proveedor/editar/' . $id);
            exit;
        }

        header('Location: ' . RUTA_URL . 'proveedor/listar');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : null;
        require_once __DIR__ . '/utils/session.php';
        start_secure_session();

        if (!$id) {
            set_flash('error', 'Proveedor no válido.');
            header('Location: ' . RUTA_URL . 'proveedor/listar');
            exit;
        }

        $resultado = $ctrl->eliminarProveedor($id);
        set_flash(!empty($resultado['success']) ? 'success' : 'error', $resultado['message']);
        header('Location: ' . RUTA_URL . 'proveedor/listar');
        exit;
    }

}

//helper
// Manejo de importación masiva de pedidos vía CSV
if (isset($ruta[0]) && $ruta[0] === 'pedidos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once "modelo/pedido.php";
    require_once "controlador/pedido.php";

    $ctrl = new PedidosController();

    if ($accion === 'importar') {
        // El método importará y redireccionará con mensajes en sesión
        $ctrl->importarPedidosCSV();
        exit;
    }

    if ($accion === 'guardarPedido') {
        require_once __DIR__ . '/utils/session.php';
        start_secure_session();

        $payload = [
            'numero_orden' => $_POST['numero_orden'] ?? '',
            'destinatario' => $_POST['destinatario'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'producto' => $_POST['producto'] ?? '',
            'cantidad' => $_POST['cantidad'] ?? null,
            'estado' => $_POST['estado'] ?? null,
            'vendedor' => $_POST['vendedor'] ?? null,
            'comentario' => $_POST['comentario'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'latitud' => $_POST['latitud'] ?? null,
            'longitud' => $_POST['longitud'] ?? null,
            'pais' => $_POST['pais'] ?? null,
            'departamento' => $_POST['departamento'] ?? null,
            'municipio' => $_POST['municipio'] ?? null,
            'barrio' => $_POST['barrio'] ?? null,
            'zona' => $_POST['zona'] ?? null,
            'precio' => $_POST['precio'] ?? null,
        ];

        $resultado = $ctrl->guardarPedidoFormulario($payload);
        set_flash(!empty($resultado['success']) ? 'success' : 'error', $resultado['message'] ?? 'No fue posible guardar el pedido.');

        $redirect = !empty($resultado['success']) ? RUTA_URL . 'pedidos/listar' : RUTA_URL . 'pedidos/crearPedido';
        header('Location: ' . $redirect);
        exit;
    }

}

// Manejo de stock
if (isset($ruta[0]) && $ruta[0] === 'stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once "controlador/stock.php";
    require_once __DIR__ . '/utils/session.php';
    start_secure_session();

    $ctrl = new StockController();

    if ($accion === 'guardar') {
        $data = [
            'id_vendedor' => isset($_POST['id_vendedor']) ? (int) $_POST['id_vendedor'] : 0,
            'producto' => $_POST['producto'] ?? '',
            'cantidad' => isset($_POST['cantidad']) ? (int) $_POST['cantidad'] : null,
        ];
        $response = $ctrl->crear($data);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'stock/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) {
            set_flash('error', 'Registro de stock inválido.');
            header('Location: ' . RUTA_URL . 'stock/listar');
            exit;
        }

        $data = [
            'id_vendedor' => isset($_POST['id_vendedor']) ? (int) $_POST['id_vendedor'] : 0,
            'producto' => $_POST['producto'] ?? '',
            'cantidad' => isset($_POST['cantidad']) ? (int) $_POST['cantidad'] : null,
        ];
        $response = $ctrl->actualizar($id, $data);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'stock/editar/' . $id);
        exit;
    }

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $response = $ctrl->eliminar($id);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'stock/listar');
        exit;
    }
}

// Manejo de actualización de usuarios
if (isset($ruta[0]) && $ruta[0] === 'usuarios' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once "controlador/usuario.php";
    require_once __DIR__ . '/utils/session.php';
    start_secure_session();

    $ctrl = new UsuariosController();

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) {
            set_flash('error', 'Usuario no válido.');
            header('Location: ' . RUTA_URL . 'usuarios/listar');
            exit;
        }

        $redirectUrl = RUTA_URL . 'usuarios/editar/' . $id;

        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $idRol = isset($_POST['id_rol']) ? (int) $_POST['id_rol'] : 0;
        $contrasena = $_POST['contrasena'] ?? '';

        if ($nombre === '' || $email === '') {
            set_flash('error', 'Nombre y correo electrónico son obligatorios.');
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Correo electrónico inválido.');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $rolesDisponibles = UsuariosController::obtenerRolesDisponibles();
        if (!array_key_exists($idRol, $rolesDisponibles)) {
            set_flash('error', 'Rol seleccionado inválido.');
            header('Location: ' . $redirectUrl);
            exit;
        }

        $payload = [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono === '' ? null : $telefono,
            'id_rol' => $idRol
        ];

        if (!empty($contrasena)) {
            $payload['contrasena'] = $contrasena;
        }

        $resultado = $ctrl->actualizarUsuario($id, $payload);

        if (!empty($resultado['success'])) {
            if (!empty($resultado['changed'])) {
                set_flash('success', 'Usuario actualizado correctamente.');
            } else {
                set_flash('success', 'No se detectaron cambios en el usuario.');
            }
        } else {
            $mensajeError = $resultado['message'] ?? 'No fue posible actualizar el usuario.';
            set_flash('error', $mensajeError);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }
}

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
require_once "modelo/proveedor.php";
require_once "modelo/stock.php";

//controladores
require_once "controlador/enlaces.php";
require_once "controlador/usuario.php";
require_once "controlador/cliente.php";
require_once "controlador/pedido.php";
require_once "controlador/proveedor.php";
require_once "controlador/stock.php";




require_once "controlador/template.php";

$plantilla = new templateController();
$plantilla->template();



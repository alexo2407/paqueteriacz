<?php
// rutas/web.php
// Archivo responsable de manejar rutas web específicas (POST/GET) fuera de index.php
// Objetivo: mantener `index.php` como bootstrap mínimo y centralizar la lógica de enrutamiento.

// Asegurarse de que la variable $ruta esté definida por index.php antes de incluir este archivo.
// index.php debe haber cargado config/constants y, si es necesario, iniciar la sesión.

// Incluir sesión por seguridad si no estuviera iniciada en index.php
// Usamos el path relativo al directorio raíz del proyecto
require_once __DIR__ . '/../utils/session.php';
start_secure_session();

// Manejo de importación masiva y creación de pedidos
if (isset($ruta[0]) && $ruta[0] === 'pedidos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción secundaria indicada en la URL: /?enlace=pedidos/<accion>
    $accion = isset($ruta[1]) ? $ruta[1] : '';

    // Cargar dependencias necesarias localmente para evitar problemas de orden
    // Usamos rutas relativas al root: ../modelo y ../controlador
    require_once __DIR__ . '/../modelo/pedido.php';
    require_once __DIR__ . '/../controlador/pedido.php';

    $ctrl = new PedidosController();

    if ($accion === 'importar') {
        // Importar CSV y redirigir (la función interna se encarga del set_flash)
        $ctrl->importarPedidosCSV();
        exit;
    }

    if ($accion === 'guardarPedido') {
        // Start buffering to prevent spurious output (warnings, notices) from breaking JSON response
        ob_start();

        // Construir payload desde $_POST y delegar al controlador
        // NOTE: usamos los mismos nombres de campos que el formulario en
        // `vista/modulos/pedidos/crearPedido.php` y que espera
        // `PedidosController::guardarPedidoFormulario()`.
        $payload = [
            'numero_orden' => $_POST['numero_orden'] ?? '',
            'destinatario' => $_POST['destinatario'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            // Support multiple products via productos[] array or single producto_id/cantidad_producto
            'producto_id' => $_POST['producto_id'] ?? null,
            'cantidad_producto' => $_POST['cantidad_producto'] ?? null,
            'productos' => isset($_POST['productos']) ? $_POST['productos'] : null,
            'estado' => $_POST['estado'] ?? null,
            'vendedor' => $_POST['vendedor'] ?? null,
            'proveedor' => $_POST['proveedor'] ?? null,
            'comentario' => $_POST['comentario'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'latitud' => $_POST['latitud'] ?? null,
            'longitud' => $_POST['longitud'] ?? null,
            'id_pais' => $_POST['id_pais'] ?? ($_POST['pais'] ?? null),
            'id_departamento' => $_POST['id_departamento'] ?? ($_POST['departamento'] ?? null),
            'municipio' => $_POST['municipio'] ?? null,
            'barrio' => $_POST['barrio'] ?? null,
            'zona' => $_POST['zona'] ?? null,
            'precio_local' => $_POST['precio_local'] ?? null,
            'precio_usd' => $_POST['precio_usd'] ?? null,
            'moneda' => $_POST['moneda'] ?? null,
        ];

        // Llamar al controlador para procesar y persistir el pedido
        $resultado = $ctrl->guardarPedidoFormulario($payload);

        // Detectar si la petición viene por AJAX (fetch/XHR) o si el cliente espera JSON
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // Si es AJAX devolvemos JSON con la estructura estándar y no redirigimos.
        // Esto permite que el frontend (fetch) muestre SweetAlert y mantenga el formulario
        // en la misma página sin reload. Para peticiones tradicionales mantenemos el
        // flujo histórico (set_flash + redirect).
        if ($isAjax) {
            // Clean buffer to ensure valid JSON
            $output = ob_get_clean();
            if (!empty($output) && defined('DEBUG') && DEBUG) {
                error_log("Spurious output in guardarPedido: " . $output);
            }

            header('Content-Type: application/json');
            $id = $resultado['id'] ?? $resultado['data'] ?? null;
            $resp = [
                'success' => !empty($resultado['success']),
                'message' => $resultado['message'] ?? 'No fue posible guardar el pedido.',
                'id' => $id
            ];

            // Si se creó un nuevo pedido, sugerimos la URL para editarlo. El cliente
            // puede usar este campo `redirect` para llevar al usuario automáticamente
            // a la página de edición.
            if (!empty($id)) {
                // Redirigir directamente a la página de edición sin query params
                $resp['redirect'] = RUTA_URL . 'pedidos/editar/' . $id;
            }

            echo json_encode($resp);
            exit;
        }

        // Flush buffer for normal requests
        ob_end_flush();

        // Mensaje flash y redirección para comportamiento no-AJAX (fallback)
        set_flash(!empty($resultado['success']) ? 'success' : 'error', $resultado['message'] ?? 'No fue posible guardar el pedido.');

        // If saving failed and this is a normal (non-AJAX) request, persist the submitted
        // payload into session so the create form can repopulate the user's inputs.
        if (empty($resultado['success'])) {
            // keep only relevant fields to avoid storing huge or sensitive data
            $store = $payload;
            // normalize productos to a safe structure
            if (isset($store['productos']) && is_array($store['productos'])) {
                $safeItems = [];
                foreach ($store['productos'] as $it) {
                    $safeItems[] = [
                        'producto_id' => isset($it['producto_id']) ? $it['producto_id'] : null,
                        'cantidad' => isset($it['cantidad']) ? $it['cantidad'] : null
                    ];
                }
                $store['productos'] = $safeItems;
            }
            // Save to session for a single-use repopulation in the view
            $_SESSION['old_pedido'] = $store;
        }

        $redirect = !empty($resultado['success']) ? RUTA_URL . 'pedidos/listar' : RUTA_URL . 'pedidos/crearPedido';
        header('Location: ' . $redirect);
        exit;
    }
}

// -----------------------
// Manejo de monedas (POST a ?enlace=monedas/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'monedas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/moneda.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new MonedasController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = [
            'codigo' => $_POST['codigo'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
            'tasa_usd' => isset($_POST['tasa_usd']) && $_POST['tasa_usd'] !== '' ? $_POST['tasa_usd'] : null,
        ];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'monedas/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) { set_flash('error', 'Moneda inválida.'); header('Location: ' . RUTA_URL . 'monedas/listar'); exit; }
        $payload = [
            'codigo' => $_POST['codigo'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
            'tasa_usd' => isset($_POST['tasa_usd']) && $_POST['tasa_usd'] !== '' ? $_POST['tasa_usd'] : null,
        ];
        $response = $ctrl->actualizar($id, $payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'monedas/editar/' . $id);
        exit;
    }

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $response = $ctrl->eliminar($id);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'monedas/listar');
        exit;
    }
}

// -----------------------
// Manejo de paises (POST a ?enlace=paises/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'paises' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/pais.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new PaisesController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'codigo_iso' => $_POST['codigo_iso'] ?? null];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'paises/listar'); exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; if ($id <= 0) { set_flash('error','País inválido'); header('Location: '.RUTA_URL.'paises/listar'); exit; }
        $payload = ['nombre'=> $_POST['nombre'] ?? '', 'codigo_iso' => $_POST['codigo_iso'] ?? null];
        $response = $ctrl->actualizar($id, $payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: '.RUTA_URL.'paises/editar/'.$id); exit;
    }

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; $response = $ctrl->eliminar($id); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'paises/listar'); exit;
    }
}

// -----------------------
// Manejo de departamentos (POST a ?enlace=departamentos/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'departamentos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/departamento.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new DepartamentosController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'id_pais' => $_POST['id_pais'] ?? null];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'departamentos/listar'); exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; if ($id<=0) { set_flash('error','Departamento inválido'); header('Location: '.RUTA_URL.'departamentos/listar'); exit; }
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'id_pais' => $_POST['id_pais'] ?? null];
        $response = $ctrl->actualizar($id, $payload); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'departamentos/editar/'.$id); exit;
    }

    if ($accion === 'eliminar') { $id = isset($ruta[2]) ? (int)$ruta[2] : 0; $response = $ctrl->eliminar($id); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'departamentos/listar'); exit; }
}

// -----------------------
// Manejo de municipios (POST a ?enlace=municipios/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'municipios' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/municipio.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new MunicipiosController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'id_departamento' => $_POST['id_departamento'] ?? null];
        $response = $ctrl->crear($payload); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'municipios/listar'); exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; if ($id<=0) { set_flash('error','Municipio inválido'); header('Location: '.RUTA_URL.'municipios/listar'); exit; }
        $payload = ['nombre'=> $_POST['nombre'] ?? '', 'id_departamento' => $_POST['id_departamento'] ?? null]; $response = $ctrl->actualizar($id,$payload); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'municipios/editar/'.$id); exit;
    }

    if ($accion === 'eliminar') { $id = isset($ruta[2]) ? (int)$ruta[2] : 0; $response = $ctrl->eliminar($id); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'municipios/listar'); exit; }
}

// -----------------------
// Manejo de barrios (POST a ?enlace=barrios/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'barrios' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/barrio.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new BarriosController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'id_municipio' => $_POST['id_municipio'] ?? null];
        $response = $ctrl->crear($payload); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'barrios/listar'); exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; if ($id<=0) { set_flash('error','Barrio inválido'); header('Location: '.RUTA_URL.'barrios/listar'); exit; }
        $payload = ['nombre'=> $_POST['nombre'] ?? '', 'id_municipio' => $_POST['id_municipio'] ?? null]; $response = $ctrl->actualizar($id,$payload); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'barrios/editar/'.$id); exit;
    }

    if ($accion === 'eliminar') { $id = isset($ruta[2]) ? (int)$ruta[2] : 0; $response = $ctrl->eliminar($id); set_flash($response['success'] ? 'success' : 'error', $response['message']); header('Location: '.RUTA_URL.'barrios/listar'); exit; }
}


// -----------------------
// Manejo de productos (POST a ?enlace=productos/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'productos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/producto.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new ProductosController();

    if ($accion === 'guardar' || $accion === 'crear') {
        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'precio_usd' => isset($_POST['precio_usd']) && $_POST['precio_usd'] !== '' ? $_POST['precio_usd'] : null,
        ];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) {
            set_flash('error', 'Producto inválido.');
            header('Location: ' . RUTA_URL . 'productos/listar');
            exit;
        }
        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'precio_usd' => isset($_POST['precio_usd']) && $_POST['precio_usd'] !== '' ? $_POST['precio_usd'] : null,
        ];
        $response = $ctrl->actualizar($id, $payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/editar/' . $id);
        exit;
    }

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $response = $ctrl->eliminar($id);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/listar');
        exit;
    }
}

// Manejo de login vía formulario (POST a ?enlace=login)
if (isset($ruta[0]) && $ruta[0] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cargar dependencias necesarias para el login
    require_once __DIR__ . '/../modelo/usuario.php';
    require_once __DIR__ . '/../controlador/usuario.php';

    // Llamar al controlador de usuarios para procesar el login
    $ctrl = new UsuariosController();
    $ctrl->login();
    exit;
}

// Manejo de acciones sobre usuarios (ej. actualizar)
if (isset($ruta[0]) && $ruta[0] === 'usuarios' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';

    // Asegurar que el controlador de usuarios esté disponible
    require_once __DIR__ . '/../controlador/usuario.php';
    // Iniciar sesión si no está iniciada (seguridad)
    require_once __DIR__ . '/../utils/session.php';
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
        // Multi-rol: lista de roles seleccionados
        $rolesSeleccionados = [];
        if (isset($_POST['roles']) && is_array($_POST['roles'])) {
            foreach ($_POST['roles'] as $rid) {
                if (is_numeric($rid)) $rolesSeleccionados[] = (int)$rid;
            }
            $rolesSeleccionados = array_values(array_unique(array_filter($rolesSeleccionados)));
        }
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
        // Validar que al menos un rol válido sea seleccionado
        if (empty($rolesSeleccionados)) {
            set_flash('error', 'Debe seleccionar al menos un rol.');
            header('Location: ' . $redirectUrl);
            exit;
        }
        foreach ($rolesSeleccionados as $rid) {
            if (!array_key_exists($rid, $rolesDisponibles)) {
                set_flash('error', 'Rol seleccionado inválido.');
                header('Location: ' . $redirectUrl);
                exit;
            }
        }

        $payload = [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono === '' ? null : $telefono,
            'roles' => $rolesSeleccionados
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

// Nota: el manejo de proveedores como entidad independiente fue removido.
// Los proveedores ahora se representan como usuarios con el rol definido por
// `ROL_NOMBRE_PROVEEDOR`. Cualquier operación sobre proveedores debe realizarse
// a través de `UsuariosController` y/o API de usuarios.

// -----------------------
// Manejo de stock (POST a ?enlace=stock/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/stock.php';
    require_once __DIR__ . '/../utils/session.php';
    start_secure_session();

    $ctrl = new StockController();

    if ($accion === 'guardar') {
        // Aceptamos nombres legacy (id_vendedor, producto) y nuevos (id_usuario, id_producto)
        $idUsuario = null;
        if (isset($_POST['id_vendedor'])) $idUsuario = (int) $_POST['id_vendedor'];
        if (isset($_POST['id_usuario'])) $idUsuario = (int) $_POST['id_usuario'];

        $productoField = null;
        if (isset($_POST['producto'])) $productoField = $_POST['producto'];
        if (isset($_POST['id_producto']) && $productoField === null) $productoField = $_POST['id_producto'];

        $data = [
            'id_vendedor' => $idUsuario ?? 0,
            'producto' => $productoField ?? '',
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

        // Accept legacy and new field names
        $idUsuario = null;
        if (isset($_POST['id_vendedor'])) $idUsuario = (int) $_POST['id_vendedor'];
        if (isset($_POST['id_usuario'])) $idUsuario = (int) $_POST['id_usuario'];

        $productoField = null;
        if (isset($_POST['producto'])) $productoField = $_POST['producto'];
        if (isset($_POST['id_producto']) && $productoField === null) $productoField = $_POST['id_producto'];

        $data = [
            'id_vendedor' => $idUsuario ?? 0,
            'producto' => $productoField ?? '',
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

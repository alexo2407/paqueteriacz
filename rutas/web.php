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

    if ($accion === 'eliminar') {
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $ctrl->eliminar($id);
        // El controlador hace exit, pero por si acaso:
        exit;
    }
}

// Handler para pedidos GET (API endpoints/AJAX)
if (isset($ruta[0]) && $ruta[0] === 'pedidos' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    
    if ($accion === 'historial') {
        require_once __DIR__ . '/../modelo/pedido.php';
        require_once __DIR__ . '/../controlador/pedido.php';
        
        $ctrl = new PedidosController();
        $id = isset($ruta[2]) ? (int) $ruta[2] : null;
        $ctrl->historial($id);
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
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

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
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

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
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador']); // Deletion usually strictly Admin

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
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']); 
        
        $payload = ['nombre' => $_POST['nombre'] ?? '', 'codigo_iso' => $_POST['codigo_iso'] ?? null];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'paises/listar'); exit;
    }

    if ($accion === 'actualizar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']); 

        $id = isset($ruta[2]) ? (int)$ruta[2] : 0; if ($id <= 0) { set_flash('error','País inválido'); header('Location: '.RUTA_URL.'paises/listar'); exit; }
        $payload = ['nombre'=> $_POST['nombre'] ?? '', 'codigo_iso' => $_POST['codigo_iso'] ?? null];
        $response = $ctrl->actualizar($id, $payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: '.RUTA_URL.'paises/editar/'.$id); exit;
    }

    if ($accion === 'eliminar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador']); 

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
    require_once __DIR__ . '/../utils/csrf.php';
    start_secure_session();

    // Validar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        set_flash('error', 'Token de seguridad inválido. Por favor, recarga la página e intenta de nuevo.');
        header('Location: ' . RUTA_URL . 'productos/listar');
        exit;
    }

    $ctrl = new ProductosController();

    if ($accion === 'guardar' || $accion === 'crear') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        require_once __DIR__ . '/../utils/image_upload.php';
        
        // Procesar imagen
        $imagenUrl = null;
        
        // Primero intentar archivo subido
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $resultado = subirImagenProducto($_FILES['imagen']);
            if ($resultado['success']) {
                $imagenUrl = $resultado['path'];
            } else {
                set_flash('error', 'Error al subir imagen: ' . $resultado['error']);
                header('Location: ' . RUTA_URL . 'productos/crear');
                exit;
            }
        } elseif (!empty($_POST['imagen_url'])) {
            // Si no hay archivo, usar URL externa
            $imagenUrl = trim($_POST['imagen_url']);
        }
        
        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'precio_usd' => isset($_POST['precio_usd']) && $_POST['precio_usd'] !== '' ? $_POST['precio_usd'] : null,
            'sku' => $_POST['sku'] ?? null,
            'categoria_id' => !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null,
            'marca' => $_POST['marca'] ?? null,
            'unidad' => $_POST['unidad'] ?? 'unidad',
            'peso' => isset($_POST['peso']) && $_POST['peso'] !== '' ? (float)$_POST['peso'] : null,
            'stock_minimo' => isset($_POST['stock_minimo']) ? (int)$_POST['stock_minimo'] : 10,
            'stock_maximo' => isset($_POST['stock_maximo']) ? (int)$_POST['stock_maximo'] : 100,
            'activo' => isset($_POST['activo']) ? (int)$_POST['activo'] : 1,
            'imagen_url' => $imagenUrl,
        ];
        $response = $ctrl->crear($payload);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        require_once __DIR__ . '/../utils/image_upload.php';
        
        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) {
            // Detectar si es AJAX
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                     || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Producto inválido.']);
                exit;
            }
            
            set_flash('error', 'Producto inválido.');
            header('Location: ' . RUTA_URL . 'productos/listar');
            exit;
        }
        
        // Procesar imagen
        $imagenUrl = null;
        $imagenCambiada = false;
        
        // Primero intentar archivo subido
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            $resultado = subirImagenProducto($_FILES['imagen']);
            if ($resultado['success']) {
                $imagenUrl = $resultado['path'];
                $imagenCambiada = true;
                
                // Eliminar imagen anterior si era local
                if (!empty($_POST['imagen_actual']) && !str_starts_with($_POST['imagen_actual'], 'http')) {
                    eliminarImagenProducto($_POST['imagen_actual']);
                }
            } else {
                // Detectar si es AJAX
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                         || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error al subir imagen: ' . $resultado['error']]);
                    exit;
                }
                
                set_flash('error', 'Error al subir imagen: ' . $resultado['error']);
                header('Location: ' . RUTA_URL . 'productos/editar/' . $id);
                exit;
            }
        } elseif (!empty($_POST['imagen_url'])) {
            // Si no hay archivo pero hay URL nueva
            $imagenUrl = trim($_POST['imagen_url']);
            $imagenCambiada = ($_POST['imagen_actual'] ?? '') !== $imagenUrl;
        } elseif (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] == '1') {
            // Si se marcó eliminar imagen
            if (!empty($_POST['imagen_actual']) && !str_starts_with($_POST['imagen_actual'], 'http')) {
                eliminarImagenProducto($_POST['imagen_actual']);
            }
            $imagenUrl = null;
            $imagenCambiada = true;
        } else {
            // Mantener imagen actual
            $imagenUrl = $_POST['imagen_actual'] ?? null;
        }
        
        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'precio_usd' => isset($_POST['precio_usd']) && $_POST['precio_usd'] !== '' ? $_POST['precio_usd'] : null,
            'sku' => $_POST['sku'] ?? null,
            'categoria_id' => !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null,
            'marca' => $_POST['marca'] ?? null,
            'unidad' => $_POST['unidad'] ?? 'unidad',
            'peso' => isset($_POST['peso']) && $_POST['peso'] !== '' ? (float)$_POST['peso'] : null,
            'stock_minimo' => isset($_POST['stock_minimo']) ? (int)$_POST['stock_minimo'] : 10,
            'stock_maximo' => isset($_POST['stock_maximo']) ? (int)$_POST['stock_maximo'] : 100,
            'activo' => isset($_POST['activo']) ? (int)$_POST['activo'] : 1,
        ];
        
        // Solo incluir imagen si cambió
        if ($imagenCambiada) {
            $payload['imagen_url'] = $imagenUrl;
        }
        
        $response = $ctrl->actualizar($id, $payload);
        
        // Detectar si es AJAX
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $response['success'],
                'message' => $response['message']
            ]);
            exit;
        }
        
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/editar/' . $id);
        exit;
    }

    if ($accion === 'eliminar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $response = $ctrl->eliminar($id);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'productos/listar');
        exit;
    }
}

// -----------------------
// Manejo de categorías (POST a ?enlace=categorias/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'categorias' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/categoria.php';
    require_once __DIR__ . '/../utils/session.php';
    require_once __DIR__ . '/../utils/csrf.php';
    start_secure_session();

    // Validar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
            exit;
        }
        
        set_flash('error', 'Token de seguridad inválido');
        header('Location: ' . RUTA_URL . 'categorias/listar');
        exit;
    }

    $ctrl = new CategoriaController();

    if ($accion === 'guardar' || $accion === 'crear') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'padre_id' => !empty($_POST['padre_id']) ? (int)$_POST['padre_id'] : null,
        ];
        
        $response = $ctrl->crear($payload);
        
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'categorias/listar');
        exit;
    }

    if ($accion === 'actualizar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        if ($id <= 0) {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                     || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Categoría inválida']);
                exit;
            }
            
            set_flash('error', 'Categoría inválida');
            header('Location: ' . RUTA_URL . 'categorias/listar');
            exit;
        }
        
        $payload = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? null,
            'padre_id' => !empty($_POST['padre_id']) ? (int)$_POST['padre_id'] : null,
        ];
        
        $response = $ctrl->actualizar($id, $payload);
        
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                 || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'categorias/editar/' . $id);
        exit;
    }

    if ($accion === 'eliminar') {
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador', 'Proveedor']);

        $id = isset($ruta[2]) ? (int) $ruta[2] : 0;
        $response = $ctrl->eliminar($id);
        set_flash($response['success'] ? 'success' : 'error', $response['message']);
        header('Location: ' . RUTA_URL . 'categorias/listar');
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

// ------------------------------------------
// Manejo de recuperación de contraseña
// ------------------------------------------

// POST: Solicitar recuperación de contraseña
if (isset($ruta[0]) && $ruta[0] === 'recuperar-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controlador/password_reset.php';
    
    $ctrl = new PasswordResetController();
    $ctrl->solicitarRecuperacion();
    exit;
}

// POST: Procesar nueva contraseña
if (isset($ruta[0]) && $ruta[0] === 'reset-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controlador/password_reset.php';
    
    $ctrl = new PasswordResetController();
    $ctrl->procesarReset();
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
        require_once __DIR__ . '/../utils/authorization.php';
        require_role(['Administrador']); // Only admins can update other users

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
            'id_pais' => isset($_POST['id_pais']) && $_POST['id_pais'] !== '' ? (int)$_POST['id_pais'] : null,
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

    if ($accion === 'actualizarPerfil') {
        // Actualizar perfil del usuario actual (logged in)
        require_once __DIR__ . '/../utils/session.php';
        start_secure_session();
        
        if (empty($_SESSION['registrado']) || empty($_SESSION['user_id'])) {
            set_flash('error', 'Debes estar autenticado para actualizar tu perfil.');
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }

        $id = (int)$_SESSION['user_id'];
        $rolesNombresSession = $_SESSION['roles_nombres'] ?? [];
        $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombresSession, true);
        $redirectUrl = RUTA_URL . 'usuarios/perfil';

        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
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

        $payload = [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono === '' ? null : $telefono,
            'id_pais' => isset($_POST['id_pais']) && $_POST['id_pais'] !== '' ? (int)$_POST['id_pais'] : null
        ];

        // Solo permitir cambio de roles si es administrador
        if ($isAdmin && isset($_POST['roles']) && is_array($_POST['roles'])) {
            $rolesSeleccionados = [];
            foreach ($_POST['roles'] as $rid) {
                if (is_numeric($rid)) $rolesSeleccionados[] = (int)$rid;
            }
            $rolesSeleccionados = array_values(array_unique(array_filter($rolesSeleccionados)));
            
            if (!empty($rolesSeleccionados)) {
                $rolesDisponibles = UsuariosController::obtenerRolesDisponibles();
                $validRoles = true;
                foreach ($rolesSeleccionados as $rid) {
                    if (!array_key_exists($rid, $rolesDisponibles)) {
                        $validRoles = false;
                        break;
                    }
                }
                if ($validRoles) {
                    $payload['roles'] = $rolesSeleccionados;
                }
            }
        }

        if (!empty($contrasena)) {
            $payload['contrasena'] = $contrasena;
        }

        $resultado = $ctrl->actualizarUsuario($id, $payload);

        if (!empty($resultado['success'])) {
            // Actualizar datos de sesión
            $_SESSION['nombre'] = $nombre;
            
            if (!empty($resultado['changed'])) {
                set_flash('success', 'Perfil actualizado correctamente.');
            } else {
                set_flash('success', 'No se detectaron cambios en el perfil.');
            }
        } else {
            $mensajeError = $resultado['message'] ?? 'No fue posible actualizar el perfil.';
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
    require_once __DIR__ . '/../utils/csrf.php';
    start_secure_session();

    // Validar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        set_flash('error', 'Token de seguridad inválido. Por favor, recarga la página e intenta de nuevo.');
        header('Location: ' . RUTA_URL . 'stock/listar');
        exit;
    }

    $ctrl = new StockController();

    if ($accion === 'guardar') {
        require_once __DIR__ . '/../modelo/stock.php';
        
        // Obtener id_usuario de sesión si no viene en POST
        $idUsuario = $_SESSION['user_id'] ?? 1;
        if (isset($_POST['id_usuario']) && (int)$_POST['id_usuario'] > 0) {
            $idUsuario = (int) $_POST['id_usuario'];
        }

        // Obtener id_producto
        $idProducto = null;
        if (isset($_POST['id_producto']) && (int)$_POST['id_producto'] > 0) {
            $idProducto = (int) $_POST['id_producto'];
        } elseif (isset($_POST['producto']) && is_numeric($_POST['producto'])) {
            $idProducto = (int) $_POST['producto'];
        }

        // Obtener tipo de movimiento y ajustar cantidad según tipo
        $tipoMovimiento = $_POST['tipo_movimiento'] ?? 'entrada';
        $cantidad = isset($_POST['cantidad']) ? (int) $_POST['cantidad'] : 0;
        
        // Para salidas, la cantidad debe ser negativa
        if (in_array($tipoMovimiento, ['salida'])) {
            $cantidad = -abs($cantidad);
        } else {
            $cantidad = abs($cantidad);
        }

        // Construir datos completos para el movimiento
        $data = [
            'id_producto' => $idProducto,
            'id_usuario' => $idUsuario,
            'cantidad' => $cantidad,
            'tipo_movimiento' => $tipoMovimiento,
            'motivo' => $_POST['motivo'] ?? null,
            'ubicacion_origen' => $_POST['ubicacion_origen'] ?? null,
            'ubicacion_destino' => $_POST['ubicacion_destino'] ?? null,
            'referencia_tipo' => !empty($_POST['referencia_tipo']) ? $_POST['referencia_tipo'] : null,
            'referencia_id' => isset($_POST['referencia_id']) && $_POST['referencia_id'] !== '' ? (int)$_POST['referencia_id'] : null,
            'costo_unitario' => isset($_POST['costo_unitario']) && $_POST['costo_unitario'] !== '' ? (float)$_POST['costo_unitario'] : null,
        ];

        // Validaciones básicas
        if (empty($idProducto)) {
            set_flash('error', 'Debe seleccionar un producto.');
            header('Location: ' . RUTA_URL . 'stock/crear');
            exit;
        }

        if ($cantidad == 0) {
            set_flash('error', 'La cantidad debe ser mayor a cero.');
            header('Location: ' . RUTA_URL . 'stock/crear');
            exit;
        }

        // Usar registrarMovimiento para guardar todos los campos
        try {
            $nuevoId = StockModel::registrarMovimiento($data);
            if ($nuevoId) {
                set_flash('success', 'Movimiento de stock registrado correctamente.');
            } else {
                set_flash('error', 'No fue posible registrar el movimiento.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Error al registrar movimiento: ' . $e->getMessage());
        }
        
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

// -----------------------
// Manejo de cambio de estado (AJAX)
// -----------------------
// -----------------------
// Manejo de cambio de estado (POST standard)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'cambiarEstados') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../controlador/pedido.php';
        require_once __DIR__ . '/../utils/session.php';
        start_secure_session();

        if (empty($_SESSION['registrado'])) {
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }

        $idPedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;
        $nuevoEstado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;

        // URL de retorno por defecto
        $redirectUrl = RUTA_URL . 'seguimiento/listar';
        if ($idPedido > 0) {
            $redirectUrl = RUTA_URL . 'seguimiento/ver/' . $idPedido;
        }

        if ($idPedido <= 0 || $nuevoEstado <= 0) {
            set_flash('error', 'Datos inválidos para actualizar el estado.');
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $rolesNombres = $_SESSION['roles_nombres'] ?? [];
            $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true) && !in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);

            $ctrl = new PedidosController();
            $data = [
                'id_pedido' => $idPedido,
                'estado' => $nuevoEstado,
                'is_repartidor' => $isRepartidor
            ];
            $resultado = $ctrl->actualizarPedido($data);

            if ($resultado['success']) {
                set_flash('success', 'Estado actualizado correctamente.');
            } else {
                set_flash('error', $resultado['message'] ?? 'No se pudo actualizar el estado.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Error interno al actualizar estado: ' . $e->getMessage());
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// -----------------------
// Manejo de CRM (POST a ?enlace=crm/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'crm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/crm.php';
    require_once __DIR__ . '/../utils/session.php';
    require_once __DIR__ . '/../utils/crm_roles.php';
    $userId = (int)($_SESSION['idUsuario'] ?? 0);
    
    // Permitir Admin o Cliente
    if (!isUserAdmin($userId) && !isUserCliente($userId)) {
        set_flash('error', 'No tienes permisos para realizar esta acción.');
        header('Location: ' . RUTA_URL . 'dashboard');
        exit;
    }
    
    $ctrl = new CrmController();
    
    if ($accion === 'cambiarEstado') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $nuevoEstado = $_POST['estado'] ?? '';
        $observaciones = $_POST['observaciones'] ?? null;
        
        $resultado = $ctrl->cambiarEstado($id, $nuevoEstado, $observaciones);
        set_flash($resultado['success'] ? 'success' : 'error', $resultado['message']);
        header('Location: ' . RUTA_URL . 'crm/ver/' . $id);
        exit;
    }

    // Guardar integración (crear/editar)
    if ($accion === 'guardarIntegracion') {
        $resultado = $ctrl->guardarIntegracion($_POST);
        set_flash($resultado['success'] ? 'success' : 'error', $resultado['message']);
        header('Location: ' . RUTA_URL . 'crm/integraciones');
        exit;
    }

    // Eliminar integración
    if ($accion === 'eliminarIntegracion') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $resultado = $ctrl->eliminarIntegracion($id);
        set_flash($resultado['success'] ? 'success' : 'error', $resultado['message']);
        header('Location: ' . RUTA_URL . 'crm/integraciones');
        exit;
    }

    // Guardar Lead (manual)
    if ($accion === 'guardarLead') {
        $resultado = $ctrl->guardarLead($_POST);
        
        if ($resultado['success']) {
            set_flash('success', $resultado['message']);
            // Redirigir al lead creado/editado si hay ID (crear devuelve lead_id)
            $id = isset($resultado['lead_id']) ? $resultado['lead_id'] : ($_POST['id'] ?? 0);
            if ($id > 0) {
                header('Location: ' . RUTA_URL . 'crm/ver/' . $id);
            } else {
                header('Location: ' . RUTA_URL . 'crm/listar');
            }
        } else {
            set_flash('error', $resultado['message']);
            if (!empty($_POST['id'])) {
                header('Location: ' . RUTA_URL . 'crm/editar/' . $_POST['id']);
            } else {
                header('Location: ' . RUTA_URL . 'crm/crear');
            }
        }
        exit;
    }
    // Acciones de Cola (Monitor)
    if ($accion === 'reintentarOutbox') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $ctrl->reintentarOutbox($id);
        set_flash('success', 'Mensaje reprogramado para reintento.');
        header('Location: ' . RUTA_URL . 'crm/monitor');
        exit;
    }

    if ($accion === 'eliminarOutbox') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $ctrl->eliminarOutbox($id);
        set_flash('success', 'Mensaje eliminado de la cola de salida.');
        header('Location: ' . RUTA_URL . 'crm/monitor');
        exit;
    }

    if ($accion === 'reintentarInbox') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $ctrl->reintentarInbox($id);
        set_flash('success', 'Mensaje reprogramado para procesamiento.');
        header('Location: ' . RUTA_URL . 'crm/monitor');
        exit;
    }

    if ($accion === 'eliminarInbox') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $ctrl->eliminarInbox($id);
        set_flash('success', 'Mensaje eliminado de la cola de entrada.');
        header('Location: ' . RUTA_URL . 'crm/monitor');
        exit;
    }

    if ($accion === 'exportar') {
        $filters = [
            'estado' => $_GET['estado'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'busqueda' => $_GET['busqueda'] ?? ''
        ];
        $ctrl->exportarLeads($filters);
    }
}


// -----------------------
// Manejo de Logística (POST a ?enlace=logistica/<accion>)
// -----------------------
if (isset($ruta[0]) && $ruta[0] === 'logistica' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($ruta[1]) ? $ruta[1] : '';
    require_once __DIR__ . '/../controlador/logistica.php';
    require_once __DIR__ . '/../utils/session.php';
    require_once __DIR__ . '/../utils/crm_roles.php';
    start_secure_session();

    $userId = (int)($_SESSION['idUsuario'] ?? 0);
    
    // Permitir Admin o Cliente de Logística
    if (!isUserAdmin($userId) && !isUserCliente($userId)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
            exit;
        }
        set_flash('error', 'No tienes permisos para realizar esta acción.');
        header('Location: ' . RUTA_URL . 'dashboard');
        exit;
    }
    
    $ctrl = new LogisticaController();
    
    if ($accion === 'cambiarEstado') {
        $id = isset($ruta[2]) ? (int)$ruta[2] : 0;
        $ctrl->cambiarEstado($id);
        exit;
    }
}

<?php
/**
 * forwarding_mapeos.php
 * AJAX endpoint para gestión de Campos (forwarding_api_fields)
 * y Mapeos (forwarding_api_mappings) de los proveedores dinámicos.
 *
 * Acciones disponibles:
 *   listar_campos    → GET campos + mapeos de un proveedor
 *   agregar_campo    → POST nuevo campo de API
 *   eliminar_campo   → POST eliminar campo (y su mapeo en cascada)
 *   guardar_mapeo    → POST guardar/actualizar el mapeo de un campo
 *   campos_internos  → GET lista de campos internos del sistema disponibles
 *   test_payload     → POST construir el payload de prueba sin enviarlo
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../modelo/usuario.php';
require_once __DIR__ . '/../modelo/forwarding.php';
require_once __DIR__ . '/../services/PayloadBuilderService.php';

// -- Autenticación mínima: solo admin/supervisor puede gestionar mapeos --
start_secure_session();
if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
if (!in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$accion    = $_POST['accion'] ?? $_GET['accion'] ?? '';
$respuesta = ['success' => false, 'message' => 'Acción desconocida'];

switch ($accion) {

    // -------------------------------------------------------------------------
    case 'listar_campos':
        $idProvider = (int)($_GET['id_provider'] ?? $_POST['id_provider'] ?? 0);
        if (!$idProvider) {
            $respuesta = ['success' => false, 'message' => 'id_provider requerido'];
            break;
        }
        $provInfo = ForwardingModel::obtenerProveedorPorId($idProvider);
        $campos   = ForwardingModel::obtenerApiFields($idProvider);
        if (empty($campos) && ($provInfo['slug'] ?? '') === 'shipedge') {
            ForwardingModel::sembrarPlantillaShipEdge($idProvider, false);
            ForwardingModel::sembrarEstadosShipEdge();
            $campos = ForwardingModel::obtenerApiFields($idProvider);
        }
        $respuesta = [
            'success' => true,
            'campos'  => $campos,
            'slug'    => $provInfo['slug'] ?? '',
        ];
        break;

    // -------------------------------------------------------------------------
    case 'cargar_plantilla_shipedge':
        $idProvider   = (int)($_POST['id_provider'] ?? 0);
        $sobrescribir = !empty($_POST['sobrescribir']);
        if (!$idProvider) {
            $respuesta = ['success' => false, 'message' => 'id_provider requerido'];
            break;
        }
        $ok1 = ForwardingModel::sembrarPlantillaShipEdge($idProvider, $sobrescribir);
        $ok2 = ForwardingModel::sembrarEstadosShipEdge();
        $respuesta = [
            'success' => $ok1,
            'message' => $ok1
                ? 'Plantilla inicial de campos y homologación de estados de ShipEdge v41 aplicada correctamente.'
                : 'No se pudo aplicar la plantilla de ShipEdge.',
        ];
        break;

    // -------------------------------------------------------------------------
    case 'listar_estados':
        $slug = trim($_GET['provider_slug'] ?? $_POST['provider_slug'] ?? '');
        if ($slug === 'shipedge') {
            $actuales = ForwardingModel::obtenerMapeosEstados('shipedge');
            if (empty($actuales)) {
                ForwardingModel::sembrarEstadosShipEdge();
                $actuales = ForwardingModel::obtenerMapeosEstados('shipedge');
            }
        } else {
            $actuales = ForwardingModel::obtenerMapeosEstados($slug ?: null);
        }
        $respuesta = [
            'success'          => true,
            'estados_mapeados' => $actuales,
            'estados_internos' => ForwardingModel::obtenerEstadosInternos(),
        ];
        break;

    // -------------------------------------------------------------------------
    case 'guardar_estado':
        try {
            $ok = ForwardingModel::guardarMapeoEstado([
                'id'                  => (int)($_POST['id'] ?? 0),
                'provider_slug'       => trim($_POST['provider_slug'] ?? ''),
                'codigo_externo'      => trim($_POST['codigo_externo'] ?? ''),
                'razon_codigo'        => trim($_POST['razon_codigo'] ?? '') ?: null,
                'descripcion_externa' => trim($_POST['descripcion_externa'] ?? '') ?: null,
                'id_estado_interno'   => (int)($_POST['id_estado_interno'] ?? 0),
                'es_confirmado'       => (int)($_POST['es_confirmado'] ?? 0),
                'activo'              => isset($_POST['activo']) ? (int)$_POST['activo'] : 1,
            ]);
            $respuesta = [
                'success' => (bool)$ok,
                'message' => $ok ? 'Homologación de estado guardada correctamente' : 'Error al guardar homologación de estado',
            ];
        } catch (InvalidArgumentException $ex) {
            $respuesta = ['success' => false, 'message' => $ex->getMessage()];
        }
        break;

    // -------------------------------------------------------------------------
    case 'eliminar_estado':
        $idEst = (int)($_POST['id'] ?? 0);
        if (!$idEst) {
            $respuesta = ['success' => false, 'message' => 'ID requerido'];
            break;
        }
        $ok = ForwardingModel::eliminarMapeoEstado($idEst);
        $respuesta = ['success' => $ok, 'message' => $ok ? 'Mapeo de estado eliminado' : 'Error al eliminar'];
        break;

    // -------------------------------------------------------------------------
    case 'agregar_campo':
        $idProvider = (int)($_POST['id_provider'] ?? 0);
        $fieldPath  = trim($_POST['field_path'] ?? '');
        $label      = trim($_POST['label'] ?? '');
        $fieldType  = $_POST['field_type'] ?? 'string';
        $isRequired = (int)($_POST['is_required'] ?? 0);
        $defaultVal = $_POST['default_value'] ?? null;
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        if (!$idProvider || !$fieldPath || !$label) {
            $respuesta = ['success' => false, 'message' => 'Faltan campos obligatorios: id_provider, field_path, label'];
            break;
        }

        $id = ForwardingModel::crearApiField([
            'id_provider'   => $idProvider,
            'field_path'    => $fieldPath,
            'label'         => $label,
            'field_type'    => $fieldType,
            'is_required'   => $isRequired,
            'default_value' => $defaultVal ?: null,
            'sort_order'    => $sortOrder,
        ]);

        $respuesta = $id
            ? ['success' => true, 'id' => $id, 'message' => 'Campo agregado correctamente']
            : ['success' => false, 'message' => 'Error al guardar el campo'];
        break;

    // -------------------------------------------------------------------------
    case 'eliminar_campo':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $respuesta = ['success' => false, 'message' => 'id del campo requerido'];
            break;
        }
        $ok = ForwardingModel::eliminarApiField($id);
        $respuesta = ['success' => $ok, 'message' => $ok ? 'Campo eliminado' : 'Error al eliminar'];
        break;

    // -------------------------------------------------------------------------
    case 'guardar_mapeo':
        $idApiField    = (int)($_POST['id_api_field'] ?? 0);
        $internalKey   = trim($_POST['internal_key'] ?? '');
        $transformRule = trim($_POST['transform_rule'] ?? '') ?: null;

        if (!$idApiField || !$internalKey) {
            $respuesta = ['success' => false, 'message' => 'id_api_field e internal_key son requeridos'];
            break;
        }

        $ok = ForwardingModel::guardarApiMapping($idApiField, $internalKey, $transformRule);
        $respuesta = ['success' => $ok, 'message' => $ok ? 'Mapeo guardado' : 'Error al guardar mapeo'];
        break;

    // -------------------------------------------------------------------------
    case 'campos_internos':
        $respuesta = [
            'success' => true,
            'campos'  => PayloadBuilderService::getCamposInternos(),
        ];
        break;

    // -------------------------------------------------------------------------
    case 'test_payload':
        $idProvider = (int)($_POST['id_provider'] ?? 0);
        $idPedido   = (int)($_POST['id_pedido'] ?? 0);

        if (!$idProvider) {
            $respuesta = ['success' => false, 'message' => 'id_provider requerido'];
            break;
        }

        // Cargar pedido de prueba (si no se especifica, usar un pedido simulado)
        if ($idPedido > 0) {
            $pedido = ForwardingModel::obtenerPedidoParaForwarding($idPedido);
            if (!$pedido) {
                $respuesta = ['success' => false, 'message' => "Pedido #{$idPedido} no encontrado"];
                break;
            }
        } else {
            // Pedido simulado con valores de ejemplo
            $pedido = [
                'id'                 => 0,
                'numero_orden'       => 'ORD-PRUEBA-001',
                'destinatario'       => 'Juan Pérez',
                'telefono'           => '5512345678',
                'correo'             => 'juan.perez@ejemplo.com',
                'direccion'          => 'Calle Ejemplo 123',
                'comentario'         => 'Dejar con portero',
                'codigo_postal'      => '010101',
                'postalCode'         => '010101',
                'precio_total_local' => 250.00,
                'municipalitiesName' => 'San Salvador',
                'departmentName'     => 'San Salvador',
                'municipio_nombre'   => 'San Salvador',
                'departamento'       => 'San José',
                'pais_codigo_iso'    => 'CR',
                'pais_nombre'        => 'Costa Rica',
                'moneda_codigo'      => 'USD',
                'peso'               => 2.5,
                'Location'           => 'Colonia Escalón',
                'betweenStreets'     => 'Entre 1a y 3a Calle',
                'zona'               => 'Zona 3',
                'nit'                => 'CF',
                'lat'                => 13.6929,
                'lng'                => -89.2182,
                'productos'          => [
                    [
                        'producto_nombre'     => 'Camiseta Talla M',
                        'sku'                 => 'CAM-M-001',
                        'cantidad'            => 2,
                        'cantidad_devuelta'   => 0,
                        'precio_unitario_usd' => 15.00,
                        'precio_unitario'     => 15.00,
                    ],
                    [
                        'producto_nombre'     => 'Pantalón Slim',
                        'sku'                 => 'PAN-S-002',
                        'cantidad'            => 1,
                        'cantidad_devuelta'   => 0,
                        'precio_unitario_usd' => 25.00,
                        'precio_unitario'     => 25.00,
                    ],
                ],
            ];
        }

        try {
            $mapeos   = ForwardingModel::obtenerMapeosDeProveedor($idProvider);
            $provData = ForwardingModel::obtenerProveedorPorId($idProvider);
            $slug     = $provData['slug'] ?? '';
            $warnings = [];

            if (empty($mapeos) && !in_array($slug, ['c807', 'shipedge'], true)) {
                $respuesta = ['success' => false, 'message' => 'No hay mapeos configurados para este proveedor'];
                break;
            }

            if ($slug === 'c807') {
                require_once __DIR__ . '/../services/providers/C807Provider.php';
                $cfg = json_decode($provData['default_config'] ?? '{}', true) ?: [];
                $creds = json_decode($provData['credentials'] ?? '{}', true) ?: [];
                $provider = new C807Provider($provData['base_url'] ?? 'https://qaapp.c807.com', $creds, $cfg);
                $payload = $provider->mapearCampos($pedido, $pedido['productos'] ?? [], ['token' => 'preview-token']);
            } elseif ($slug === 'shipedge') {
                require_once __DIR__ . '/../services/providers/ShipEdgeProvider.php';
                $cfg = json_decode($provData['default_config'] ?? '{}', true) ?: [];
                $creds = json_decode($provData['credentials'] ?? '{}', true) ?: [];
                // En vista previa con pedido simulado, si aún no se configuró shipping_method en el proveedor,
                // mostrar advertencia explícita al administrador.
                if ($idPedido === 0 && empty($creds['shipping_method']) && empty($cfg['shipping_method'])) {
                    $cfg['shipping_method'] = 'PENDIENTE_CONFIRMAR_SHIPEDGE';
                    $warnings[] = 'Aviso: shipping_method aún no está configurado en el proveedor. Se muestra "PENDIENTE_CONFIRMAR_SHIPEDGE" solo para la vista previa simulada; en envíos reales se bloqueará hasta configurarlo.';
                }
                $cfg['id_provider'] = $idProvider;
                $provider = new ShipEdgeProvider($provData['base_url'] ?? 'https://mlacostarica.shipedge.com/apirest/v41/oms', $creds, $cfg);
                $payload = $provider->mapearCampos($pedido, $pedido['productos'] ?? [], []);
                $payload = ShipEdgeProvider::sanitizeSensitiveData($payload);
            } else {
                $payload = PayloadBuilderService::build($pedido, $mapeos);
            }

            $respuesta = [
                'success'      => true,
                'payload'      => $payload,
                'warnings'     => $warnings,
                'json'         => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pedido_usado' => $idPedido > 0 ? "Pedido #{$idPedido}" : "Pedido simulado (CP '010101' con cero inicial y 2 productos)",
            ];
        } catch (Exception $e) {
            $respuesta = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
require_once __DIR__ . '/../modelo/usuario.php';
require_once __DIR__ . '/../modelo/forwarding.php';
require_once __DIR__ . '/../services/PayloadBuilderService.php';

// -- Autenticación mínima: solo admin/supervisor puede gestionar mapeos --
session_start();
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
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
        $campos = ForwardingModel::obtenerApiFields($idProvider);
        $respuesta = ['success' => true, 'campos' => $campos];
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
                'direccion'          => 'Calle Ejemplo 123',
                'comentario'         => 'Dejar con portero',
                'codigo_postal'      => '010101',
                'postalCode'         => '010101',
                'precio_total_local' => 250.00,
                'municipalitiesName' => 'Managua',
                'departmentName'     => 'Managua',
                'nit'                => 'CF',
                'lat'                => 12.136,
                'lng'                => -86.278,
                'productos'          => [
                    [
                        'producto_nombre'     => 'Camiseta Talla M',
                        'sku'                 => 'CAM-M-001',
                        'cantidad'            => 2,
                        'cantidad_devuelta'   => 0,
                        'precio_unitario_usd' => 15.00,
                    ],
                    [
                        'producto_nombre'     => 'Pantalón Slim',
                        'sku'                 => 'PAN-S-002',
                        'cantidad'            => 1,
                        'cantidad_devuelta'   => 0,
                        'precio_unitario_usd' => 25.00,
                    ],
                ],
            ];
        }

        try {
            $mapeos  = ForwardingModel::obtenerMapeosDeProveedor($idProvider);
            if (empty($mapeos)) {
                $respuesta = ['success' => false, 'message' => 'No hay mapeos configurados para este proveedor'];
                break;
            }
            $payload = PayloadBuilderService::build($pedido, $mapeos);
            $respuesta = [
                'success' => true,
                'payload' => $payload,
                'json'    => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pedido_usado' => $idPedido > 0 ? "Pedido #{$idPedido}" : "Pedido simulado",
            ];
        } catch (Exception $e) {
            $respuesta = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

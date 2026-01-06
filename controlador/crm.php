<?php

class CrmController {

    /**
     * Dashboard CRM - Métricas generales
     */
    public function dashboard() {
        require_once "modelo/crm_lead.php";
        require_once "modelo/crm_inbox.php";
        require_once "modelo/crm_outbox.php";
        require_once "modelo/usuario.php";
        
        // Filtros
        $proveedorId = isset($_GET['proveedor_id']) && is_numeric($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : null;
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;
        
        $filters = array_filter([
            'proveedor_id' => $proveedorId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta
        ]);
        
        // Total de leads por estado
        $leadsPorEstado = CrmLead::contarPorEstado($filters);
        
        // Últimos 10 leads (filtrados)
        $ultimosLeads = CrmLead::obtenerRecientes(10, $filters);
        
        // Estado de las colas (no filtramos colas por ahora, is global queue)
        $inboxPendientes = CrmInbox::contarPorEstado('pending');
        $inboxProcesados = CrmInbox::contarPorEstado('processed');
        $inboxFallidos = CrmInbox::contarPorEstado('failed');
        
        $outboxPendientes = CrmOutbox::contarPorEstado('pending');
        $outboxEnviados = CrmOutbox::contarPorEstado('sent');
        $outboxFallidos = CrmOutbox::contarPorEstado('failed');
        
        // Tendencia de leads (últimos 30 días o rango seleccionado)
        $tendencia = CrmLead::obtenerTendencia(30, $filters);
        
        // Obtener lista de proveedores para el filtro
        $usuarioModel = new UsuarioModel();
        $proveedores = $usuarioModel->obtenerUsuariosPorRolNombre('Proveedor');
        
        return [
            'leadsPorEstado' => $leadsPorEstado,
            'ultimosLeads' => $ultimosLeads,
            'inbox' => [
                'pendientes' => $inboxPendientes,
                'procesados' => $inboxProcesados,
                'fallidos' => $inboxFallidos
            ],
            'outbox' => [
                'pendientes' => $outboxPendientes,
                'enviados' => $outboxEnviados,
                'fallidos' => $outboxFallidos
            ],
            'tendencia' => $tendencia,
            'proveedores' => $proveedores,
            'filters' => $filters
        ];
    }

    /**
     * Listar leads con paginación y filtros
     */
    public function listar() {
        require_once "modelo/crm_lead.php";
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $estado = $_GET['estado'] ?? null;
        $proveedorId = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : null;
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;
        $busqueda = $_GET['busqueda'] ?? null;
        
        $filtros = compact('estado', 'proveedorId', 'clienteId', 'fechaDesde', 'fechaHasta', 'busqueda');
        
        $resultado = CrmLead::listarConFiltros($filtros, $page, $limit);
        
        return $resultado;
    }

    /**
     * Ver detalle de un lead
     */
    public function ver($id) {
        require_once "modelo/crm_lead.php";
        require_once "modelo/crm_outbox.php";
        require_once __DIR__ . '/../utils/crm_roles.php';
        
        $lead = CrmLead::obtenerPorId($id);
        if (!$lead) {
            return null;
        }
        
        // Validación de Seguridad (Ownership)
        $userId = $_SESSION['idUsuario'] ?? 0;
        
        // Si no es admin, verificar propiedad
        if (!isUserAdmin($userId)) {
            $esCliente = isUserCliente($userId);
            $esProveedor = isUserProveedor($userId); // Asumiendo que existe esta función en crm_roles
            
            if ($esCliente && (int)$lead['cliente_id'] !== $userId) {
                return null; // Acceso denegado, lead de otro cliente
            }
            
            if ($esProveedor && (int)$lead['proveedor_id'] !== $userId) {
                return null; // Acceso denegado, lead de otro proveedor
            }
            
            // Si no es ni cliente ni proveedor ni admin (y entró aquí), bloquear
            if (!$esCliente && !$esProveedor) {
                return null;
            }
        }
        
        // Timeline de cambios de estado
        $timeline = CrmLead::obtenerTimeline($id);
        
        // Webhooks relacionados
        $webhooks = CrmOutbox::obtenerPorLeadId($id);
        
        return [
            'lead' => $lead,
            'timeline' => $timeline,
            'webhooks' => $webhooks
        ];
    }

    /**
     * Cambiar estado de un lead
     */
    public function cambiarEstado($id, $nuevoEstado, $observaciones = null) {
        require_once "modelo/crm_lead.php";
        require_once "modelo/crm_notification.php";
        require_once __DIR__ . '/../utils/crm_roles.php';
        
        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
        
        // Obtener lead ANTES de cambiar estado
        $lead = CrmLead::obtenerPorId($id);
        if (!$lead) return ['success' => false, 'message' => 'Lead no encontrado'];
        
        $estadoAnterior = $lead['estado_actual'];
        
        // Validar Ownership
        if (!isUserAdmin($userId)) {
            // Proveedores NO pueden cambiar estado
            if (isUserProveedor($userId)) {
                return ['success' => false, 'message' => 'Acceso denegado: los proveedores no pueden cambiar estados.'];
            }

            // Cliente solo puede cambiar su lead
            if (isUserCliente($userId) && (int)$lead['cliente_id'] !== $userId) {
                return ['success' => false, 'message' => 'Acceso denegado: este lead no te pertenece'];
            }
        }
        
        // Cambiar estado
        $resultado = CrmLead::cambiarEstado($id, $nuevoEstado, $observaciones, $userId);
        
        // Si el cambio fue exitoso Y lo hizo un cliente, crear notificación para el proveedor
        if ($resultado['success'] && isUserCliente($userId) && !empty($lead['proveedor_id'])) {
            CrmNotificationModel::agregar(
                'SEND_TO_PROVIDER',
                $id,
                (int)$lead['proveedor_id'],
                [
                    'lead_id' => $id,
                    'proveedor_lead_id' => $lead['proveedor_lead_id'],
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $nuevoEstado,
                    'observaciones' => $observaciones,
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }
        
        return $resultado;
    }

    /**
     * Integraciones - Listar webhooks configurados
     */
    public function integraciones() {
        require_once "modelo/crm_integration.php";
        
        $integraciones = CrmIntegration::listarTodas();
        
        return [
            'integraciones' => $integraciones
        ];
    }

    /**
     * Monitor del worker
     */
    public function monitor() {
        require_once "modelo/crm_inbox.php";
        require_once "modelo/crm_outbox.php";
        
        // Estado de colas
        $inboxPendientes = CrmInbox::obtenerPendientes(20);
        $inboxFallidos = CrmInbox::obtenerFallidos(20);
        $outboxPendientes = CrmOutbox::obtenerPendientes(20);
        $outboxFallidos = CrmOutbox::obtenerFallidos(20);
        
        // Estadísticas
        $stats = [
            'inbox_pending' => CrmInbox::contarPorEstado('pending'),
            'inbox_processed' => CrmInbox::contarPorEstado('processed'),
            'inbox_failed' => CrmInbox::contarPorEstado('failed'),
            'outbox_pending' => CrmOutbox::contarPorEstado('pending'),
            'outbox_sent' => CrmOutbox::contarPorEstado('sent'),
            'outbox_failed' => CrmOutbox::contarPorEstado('failed')
        ];
        
        return [
            'inbox' => [
                'pendientes' => $inboxPendientes,
                'fallidos' => $inboxFallidos
            ],
            'outbox' => [
                'pendientes' => $outboxPendientes,
                'fallidos' => $outboxFallidos
            ],
            'stats' => $stats
        ];
    }

    /**
     * Reportes y estadísticas
     */
    public function reportes() {
        require_once "modelo/crm_lead.php";
        require_once "modelo/crm_outbox.php";
        
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-t');
        
        // Leads por proveedor
        $leadsPorProveedor = CrmLead::contarPorProveedor($fechaDesde, $fechaHasta);
        
        // Leads por cliente
        $leadsPorCliente = CrmLead::contarPorCliente($fechaDesde, $fechaHasta);
        
        // Conversión por estado
        $conversionPorEstado = CrmLead::obtenerConversionPorEstado($fechaDesde, $fechaHasta);
        
        // Tasa de éxito de webhooks
        $tasaExitoWebhooks = CrmOutbox::obtenerTasaExito($fechaDesde, $fechaHasta);
        
        return [
            'leadsPorProveedor' => $leadsPorProveedor,
            'leadsPorCliente' => $leadsPorCliente,
            'conversionPorEstado' => $conversionPorEstado,
            'tasaExitoWebhooks' => $tasaExitoWebhooks,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta
        ];
    }
    /**
     * Guardar integración (crear/actualizar)
     */
    public function guardarIntegracion($data) {
        require_once "modelo/crm_integration.php";
        
        $userId = (int)$data['user_id'];
        $kind = $data['kind'];
        $webhookUrl = trim($data['webhook_url']);
        $secret = trim($data['secret']);
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        if (empty($userId) || empty($kind)) {
            return ['success' => false, 'message' => 'Usuario y tipo son requeridos'];
        }
        
        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'URL de webhook inválida'];
        }
        
        return CrmIntegration::guardar($userId, $kind, $webhookUrl, $secret, $isActive);
    }
    
    /**
     * Eliminar integración
     */
    public function eliminarIntegracion($id) {
        require_once "modelo/crm_integration.php";
        return CrmIntegration::eliminar($id);
    }
    
    /**
     * Obtener integración por ID
     */
    public function obtenerIntegracion($id) {
         require_once "modelo/crm_integration.php";
         return CrmIntegration::obtenerPorId($id);
    }

    /**
     * Obtener lista de usuarios (para el select de crear integración)
     */
    public function obtenerUsuarios() {
        require_once "modelo/usuario.php";
        // Asumiendo que existe UsuarioModel::listar() o similar
        // Si no, hacemos una query directa rapida o usamos lo que haya
        $db = (new Conexion())->conectar();
        $stmt = $db->query("SELECT id, nombre, email FROM usuarios ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Guardar lead (crear/actualizar) manualmente
     */
    public function guardarLead($data) {
        require_once "modelo/crm_lead.php";
        require_once __DIR__ . '/../utils/crm_roles.php';
        
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $userId = (int)($_SESSION['idUsuario'] ?? 0);
        
        // Validar Edición
        if ($id > 0 && !isUserAdmin($userId)) {
             $lead = CrmLead::obtenerPorId($id);
             
             // Proveedores NO pueden editar
             if (isUserProveedor($userId)) {
                 return ['success' => false, 'message' => 'Acceso denegado: los proveedores no pueden editar leads.'];
             }

             if (isUserCliente($userId) && (int)$lead['cliente_id'] !== $userId) {
                  return ['success' => false, 'message' => 'No puedes editar un lead que no es tuyo'];
             }
        }
        
        // Datos básicos
        $leadData = [
            'nombre'   => trim($data['nombre'] ?? ''),
            'telefono' => trim($data['telefono'] ?? ''),
            'producto' => trim($data['producto'] ?? ''),
            'precio'   => floatval($data['precio'] ?? 0),
            'cliente_id' => !empty($data['cliente_id']) ? (int)$data['cliente_id'] : null,
            'proveedor_id' => !empty($data['proveedor_id']) ? (int)$data['proveedor_id'] : null
        ];
        
        if ($id > 0) {
            // Actualizar
            return CrmLead::actualizarLead($id, $leadData);
        } else {
            // Crear nuevo lead manual
            
            // Validar proveedor
            if (empty($leadData['proveedor_id'])) {
                return ['success' => false, 'message' => 'El proveedor es obligatorio'];
            }
            
            // Generar ID externo si no existe
            $leadData['proveedor_lead_id'] = 'MANUAL-' . time() . '-' . rand(100,999);
            $leadData['fecha_hora'] = date('Y-m-d H:i:s');
            
            // Reutilizar el método del modelo
            // Nota: CrmLead::crearLead requiere ($data, $proveedorId)
            return CrmLead::crearLead($leadData, $leadData['proveedor_id']);
        }
    }
    /**
     * Reintentar mensaje outbox
     */
    public function reintentarOutbox($id) {
        require_once "modelo/crm_outbox.php";
        return ['success' => CrmOutbox::resetear($id)];
    }

    /**
     * Eliminar mensaje outbox
     */
    public function eliminarOutbox($id) {
        require_once "modelo/crm_outbox.php";
        return ['success' => CrmOutbox::eliminar($id)];
    }

    /**
     * Reintentar mensaje inbox
     */
    public function reintentarInbox($id) {
        require_once "modelo/crm_inbox.php";
        return ['success' => CrmInbox::resetear($id)];
    }

    /**
     * Eliminar mensaje inbox
     */
    public function eliminarInbox($id) {
        require_once "modelo/crm_inbox.php";
        return ['success' => CrmInbox::eliminar($id)];
    }
    
    /**
     * Notificaciones - Bandeja de entrada del usuario
     */
    public function notificaciones() {
        require_once "modelo/crm_notification.php";
        
        $userId = $_SESSION['idUsuario'] ?? 0;
        if ($userId <= 0) {
            return ['notificaciones' => [], 'unread_count' => 0, 'pagination' => []];
        }
        
        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
        // Aumentamos a 500 para permitir que DataTables gestione un buen historial en cliente
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 500) : 500; 
        $offset = ($page - 1) * $limit;
        $onlyUnread = isset($_GET['unread']) && $_GET['unread'] === 'true';
        $search = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        // Fechas por defecto: Últimos 6 meses
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

        // FILTROS DASHBOARD PROVEEDOR
        $dashboardFilters = [
            'start_date' => $startDate, 
            'end_date' => $endDate,
            'client_id' => $clientId
        ];
        
        // Obtener datos
        // NOTA: Para el listado de notificaciones (historial), podríamos querer filtrar también por cliente si es lead update,
        // pero por ahora el requerimiento principal es el dashboard. 
        // Si se requiere filtrar el historial de notificaciones por cliente, se necesitaría update en CrmNotificationModel.
        
        $notificaciones = CrmNotificationModel::obtenerPorUsuario($userId, $onlyUnread, $limit, $offset, $search, $startDate, $endDate);
        $totalNotificaciones = CrmNotificationModel::contarTotalPorUsuario($userId, $onlyUnread, $search, $startDate, $endDate);
        $unreadCount = CrmNotificationModel::contarNoLeidas($userId);
        
        // Obtener leads pendientes "reales" (No paginados, son tareas)
        $leadsPendientes = CrmNotificationModel::obtenerLeadsPendientes($userId);
        
        // Obtener clientes asociados (para el dropdown de filtros del proveedor)
        $clientesAsociados = [];
        if (isUserProveedor($userId)) {
             require_once "modelo/crm_lead.php";
             $clientesAsociados = CrmLead::obtenerClientesAsociados($userId);
             
             // Si hay filtro de cliente, verificar que pertenezca al proveedor
        }

        // Función helper local para decodificar
        $decoder = function(&$item) {
            if (isset($item['payload']) && is_string($item['payload'])) {
                $item['payload'] = json_decode($item['payload'], true);
            }
        };
        
        // Decodificar ambos arrays
        array_walk($notificaciones, $decoder);
        array_walk($leadsPendientes, $decoder);
        
        // Calcular info de paginación
        $totalPages = ceil($totalNotificaciones / $limit);
        
        return [
            'notificaciones' => $notificaciones, // Historial Paginado + Busqueda
            'leads_pendientes' => $leadsPendientes, // Lista de tareas urgentes
            'unread_count' => $unreadCount,
            'search_query' => $search,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'client_id' => $clientId,
            'dashboard_filters' => $dashboardFilters, // Para pasar al modelo de métricas en la vista si se llama directo o aquí
            'clientes_asociados' => $clientesAsociados,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalNotificaciones,
                'limit' => $limit
            ]
        ];
    }
    
    /**
     * Exportar leads a CSV
     */
    public function exportarLeads($filters) {
        require_once "modelo/crm_lead.php";
        
        // Obtener leads (límite alto para exportación)
        $result = CrmLead::listar($filters, 1, 10000); 
        $leads = $result['leads'];
        
        // Limpiar buffer
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_crm_'.date('Y-m-d_H-i').'.csv"');
        
        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        fputcsv($output, ['ID', 'Proveedor ID', 'Lead ID (Prov)', 'Nombre', 'Telefono', 'Producto', 'Precio', 'Estado', 'Fecha', 'Creado']);
        
        foreach ($leads as $lead) {
            fputcsv($output, [
                $lead['id'],
                $lead['proveedor_id'],
                $lead['proveedor_lead_id'],
                $lead['nombre'],
                $lead['telefono'],
                $lead['producto'],
                $lead['precio'],
                $lead['estado_actual'],
                $lead['fecha_hora'],
                $lead['created_at']
            ]);
        }
        fclose($output);
        exit;
    }
}

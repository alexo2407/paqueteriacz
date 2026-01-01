<?php

class CrmController {

    /**
     * Dashboard CRM - Métricas generales
     */
    public function dashboard() {
        require_once "modelo/crm_lead.php";
        require_once "modelo/crm_inbox.php";
        require_once "modelo/crm_outbox.php";
        
        // Total de leads por estado
        $leadsPorEstado = CrmLead::contarPorEstado();
        
        // Últimos 10 leads
        $ultimosLeads = CrmLead::obtenerRecientes(10);
        
        // Estado de las colas
        $inboxPendientes = CrmInbox::contarPorEstado('pending');
        $inboxProcesados = CrmInbox::contarPorEstado('processed');
        $inboxFallidos = CrmInbox::contarPorEstado('failed');
        
        $outboxPendientes = CrmOutbox::contarPorEstado('pending');
        $outboxEnviados = CrmOutbox::contarPorEstado('sent');
        $outboxFallidos = CrmOutbox::contarPorEstado('failed');
        
        // Tendencia de leads (últimos 30 días)
        $tendencia = CrmLead::obtenerTendencia(30);
        
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
            'tendencia' => $tendencia
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
        
        $lead = CrmLead::obtenerPorId($id);
        if (!$lead) {
            return null;
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
        
        return CrmLead::cambiarEstado($id, $nuevoEstado, $observaciones, $_SESSION['user_id']);
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
        
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        
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

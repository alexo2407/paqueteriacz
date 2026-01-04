<?php
/**
 * POST /api/crm/leads/bulk-status
 * 
 * Endpoint para cambio masivo de estado de múltiples leads.
 * Los clientes solo pueden actualizar leads que les pertenecen (cliente_id).
 * Admins pueden actualizar cualquier lead.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../../utils/crm_roles.php';
    require_once __DIR__ . '/../../utils/crm_status.php';
    require_once __DIR__ . '/../../modelo/crm_lead.php';
    
    // Validar JWT
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }
    
    $userData = $validacion['data'];
    $userId = (int)$userData['id'];
    
    // Verificar que es Cliente o Admin
    $isAdmin = isUserAdmin($userId);
    $isCliente = isUserCliente($userId);
    
    if (!$isCliente && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: solo clientes o administradores']);
        exit;
    }
    
    // Leer payload
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validar campos requeridos
    if (!$data || !isset($data['lead_ids']) || !isset($data['estado'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Campos requeridos: lead_ids (array), estado (string)'
        ]);
        exit;
    }
    
    $leadIds = $data['lead_ids'];
    $estadoNuevo = normalizeEstado($data['estado']);
    $observaciones = $data['observaciones'] ?? null;
    
    // Validar que lead_ids sea un array
    if (!is_array($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'lead_ids debe ser un array']);
        exit;
    }
    
    // Validar que no esté vacío
    if (empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'lead_ids no puede estar vacío']);
        exit;
    }
    
    // Validar límite máximo (100 leads)
    if (count($leadIds) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Límite máximo de 100 leads por request',
            'received' => count($leadIds)
        ]);
        exit;
    }
    
    // Validar que todos los IDs sean enteros
    $leadIds = array_map('intval', $leadIds);
    $leadIds = array_filter($leadIds, function($id) { return $id > 0; });
    
    if (empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron lead_ids válidos']);
        exit;
    }
    
    // Validar estado
    if (!isValidEstado($estadoNuevo)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Estado inválido: {$data['estado']}",
            'estados_validos' => getEstadosValidos()
        ]);
        exit;
    }
    
    // Procesar leads en batch (optimizado)
    $results = [];
    $successCount = 0;
    $failedCount = 0;
    
    // Agregar nota de que fue actualización masiva
    $observacionesFinal = $observaciones 
        ? "Actualización masiva: $observaciones" 
        : "Actualización masiva de estado";
    
    // Pre-calcular timestamp una sola vez
    $currentTimestamp = date('Y-m-d H:i:s');

    
    try {
        require_once __DIR__ . '/../../modelo/conexion.php';
        $db = (new Conexion())->conectar();
        
        // PASO 1: Obtener todos los leads en una sola query (BATCH SELECT)
        // No necesita estar en transacción - es solo lectura
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = $db->prepare("SELECT * FROM crm_leads WHERE id IN ($placeholders)");
        $stmt->execute($leadIds);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexar por ID para acceso rápido
        $leadsById = [];
        foreach ($leads as $lead) {
            $leadsById[(int)$lead['id']] = $lead;
        }
        
        // PASO 2: Validar ownership y preparar datos para batch update
        $validLeadIds = [];
        $historyData = [];
        $outboxData = [];
        
        foreach ($leadIds as $leadId) {
            if (!isset($leadsById[$leadId])) {
                $results[] = [
                    'lead_id' => $leadId,
                    'success' => false,
                    'message' => 'Lead no encontrado'
                ];
                $failedCount++;
                continue;
            }
            
            $lead = $leadsById[$leadId];
            
            // Verificar ownership (solo si no es admin)
            if (!$isAdmin && (int)$lead['cliente_id'] !== $userId) {
                $results[] = [
                    'lead_id' => $leadId,
                    'success' => false,
                    'message' => 'No tienes permiso para este lead'
                ];
                $failedCount++;
                continue;
            }
            
            $estadoAnterior = $lead['estado_actual'];
            
            // Lead válido para actualizar
            $validLeadIds[] = $leadId;
            
            // Preparar datos para historial
            $historyData[] = [
                'lead_id' => $leadId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'actor_user_id' => $userId,
                'observaciones' => $observacionesFinal
            ];
            
            // Preparar datos para outbox
            $outboxData[] = [
                'lead_id' => $leadId,
                'proveedor_id' => (int)$lead['proveedor_id'],
                'proveedor_lead_id' => $lead['proveedor_lead_id'],
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo
            ];
            
            $results[] = [
                'lead_id' => $leadId,
                'success' => true,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo
            ];
            $successCount++;
        }
        
        // PASO 3: Batch UPDATE - Actualizar todos los leads válidos en una sola query
        if (!empty($validLeadIds)) {
            // Iniciar transacción solo para writes (optimización)
            $db->beginTransaction();
            
            $placeholders = implode(',', array_fill(0, count($validLeadIds), '?'));
            $updateStmt = $db->prepare("
                UPDATE crm_leads 
                SET estado_actual = ?, updated_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $updateParams = array_merge([$estadoNuevo], $validLeadIds);
            $updateStmt->execute($updateParams);
            
            // PASO 4: Batch INSERT - Historial (todos los registros en una query)
            if (!empty($historyData)) {
                $historyValues = [];
                $historyParams = [];
                foreach ($historyData as $h) {
                    $historyValues[] = "(?, ?, ?, ?, ?, NOW())";
                    $historyParams[] = $h['lead_id'];
                    $historyParams[] = $h['estado_anterior'];
                    $historyParams[] = $h['estado_nuevo'];
                    $historyParams[] = $h['actor_user_id'];
                    $historyParams[] = $h['observaciones'];
                }
                
                $historySQL = "
                    INSERT INTO crm_lead_status_history 
                    (lead_id, estado_anterior, estado_nuevo, actor_user_id, observaciones, created_at)
                    VALUES " . implode(', ', $historyValues);
                
                $historyStmt = $db->prepare($historySQL);
                $historyStmt->execute($historyParams);
            }
            
            // PASO 5: Batch INSERT - Notificaciones internas (todas en una query)
            if (!empty($outboxData)) {
                $notifValues = [];
                $notifParams = [];
                
                // Pre-crear template de payload base (optimización)
                $basePayload = [
                    'observaciones' => $observacionesFinal,
                    'updated_by' => $userId,
                    'updated_at' => $currentTimestamp,
                    'bulk_update' => true
                ];
                
                foreach ($outboxData as $o) {
                    $notifValues[] = "(?, ?, ?, ?, ?, 0, NOW())";
                    $notifParams[] = $o['proveedor_id']; // user_id (destinatario)
                    $notifParams[] = 'status_updated'; // type
                    $notifParams[] = 'SEND_TO_PROVIDER'; // event_type
                    $notifParams[] = $o['lead_id']; // related_lead_id
                    
                    // Merge con datos específicos del lead
                    $payloadFinal = array_merge($basePayload, [
                        'lead_id' => $o['lead_id'],
                        'proveedor_lead_id' => $o['proveedor_lead_id'],
                        'estado_anterior' => $o['estado_anterior'],
                        'estado_nuevo' => $o['estado_nuevo']
                    ]);
                    
                    $notifParams[] = json_encode($payloadFinal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                
                $notifSQL = "
                    INSERT INTO crm_notifications 
                    (user_id, type, event_type, related_lead_id, payload, is_read, created_at)
                    VALUES " . implode(', ', $notifValues);
                
                $notifStmt = $db->prepare($notifSQL);
                $notifStmt->execute($notifParams);
            }
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error en bulk status update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al procesar actualización masiva',
            'error' => $e->getMessage()
        ]);
        exit;
    }
    
    // Determinar código de respuesta
    $httpCode = 200;
    if ($successCount === 0) {
        $httpCode = 400; // Todos fallaron
    } elseif ($failedCount > 0) {
        $httpCode = 207; // Multi-Status (algunos fallaron)
    }
    
    // Filtrar solo los que fallaron para response optimizada
    $failedDetails = array_filter($results, fn($r) => !$r['success']);
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => $successCount > 0,
        'message' => "$successCount de " . count($leadIds) . " leads actualizados exitosamente",
        'updated' => $successCount,
        'failed' => $failedCount,
        'total' => count($leadIds),
        'estado_nuevo' => $estadoNuevo,
        // Solo enviar detalles de los que fallaron (optimización de respuesta)
        'failed_leads' => array_values($failedDetails)
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
    error_log("Error en bulk status update: " . $e->getMessage());
}

<?php
/**
 * CRM Bulk Jobs Worker
 * 
 * Procesa jobs de actualización masiva de leads en background.
 * Ejecutar con: php cli/crm_bulk_worker.php
 */

require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/crm_lead.php';
require_once __DIR__ . '/../modelo/crm_notification.php';
require_once __DIR__ . '/../utils/crm_status.php';
require_once __DIR__ . '/../utils/crm_roles.php';

echo "[" . date('Y-m-d H:i:s') . "] CRM Bulk Jobs Worker iniciado\n";

// Loop infinito para procesar jobs
while (true) {
    // Update heartbeat
    $heartbeatFile = __DIR__ . '/../logs/crm_bulk_worker.heartbeat';
    if (!file_exists(dirname($heartbeatFile))) {
        mkdir(dirname($heartbeatFile), 0755, true);
    }
    touch($heartbeatFile);

    try {
        processPendingJobs();
        sleep(2); // Esperar 2 segundos antes de verificar nuevos jobs
    } catch (Exception $e) {
        error_log("Error en bulk worker: " . $e->getMessage());
        sleep(5); // Esperar más tiempo si hay error
    }
}

/**
 * Procesa jobs pendientes en la cola
 */
function processPendingJobs() {
    $db = (new Conexion())->conectar();
    
    // Buscar el próximo job en cola o procesando (por si el worker se cayó)
    $stmt = $db->prepare("
        SELECT * FROM crm_bulk_jobs 
        WHERE status IN ('queued', 'processing')
        ORDER BY created_at ASC
        LIMIT 1
    ");
    
    $stmt->execute();
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        return; // No hay jobs pendientes
    }
    
    // Marcar como procesando
    if ($job['status'] === 'queued') {
        $stmt = $db->prepare("
            UPDATE crm_bulk_jobs 
            SET status = 'processing', started_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $job['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] Procesando job {$job['id']} ({$job['total_leads']} leads)\n";
    }
    
    // Procesar el job
    processJob($job);
}

/**
 * Procesa un job específico
 */
function processJob($job) {
    $db = (new Conexion())->conectar();
    
    $leadIds = json_decode($job['lead_ids'], true);
    $estadoNuevo = $job['estado'];
    $userId = (int)$job['user_id'];
    $observaciones = $job['observaciones'];
    
    $successCount = 0;
    $failedCount = 0;
    $failedDetails = [];
    $successfulDetails = []; // Nuevo: trackear exitosos también
    
    $observacionesFinal = $observaciones 
        ? "Actualización masiva: $observaciones" 
        : "Actualización masiva de estado";
    
    $currentTimestamp = date('Y-m-d H:i:s');
    
    // Verificar si es admin (para ownership validation)
    $isAdmin = isUserAdmin($userId);
    
    try {
        // PASO 1: Obtener todos los leads (batch)
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = $db->prepare("SELECT * FROM crm_leads WHERE id IN ($placeholders)");
        $stmt->execute($leadIds);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $leadsById = [];
        foreach ($leads as $lead) {
            $leadsById[(int)$lead['id']] = $lead;
        }
        
        // PASO 2: Validar y preparar datos
        $validLeadIds = [];
        $historyData = [];
        $outboxData = [];
        
        foreach ($leadIds as $leadId) {
            if (!isset($leadsById[$leadId])) {
                $failedDetails[] = ['lead_id' => $leadId, 'error' => 'Lead no encontrado'];
                $failedCount++;
                continue;
            }
            
            $lead = $leadsById[$leadId];
            
            // Verificar ownership
            if (!$isAdmin && (int)$lead['cliente_id'] !== $userId) {
                $failedDetails[] = ['lead_id' => $leadId, 'error' => 'Sin permiso'];
                $failedCount++;
                continue;
            }
            
            $estadoAnterior = $lead['estado_actual'];
            $validLeadIds[] = $leadId;
            
            // Guardar detalles del lead exitoso
            $successfulDetails[] = [
                'lead_id' => $leadId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo
            ];
            
            $historyData[] = [
                'lead_id' => $leadId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'actor_user_id' => $userId,
                'observaciones' => $observacionesFinal
            ];
            
            $outboxData[] = [
                'lead_id' => $leadId,
                'proveedor_id' => (int)$lead['proveedor_id'],
                'proveedor_lead_id' => $lead['proveedor_lead_id'],
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo
            ];
            
            $successCount++;
        }
        
        // PASO 3: Batch UPDATE
        if (!empty($validLeadIds)) {
            $db->beginTransaction();
            
            $placeholders = implode(',', array_fill(0, count($validLeadIds), '?'));
            $updateStmt = $db->prepare("
                UPDATE crm_leads 
                SET estado_actual = ?, updated_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $updateParams = array_merge([$estadoNuevo], $validLeadIds);
            $updateStmt->execute($updateParams);
            
            // PASO 4: Batch INSERT - Historial
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
            
            // PASO 5: Batch INSERT - Notificaciones Internas
            if (!empty($outboxData)) {
                $notifValues = [];
                $notifParams = [];
                
                $basePayload = [
                    'observaciones' => $observacionesFinal,
                    'updated_by' => $userId,
                    'updated_at' => $currentTimestamp,
                    'bulk_update' => true
                ];
                
                foreach ($outboxData as $o) {
                    $notifValues[] = "(?, ?, ?, ?, ?, 0, NOW())";
                    $notifParams[] = $o['proveedor_id']; // user_id
                    $notifParams[] = 'status_updated'; // type
                    $notifParams[] = 'SEND_TO_PROVIDER'; // event_type
                    $notifParams[] = $o['lead_id']; // related_lead_id
                    
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
            
            $db->commit();
        }
        
        // Determinar qué details guardar (el array más pequeño)
        $detailsToStore = [];
        $detailsType = '';
        
        if ($successCount < $failedCount) {
            // Guardar successful_details si hay menos exitosos
            $detailsToStore = $successfulDetails;
            $detailsType = 'successful';
        } else {
            // Guardar failed_details si hay menos fallidos (o igual)
            $detailsToStore = $failedDetails;
            $detailsType = 'failed';
        }
        
        // Actualizar job a completado
        $stmt = $db->prepare("
            UPDATE crm_bulk_jobs 
            SET status = 'completed',
                processed_leads = :processed,
                successful_leads = :successful,
                failed_leads = :failed,
                failed_details = :details,
                completed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':processed' => count($leadIds),
            ':successful' => $successCount,
            ':failed' => $failedCount,
            ':details' => json_encode([
                'type' => $detailsType,
                'items' => $detailsToStore
            ]),
            ':id' => $job['id']
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Job {$job['id']} completado: {$successCount} exitosos, {$failedCount} fallidos (guardados: $detailsType)\n";
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        // Marcar job como fallido
        $stmt = $db->prepare("
            UPDATE crm_bulk_jobs 
            SET status = 'failed',
                error_message = :error,
                completed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':error' => $e->getMessage(),
            ':id' => $job['id']
        ]);
        
        error_log("Error procesando job {$job['id']}: " . $e->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] Job {$job['id']} FALLÓ: {$e->getMessage()}\n";
    }
}

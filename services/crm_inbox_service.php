<?php
/**
 * CRM Inbox Service
 * 
 * Servicio para procesar mensajes de la cola de entrada.
 * Procesa leads y los crea en crm_leads.
 */

require_once __DIR__ . '/../modelo/crm_inbox.php';
require_once __DIR__ . '/../modelo/crm_lead.php';
require_once __DIR__ . '/../modelo/crm_notification.php';
require_once __DIR__ . '/../utils/crm_status.php';

class CrmInboxService {
    
    /**
     * Procesa mensajes pendientes del inbox.
     * 
     * @param int $limit Límite de mensajes a procesar
     * @return array Estadísticas de procesamiento
     */
    public static function procesar($limit = 10) {
        $stats = [
            'processed' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            $mensajes = CrmInboxModel::obtenerPendientes($limit);
            
            foreach ($mensajes as $mensaje) {
                try {
                    $payload = json_decode($mensaje['payload'], true);
                    
                    if (!$payload) {
                        throw new Exception("Payload inválido: no es JSON válido");
                    }
                    
                    // Procesar según source
                    if ($mensaje['source'] === 'proveedor') {
                        $resultado = self::procesarLeadDeProveedor($mensaje, $payload);
                    } elseif ($mensaje['source'] === 'cliente') {
                        $resultado = self::procesarActualizacionDeCliente($mensaje, $payload);
                    } else {
                        throw new Exception("Source desconocido: {$mensaje['source']}");
                    }
                    
                    if ($resultado['success']) {
                        CrmInboxModel::marcarProcesado($mensaje['id']);
                        $stats['processed']++;
                    } else {
                        CrmInboxModel::marcarFallido($mensaje['id'], $resultado['message']);
                        $stats['failed']++;
                        $stats['errors'][] = $resultado['message'];
                    }
                    
                } catch (Exception $e) {
                    $error = "Error procesando inbox {$mensaje['id']}: " . $e->getMessage();
                    error_log($error);
                    CrmInboxModel::marcarFallido($mensaje['id'], $error);
                    $stats['failed']++;
                    $stats['errors'][] = $error;
                }
            }
            
        } catch (Exception $e) {
            error_log("Error en CrmInboxService::procesar: " . $e->getMessage());
            $stats['errors'][] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Procesa un lead recibido de un proveedor.
     * 
     * @param array $mensaje Mensaje del inbox
     * @param array $payload Payload decodificado
     * @return array Resultado del procesamiento
     */
    private static function procesarLeadDeProveedor($mensaje, $payload) {
        try {
            // Extraer proveedor_id del payload
            $proveedorId = isset($payload['proveedor_id']) ? $payload['proveedor_id'] : null;
            if (!$proveedorId) {
                return ['success' => false, 'message' => 'proveedor_id faltante en payload'];
            }
            
            // Determinar si es batch o individual
            if (isset($payload['leads']) && is_array($payload['leads'])) {
                // Batch
                return self::procesarBatch($proveedorId, $payload['leads']);
            } elseif (isset($payload['lead'])) {
                // Individual
                return self::procesarLeadIndividual($proveedorId, $payload['lead']);
            } else {
                return ['success' => false, 'message' => 'Formato de payload desconocido'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Procesa un lead individual.
     * 
     * @param int $proveedorId ID del proveedor
     * @param array $leadData Datos del lead
     * @return array Resultado
     */
    private static function procesarLeadIndividual($proveedorId, $leadData) {
        // Crear lead
        $resultado = CrmLeadModel::crearLead($leadData, $proveedorId);
        
        if (!$resultado['success']) {
            return $resultado;
        }
        
        // Crear notificación interna para el cliente (si está asignado)
        $clienteId = isset($leadData['cliente_id']) ? $leadData['cliente_id'] : null;
        if ($clienteId) {
            // Notificar al cliente sobre el nuevo lead
            CrmNotificationModel::agregar(
                'SEND_TO_CLIENT',
                $resultado['lead_id'],
                $clienteId,
                [
                    'lead_id' => $resultado['lead_id'],
                    'proveedor_lead_id' => $leadData['proveedor_lead_id'],
                    'nombre' => isset($leadData['nombre']) ? $leadData['nombre'] : null,
                    'telefono' => isset($leadData['telefono']) ? $leadData['telefono'] : null,
                    'producto' => isset($leadData['producto']) ? $leadData['producto'] : null,
                    'precio' => isset($leadData['precio']) ? $leadData['precio'] : null,
                    'fecha_hora' => $leadData['fecha_hora']
                ]
            );
        }
        
        return $resultado;
    }
    
    /**
     * Procesa un lote de leads.
     * 
     * @param int $proveedorId ID del proveedor
     * @param array $leads Array de leads
     * @return array Resultado
     */
    private static function procesarBatch($proveedorId, $leads) {
        $created = 0;
        $duplicated = 0;
        $errors = [];
        
        foreach ($leads as $leadData) {
            $resultado = self::procesarLeadIndividual($proveedorId, $leadData);
            
            if ($resultado['success']) {
                $created++;
            } elseif (!empty($resultado['duplicated'])) {
                $duplicated++;
            } else {
                $errors[] = $resultado['message'];
            }
        }
        
        if (count($errors) > 0) {
            return [
                'success' => false,
                'message' => "Batch completado con errores",
                'created' => $created,
                'duplicated' => $duplicated,
                'errors' => $errors
            ];
        }
        
        return [
            'success' => true,
            'message' => "Batch procesado exitosamente",
            'created' => $created,
            'duplicated' => $duplicated
        ];
    }
    
    /**
     * Procesa una actualización de estado desde cliente.
     * (Reservado para futuro uso si cliente envía actualizaciones via inbox)
     * 
     * @param array $mensaje Mensaje del inbox
     * @param array $payload Payload decodificado
     * @return array Resultado
     */
    private static function procesarActualizacionDeCliente($mensaje, $payload) {
        // Por ahora no implementado, el cliente actualiza via endpoint directo
        return ['success' => true, 'message' => 'Actualización de cliente no requiere procesamiento'];
    }
}

<?php
/**
 * ForwardingRetryProcessor
 * 
 * Procesador para reintentar de forma específica y asíncrona una regla de forwarding fallida.
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../services/ForwardingService.php';

class ForwardingRetryProcessor extends BaseProcessor {
    
    /**
     * Procesa un reintento específico de forwarding.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando reintento automático de forwarding para pedido {$job['pedido_id']}");
        
        $validation = $this->validate($job, ['id_rule']);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        $payload = $validation['payload'];
        $pedidoId = (int)$job['pedido_id'];
        $idRegla = (int)$payload['id_rule'];
        
        try {
            // Reintentar indicando que viene de la cola para no re-encolar en caso de fallo
            $resultado = ForwardingService::reintentarRegla($pedidoId, $idRegla, true);
            
            $success = !empty($resultado['success']);
            $httpStatus = isset($resultado['http_status']) ? (int)$resultado['http_status'] : 0;
            // Error HTTP 4xx (excepto 429) es un error permanente de cliente
            $isPermanent = !$success && ($httpStatus >= 400 && $httpStatus < 500 && $httpStatus !== 429);

            return [
                'success' => $success,
                'message' => $resultado['message'] ?? 'Reintento ejecutado sin mensaje de respuesta',
                'is_permanent' => $isPermanent
            ];
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error en reintento automático de forwarding: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Error procesando reintento de forwarding: " . $e->getMessage()
            ];
        }
    }
}

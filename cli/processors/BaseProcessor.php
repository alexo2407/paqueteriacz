<?php
/**
 * BaseProcessor
 * 
 * Clase base abstracta para todos los procesadores de trabajos de logística.
 * Define la interfaz común que deben implementar todos los procesadores.
 */

abstract class BaseProcessor {
    
    /**
     * Procesa un trabajo de la cola.
     * 
     * @param array $job Trabajo a procesar (row de logistics_queue)
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function process($job);
    
    /**
     * Valida que el trabajo tenga los datos necesarios.
     * 
     * @param array $job Trabajo a validar
     * @param array $requiredFields Campos requeridos en el payload
     * @return array ['valid' => bool, 'message' => string]
     */
    protected function validate($job, $requiredFields = []) {
        // Validar que exista el pedido_id
        if (empty($job['pedido_id'])) {
            return [
                'valid' => false,
                'message' => 'pedido_id faltante'
            ];
        }
        
        // Decodificar payload
        $payload = json_decode(isset($job['payload']) ? $job['payload'] : '{}', true);
        if (!is_array($payload)) {
            return [
                'valid' => false,
                'message' => 'Payload JSON inválido'
            ];
        }
        
        // Validar campos requeridos
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return [
                    'valid' => false,
                    'message' => "Campo requerido faltante: {$field}"
                ];
            }
        }
        
        return [
            'valid' => true,
            'message' => 'Validación exitosa',
            'payload' => $payload
        ];
    }
    
    /**
     * Obtiene los datos del pedido asociado.
     * 
     * @param int $pedidoId ID del pedido
     * @return array|null Datos del pedido o null si no existe
     */
    protected function obtenerPedido($pedidoId) {
        try {
            require_once __DIR__ . '/../../modelo/pedido.php';
            return PedidosModel::obtenerPedidoPorId($pedidoId);
        } catch (Exception $e) {
            error_log("Error obteniendo pedido {$pedidoId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Registra un log del procesamiento.
     * 
     * @param int $jobId ID del trabajo
     * @param string $message Mensaje a registrar
     * @param string $level Nivel: info, warning, error
     */
    protected function log($jobId, $message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = strtoupper($level);
        error_log("[{$timestamp}][Job {$jobId}][{$prefix}] {$message}");
    }
}

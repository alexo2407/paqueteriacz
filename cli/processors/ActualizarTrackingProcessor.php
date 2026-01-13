<?php
/**
 * ActualizarTrackingProcessor
 * 
 * Procesador para actualizar el estado de tracking de pedidos.
 * Consulta APIs externas de paqueterías para obtener el estado actual.
 */

require_once __DIR__ . '/BaseProcessor.php';

class ActualizarTrackingProcessor extends BaseProcessor {
    
    /**
     * Procesa la actualización de tracking.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando actualización de tracking para pedido {$job['pedido_id']}");
        
        // Validar trabajo
        $validation = $this->validate($job);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        $payload = $validation['payload'];
        
        // Obtener datos del pedido
        $pedido = $this->obtenerPedido($job['pedido_id']);
        if (!$pedido) {
            return [
                'success' => false,
                'message' => "Pedido {$job['pedido_id']} no encontrado"
            ];
        }
        
        try {
            // Obtener número de guía del pedido
            $numeroGuia = $this->obtenerNumeroGuia($pedido);
            if (!$numeroGuia) {
                return [
                    'success' => false,
                    'message' => 'El pedido no tiene número de guía asignado'
                ];
            }
            
            $this->log($job['id'], "Consultando tracking para guía: {$numeroGuia}");
            
            // TODO: Integración con API de paquetería (FedEx, DHL, etc.)
            // Por ahora, simulamos la consulta
            $trackingInfo = $this->consultarTracking($numeroGuia, $payload);
            
            if (!$trackingInfo) {
                return [
                    'success' => false,
                    'message' => 'No se pudo obtener información de tracking'
                ];
            }
            
            // Actualizar estado del pedido si cambió
            if (isset($trackingInfo['estado'])) {
                $this->actualizarEstadoPedido($job['pedido_id'], $trackingInfo['estado']);
                $this->log($job['id'], "Estado actualizado a: {$trackingInfo['estado']}");
            }
            
            return [
                'success' => true,
                'message' => 'Tracking actualizado exitosamente',
                'tracking_info' => $trackingInfo
            ];
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Error actualizando tracking: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el número de guía del pedido.
     * 
     * @param array $pedido Datos del pedido
     * @return string|null Número de guía
     */
    private function obtenerNumeroGuia($pedido) {
        // Verificar si existe campo numero_guia
        if (isset($pedido['numero_guia']) && !empty($pedido['numero_guia'])) {
            return $pedido['numero_guia'];
        }
        
        // Buscar en notas si no existe campo dedicado
        if (isset($pedido['notas'])) {
            if (preg_match('/Guía:\s*([A-Z0-9-]+)/i', $pedido['notas'], $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Consulta el tracking en la API externa.
     * TODO: Implementar integración real con APIs de paqueterías.
     * 
     * @param string $numeroGuia Número de guía
     * @param array $payload Datos adicionales del payload
     * @return array|null Información de tracking
     */
    private function consultarTracking($numeroGuia, $payload) {
        // Por ahora, simulamos una respuesta
        // En producción, aquí se haría la llamada real a la API
        
        $paqueteria = isset($payload['paqueteria']) ? $payload['paqueteria'] : 'generic';
        
        // Simular diferentes estados basados en el número de guía
        $estados = ['en_transito', 'en_distribucion', 'entregado', 'pendiente'];
        $estadoIndex = strlen($numeroGuia) % count($estados);
        
        return [
            'numero_guia' => $numeroGuia,
            'paqueteria' => $paqueteria,
            'estado' => $estados[$estadoIndex],
            'ultima_actualizacion' => date('Y-m-d H:i:s'),
            'ubicacion_actual' => 'Centro de distribución',
            'eventos' => [
                [
                    'fecha' => date('Y-m-d H:i:s', time() - 3600),
                    'descripcion' => 'Paquete en tránsito',
                    'ubicacion' => 'Origen'
                ]
            ]
        ];
    }
    
    /**
     * Actualiza el estado del pedido.
     * 
     * @param int $pedidoId ID del pedido
     * @param string $nuevoEstado Nuevo estado
     * @return bool
     */
    private function actualizarEstadoPedido($pedidoId, $nuevoEstado) {
        try {
            require_once __DIR__ . '/../../modelo/pedido.php';
            require_once __DIR__ . '/../../modelo/conexion.php';
            
            // Mapear estados de tracking a estados de pedido
            $estadosMap = [
                'pendiente' => 1,        // Asumiendo ID de estado
                'en_transito' => 2,
                'en_distribucion' => 3,
                'entregado' => 4
            ];
            
            if (isset($estadosMap[$nuevoEstado])) {
                $estadoId = $estadosMap[$nuevoEstado];
                
                $db = (new Conexion())->conectar();
                $stmt = $db->prepare("
                    UPDATE pedidos
                    SET id_estado = :estado
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':estado' => $estadoId,
                    ':id' => $pedidoId
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error actualizando estado del pedido: " . $e->getMessage());
            return false;
        }
    }
}

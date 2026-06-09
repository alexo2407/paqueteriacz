<?php
/**
 * ForwardingEvalProcessor
 * 
 * Procesador para evaluar y ejecutar el forwarding de pedidos a proveedores externos.
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../services/ForwardingService.php';

class ForwardingEvalProcessor extends BaseProcessor {
    
    /**
     * Procesa la evaluación de forwarding de un pedido.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando evaluación de forwarding para pedido {$job['pedido_id']}");
        
        $validation = $this->validate($job);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        $payload = $validation['payload'];
        $pedidoId = (int)$job['pedido_id'];
        $idCliente = isset($payload['id_cliente']) ? (int)$payload['id_cliente'] : null;
        
        // Si no viene en el payload, cargarlo del pedido
        if (!$idCliente) {
            $pedido = $this->obtenerPedido($pedidoId);
            if (!$pedido) {
                return [
                    'success' => false,
                    'message' => "Pedido {$pedidoId} no encontrado"
                ];
            }
            $idCliente = isset($pedido['id_cliente']) ? (int)$pedido['id_cliente'] : null;
        }
        
        if (!$idCliente || $idCliente <= 0) {
            return [
                'success' => false,
                'message' => "El pedido {$pedidoId} no tiene un id_cliente válido para forwarding"
            ];
        }
        
        try {
            // Invocar el servicio de forwarding (indicando que viene de la cola)
            $resultados = ForwardingService::evaluarYReenviar($pedidoId, $idCliente, true);
            
            if ($resultados === null) {
                $msg = "No se encontraron reglas de forwarding activas para el cliente {$idCliente}";
                $this->log($job['id'], $msg);
                return [
                    'success' => true,
                    'message' => $msg
                ];
            }
            
            // Consolidar resultados
            $success = true;
            $detalles = [];
            foreach ($resultados as $res) {
                if (!$res['success']) {
                    $success = false;
                }
                $detalles[] = ($res['provider'] ?? 'unknown') . ': ' . ($res['message'] ?? 'sin mensaje');
            }
            
            $msgFinal = implode(' | ', $detalles);
            $this->log($job['id'], "Resultado de forwarding: " . $msgFinal);
            
            return [
                'success' => $success,
                'message' => $msgFinal
            ];
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error en forwarding: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Error procesando forwarding: " . $e->getMessage()
            ];
        }
    }
}

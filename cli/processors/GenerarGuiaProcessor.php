<?php
/**
 * GenerarGuiaProcessor
 * 
 * Procesador para generar guías de envío.
 * Este es un procesador de ejemplo que puede ser extendido con integración real a APIs de paqueterías.
 */

require_once __DIR__ . '/BaseProcessor.php';

class GenerarGuiaProcessor extends BaseProcessor {
    
    /**
     * Procesa la generación de una guía de envío.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando generación de guía para pedido {$job['pedido_id']}");
        
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
            // TODO: Integración con API de paquetería (FedEx, DHL, etc.)
            // Por ahora, simulamos la generación
            
            $this->log($job['id'], "Generando guía para: {$pedido['destinatario']}");
            
            // Validar que el pedido tenga dirección
            if (empty($pedido['direccion'])) {
                return [
                    'success' => false,
                    'message' => 'El pedido no tiene dirección de entrega'
                ];
            }
            
            // Simular generación de número de guía
            $numeroGuia = $this->generarNumeroGuia();
            
            // Actualizar pedido con número de guía
            $this->actualizarPedidoConGuia($job['pedido_id'], $numeroGuia);
            
            $this->log($job['id'], "Guía generada exitosamente: {$numeroGuia}");
            
            return [
                'success' => true,
                'message' => "Guía generada: {$numeroGuia}",
                'numero_guia' => $numeroGuia
            ];
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Error generando guía: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera un número de guía simulado.
     * TODO: Reemplazar con llamada real a API de paquetería.
     * 
     * @return string Número de guía
     */
    private function generarNumeroGuia() {
        // Formato: GUIA-YYYYMMDD-XXXXXX
        return 'GUIA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    /**
     * Actualiza el pedido con el número de guía generado.
     * 
     * @param int $pedidoId ID del pedido
     * @param string $numeroGuia Número de guía
     * @return bool
     */
    private function actualizarPedidoConGuia($pedidoId, $numeroGuia) {
        try {
            require_once __DIR__ . '/../../modelo/pedido.php';
            require_once __DIR__ . '/../../modelo/conexion.php';
            
            $db = (new Conexion())->conectar();
            
            // Guardar número de guía en notas
            $stmt = $db->prepare("
                UPDATE pedidos
                SET notas = CONCAT(COALESCE(notas, ''), '\nGuía: ', :numero_guia)
                WHERE id = :id
            ");
            $stmt->execute([
                ':numero_guia' => $numeroGuia,
                ':id' => $pedidoId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error actualizando pedido con guía: " . $e->getMessage());
            return false;
        }
    }
}

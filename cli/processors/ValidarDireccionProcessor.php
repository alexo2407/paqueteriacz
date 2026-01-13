<?php
/**
 * ValidarDireccionProcessor
 * 
 * Procesador para validar y normalizar direcciones.
 * Utiliza servicios de geocodificación para validar y obtener coordenadas.
 */

require_once __DIR__ . '/BaseProcessor.php';

class ValidarDireccionProcessor extends BaseProcessor {
    
    /**
     * Procesa la validación de dirección.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando validación de dirección para pedido {$job['pedido_id']}");
        
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
            // Obtener dirección del pedido
            $direccion = $pedido['direccion'] ?? null;
            if (empty($direccion)) {
                return [
                    'success' => false,
                    'message' => 'El pedido no tiene dirección'
                ];
            }
            
            $this->log($job['id'], "Validando dirección: {$direccion}");
            
            // TODO: Integración con API de geocodificación (Google Maps, OpenStreetMap, etc.)
            $resultado = $this->validarDireccion($direccion, $payload);
            
            if (!$resultado['valida']) {
                return [
                    'success' => false,
                    'message' => 'Dirección no válida o no se pudo geocodificar'
                ];
            }
            
            // Actualizar pedido con dirección normalizada y coordenadas
            $actualizado = $this->actualizarPedidoConDireccion(
                $job['pedido_id'],
                $resultado['direccion_normalizada'],
                $resultado['latitud'],
                $resultado['longitud']
            );
            
            if ($actualizado) {
                $this->log($job['id'], "Dirección validada y actualizada");
                return [
                    'success' => true,
                    'message' => 'Dirección validada exitosamente',
                    'direccion_normalizada' => $resultado['direccion_normalizada'],
                    'coordenadas' => [
                        'lat' => $resultado['latitud'],
                        'lng' => $resultado['longitud']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error actualizando pedido con dirección validada'
                ];
            }
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Error validando dirección: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida una dirección usando un servicio de geocodificación.
     * TODO: Implementar integración real con API de geocodificación.
     * 
     * @param string $direccion Dirección a validar
     * @param array $payload Datos adicionales
     * @return array Resultado de la validación
     */
    private function validarDireccion($direccion, $payload) {
        // Por ahora, simulamos la validación
        // En producción, aquí se haría la llamada real a la API
        
        // Simular coordenadas basadas en la dirección
        $hash = crc32($direccion);
        $latitud = 14.0 + ($hash % 100) / 100;
        $longitud = -87.0 - ($hash % 100) / 100;
        
        return [
            'valida' => true,
            'direccion_normalizada' => ucwords(strtolower($direccion)),
            'latitud' => $latitud,
            'longitud' => $longitud,
            'confianza' => 0.95,
            'componentes' => [
                'calle' => 'Calle Principal',
                'ciudad' => 'Ciudad',
                'pais' => 'País'
            ]
        ];
    }
    
    /**
     * Actualiza el pedido con la dirección validada y coordenadas.
     * 
     * @param int $pedidoId ID del pedido
     * @param string $direccionNormalizada Dirección normalizada
     * @param float $latitud Latitud
     * @param float $longitud Longitud
     * @return bool
     */
    private function actualizarPedidoConDireccion($pedidoId, $direccionNormalizada, $latitud, $longitud) {
        try {
            require_once __DIR__ . '/../../modelo/conexion.php';
            
            $db = (new Conexion())->conectar();
            
            // Verificar si la tabla soporta columna coordenadas (type POINT)
            $hasCoordinates = true; // La tabla pedidos ya tiene este campo
            
            if ($hasCoordinates) {
                // Usar POINT para coordenadas geográficas
                $stmt = $db->prepare("
                    UPDATE pedidos
                    SET 
                        direccion = :direccion,
                        coordenadas = POINT(:longitud, :latitud)
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':direccion' => $direccionNormalizada,
                    ':latitud' => $latitud,
                    ':longitud' => $longitud,
                    ':id' => $pedidoId
                ]);
            } else {
                // Fallback: solo actualizar dirección
                $stmt = $db->prepare("
                    UPDATE pedidos
                    SET direccion = :direccion
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':direccion' => $direccionNormalizada,
                    ':id' => $pedidoId
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error actualizando pedido con dirección: " . $e->getMessage());
            return false;
        }
    }
}

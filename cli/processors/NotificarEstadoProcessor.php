<?php
/**
 * NotificarEstadoProcessor
 * 
 * Procesador para enviar notificaciones de cambio de estado.
 * Envía emails/SMS a clientes cuando cambia el estado de su pedido.
 */

require_once __DIR__ . '/BaseProcessor.php';

class NotificarEstadoProcessor extends BaseProcessor {
    
    /**
     * Procesa el envío de notificación.
     * 
     * @param array $job Trabajo de la cola
     * @return array Resultado del procesamiento
     */
    public function process($job) {
        $this->log($job['id'], "Iniciando notificación de estado para pedido {$job['pedido_id']}");
        
        // Validar trabajo
        $validation = $this->validate($job, ['estado_nuevo']);
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
            $estadoNuevo = $payload['estado_nuevo'];
           $estadoAnterior = $payload['estado_anterior'] ?? null;
            
            $this->log($job['id'], "Cambio de estado: {$estadoAnterior} -> {$estadoNuevo}");
            
            // Obtener email del cliente
            $email = $this->obtenerEmailCliente($pedido);
            if (!$email) {
                return [
                    'success' => false,
                    'message' => 'No se pudo obtener email del cliente'
                ];
            }
            
            // Enviar notificación
            $enviado = $this->enviarNotificacion($email, $pedido, $estadoNuevo, $estadoAnterior);
            
            if ($enviado) {
                $this->log($job['id'], "Notificación enviada a: {$email}");
                return [
                    'success' => true,
                    'message' => 'Notificación enviada exitosamente',
                    'email' => $email
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al enviar notificación'
                ];
            }
            
        } catch (Exception $e) {
            $this->log($job['id'], "Error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Error enviando notificación: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el email del cliente asociado al pedido.
     * 
     * @param array $pedido Datos del pedido
     * @return string|null Email del cliente
     */
    private function obtenerEmailCliente($pedido) {
        try {
            // Verificar si el pedido tiene email directamente
            if (isset($pedido['email']) && !empty($pedido['email'])) {
                return $pedido['email'];
            }
            
            // Obtener del vendedor/cliente asignado
            if (isset($pedido['id_vendedor'])) {
                require_once __DIR__ . '/../../modelo/usuario.php';
                require_once __DIR__ . '/../../modelo/conexion.php';
                
                $db = (new Conexion())->conectar();
                $stmt = $db->prepare("SELECT email FROM usuarios WHERE id = :id");
                $stmt->execute([':id' => $pedido['id_vendedor']]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario && !empty($usuario['email'])) {
                    return $usuario['email'];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error obteniendo email del cliente: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Envía la notificación por email.
     * TODO: Integrar con servicio de email real (PHPMailer, SendGrid, etc.)
     * 
     * @param string $email Email destinatario
     * @param array $pedido Datos del pedido
     * @param string $estadoNuevo Nuevo estado
     * @param string|null $estadoAnterior Estado anterior
     * @return bool
     */
    private function enviarNotificacion($email, $pedido, $estadoNuevo, $estadoAnterior) {
        // Por ahora, solo loguear
        // En producción, aquí se enviaría el email real
        
        $asunto = "Actualización de pedido #{$pedido['numero_orden']}";
        $mensaje = $this->generarMensaje($pedido, $estadoNuevo, $estadoAnterior);
        
        // Simulación de envío
        error_log("EMAIL TO: {$email}");
        error_log("SUBJECT: {$asunto}");
        error_log("MESSAGE: {$mensaje}");
        
        // TODO: Descomentaruse cuando se integre PHPMailer
        /*
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->setFrom('noreply@paqueteria.com', 'Paquetería CZ');
            $mail->addAddress($email);
            $mail->Subject = $asunto;
            $mail->Body = $mensaje;
            return $mail->send();
        }
        */
        
        return true; // Simulamos éxito
    }
    
    /**
     * Genera el mensaje de la notificación.
     * 
     * @param array $pedido Datos del pedido
     * @param string $estadoNuevo Nuevo estado
     * @param string|null $estadoAnterior Estado anterior
     * @return string
     */
    private function generarMensaje($pedido, $estadoNuevo, $estadoAnterior) {
        $mensaje = "Estimado cliente,\n\n";
        $mensaje .= "Su pedido #{$pedido['numero_orden']} ha cambiado de estado.\n\n";
        
        if ($estadoAnterior) {
            $mensaje .= "Estado anterior: {$estadoAnterior}\n";
        }
        
        $mensaje .= "Estado actual: {$estadoNuevo}\n\n";
        
        if (isset($pedido['destinatario'])) {
            $mensaje .= "Destinatario: {$pedido['destinatario']}\n";
        }
        
        if (isset($pedido['direccion'])) {
            $mensaje .= "Dirección: {$pedido['direccion']}\n";
        }
        
        $mensaje .= "\nGracias por su preferencia.\n";
        $mensaje .= "Paquetería CZ";
        
        return $mensaje;
    }
}

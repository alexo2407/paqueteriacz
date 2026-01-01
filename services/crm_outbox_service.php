<?php
/**
 * CRM Outbox Service
 * 
 * Servicio para procesar mensajes de la cola de salida.
 * Envía webhooks a clientes y proveedores con reintentos.
 */

require_once __DIR__ . '/../modelo/crm_outbox.php';
require_once __DIR__ . '/../modelo/crm_integration.php';
require_once __DIR__ . '/../utils/hmac_signature.php';

class CrmOutboxService {
    
    /**
     * Procesa mensajes pendientes del outbox.
     * 
     * @param int $limit Límite de mensajes a procesar
     * @return array Estadísticas de procesamiento
     */
    public static function procesar($limit = 10) {
        $stats = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            $mensajes = CrmOutboxModel::obtenerPendientes($limit);
            
            foreach ($mensajes as $mensaje) {
                try {
                    // Marcar como enviando (lock)
                    CrmOutboxModel::marcarEnviando($mensaje['id']);
                    
                    // Obtener integración del destinatario
                    $kind = ($mensaje['event_type'] === 'SEND_TO_CLIENT') ? 'cliente' : 'proveedor';
                    $integracion = CrmIntegrationModel::obtenerActiva($mensaje['destination_user_id'], $kind);
                    
                    if (!$integracion) {
                        throw new Exception("Integración no activa para user_id {$mensaje['destination_user_id']} ({$kind})");
                    }
                    
                    // Enviar webhook
                    $resultado = self::enviarWebhook(
                        $integracion['webhook_url'],
                        json_decode($mensaje['payload'], true),
                        $integracion['secret']
                    );
                    
                    if ($resultado['success']) {
                        CrmOutboxModel::marcarEnviado($mensaje['id']);
                        $stats['sent']++;
                    } else {
                        CrmOutboxModel::incrementarIntento($mensaje['id'], $resultado['error']);
                        $stats['failed']++;
                        $stats['errors'][] = $resultado['error'];
                    }
                    
                } catch (Exception $e) {
                    $error = "Error procesando outbox {$mensaje['id']}: " . $e->getMessage();
                    error_log($error);
                    CrmOutboxModel::incrementarIntento($mensaje['id'], $error);
                    $stats['failed']++;
                    $stats['errors'][] = $error;
                }
            }
            
        } catch (Exception $e) {
            error_log("Error en CrmOutboxService::procesar: " . $e->getMessage());
            $stats['errors'][] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Envía un webhook HTTP POST con firma HMAC.
     * 
     * @param string $url URL del webhook
     * @param array $payload Payload a enviar
     * @param string $secret Secret para firmar
     * @return array ['success' => bool, 'error' => string]
     */
    private static function enviarWebhook($url, $payload, $secret) {
        try {
            // Generar firma HMAC
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature = buildSignatureHeader($payloadJson, $secret);
            
            // Configurar request con cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout 5 segundos
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Connect timeout 3 segundos
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Signature: ' . $signature,
                'User-Agent: CRM-Relay/1.0'
            ]);
            
            // No verificar SSL en desarrollo (cambiar en producción)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Verificar respuesta
            if ($error) {
                return ['success' => false, 'error' => "cURL error: {$error}"];
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

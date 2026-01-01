<?php
/**
 * HMAC Signature Helper
 * 
 * Utilidades para firmar y verificar payloads de webhooks usando HMAC SHA256.
 * Esto garantiza que los webhooks enviados/recibidos no han sido alterados.
 */

/**
 * Genera una firma HMAC SHA256 para un payload.
 * 
 * @param array|string $payload Payload a firmar (se serializa a JSON si es array)
 * @param string $secret Clave secreta compartida
 * @return string Firma HMAC en formato hexadecimal
 */
function generateHmacSignature($payload, $secret) {
    // Si es array, convertir a JSON
    if (is_array($payload)) {
        $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    // Generar HMAC SHA256
    return hash_hmac('sha256', $payload, $secret);
}

/**
 * Verifica una firma HMAC SHA256.
 * 
 * @param array|string $payload Payload recibido
 * @param string $signature Firma a verificar
 * @param string $secret Clave secreta compartida
 * @return bool True si la firma es válida
 */
function verifyHmacSignature($payload, $signature, $secret) {
    $expectedSignature = generateHmacSignature($payload, $secret);
    
    // Comparación timing-safe para prevenir timing attacks
    return hash_equals($expectedSignature, $signature);
}

/**
 * Construye el header X-Signature para webhooks salientes.
 * 
 * @param array|string $payload Payload a enviar
 * @param string $secret Clave secreta
 * @return string Valor del header X-Signature (formato: sha256=HASH)
 */
function buildSignatureHeader($payload, $secret) {
    $signature = generateHmacSignature($payload, $secret);
    return 'sha256=' . $signature;
}

/**
 * Extrae y verifica el header X-Signature de un request entrante.
 * 
 * @param string $signatureHeader Valor del header X-Signature
 * @param array|string $payload Payload recibido
 * @param string $secret Clave secreta
 * @return bool True si la firma es válida
 */
function verifySignatureHeader($signatureHeader, $payload, $secret) {
    // Formato esperado: "sha256=HASH"
    if (strpos($signatureHeader, 'sha256=') !== 0) {
        return false;
    }
    
    // Extraer solo el hash
    $signature = substr($signatureHeader, 7); // Quitar "sha256="
    
    return verifyHmacSignature($payload, $signature, $secret);
}

/**
 * Genera un secret aleatorio para usar en integraciones.
 * 
 * @param int $length Longitud del secret (default: 32 bytes = 64 chars hex)
 * @return string Secret en formato hexadecimal
 */
function generateWebhookSecret($length = 32) {
    return bin2hex(random_bytes($length));
}

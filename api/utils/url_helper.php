<?php
/**
 * Helper para generar URLs de API correctas
 * 
 * Las URLs siempre se generan como /api/... sin incluir el directorio del proyecto.
 * Esto permite que funcionen en:
 * - Desarrollo: http://localhost/paqueteriacz/api/...
 * - Producción: https://dominio.com/api/...
 */

/**
 * Genera una URL de API estándar
 * 
 * @param string $endpoint Endpoint relativo (ej: crm/jobs/123)
 * @return string URL de API (ej: /api/crm/jobs/123)
 */
function getApiUrl($endpoint) {
    $endpoint = ltrim($endpoint, '/');
    return '/api/' . $endpoint;
}

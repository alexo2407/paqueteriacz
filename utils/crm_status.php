<?php
/**
 * CRM Status Helper
 * 
 * Utilidades para normalizar y validar estados CRM.
 * Estados canónicos: CANCELADO, APROBADO, CONFIRMADO, EN_TRANSITO, EN_BODEGA, EN_ESPERA
 */

/**
 * Normaliza un estado a su forma canónica.
 * Acepta aliases comunes y los convierte al estado oficial.
 * 
 * @param string $estado Estado crudo del cliente
 * @return string Estado normalizado
 */
function normalizeEstado($estado) {
    // Convertir a mayúsculas y quitar espacios
    $estado = mb_strtoupper(trim($estado));
    
    // Mapa de aliases a estados canónicos
    $aliases = [
        'APROVADO' => 'APROBADO',
        'APPROVED' => 'APROBADO',
        'CANCELAR' => 'CANCELADO',
        'CANCELLED' => 'CANCELADO',
        'CANCELED' => 'CANCELADO',
        'CONFIRMAR' => 'CONFIRMADO',
        'CONFIRMED' => 'CONFIRMADO',
        'EN TRANSITO' => 'EN_TRANSITO',
        'TRANSITO' => 'EN_TRANSITO',
        'TRANSIT' => 'EN_TRANSITO',
        'EN BODEGA' => 'EN_BODEGA',
        'BODEGA' => 'EN_BODEGA',
        'WAREHOUSE' => 'EN_BODEGA',
        'EN ESPERA' => 'EN_ESPERA',
        'ESPERA' => 'EN_ESPERA',
        'PENDING' => 'EN_ESPERA',
        'WAITING' => 'EN_ESPERA'
    ];
    
    // Si está en el mapa, retornar normalizado
    if (isset($aliases[$estado])) {
        return $aliases[$estado];
    }
    
    // Si ya es canónico, retornarlo
    if (isValidEstado($estado)) {
        return $estado;
    }
    
    // No se pudo normalizar
    return $estado;
}

/**
 * Valida si un estado es válido (canónico).
 * 
 * @param string $estado Estado a validar
 * @return bool True si es un estado válido
 */
function isValidEstado($estado) {
    $estadosValidos = [
        'CANCELADO',
        'APROBADO',
        'CONFIRMADO',
        'EN_TRANSITO',
        'EN_BODEGA',
        'EN_ESPERA'
    ];
    
    return in_array(mb_strtoupper($estado), $estadosValidos, true);
}

/**
 * Valida si una transición de estado es permitida.
 * Matriz de transiciones permitidas para evitar saltos ilógicos.
 * 
 * @param string $estadoAnterior Estado actual
 * @param string $estadoNuevo Estado deseado
 * @return bool True si la transición es válida
 */
function canTransition($estadoAnterior, $estadoNuevo) {
    // Normalizar ambos estados
    $from = normalizeEstado($estadoAnterior);
    $to = normalizeEstado($estadoNuevo);
    
    // Matriz de transiciones permitidas
    $transiciones = [
        'EN_ESPERA' => ['APROBADO', 'CANCELADO'],
        'APROBADO' => ['CONFIRMADO', 'CANCELADO'],
        'CONFIRMADO' => ['EN_TRANSITO', 'CANCELADO'],
        'EN_TRANSITO' => ['EN_BODEGA', 'CANCELADO'],
        'EN_BODEGA' => ['CANCELADO'], // Estado casi final
        'CANCELADO' => [] // Estado final, no permite transiciones
    ];
    
    // Si el estado anterior no existe en la matriz, no permitir
    if (!isset($transiciones[$from])) {
        return false;
    }
    
    // Si el estado nuevo está en la lista de permitidos, OK
    return in_array($to, $transiciones[$from], true);
}

/**
 * Obtiene todos los estados canónicos disponibles.
 * 
 * @return array Lista de estados válidos
 */
function getEstadosValidos() {
    return [
        'CANCELADO',
        'APROBADO',
        'CONFIRMADO',
        'EN_TRANSITO',
        'EN_BODEGA',
        'EN_ESPERA'
    ];
}

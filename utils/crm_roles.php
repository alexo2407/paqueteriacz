<?php
/**
 * CRM Roles Helper
 * 
 * Utilidades para verificar roles de usuarios de forma stateless (sin $_SESSION).
 * Se consulta directamente la tabla usuarios_roles usando el user_id del JWT.
 */

require_once __DIR__ . '/../modelo/conexion.php';

/**
 * Obtiene todos los roles de un usuario por su ID.
 * Consulta la tabla usuarios_roles y retorna array de nombres de roles.
 * 
 * @param int $userId ID del usuario
 * @return array Array de nombres de roles ['Administrador', 'Proveedor']
 */
function getRolesForUser($userId) {
    // Cache por request para evitar queries repetitivas
    static $cache = [];
    
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    try {
        $db = (new Conexion())->conectar();
        
        $stmt = $db->prepare("
            SELECT r.nombre_rol 
            FROM usuarios_roles ur
            INNER JOIN roles r ON r.id = ur.id_rol
            WHERE ur.id_usuario = :user_id
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Cachear resultado
        $cache[$userId] = $roles;
        
        return $roles;
        
    } catch (Exception $e) {
        error_log("Error getting roles for user {$userId}: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si un usuario tiene un rol específico.
 * 
 * @param int $userId ID del usuario
 * @param string $roleName Nombre del rol a verificar (ej: 'Administrador')
 * @return bool True si el usuario tiene ese rol
 */
function userHasRole($userId, $roleName) {
    $roles = getRolesForUser($userId);
    return in_array($roleName, $roles, true);
}

/**
 * Verifica si un usuario tiene al menos uno de los roles especificados.
 * 
 * @param int $userId ID del usuario
 * @param array $roleNames Array de nombres de roles ['Administrador', 'Proveedor']
 * @return bool True si el usuario tiene al menos uno de los roles
 */
function userHasAnyRole($userId, array $roleNames) {
    $userRoles = getRolesForUser($userId);
    return count(array_intersect($userRoles, $roleNames)) > 0;
}

/**
 * Verifica si un usuario es administrador.
 * Renombrado para evitar conflicto con permissions.php
 * 
 * @param int $userId ID del usuario
 * @return bool True si es administrador
 */
function isUserAdmin($userId) {
    return userHasRole($userId, 'Administrador');
}

/**
 * Verifica si un usuario es proveedor.
 * 
 * @param int $userId ID del usuario
 * @return bool True si es proveedor
 */
function isUserProveedor($userId) {
    return userHasRole($userId, 'Proveedor');
}

/**
 * Verifica si un usuario es cliente.
 * 
 * @param int $userId ID del usuario
 * @return bool True si es cliente
 */
function isUserCliente($userId) {
    return userHasRole($userId, 'Cliente');
}

/**
 * Verifica si un usuario es proveedor CRM.
 * NUEVO: Específico para el módulo CRM, separado de Logística.
 * 
 * @param int $userId ID del usuario
 * @return bool True si tiene rol Proveedor CRM
 */
function isProveedorCRM($userId) {
    return userHasRole($userId, ROL_NOMBRE_PROVEEDOR_CRM);
}

/**
 * Verifica si un usuario es cliente CRM.
 * NUEVO: Específico para el módulo CRM, separado de Logística.
 * 
 * @param int $userId ID del usuario
 * @return bool True si tiene rol Cliente CRM
 */
function isClienteCRM($userId) {
    return userHasRole($userId, ROL_NOMBRE_CLIENTE_CRM);
}

/**
 * Limpia el cache de roles (útil para testing).
 * 
 * @return void
 */
function clearRolesCache() {
    getRolesForUser(0); // Resetea el static cache
}

<?php
/**
 * Permissions Helper
 * 
 * Funciones helper para verificar permisos basados en roles de usuario.
 * Requiere que la sesión esté iniciada y contenga 'rol' y 'user_id'.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Verifica si el usuario actual puede ver un pedido específico.
 * 
 * @param array $pedido Datos del pedido con al menos 'id_proveedor'
 * @return bool
 */
function canViewOrder($pedido) {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    
    // Super Admin puede ver todos los pedidos
    if ($_SESSION['rol'] == ROL_ADMIN) {
        return true;
    }
    
    // Proveedor solo puede ver sus propios pedidos
    if ($_SESSION['rol'] == ROL_PROVEEDOR) {
        return isset($pedido['id_proveedor']) && 
               $pedido['id_proveedor'] == $_SESSION['user_id'];
    }
    
    // Otros roles (Vendedor, Repartidor) pueden ver todos
    return true;
}

/**
 * Verifica si el usuario actual puede editar un pedido específico.
 * 
 * @param array $pedido Datos del pedido
 * @return bool
 */
function canEditOrder($pedido) {
    // Misma lógica que canViewOrder por ahora
    return canViewOrder($pedido);
}

/**
 * Verifica si el usuario actual puede crear datos de catálogo
 * (productos, stock, monedas, etc.)
 * 
 * @return bool
 */
function canCreateCatalogData() {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    
    $allowedRoles = [ROL_ADMIN, ROL_PROVEEDOR];
    return in_array($_SESSION['rol'], $allowedRoles);
}

/**
 * Verifica si el usuario actual es Super Admin.
 * 
 * @return bool
 */
function isSuperAdmin() {
    // Verificar en el rol principal
    if (isset($_SESSION['rol']) && $_SESSION['rol'] == ROL_ADMIN) {
        return true;
    }
    
    // Verificar en el array de roles (multi-rol)
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        return in_array(ROL_ADMIN, $_SESSION['roles']);
    }
    
    return false;
}

/**
 * Verifica si el usuario actual es Proveedor.
 * 
 * @return bool
 */
function isProveedor() {
    // Verificar en el rol principal
    if (isset($_SESSION['rol']) && $_SESSION['rol'] == ROL_PROVEEDOR) {
        return true;
    }
    
    // Verificar en el array de roles (multi-rol)
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        return in_array(ROL_PROVEEDOR, $_SESSION['roles']);
    }
    
    return false;
}

/**
 * Obtiene el ID del proveedor para el usuario actual.
 * Si es proveedor, retorna su user_id. Si es admin, retorna null (puede elegir).
 * 
 * @return int|null
 */
function getProveedorIdForCurrentUser() {
    if (isProveedor()) {
        return $_SESSION['user_id'] ?? null;
    }
    return null; // Admin puede elegir cualquier proveedor
}

/**
 * Verifica si el usuario actual puede ver todos los proveedores
 * (para el select de proveedores en formularios).
 * 
 * @return bool
 */
function canSelectAnyProveedor() {
    return isSuperAdmin();
}

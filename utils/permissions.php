<?php

// Definición de constantes de Roles (IDs)
if (!defined('ROL_ADMIN')) define('ROL_ADMIN', 1);
if (!defined('ROL_VENDEDOR')) define('ROL_VENDEDOR', 2);
if (!defined('ROL_REPARTIDOR')) define('ROL_REPARTIDOR', 3);
if (!defined('ROL_PROVEEDOR')) define('ROL_PROVEEDOR', 4);  // Logística
if (!defined('ROL_CLIENTE')) define('ROL_CLIENTE', 5);      // Logística
if (!defined('ROL_PROVEEDOR_CRM')) define('ROL_PROVEEDOR_CRM', 6);  // CRM (verificado en BD)
if (!defined('ROL_CLIENTE_CRM')) define('ROL_CLIENTE_CRM', 7);      // CRM (verificado en BD)



// Definición de constantes de Nombres de Roles
if (!defined('ROL_NOMBRE_ADMIN')) define('ROL_NOMBRE_ADMIN', 'Administrador');
if (!defined('ROL_NOMBRE_VENDEDOR')) define('ROL_NOMBRE_VENDEDOR', 'Vendedor');
if (!defined('ROL_NOMBRE_REPARTIDOR')) define('ROL_NOMBRE_REPARTIDOR', 'Repartidor');
if (!defined('ROL_NOMBRE_PROVEEDOR')) define('ROL_NOMBRE_PROVEEDOR', 'Proveedor');  // Logística
if (!defined('ROL_NOMBRE_CLIENTE')) define('ROL_NOMBRE_CLIENTE', 'Cliente');               // Logística
if (!defined('ROL_NOMBRE_PROVEEDOR_CRM')) define('ROL_NOMBRE_PROVEEDOR_CRM', 'Proveedor CRM');  // CRM
if (!defined('ROL_NOMBRE_CLIENTE_CRM')) define('ROL_NOMBRE_CLIENTE_CRM', 'Cliente CRM');      // CRM

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

    // Cliente solo puede ver sus propios pedidos
    if ($_SESSION['rol'] == ROL_CLIENTE) {
        return isset($pedido['id_cliente']) && 
               $pedido['id_cliente'] == $_SESSION['user_id'];
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
 * Verifica si el usuario actual es Repartidor (sin ser Admin).
 * 
 * @return bool
 */
function isRepartidor() {
    // Verificar en el rol principal
    if (isset($_SESSION['rol']) && $_SESSION['rol'] == ROL_REPARTIDOR) {
        // Asegurar que no sea también admin
        if (isSuperAdmin()) {
            return false;
        }
        return true;
    }
    
    // Verificar en el array de roles (multi-rol)
    if (isset($_SESSION['roles_nombres']) && is_array($_SESSION['roles_nombres'])) {
        $hasRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $_SESSION['roles_nombres'], true);
        $hasAdmin = in_array(ROL_NOMBRE_ADMIN, $_SESSION['roles_nombres'], true);
        
        return $hasRepartidor && !$hasAdmin;
    }
    
    return false;
}

/**
 * Verifica si el usuario actual es Cliente de Logística.
 * 
 * @return bool
 */
function isCliente() {
    // Verificar en el rol principal
    if (isset($_SESSION['rol']) && $_SESSION['rol'] == ROL_CLIENTE) {
        return true;
    }
    
    // Verificar en el array de roles (multi-rol)
    if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        return in_array(ROL_CLIENTE, $_SESSION['roles']);
    }

    if (isset($_SESSION['roles_nombres']) && is_array($_SESSION['roles_nombres'])) {
        return in_array(ROL_NOMBRE_CLIENTE, $_SESSION['roles_nombres'], true);
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

/**
 * Verifica si el usuario actual puede ver un producto específico.
 * Admin ve todos, Proveedor solo los suyos.
 * 
 * @param array $producto Datos del producto con 'id_usuario_creador'
 * @return bool
 */
function canViewProduct($producto) {
    // Admin puede ver todos
    if (isSuperAdmin()) {
        return true;
    }
    
    // Si no hay creador asignado (producto legacy), todos pueden verlo
    if (!isset($producto['id_usuario_creador']) || $producto['id_usuario_creador'] === null) {
        return true;
    }
    
    // Proveedor solo puede ver sus propios productos
    $userId = $_SESSION['user_id'] ?? $_SESSION['ID_Usuario'] ?? null;
    if ($userId === null) {
        return false;
    }
    
    return (int)$producto['id_usuario_creador'] === (int)$userId;
}

/**
 * Verifica si el usuario actual puede editar un producto específico.
 * Admin puede editar todos, Proveedor solo los suyos.
 * 
 * @param array $producto Datos del producto con 'id_usuario_creador'
 * @return bool
 */
function canEditProduct($producto) {
    // Misma lógica que canViewProduct para proveedores
    // Solo admin y el creador pueden editar
    if (isSuperAdmin()) {
        return true;
    }
    
    // Si no hay creador asignado, solo admin puede editar (productos legacy)
    if (!isset($producto['id_usuario_creador']) || $producto['id_usuario_creador'] === null) {
        return isSuperAdmin();
    }
    
    // Proveedor solo puede editar sus propios productos
    $userId = $_SESSION['user_id'] ?? $_SESSION['ID_Usuario'] ?? null;
    if ($userId === null) {
        return false;
    }
    
    return (int)$producto['id_usuario_creador'] === (int)$userId;
}

/**
 * Obtiene el ID de usuario para usar como filtro de productos.
 * Admin: null (sin filtro, ve todos)
 * Proveedor: su user_id (solo ve los suyos)
 * 
 * @return int|null ID de usuario para filtrar, o null para ver todos
 */
function getIdUsuarioCreadorFilter() {
    // Admin ve todos los productos
    if (isSuperAdmin()) {
        return null;
    }
    
    // Proveedor solo ve sus productos
    if (isProveedor()) {
        return $_SESSION['user_id'] ?? $_SESSION['ID_Usuario'] ?? null;
    }
    
    // Otros roles (Vendedor) ven todos los productos por defecto
    // Puedes cambiar esto si quieres restringir más
    return null;
}

/**
 * Obtiene el ID del usuario actual para auditoría y creación de registros.
 * Busca en sesión web y en variable global de API.
 * 
 * @return int|null
 */
function getCurrentUserId() {
    // Primero verificar variable global de API (desde JWT)
    if (isset($GLOBALS['API_USER_ID']) && is_numeric($GLOBALS['API_USER_ID'])) {
        return (int)$GLOBALS['API_USER_ID'];
    }
    
    // Luego verificar sesión web
    if (isset($_SESSION['ID_Usuario'])) {
        return (int)$_SESSION['ID_Usuario'];
    }
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    
    return null;
}

/**
 * Alias para isSuperAdmin() - verificar si el usuario es administrador.
 * 
 * @return bool
 */
function isAdmin() {
    return isSuperAdmin();
}



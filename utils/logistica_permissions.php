<?php

declare(strict_types=1);

/**
 * utils/logistica_permissions.php
 *
 * Helper centralizado de autorización para el módulo Logística Operativa.
 *
 * Proporciona tres funciones públicas:
 *
 *   require_permission(string $codigo)
 *       Rutas web con sesión PHP. Sin permiso → redirige (302).
 *       Política: deny by default (si hay error inesperado → deniega).
 *
 *   api_require_permission(string $codigo, array $usuario)
 *       Endpoints API con JWT. Sin permiso → responde JSON 403 y sale.
 *       El caller debe haber validado el token antes de llamar esta función.
 *
 *   current_user_has_permission(string $codigo): bool
 *       Sin efectos secundarios. Devuelve true/false.
 *       Usado en header.php y vistas para mostrar/ocultar elementos.
 *       Usa caché estático por request para evitar queries repetidas.
 *
 * Diseño de seguridad:
 *   - Deny by default: cualquier excepción o dato inesperado deniega el acceso.
 *   - Sin exposición de SQL, DSN, contraseñas ni traza en respuestas al cliente.
 *   - Solo registra errores internos con error_log().
 *   - No modifica pedidos, stock, inventario ni reservas.
 *   - Inversión semántica de roles (IDs 4/5) preservada: este helper consulta
 *     permisos en BD (tabla permisos + roles_permisos), no los nombres de rol.
 *
 * Dependencias:
 *   - config/config.php (DB_HOST, DB_SCHEMA, DB_USER, DB_PASSWORD, RUTA_URL)
 *   - utils/session.php (start_secure_session, require_login, set_flash)
 *   - Tabla `permisos` y `roles_permisos` en BD (migración 022)
 *   - $_SESSION['roles'] — array de IDs de rol del usuario (array<int>)
 *   - $_SESSION['permisos'] — caché opcional (array<string>)
 *
 * Hidratación de $_SESSION['permisos']:
 *   Este helper la completa automáticamente si falta, consultando BD
 *   a partir de $_SESSION['roles'] (IDs de rol del usuario).
 *
 * @see database/migrations/022_create_logistica_operativa_permisos.sql
 */

// ── Dependencias ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/session.php';

// ── Caché estático por request ────────────────────────────────────────────────
// Evita múltiples queries a BD por cada llamada a current_user_has_permission().
// Se reinicia automáticamente en cada request (static local a la función).

/**
 * Devuelve una conexión PDO a la BD activa, reutilizando la instancia
 * por request para no abrir múltiples conexiones desde header.php.
 *
 * @return PDO|null  null si la conexión falla (deniega el acceso al caller)
 */
function _logistica_perm_db(): ?PDO
{
    static $pdo = null;
    static $intentado = false;

    if ($intentado) {
        return $pdo;
    }

    $intentado = true;

    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_SCHEMA
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        error_log('[logistica_permissions] Error de conexión BD: ' . $e->getMessage());
        $pdo = null;
    }

    return $pdo;
}

/**
 * Consulta si alguno de los roles indicados tiene el permiso dado.
 * Centraliza toda la lógica SQL en un único lugar.
 *
 * @param  int[]  $rolIds       IDs de los roles del usuario.
 * @param  string $codigo       Código del permiso a verificar.
 * @return bool                 true si al menos un rol tiene el permiso.
 */
function _logistica_perm_consultar_bd(array $rolIds, string $codigo): bool
{
    if (empty($rolIds) || $codigo === '') {
        return false;
    }

    $rolIdsInt = array_map('intval', $rolIds);
    $hasAdmin    = in_array(ROL_ADMIN,    $rolIdsInt, true) || in_array(1, $rolIdsInt, true);
    $hasProveedor = in_array(ROL_PROVEEDOR, $rolIdsInt, true) || in_array(5, $rolIdsInt, true);

    // Los permisos de Logística Operativa son exclusivos de Administrador y Proveedor.
    // El rol Cliente (ID 4) NO tiene acceso a Logística Operativa.
    if (!$hasAdmin && !$hasProveedor) {
        return false;
    }

    // Construir lista de IDs de rol a consultar (Admin y/o Proveedor)
    $rolIdsConsulta = [];
    if ($hasProveedor) {
        $rolIdsConsulta[] = ROL_PROVEEDOR; // 5
    }
    if ($hasAdmin) {
        $rolIdsConsulta[] = ROL_ADMIN; // 1
    }

    $db = _logistica_perm_db();
    if ($db === null) {
        return false; // deny by default ante error de conexión
    }

    try {
        $placeholders = implode(',', array_fill(0, count($rolIdsConsulta), '?'));

        $sql = "
            SELECT COUNT(*) AS tiene
            FROM `roles_permisos` rp
            JOIN `permisos` p ON p.`id` = rp.`id_permiso`
            WHERE rp.`id_rol` IN ({$placeholders})
              AND p.`codigo`  = ?
              AND p.`activo`  = 1
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);

        $params = array_values($rolIdsConsulta);
        $params[] = $codigo;

        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) ($row['tiene'] ?? 0) > 0;

    } catch (Throwable $e) {
        error_log('[logistica_permissions] Error consultando permiso "' . $codigo . '": ' . $e->getMessage());
        return false; // deny by default
    }
}

/**
 * Obtiene los IDs de rol del usuario actual desde la sesión.
 * Devuelve array vacío si no hay sesión activa o no tiene roles.
 *
 * @return int[]
 */
function _logistica_perm_rol_ids(): array
{
    $roles = $_SESSION['roles'] ?? [];
    if (!is_array($roles) || empty($roles)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('intval', $roles))));
}

// ── Función pública 1 — current_user_has_permission() ────────────────────────

/**
 * Verifica si el usuario de sesión actual tiene el permiso indicado.
 *
 * Sin efectos secundarios. No redirige. No lanza excepciones al caller.
 * Usada en header.php y vistas para mostrar/ocultar elementos de UI.
 *
 * Estrategia de caché:
 *   1. Revisa $_SESSION['permisos'] (array de códigos precargados).
 *   2. Si no está en caché, consulta BD y guarda el resultado.
 *   3. Usa variable estática para evitar múltiples queries a la misma clave
 *      en un mismo request.
 *
 * @param  string $codigo  Código del permiso, ej: 'logistica_operativa_bodega'
 * @return bool            true = tiene el permiso; false = no lo tiene (o error)
 */
function current_user_has_permission(string $codigo, bool $resetCache = false): bool
{
    // Caché por request: evita repetir queries para la misma clave
    static $cache = [];

    if ($resetCache) {
        $cache = [];
    }

    if (isset($cache[$codigo])) {
        return $cache[$codigo];
    }

    // Verificar que hay sesión activa o datos en $_SESSION (entorno CLI/tests)
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_SESSION)) {
        $cache[$codigo] = false;
        return false;
    }

    // Si está precargado en sesión (hidratado por EnlacesController), usarlo
    $permisosEnSesion = $_SESSION['permisos'] ?? null;
    if (is_array($permisosEnSesion)) {
        $resultado = in_array($codigo, $permisosEnSesion, true);
        $cache[$codigo] = $resultado;
        return $resultado;
    }

    // Consultar BD usando los IDs de rol del usuario (con caché estático por request)
    $rolIds = _logistica_perm_rol_ids();
    if (empty($rolIds)) {
        $cache[$codigo] = false;
        return false;
    }

    $resultado = _logistica_perm_consultar_bd($rolIds, $codigo);
    $cache[$codigo] = $resultado;
    return $resultado;
}

// ── Función pública 2 — require_permission() ──────────────────────────────────

/**
 * Protege rutas web. Requiere sesión activa y el permiso indicado.
 *
 * Flujo:
 *   1. Sin sesión → require_login() → redirige a /login (302) y sale.
 *   2. Sin permiso → flash error + redirige a /dashboard (302) y sale.
 *   3. Con permiso → no hace nada (el caller continúa).
 *
 * Política de seguridad:
 *   - Deny by default: si la consulta BD falla, deniega el acceso.
 *   - No expone SQL, DSN, contraseñas ni traza al usuario.
 *
 * @param string $codigo  Código del permiso, ej: 'logistica_operativa_bodega'
 */
function require_permission(string $codigo): void
{
    // Paso 1: verificar sesión activa (redirige a login si no)
    require_login();

    // Paso 2: verificar el permiso
    $tiene = current_user_has_permission($codigo);

    if (!$tiene) {
        $baseUrl = defined('RUTA_URL') ? RUTA_URL : '/paqueteriacz/';
        set_flash('error', 'No tienes permiso para acceder a esta sección.');

        // Redirigir a dashboard (nunca al login — la sesión sí existe)
        header('Location: ' . $baseUrl . 'dashboard');
        exit;
    }
}

// ── Función pública 3 — api_require_permission() ──────────────────────────────

/**
 * Protege endpoints API (JWT). Responde JSON 403 si el usuario no tiene el permiso.
 *
 * Pre-condición: el token JWT ya fue validado y $usuario contiene los datos
 * del claim. Esta función NO valida el JWT — solo verifica el permiso en BD.
 *
 * Flujo:
 *   1. Sin rol en el token → 403 FORBIDDEN y sale.
 *   2. Sin permiso en BD   → 403 FORBIDDEN y sale.
 *   3. Con permiso         → no hace nada (el caller continúa).
 *
 * Política de seguridad:
 *   - Deny by default: si la consulta BD falla, devuelve 403.
 *   - No expone SQL, DSN, traza ni contraseñas en la respuesta JSON.
 *   - Registra errores internos solo con error_log().
 *
 * @param string $codigo   Código del permiso, ej: 'logistica_operativa_colectas'
 * @param array  $usuario  Datos del JWT decodificado: { id: int, nombre: string, rol: int }
 */
function api_require_permission(string $codigo, array $usuario): void
{
    $rolId = (int) ($usuario['rol'] ?? 0);

    if ($rolId <= 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code'    => 'FORBIDDEN',
            'message' => 'No tiene permiso para acceder a este recurso (' . $codigo . ').',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tiene = _logistica_perm_consultar_bd([$rolId], $codigo);

    if (!$tiene) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code'    => 'FORBIDDEN',
            'message' => 'No tiene permiso para acceder a este recurso (' . $codigo . ').',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

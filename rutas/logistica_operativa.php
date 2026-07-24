<?php
/**
 * rutas/logistica_operativa.php
 *
 * Manejador de rutas web internas para el módulo Logística Operativa.
 *
 * Rutas registradas:
 *   GET  /logistica-operativa/colectas          → index de colectas
 *   GET  /logistica-operativa/colectas/ver/{id} → detalle de colecta
 *
 * Seguridad:
 *   - Requiere sesión activa (require_login).
 *   - Permite acceso a Administrador y Proveedor (internos autorizados).
 *   - Bloquea si LOGISTICA_OPERATIVA_ENABLED=false.
 *
 * PROPUESTA DE PERMISO (no implementada en BD todavía):
 *   logistica_operativa_colectas
 *   → Se sugiere crear una entrada en la tabla de permisos con este código
 *     y asignarlo a los roles que deben operar colectas (ej: Administrador, Supervisor).
 *     Por ahora se usa la validación de rol por nombre como el resto del sistema.
 */

declare(strict_types=1);

// Solo actuar si el primer segmento es 'logistica-operativa'
if (!isset($ruta[0]) || $ruta[0] !== 'logistica-operativa') {
    return; // No es nuestra ruta
}

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/authorization.php';
require_once __DIR__ . '/../utils/permissions.php';
require_once __DIR__ . '/../services/LogisticaOperativaFlags.php';

start_secure_session();

// ── 1. Autenticación ──────────────────────────────────────────────────────────
require_login();

// ── 2. Permiso (internos autorizados) ─────────────────────────────────────────
//
// Propuesta: crear permiso 'logistica_operativa_colectas' en BD.
// Por ahora: solo Administrador y Proveedor (usuarios internos operativos).
// No se usan IDs de rol, solo nombres según el patrón del sistema.
//
$_rolesPermitidosLO = [ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR];
$_userRolesLO       = $_SESSION['roles_nombres'] ?? [];
$_tienePermisoLO    = false;

foreach ($_rolesPermitidosLO as $_r) {
    if (in_array($_r, $_userRolesLO, true)) {
        $_tienePermisoLO = true;
        break;
    }
}

if (!$_tienePermisoLO) {
    set_flash('error', 'No tienes permisos para acceder al módulo de Logística Operativa.');
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── 3. Feature flag ───────────────────────────────────────────────────────────
if (!LogisticaOperativaFlags::enabled()) {
    set_flash('error', 'El módulo Logística Operativa no está habilitado actualmente.');
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── 4. Enrutamiento interno ───────────────────────────────────────────────────

$_submodulo = $ruta[1] ?? '';     // 'colectas'
$_accion    = $ruta[2] ?? '';     // 'ver' | ''
$parametros = array_slice($ruta, 3); // [id, ...]

if ($_submodulo === 'colectas') {

    if ($_accion === 'ver' && !empty($ruta[3])) {
        // GET /logistica-operativa/colectas/ver/{id}
        $parametros = [$ruta[3]]; // el ID de la colecta
        require __DIR__ . '/../vista/modulos/logistica_operativa/colectas/ver.php';
        exit;
    }

    // GET /logistica-operativa/colectas  (index, con filtros)
    require __DIR__ . '/../vista/modulos/logistica_operativa/colectas/index.php';
    exit;
}

// Sub-módulo no reconocido → 404
http_response_code(404);
require __DIR__ . '/../vista/modulos/404.php';
exit;

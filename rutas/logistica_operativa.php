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
 * Seguridad (en orden):
 *   1. Sesión activa (require_login vía require_permission).
 *   2. Permiso formal 'logistica_operativa_colectas' (tabla permisos, migración 022).
 *   3. LOGISTICA_OPERATIVA_ENABLED=true.
 *
 * Fase 4 implementada: permiso formal en BD.
 * @see database/migrations/022_create_logistica_operativa_permisos.sql
 * @see utils/logistica_permissions.php
 */

declare(strict_types=1);

// Solo actuar si el primer segmento es 'logistica-operativa'
if (!isset($ruta[0]) || $ruta[0] !== 'logistica-operativa') {
    return; // No es nuestra ruta
}

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/logistica_permissions.php';
require_once __DIR__ . '/../services/LogisticaOperativaFlags.php';

// ── 1. Autenticación + permiso formal ─────────────────────────────────────────
// require_permission() verifica sesión activa y el permiso 'logistica_operativa_colectas'
// en la tabla permisos (migración 022). Sin permiso → 302 /dashboard + flash error.
// Deny by default: si la consulta BD falla, deniega el acceso.
require_permission('logistica_operativa_colectas');

// ── 2. Feature flag ───────────────────────────────────────────────────────────
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

// ── Sub-módulo bodega (FASE 3D) ───────────────────────────────────────────────
// Delega a logistica_bodega.php para que maneje /logistica-operativa/bodega
if ($_submodulo === 'bodega') {
    require __DIR__ . '/logistica_bodega.php';
    exit;
}

// ── Sub-módulo rutas (FASE 5) ────────────────────────────────────────────────
if ($_submodulo === 'rutas') {
    require_permission('logistica_operativa_rutas');

    if ($_accion === 'crear') {
        require __DIR__ . '/../vista/modulos/logistica_operativa/rutas/crear.php';
        exit;
    }
    if ($_accion === 'ver' && !empty($ruta[3])) {
        $parametros = [$ruta[3]];
        require __DIR__ . '/../vista/modulos/logistica_operativa/rutas/ver.php';
        exit;
    }
    require __DIR__ . '/../vista/modulos/logistica_operativa/rutas/index.php';
    exit;
}

// ── Sub-módulos de Configuración / Maestros ──────────────────────────────────
if ($_submodulo === 'bodegas') {
    require_permission('admin');
    require __DIR__ . '/../vista/modulos/logistica_operativa/bodegas/index.php';
    exit;
}

if ($_submodulo === 'zonas') {
    require_permission('admin');
    require __DIR__ . '/../vista/modulos/logistica_operativa/zonas/index.php';
    exit;
}

if ($_submodulo === 'repartidores') {
    require_permission('admin');
    require __DIR__ . '/../vista/modulos/logistica_operativa/repartidores/index.php';
    exit;
}

if ($_submodulo === 'etiquetas') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/etiquetas/index.php';
    exit;
}

if ($_submodulo === 'liquidaciones') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/liquidaciones/index.php';
    exit;
}

if ($_submodulo === 'campo') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/campo/index.php';
    exit;
}

if ($_submodulo === 'custodias') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/custodias/index.php';
    exit;
}

if ($_submodulo === 'devoluciones') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/devoluciones/index.php';
    exit;
}

if ($_submodulo === 'dashboard') {
    require __DIR__ . '/../vista/modulos/logistica_operativa/dashboard/index.php';
    exit;
}

// Sub-módulo no reconocido → 404
http_response_code(404);
require __DIR__ . '/../vista/modulos/404.php';
exit;

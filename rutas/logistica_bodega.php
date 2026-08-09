<?php
/**
 * rutas/logistica_bodega.php
 *
 * Manejador de rutas web internas para el sub-módulo Bodega de Logística Operativa.
 *
 * Rutas registradas:
 *   GET /logistica-operativa/bodega  → panel operativo de bodega
 *
 * Seguridad (en orden):
 *   1. Sesión activa (require_login vía require_permission).
 *   2. Usuario activo (si el dato está disponible en sesión).
 *   3. Permiso formal 'logistica_operativa_bodega' (tabla permisos, migración 022).
 *   4. LOGISTICA_OPERATIVA_ENABLED=true.
 *   5. LOGISTICA_OPERATIVA_SHADOW_MODE=true.
 *   6. Renderizado de la vista.
 *
 * Fase 4 implementada: permiso formal en BD.
 * @see database/migrations/022_create_logistica_operativa_permisos.sql
 * @see utils/logistica_permissions.php
 *
 * No agrega enlace al menú principal (header.php lo gestiona con current_user_has_permission).
 */

declare(strict_types=1);

// Protección cuando el archivo se incluye directamente desde web.php
// (no cuando viene delegado desde logistica_operativa.php, que ya verificó la ruta).
// Solo actuar si la ruta coincide con /logistica-operativa/bodega.
// Si ya fue delegado, $_bodegaDelegado estará definido y saltamos esta guarda.
if (!isset($_bodegaDelegado)) {
    if (!isset($ruta[0], $ruta[1]) || $ruta[0] !== 'logistica-operativa' || $ruta[1] !== 'bodega') {
        return; // No es nuestra ruta
    }
}

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/logistica_permissions.php';
require_once __DIR__ . '/../services/LogisticaOperativaFlags.php';

// ── 1. Autenticación + permiso formal ─────────────────────────────────────────
// require_permission() llama internamente a require_login() primero.
// Sin sesión → 302 /login. Sin permiso → 302 /dashboard + flash error.
require_permission('logistica_operativa_bodega');

// ── 2. Usuario activo (si disponible en sesión) ─────────────────────────────
// Los datos de 'activo' no siempre están en sesión; solo se bloquea si
// explícitamente consta como inactivo.
if (isset($_SESSION['activo']) && !(bool) $_SESSION['activo']) {
    set_flash('error', 'Tu cuenta está desactivada. Contacta al administrador.');
    header('Location: ' . RUTA_URL . 'login');
    exit;
}

// ── 4. Feature flag: módulo habilitado ────────────────────────────────────────
if (!LogisticaOperativaFlags::enabled()) {
    set_flash('error', 'El módulo Logística Operativa no está habilitado actualmente.');
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── 5. Feature flag: shadow mode activo ──────────────────────────────────────
if (!LogisticaOperativaFlags::shadowMode()) {
    set_flash('error', 'El módulo de Bodega requiere que el modo sombra esté activo.');
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── 6. Renderizar vista ───────────────────────────────────────────────────────
require __DIR__ . '/../vista/modulos/logistica_operativa/bodega/index.php';
exit;

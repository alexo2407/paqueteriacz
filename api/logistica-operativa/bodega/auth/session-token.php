<?php
/**
 * GET /api/logistica-operativa/bodega/auth/session-token
 *
 * Bridge seguro: convierte una sesión PHP activa y autorizada en un JWT
 * de vida corta (15 minutos) para que la vista web interna pueda consumir
 * los endpoints protegidos de bodega sin exponer credenciales.
 *
 * Seguridad:
 *   - No acepta credenciales en el body.
 *   - Requiere cookie de sesión válida (HttpOnly, SameSite=Lax).
 *   - Verifica sesión activa, usuario activo, autorización y flags.
 *   - El JWT generado tiene exp corto (15 min) y solo sirve para bodega.
 *   - No devuelve el JWT en ningún header; solo en el body JSON.
 *   - No almacena el token en la sesión (stateless).
 *
 * Respuestas:
 *   200 → { success: true, data: { token: "...", expires_in: 900 } }
 *   401 → sesión inválida o usuario sin sesión
 *   403 → sin autorización o módulo deshabilitado
 *   405 → método no GET
 *   500 → error interno
 *
 * Fase 4 implementada: usa el permiso formal 'logistica_operativa_bodega'.
 * @see utils/logistica_permissions.php
 * @see database/migrations/022_create_logistica_operativa_permisos.sql
 */

declare(strict_types=1);

use Firebase\JWT\JWT;

header('Content-Type: application/json; charset=utf-8');

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Método no permitido.']);
    exit;
}

try {
    require_once __DIR__ . '/../../../../config/config.php';
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    require_once __DIR__ . '/../../../../utils/session.php';
    require_once __DIR__ . '/../../../../utils/permissions.php';
    require_once __DIR__ . '/../../../../utils/logistica_permissions.php';
    require_once __DIR__ . '/../../../../services/LogisticaOperativaFlags.php';

    // ── 1. Sesión activa ──────────────────────────────────────────────────────
    start_secure_session();

    $userId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? null;
    if (empty($userId)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Se requiere sesión activa.']);
        exit;
    }

    // ── 2. Autorización: permiso formal 'logistica_operativa_bodega' ────────────────
    // Construimos un array $usuario compatible con api_require_permission().
    // El campo 'rol' debe ser el ID de rol principal del usuario.
    $rolId = (int) ($_SESSION['rol'] ?? (is_array($_SESSION['roles'] ?? null) && !empty($_SESSION['roles'])
        ? (int) ($_SESSION['roles'][0])
        : 0));

    $usuarioSimulado = ['id' => (int) $userId, 'nombre' => $_SESSION['nombre'] ?? '', 'rol' => $rolId];

    // api_require_permission() responde 403 JSON y sale si no tiene permiso.
    // Deny by default: si la consulta BD falla → 403.
    api_require_permission('logistica_operativa_bodega', $usuarioSimulado);

    // ── 3. Feature flags ──────────────────────────────────────────────────────
    if (!LogisticaOperativaFlags::enabled()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'MODULE_DISABLED', 'message' => 'El módulo Logística Operativa no está habilitado.']);
        exit;
    }
    if (!LogisticaOperativaFlags::shadowMode()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'SHADOW_MODE_REQUIRED', 'message' => 'El modo sombra debe estar activo.']);
        exit;
    }

    // ── 4. Obtener rol principal del usuario de sesión ────────────────────────
    $rolId = $_SESSION['rol'] ?? (is_array($_SESSION['roles'] ?? null) && !empty($_SESSION['roles'])
        ? (int) ($_SESSION['roles'][0])
        : 1);

    // ── 5. Generar JWT de vida corta (15 minutos) ─────────────────────────────
    $expiresIn = 900; // 15 minutos
    $payload   = [
        'iss'  => defined('APP_URL') ? APP_URL : 'paqueteriacz',
        'aud'  => 'logistica_bodega_ui',
        'iat'  => time(),
        'exp'  => time() + $expiresIn,
        'data' => [
            'id'     => (int) $userId,
            'nombre' => $_SESSION['nombre'] ?? '',
            'rol'    => (int) $rolId,
            'scope'  => 'logistica_operativa_bodega', // scope interno, no validado en endpoints
        ],
    ];

    $token = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data'    => [
            'token'      => $token,
            'expires_in' => $expiresIn,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[session-token] Error interno: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}

<?php
/**
 * api/utils/set_timezone.php
 *
 * Endpoint que recibe la timezone detectada por el navegador (via JS Intl API)
 * y la guarda en la sesión del usuario. Se llama automáticamente en el login.
 *
 * POST { "timezone": "America/Managua" }
 * Responde: { "ok": true }
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

start_secure_session();

// Solo se acepta si hay sesión activa
if (empty($_SESSION['registrado'])) {
    // También permitir sin sesión (para guardar antes del redirect post-login)
    // En ese caso guardamos en sesión igualmente
}

$input = json_decode(file_get_contents('php://input'), true);
$tz = $input['timezone'] ?? ($_POST['timezone'] ?? null);

// Validar que sea una timezone real de PHP
if ($tz && @timezone_open($tz) !== false) {
    $_SESSION['user_timezone'] = $tz;
    echo json_encode(['ok' => true, 'timezone' => $tz]);
} else {
    // Fallback: guardar UTC si viene algo inválido
    $_SESSION['user_timezone'] = 'UTC';
    echo json_encode(['ok' => false, 'error' => 'Timezone inválida, se usará UTC', 'timezone' => 'UTC']);
}

<?php
// api/crm/session_debug.php
// Endpoint de diagnóstico temporal para verificar sesión
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/session.php';

start_secure_session();

$debug = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => [
        'registrado' => $_SESSION['registrado'] ?? 'NO SET',
        'user_id' => $_SESSION['user_id'] ?? 'NO SET',
        'idUsuario' => $_SESSION['idUsuario'] ?? 'NO SET',
        'id' => $_SESSION['id'] ?? 'NO SET',
        'roles_nombres' => $_SESSION['roles_nombres'] ?? 'NO SET',
        'rol' => $_SESSION['rol'] ?? 'NO SET'
    ],
    'cookies' => $_COOKIE,
    'server' => [
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'NO SET',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'NO SET',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NO SET'
    ]
];

echo json_encode($debug, JSON_PRETTY_PRINT);

<?php
// Sesión segura y helpers

function start_secure_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar cookie params seguros
        // Detectar si estamos en HTTPS (incluyendo detrás de proxy/CDN)
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $httponly = true;
        $samesite = 'Strict'; // Cambiado de 'Lax' a 'Strict' para mayor seguridad

        // Configuración de cookies de sesión con flags de seguridad
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);

        session_start();
        
        // Regenerar id de sesión para evitar fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['last_activity'] = time();
        }
        
        // Check for session timeout (30 minutes of inactivity)
        $timeout = 1800; // 30 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            // Session expired due to inactivity
            if (file_exists(__DIR__ . '/Logger.php')) {
                require_once __DIR__ . '/Logger.php';
                Logger::security('Session timeout', [
                    'user_id' => $_SESSION['id'] ?? null,
                    'last_activity' => $_SESSION['last_activity']
                ]);
            }
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically (every 30 minutes)
        if (!isset($_SESSION['last_regenerate'])) {
            $_SESSION['last_regenerate'] = time();
        } elseif (time() - $_SESSION['last_regenerate'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regenerate'] = time();
        }
    }
}

function require_login()
{
    start_secure_session();
    if (empty($_SESSION['registrado'])) {
        // Redirigir al login
        $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
        header('Location: ' . $loginUrl);
        exit;
    }
}

function require_role($roles)
{
    start_secure_session();
    if (empty($_SESSION['registrado'])) {
        $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
        header('Location: ' . $loginUrl);
        exit;
    }

    $userRole = $_SESSION['rol'] ?? null;
    if (is_array($roles)) {
        if (!in_array($userRole, $roles)) {
            $homeUrl = defined('RUTA_URL') ? RUTA_URL : 'index.php';
            header('Location: ' . $homeUrl);
            exit;
        }
    } else {
        if ($userRole !== $roles) {
            $homeUrl = defined('RUTA_URL') ? RUTA_URL : 'index.php';
            header('Location: ' . $homeUrl);
            exit;
        }
    }
}

// Helpers para multi-rol por nombre
function user_has_any_role_names(array $names): bool
{
    start_secure_session();
    $userNames = $_SESSION['roles_nombres'] ?? [];
    if (!is_array($userNames)) $userNames = [];
    return count(array_intersect($names, $userNames)) > 0;
}

function require_role_name($names)
{
    start_secure_session();
    if (empty($_SESSION['registrado'])) {
        $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
        header('Location: ' . $loginUrl);
        exit;
    }
    $names = is_array($names) ? $names : [$names];
    if (!user_has_any_role_names($names)) {
        $homeUrl = defined('RUTA_URL') ? RUTA_URL . 'dashboard' : 'index.php?enlace=dashboard';
        header('Location: ' . $homeUrl);
        exit;
    }
}

function logout()
{
    // Garantiza que la sesión esté activa antes de limpiarla
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_unset();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
        }

        session_destroy();
    }
}

// Flash message helpers (simple session-based)
function set_flash($type, $message)
{
    start_secure_session();
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash()
{
    start_secure_session();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

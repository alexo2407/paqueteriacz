<?php
// Sesión segura y helpers

function start_secure_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar cookie params seguros
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $httponly = true;
        $samesite = 'Lax';

        // PHP < 7.3 compatibility: setcookie no soporta samesite directamente
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

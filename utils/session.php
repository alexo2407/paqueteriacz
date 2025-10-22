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
        header('Location: index.php?enlace=login');
        exit;
    }
}

function require_role($roles)
{
    start_secure_session();
    if (empty($_SESSION['registrado'])) {
        header('Location: index.php?enlace=login');
        exit;
    }

    $userRole = $_SESSION['rol'] ?? null;
    if (is_array($roles)) {
        if (!in_array($userRole, $roles)) {
            header('Location: index.php');
            exit;
        }
    } else {
        if ($userRole !== $roles) {
            header('Location: index.php');
            exit;
        }
    }
}

function logout()
{
    start_secure_session();
    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // Finally, destroy the session.
    session_destroy();
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

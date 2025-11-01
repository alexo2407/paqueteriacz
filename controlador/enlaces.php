<?php 
class EnlacesController
{
    public static function enlacesController()
    {
        // Obtén la URL solicitada
        $url = isset($_GET['enlace']) ? $_GET['enlace'] : "inicio";

        // Obtén el archivo y los parámetros desde el modelo
        $respuesta = EnlacesModel::enlacesModel($url);
        $archivo = $respuesta['archivo'];
        $parametros = $respuesta['parametros'];

        // Enforce active session for private modules
        $segmentos = explode('/', trim($url));
        $modulo = $segmentos[0] ?? 'inicio';
        $modulosPublicos = ['inicio', 'login', '', 'api'];

        if (!in_array($modulo, $modulosPublicos, true)) {
            require_once __DIR__ . '/../utils/session.php';
            require_login();
            // Hidratar datos de rol en sesión si faltan (compat con sesiones antiguas)
            require_once __DIR__ . '/../modelo/usuario.php';
            $um = new UsuarioModel();

            // 1) Asegurar rol_nombre si falta (a partir del id en sesión)
            if (empty($_SESSION['rol_nombre'])) {
                $rolesMap = $um->listarRoles(); // [id => nombre]
                $rid = $_SESSION['rol'] ?? null;
                if ($rid !== null && isset($rolesMap[$rid])) {
                    $_SESSION['rol_nombre'] = $rolesMap[$rid];
                }
            }

            // 2) Asegurar arrays multi-rol si faltan, usando pivot usuarios_roles
            if (empty($_SESSION['roles']) || empty($_SESSION['roles_nombres'])) {
                $uid = $_SESSION['user_id'] ?? null;
                if ($uid) {
                    $roles = $um->obtenerRolesDeUsuario((int)$uid);
                    if (!empty($roles['ids'])) {
                        $_SESSION['roles'] = array_values(array_unique(array_map('intval', $roles['ids'])));
                    }
                    if (!empty($roles['nombres'])) {
                        $_SESSION['roles_nombres'] = array_values(array_unique(array_filter($roles['nombres'])));
                    }
                    // Si aún falta rol y tenemos al menos un id/nombre, setear principales
                    if (empty($_SESSION['rol']) && !empty($_SESSION['roles'])) {
                        $_SESSION['rol'] = (int)$_SESSION['roles'][0];
                    }
                    if (empty($_SESSION['rol_nombre']) && !empty($_SESSION['roles_nombres'])) {
                        $_SESSION['rol_nombre'] = $_SESSION['roles_nombres'][0];
                    }
                }
            }

            // Políticas de acceso por módulo basadas en nombre de rol
            $allowedByModule = [
                'pedidos' => [ROL_NOMBRE_ADMIN],
                'usuarios' => [ROL_NOMBRE_ADMIN],
                'stock' => [ROL_NOMBRE_ADMIN],
                'seguimiento' => [ROL_NOMBRE_REPARTIDOR],
            ];

            $userRoleNames = $_SESSION['roles_nombres'] ?? [];
            $isAdmin = is_array($userRoleNames) && in_array(ROL_NOMBRE_ADMIN, $userRoleNames, true);

            if (!$isAdmin && isset($allowedByModule[$modulo])) {
                $permitidos = $allowedByModule[$modulo];
                if (!is_array($userRoleNames) || count(array_intersect($permitidos, $userRoleNames)) === 0) {
                    // Denegar acceso y redirigir con mensaje
                    set_flash('error', 'Acceso denegado para tu rol.');
                    $destino = defined('RUTA_URL') ? RUTA_URL . 'dashboard' : 'index.php?enlace=dashboard';
                    header('Location: ' . $destino);
                    exit;
                }
            }
        }

        // Incluye la vista correspondiente
        if (file_exists($archivo)) {
            // Los parámetros estarán disponibles en la vista
            include $archivo;
        } else {
            include "vista/modulos/404.php";
        }
    }
}


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
            // Asegurar que tengamos el nombre del rol en sesión para checks por vista
            if (empty($_SESSION['rol_nombre'])) {
                require_once __DIR__ . '/../modelo/usuario.php';
                $um = new UsuarioModel();
                $rolesMap = $um->listarRoles(); // [id => nombre]
                $rid = $_SESSION['rol'] ?? null;
                if ($rid !== null && isset($rolesMap[$rid])) {
                    $_SESSION['rol_nombre'] = $rolesMap[$rid];
                }
            }

            // Políticas de acceso por módulo basadas en nombre de rol
            $allowedByModule = [
                'pedidos' => [ROL_NOMBRE_ADMIN],
                'usuarios' => [ROL_NOMBRE_ADMIN],
                'stock' => [ROL_NOMBRE_ADMIN],
                'seguimiento' => [ROL_NOMBRE_REPARTIDOR],
            ];

            if (isset($allowedByModule[$modulo])) {
                $permitidos = $allowedByModule[$modulo];
                $userRoleNames = $_SESSION['roles_nombres'] ?? [];
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


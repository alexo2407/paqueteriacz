<?php 
/**
 * EnlacesController
 *
 * Controlador encargado de resolver la ruta/archivo que se debe incluir
 * para cada enlace (parámetro `enlace` en la URL). También aplica políticas
 * de acceso (sesiones y roles) para proteger módulos privados.
 *
 * Este controlador actúa como un pequeño dispatcher para el frontend
 * (vistas) y no debe contener lógica de negocio compleja; esa lógica debe
 * residir en los modelos.
 */
class EnlacesController
{
    /**
     * Resolver la ruta solicitada y cargar la vista correspondiente.
     *
     * - Extrae el parámetro `enlace` de la query string.
     * - Consulta al modelo para obtener el archivo y parámetros.
     * - Aplica comprobaciones de sesión/rol para módulos privados.
     * - Incluye la vista o muestra 404 si no existe.
     *
     * @return void
     */
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
            start_secure_session();

            // Detectar petición AJAX (fetch/XHR) para devolver JSON en vez de redirigir
            $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            // Si no está autenticado y es AJAX, responder 401 con JSON
            if (empty($_SESSION['registrado']) && $isAjaxRequest) {
                header('Content-Type: application/json', true, 401);
                echo json_encode(['success' => false, 'message' => 'No autenticado. Inicia sesión.']);
                exit;
            }

            // Para peticiones normales, usar el comportamiento histórico (redirigir al login)
            if (empty($_SESSION['registrado'])) {
                require_login();
            }
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


<?php

class UsuariosController
{
    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    public static function mostrarUsuariosController()
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        $repuesta = $model->mostrarUsuarios();

        return $repuesta;
    }

    public static function obtenerRolesDisponibles()
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        return $model->listarRoles();
    }

    public function verUsuario($id)
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        return $model->obtenerPorId($id);
    }

    public function actualizarUsuario($id, array $data)
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();

        // Separar campos de usuario de roles
        $roles = isset($data['roles']) && is_array($data['roles']) ? $data['roles'] : [];
        $primaryRoleId = isset($data['id_rol']) ? (int)$data['id_rol'] : 0;

        $userFields = $data;
        unset($userFields['roles']);

        $resUser = $model->actualizarUsuario($id, $userFields);
        if (empty($resUser['success'])) {
            return $resUser;
        }

        $changed = !empty($resUser['changed']);

        if (!empty($roles)) {
            $resRoles = $model->setRolesForUser($id, $roles, $primaryRoleId);
            if (empty($resRoles['success'])) {
                return $resRoles; // devolver error de roles si falló
            }
            $changed = true;
        }

        return [
            'success' => true,
            'changed' => $changed
        ];
    }

    /**
     * Procesa el login desde un formulario POST
     */
    public function login()
    {
        // Iniciar sesión si no está iniciada
        require_once __DIR__ . '/../utils/session.php';
        start_secure_session();

        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $password = isset($_POST['password']) ? $_POST['password'] : null;

        if (!$email || !$password) {
            set_flash('error', 'Faltan parámetros');
            $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
            header('Location: ' . $loginUrl);
            exit;
        }

        // Usar el modelo de Usuario para verificar credenciales
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        $user = $model->verificarCredenciales($email, $password);

        if ($user) {
            // Guardar datos en sesión
            $_SESSION['registrado'] = true;
            $_SESSION['nombre'] = $user['Usuario'];
            $_SESSION['rol'] = $user['Rol']; // id_rol numérico
            $_SESSION['user_id'] = $user['ID_Usuario'];

            // Guardar también el nombre del rol para facilitar comprobaciones por vista
            try {
                $rolesMap = $model->listarRoles(); // [id => nombre]
                $roleId = (int)$user['Rol'];
                if (isset($rolesMap[$roleId])) {
                    $_SESSION['rol_nombre'] = $rolesMap[$roleId];
                }
                // Soporte multi-rol: guardar arrays con ids y nombres
                $roles = $user['Roles'] ?? null;
                $rolesNombres = $user['RolesNombres'] ?? null;
                if (is_array($roles)) {
                    $_SESSION['roles'] = array_values(array_unique(array_map('intval', $roles)));
                }
                if (is_array($rolesNombres)) {
                    $_SESSION['roles_nombres'] = array_values(array_unique(array_filter($rolesNombres)));
                    // Si rol_nombre no está definido, tomar el primero de nombres
                    if (empty($_SESSION['rol_nombre']) && !empty($_SESSION['roles_nombres'])) {
                        $_SESSION['rol_nombre'] = $_SESSION['roles_nombres'][0];
                    }
                }
            } catch (Exception $e) {
                // Silencioso: si falla, simplemente no seteamos rol_nombre
            }

            // Redirigir a dashboard
            set_flash('success', 'Bienvenido ' . ($user['Usuario'] ?? '')); 
            $dashboardUrl = defined('RUTA_URL') ? RUTA_URL . 'dashboard' : 'index.php?enlace=dashboard';
            header('Location: ' . $dashboardUrl);
            exit;
        } else {
            set_flash('error', 'Credenciales inválidas');
            $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
            header('Location: ' . $loginUrl);
            exit;
        }
    }

}

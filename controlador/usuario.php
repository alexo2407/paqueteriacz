<?php

/**
 * UsuariosController
 *
 * Controlador que expone operaciones relacionadas con usuarios: listado,
 * visualización, actualización y procesos de login para el frontend.
 * Todas las operaciones de persistencia se delegan a `UsuarioModel`.
 */
class UsuariosController
{
    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    /**
     * Retornar todos los usuarios registrados.
     * Delegado al modelo UsuarioModel::mostrarUsuarios.
     *
     * @return array Lista de usuarios o estructura vacía en caso de error.
     */
    public static function mostrarUsuariosController()
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        $repuesta = $model->mostrarUsuarios();

        return $repuesta;
    }

    /**
     * Obtener el mapa/lista de roles disponibles en el sistema.
     *
     * @return array Mapa id => nombre de rol
     */
    public static function obtenerRolesDisponibles()
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        return $model->listarRoles();
    }

    /**
     * Obtener la información de un usuario por id.
     *
     * @param int $id
     * @return array|null
     */
    public function verUsuario($id)
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();
        return $model->obtenerPorId($id);
    }

    /**
     * Actualiza un usuario y sus roles.
     *
     * - Actualiza campos de usuario mediante UsuarioModel::actualizarUsuario
     * - Si se envían roles, actualiza asignaciones con setRolesForUser
     *
     * @param int $id
     * @param array $data Campos del usuario y opcionalmente 'roles' => []
     * @return array Envelope con success y changed
     */
    public function actualizarUsuario($id, array $data)
    {
        require_once __DIR__ . '/../modelo/usuario.php';
        $model = new UsuarioModel();

        // Separar campos de usuario de roles
        $roles = isset($data['roles']) && is_array($data['roles']) ? $data['roles'] : [];

        $userFields = $data;
        unset($userFields['roles']);

        $resUser = $model->actualizarUsuario($id, $userFields);
        if (empty($resUser['success'])) {
            return $resUser;
        }

        $changed = !empty($resUser['changed']);

        if (!empty($roles)) {
            $resRoles = $model->setRolesForUser($id, $roles);
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
     * Procesar la creación de un nuevo usuario desde el formulario.
     */
    public function crearUsuarioController()
    {
        if (isset($_POST['nombre']) && isset($_POST['email']) && isset($_POST['password'])) {
            
            // Validaciones básicas
            if (empty($_POST['nombre']) || empty($_POST['email']) || empty($_POST['password'])) {
                echo '<script>
                    if (window.history.replaceState) {
                        window.history.replaceState(null, null, window.location.href);
                    }
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Todos los campos obligatorios deben ser completados.",
                        showConfirmButton: true,
                        confirmButtonText: "Cerrar"
                    });
                </script>';
                return;
            }

            $data = [
                'nombre' => $_POST['nombre'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'telefono' => $_POST['telefono'] ?? null,
                'id_pais' => $_POST['id_pais'] ?? null,
                'activo' => isset($_POST['activo']) ? 1 : 0,
                'id_estado' => $_POST['id_estado'] ?? null
            ];

            require_once __DIR__ . '/../modelo/usuario.php';
            $model = new UsuarioModel();
            
            $newId = $model->crearUsuario($data);

            if ($newId) {
                // Asignar roles si se seleccionaron
                if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
                    // Convertir a enteros
                    $rolesIds = array_map('intval', $_POST['roles']);
                    $model->setRolesForUser($newId, $rolesIds);
                } elseif (!empty($_POST['rol'])) {
                    // Fallback para compatibilidad si viniera como 'rol' simple
                    $model->setRolesForUser($newId, [(int)$_POST['rol']]);
                }

                echo '<script>
                    if (window.history.replaceState) {
                        window.history.replaceState(null, null, window.location.href);
                    }
                    Swal.fire({
                        icon: "success",
                        title: "¡Usuario creado!",
                        text: "El usuario ha sido registrado correctamente.",
                        showConfirmButton: true,
                        confirmButtonText: "Cerrar"
                    }).then(function(result){
                        if(result.value){
                            window.location = "'.RUTA_URL.'usuarios/listar";
                        }
                    });
                </script>';
            } else {
                echo '<script>
                    if (window.history.replaceState) {
                        window.history.replaceState(null, null, window.location.href);
                    }
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se pudo crear el usuario. Verifique si el email ya existe.",
                        showConfirmButton: true,
                        confirmButtonText: "Cerrar"
                    });
                </script>';
            }
        }
    }

    /**
     * Procesa el login desde un formulario POST
     */
    /**
     * Procesar el login desde el formulario del frontend.
     *
     * - Valida parámetros POST (email, password), verifica credenciales con
     *   UsuarioModel::verificarCredenciales y establece la sesión.
     * - Redirige a dashboard en caso de éxito o vuelve a login con flash en fallo.
     *
     * @return void
     */
    public function login()
    {
        // Iniciar sesión si no está iniciada
        require_once __DIR__ . '/../utils/session.php';
        start_secure_session();

        // CSRF Protection
        require_once __DIR__ . '/../utils/csrf.php';
        require_csrf_token($_POST['csrf_token'] ?? null);

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
            // id numérico principal (primer rol si existe)
            $_SESSION['rol'] = is_array($user['Roles']) && !empty($user['Roles']) ? (int)$user['Roles'][0] : ($user['Rol'] ?? null);
            $_SESSION['user_id'] = $user['ID_Usuario'];

            // Guardar también el nombre del rol para facilitar comprobaciones por vista
            try {
                $rolesMap = $model->listarRoles(); // [id => nombre]
                $roleId = $_SESSION['rol'] ?? null;
                if ($roleId !== null && isset($rolesMap[$roleId])) {
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

            // Limpiar posibles errores de login previos
            unset($_SESSION['login_error']);

            // Determinar la URL de redirección según el rol del usuario
            $rolesNombres = $_SESSION['roles_nombres'] ?? [];
            $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
            $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
            
            // Si es repartidor (y no es admin), redirigir a su página de seguimiento
            if ($isRepartidor && !$isAdmin) {
                set_flash('success', 'Bienvenido ' . ($user['Usuario'] ?? '')); 
                $redirectUrl = defined('RUTA_URL') ? RUTA_URL . 'seguimiento/listar' : 'index.php?enlace=seguimiento/listar';
                header('Location: ' . $redirectUrl);
                exit;
            }
            
            // Para otros roles, redirigir a dashboard
            set_flash('success', 'Bienvenido ' . ($user['Usuario'] ?? '')); 
            $dashboardUrl = defined('RUTA_URL') ? RUTA_URL . 'dashboard' : 'index.php?enlace=dashboard';
            header('Location: ' . $dashboardUrl);
            exit;
        } else {
            set_flash('error', 'Credenciales inválidas');
            // También colocar el error clásico para la vista de inicio de sesión
            $_SESSION['login_error'] = 'Credenciales inválidas';
            $loginUrl = defined('RUTA_URL') ? RUTA_URL . 'login' : 'index.php?enlace=login';
            header('Location: ' . $loginUrl);
            exit;
        }
    }

}

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
            header('Location: index.php?enlace=login');
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
            $_SESSION['rol'] = $user['Rol'];

            // Redirigir a dashboard
            set_flash('success', 'Bienvenido ' . ($user['Usuario'] ?? '')); 
            header('Location: index.php?enlace=dashboard');
            exit;
        } else {
            set_flash('error', 'Credenciales inválidas');
            header('Location: index.php?enlace=login');
            exit;
        }
    }

}

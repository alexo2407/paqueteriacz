<?php

require_once __DIR__ . '/../modelo/password_reset.php';
require_once __DIR__ . '/../modelo/usuario.php';
require_once __DIR__ . '/../utils/mailer.php';
require_once __DIR__ . '/../utils/session.php';

/**
 * PasswordResetController
 * 
 * Controlador para gestionar el flujo de recuperación de contraseña
 */
class PasswordResetController {
    
    private $model;
    private $userModel;
    
    public function __construct() {
        $this->model = new PasswordResetModel();
        $this->userModel = new UsuarioModel();
    }
    
    /**
     * Procesar solicitud de recuperación de contraseña
     * Genera token y envía email
     */
    public function solicitarRecuperacion() {
        start_secure_session();
        
       // CSRF Protection
        require_once __DIR__ . '/../utils/csrf.php';
        require_csrf_token($_POST['csrf_token'] ?? null);
        
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Por favor ingresa un email válido.');
            header('Location: ' . RUTA_URL . 'recuperar-password');
            exit;
        }
        
        // Verificar si el email existe (sin revelar info)
        $emailExiste = $this->model->emailExiste($email);
        
        if ($emailExiste) {
            // Generar token
            $resultado = $this->model->crearToken($email);
            
            if ($resultado['success']) {
                // Enviar email
                $envio = Mailer::enviarEmailRecuperacion($email, $resultado['token']);
                
                if (!$envio['success']) {
                    error_log('Error al enviar email de recuperación: ' . $envio['message'], 3, __DIR__ . '/../logs/errors.log');
                    // No mostrar error específico al usuario por seguridad
                }
            }
        }
        
        // Mensaje genérico (no revela si el email existe o no)
        set_flash('success', 'Si el email existe en nuestro sistema, recibirás un enlace de recuperación en breve.');
        header('Location: ' . RUTA_URL . 'login');
        exit;
    }
    
    /**
     * Mostrar formulario de reset con token
     * 
     * @param string|null $token Token de recuperación
     */
    public function mostrarFormularioReset($token = null) {
        if (empty($token)) {
            set_flash('error', 'Token de recuperación inválido.');
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }
        
        // Validar token
        $validacion = $this->model->validarToken($token);
        
        if (!$validacion['valid']) {
            set_flash('error', $validacion['message']);
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }
        
        // Token válido - la vista se cargará desde enlaces.php
        return [
            'token' => $token,
            'email' => $validacion['email']
        ];
    }
    
    /**
     * Procesar nueva contraseña
     */
    public function procesarReset() {
        start_secure_session();
        
        // CSRF Protection
        require_once __DIR__ . '/../utils/csrf.php';
        require_csrf_token($_POST['csrf_token'] ?? null);
        
        $token = isset($_POST['token']) ? trim($_POST['token']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $passwordConfirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        
        // Validaciones
        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            set_flash('error', 'Todos los campos son obligatorios.');
            header('Location: ' . RUTA_URL . 'reset-password?token=' . urlencode($token));
            exit;
        }
        
        if ($password !== $passwordConfirm) {
            set_flash('error', 'Las contraseñas no coinciden.');
            header('Location: ' . RUTA_URL . 'reset-password?token=' . urlencode($token));
            exit;
        }
        
        if (strlen($password) < 6) {
            set_flash('error', 'La contraseña debe tener al menos 6 caracteres.');
            header('Location: ' . RUTA_URL . 'reset-password?token=' . urlencode($token));
            exit;
        }
        
        // Validar token nuevamente
        $validacion = $this->model->validarToken($token);
        
        if (!$validacion['valid']) {
            set_flash('error', $validacion['message']);
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }
        
        $email = $validacion['email'];
        
        // Obtener usuario por email
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = :email');
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                set_flash('error', 'Usuario no encontrado.');
                header('Location: ' . RUTA_URL . 'login');
                exit;
            }
            
            // Actualizar contraseña
            $resultado = $this->userModel->actualizarUsuario($user['id'], [
                'contrasena' => $password
            ]);
            
            if ($resultado['success']) {
                // Marcar token como usado
                $this->model->marcarComoUsado($token);
                
                // Limpiar tokens expirados
                $this->model->limpiarTokensExpirados();
                
                set_flash('success', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
                header('Location: ' . RUTA_URL . 'login');
                exit;
            } else {
                set_flash('error', 'No se pudo actualizar la contraseña. Intenta nuevamente.');
                header('Location: ' . RUTA_URL . 'reset-password?token=' . urlencode($token));
                exit;
            }
            
        } catch (PDOException $e) {
            error_log('Error al actualizar contraseña: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            set_flash('error', 'Ocurrió un error. Por favor intenta nuevamente.');
            header('Location: ' . RUTA_URL . 'reset-password?token=' . urlencode($token));
            exit;
        }
    }
}

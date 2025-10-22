<?php

include_once __DIR__ . '/conexion.php';


class UsuarioModel {

    public function verificarCredenciales($email, $password) {

        $db = (new Conexion())->conectar();

        $sql = "SELECT id, nombre, id_rol, contrasena FROM usuarios WHERE email = :email";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $email);

        if (!$stmt->execute()) {
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe el usuario
        if (!$user) {
            return false;
        }

        // Verificar contraseña (si está hasheada con password_hash)
        if (isset($user['contrasena']) && password_verify($password, $user['contrasena'])) {
            return [
                'ID_Usuario' => $user['id'],
                'Usuario' => $user['nombre'],
                'Rol' => $user['id_rol']
            ];
        }

        // Fallback: migración automática si la DB contiene contraseñas en texto plano
        if (defined('ALLOW_PLAINTEXT_MIGRATION') && ALLOW_PLAINTEXT_MIGRATION === true) {
            // Si el valor en la BD coincide exactamente con la contraseña suministrada,
            // asumimos que la contraseña estaba en texto plano y la migramos a hash.
            if (isset($user['contrasena']) && $user['contrasena'] === $password) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $update = $db->prepare("UPDATE usuarios SET contrasena = :hash WHERE id = :id");
                    $update->bindParam(':hash', $newHash);
                    $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
                    $update->execute();
                } catch (PDOException $e) {
                    error_log('Error al migrar contraseña: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
                }

                return [
                    'ID_Usuario' => $user['id'],
                    'Usuario' => $user['nombre'],
                    'Rol' => $user['id_rol']
                ];
            }
        }

        // Contraseña no válida
        return false;
    }

    /**
     * Obtener todos los usuarios (sin contraseñas)
     */
    public function mostrarUsuarios()
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT id, nombre, email, id_rol FROM usuarios";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener usuarios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
}

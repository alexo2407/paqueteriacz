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
            $sql = "SELECT 
                        u.id,
                        u.nombre,
                        u.email,
                        u.id_rol,
                        NULL AS fecha_creacion,
                        CASE u.id_rol
                            WHEN 1 THEN 'Administrador'
                            WHEN 2 THEN 'Vendedor'
                            WHEN 3 THEN 'Supervisor'
                            ELSE 'Usuario'
                        END AS rol_nombre
                    FROM usuarios u";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener usuarios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, telefono, email, id_rol FROM usuarios WHERE id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener usuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public function actualizarUsuario($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();

            $fields = [];
            $params = [];

            if (isset($data['nombre'])) {
                $fields[] = 'nombre = :nombre';
                $params[':nombre'] = [$data['nombre'], PDO::PARAM_STR];
            }

            if (isset($data['email'])) {
                $fields[] = 'email = :email';
                $params[':email'] = [$data['email'], PDO::PARAM_STR];
            }

            if (array_key_exists('telefono', $data)) {
                $telefono = $data['telefono'];
                $fields[] = 'telefono = :telefono';
                if ($telefono === null || $telefono === '') {
                    $params[':telefono'] = [null, PDO::PARAM_NULL];
                } else {
                    $params[':telefono'] = [$telefono, PDO::PARAM_STR];
                }
            }

            if (isset($data['id_rol'])) {
                $fields[] = 'id_rol = :id_rol';
                $params[':id_rol'] = [(int) $data['id_rol'], PDO::PARAM_INT];
            }

            if (!empty($data['contrasena'])) {
                $fields[] = 'contrasena = :contrasena';
                $params[':contrasena'] = [password_hash($data['contrasena'], PASSWORD_DEFAULT), PDO::PARAM_STR];
            }

            if (empty($fields)) {
                return [
                    'success' => false,
                    'message' => 'No se proporcionaron datos para actualizar.'
                ];
            }

            $sql = 'UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);

            foreach ($params as $param => [$value, $type]) {
                $stmt->bindValue($param, $value, $type);
            }

            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            $ok = $stmt->execute();
            if (!$ok) {
                return [
                    'success' => false,
                    'message' => 'No fue posible actualizar el usuario.'
                ];
            }

            return [
                'success' => true,
                'changed' => $stmt->rowCount() > 0
            ];
        } catch (PDOException $e) {
            error_log('Error al actualizar usuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [
                'success' => false,
                'message' => 'Se produjo un error al actualizar el usuario.'
            ];
        }
    }
}

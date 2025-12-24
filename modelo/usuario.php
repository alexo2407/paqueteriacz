<?php

include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';

/**
 * UsuarioModel
 *
 * Encapsula operaciones sobre la tabla `usuarios` y tablas relacionadas
 * (usuarios_roles, roles, etc.). Provee utilidades para autenticación,
 * gestión de roles y CRUD de usuarios.
 */
class UsuarioModel {
    /**
     * Verificar credenciales de un usuario.
     *
     * - Devuelve un array con datos del usuario y roles cuando la autenticación es exitosa.
     * - Soporta migración de contraseñas (texto plano / MD5 / SHA1) cuando ALLOW_PLAINTEXT_MIGRATION está activado.
     *
     * @param string $email
     * @param string $password
     * @return array|false Array con claves ID_Usuario, Usuario, Rol, Roles, RolesNombres o false si falla.
     */
    public function verificarCredenciales($email, $password) {

        $db = (new Conexion())->conectar();

    // No dependas de usuarios.id_rol: los roles se obtienen exclusivamente desde usuarios_roles
    $sql = "SELECT id, nombre, contrasena FROM usuarios WHERE email = :email";
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
            $roles = $this->obtenerRolesDeUsuario((int)$user['id']);
            return [
                'ID_Usuario' => $user['id'],
                'Usuario' => $user['nombre'],
                'Rol' => $roles['ids'][0] ?? null, // compat: primer rol
                'Roles' => $roles['ids'],
                'RolesNombres' => $roles['nombres']
            ];
        }

        // Fallbacks de migración: texto plano, MD5 o SHA1 -> password_hash
        if (defined('ALLOW_PLAINTEXT_MIGRATION') && ALLOW_PLAINTEXT_MIGRATION === true) {
            $stored = $user['contrasena'] ?? '';
            $shouldMigrate = false;

            // Texto plano
            if ($stored === $password) {
                $shouldMigrate = true;
            } else {
                // MD5 (32 hex)
                $isMd5 = (bool) preg_match('/^[a-f0-9]{32}$/i', $stored);
                if ($isMd5 && strtolower($stored) === md5($password)) {
                    $shouldMigrate = true;
                }
                // SHA1 (40 hex)
                $isSha1 = (bool) preg_match('/^[a-f0-9]{40}$/i', $stored);
                if (!$shouldMigrate && $isSha1 && strtolower($stored) === sha1($password)) {
                    $shouldMigrate = true;
                }
            }

            if ($shouldMigrate) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $update = $db->prepare("UPDATE usuarios SET contrasena = :hash WHERE id = :id");
                    $update->bindParam(':hash', $newHash);
                    $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
                    $update->execute();
                } catch (PDOException $e) {
                    error_log('Error al migrar contraseña: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
                }

                $roles = $this->obtenerRolesDeUsuario((int)$user['id']);
                return [
                    'ID_Usuario' => $user['id'],
                    'Usuario' => $user['nombre'],
                    'Rol' => $roles['ids'][0] ?? null,
                    'Roles' => $roles['ids'],
                    'RolesNombres' => $roles['nombres']
                ];
            }
        }

        // Contraseña no válida
        return false;
    }

    /**
     * Obtener todos los usuarios (sin contraseñas)
     */
    /**
     * Obtener lista de usuarios (sin contraseñas).
     * @return array Lista de usuarios como arrays asociativos.
     */
    public function mostrarUsuarios()
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT 
                        u.id,
                        u.nombre,
                        u.email,
                        u.telefono,
                        u.id_pais,
                        u.activo,
                        u.created_at,
                        p.nombre AS pais_nombre
                    FROM usuarios u
                    LEFT JOIN paises p ON p.id = u.id_pais";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener usuarios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener usuario por id.
     * @param int $id
     * @return array|null
     */
    public function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            // Evitar seleccionar u.id_rol: esquema migrado a pivot usuarios_roles
            $stmt = $db->prepare('SELECT u.id, u.nombre, u.telefono, u.email, u.id_pais, u.activo, u.id_estado FROM usuarios u WHERE u.id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener usuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar campos de un usuario.
     *
     * Acepta un array con campos opcionales (nombre, email, telefono, id_pais, activo, id_estado, contrasena).
     * Retorna ['success'=>bool, 'changed'=>bool] o un arreglo de error.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function actualizarUsuario($id, array $data)
    {
        try {
            // Obtener datos anteriores
            $datosAnteriores = self::obtenerPorId($id);
            
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

            // id_rol eliminado: roles se gestionan solo en usuarios_roles

            if (array_key_exists('id_pais', $data)) {
                $fields[] = 'id_pais = :id_pais';
                if ($data['id_pais'] === null || $data['id_pais'] === '') {
                    $params[':id_pais'] = [null, PDO::PARAM_NULL];
                } else {
                    $params[':id_pais'] = [(int) $data['id_pais'], PDO::PARAM_INT];
                }
            }

            if (array_key_exists('activo', $data)) {
                $fields[] = 'activo = :activo';
                $params[':activo'] = [!empty($data['activo']) ? 1 : 0, PDO::PARAM_INT];
            }

            if (array_key_exists('id_estado', $data)) {
                $fields[] = 'id_estado = :id_estado';
                if ($data['id_estado'] === null || $data['id_estado'] === '') {
                    $params[':id_estado'] = [null, PDO::PARAM_NULL];
                } else {
                    $params[':id_estado'] = [(int) $data['id_estado'], PDO::PARAM_INT];
                }
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

            // Registrar auditoría (sin incluir contraseña en datos nuevos)
            $datosNuevos = $data;
            unset($datosNuevos['contrasena']);
            if (!empty($data['contrasena'])) {
                $datosNuevos['contrasena_cambiada'] = true;
            }
            AuditoriaModel::registrar(
                'usuarios',
                $id,
                'actualizar',
                AuditoriaModel::getIdUsuarioActual(),
                $datosAnteriores,
                $datosNuevos
            );

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

    /**
     * Crear un nuevo usuario.
     *
     * @param array $data Datos del usuario (nombre, email, password, telefono, id_pais, activo, id_estado)
     * @return int|null ID del nuevo usuario o null en caso de error.
     */
    public function crearUsuario(array $data)
    {
        try {
            $db = (new Conexion())->conectar();

            $sql = "INSERT INTO usuarios (nombre, email, contrasena, telefono, id_pais, activo, id_estado, created_at) 
                    VALUES (:nombre, :email, :contrasena, :telefono, :id_pais, :activo, :id_estado, NOW())";
            
            $stmt = $db->prepare($sql);

            $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':contrasena', password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
            
            $telefono = !empty($data['telefono']) ? $data['telefono'] : null;
            $stmt->bindValue(':telefono', $telefono, $telefono ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $idPais = !empty($data['id_pais']) ? (int)$data['id_pais'] : null;
            $stmt->bindValue(':id_pais', $idPais, $idPais ? PDO::PARAM_INT : PDO::PARAM_NULL);

            $activo = isset($data['activo']) ? (int)$data['activo'] : 1; // Default activo
            $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);

            $idEstado = !empty($data['id_estado']) ? (int)$data['id_estado'] : null;
            $stmt->bindValue(':id_estado', $idEstado, $idEstado ? PDO::PARAM_INT : PDO::PARAM_NULL);

            if ($stmt->execute()) {
                $nuevoId = (int)$db->lastInsertId();
                
                // Registrar auditoría (sin incluir contraseña)
                $datosNuevos = $data;
                unset($datosNuevos['password']);
                AuditoriaModel::registrar(
                    'usuarios',
                    $nuevoId,
                    'crear',
                    AuditoriaModel::getIdUsuarioActual(),
                    null,
                    $datosNuevos
                );
                
                return $nuevoId;
            }
            return null;
        } catch (PDOException $e) {
            error_log('Error al crear usuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Establecer los roles de un usuario (reemplaza todos por los proporcionados).
     * Opcionalmente también actualiza el rol principal (usuarios.id_rol) si $primaryRoleId > 0.
     */
    public function setRolesForUser(int $userId, array $roleIds, int $primaryRoleId = 0): array
    {
        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            // Limpiar actuales
            $del = $db->prepare('DELETE FROM usuarios_roles WHERE id_usuario = :uid');
            $del->bindValue(':uid', $userId, PDO::PARAM_INT);
            $del->execute();

            // Insertar nuevos (únicos)
            $ins = $db->prepare('INSERT INTO usuarios_roles (id_usuario, id_rol) VALUES (:uid, :rid)');
            $added = 0;
            $unique = array_values(array_unique(array_map('intval', $roleIds)));
            foreach ($unique as $rid) {
                if ($rid <= 0) continue;
                $ins->bindValue(':uid', $userId, PDO::PARAM_INT);
                $ins->bindValue(':rid', $rid, PDO::PARAM_INT);
                $ins->execute();
                $added++;
            }

            // Nota: id_rol en usuarios se depreca y no se actualiza más.

            $db->commit();
            return ['success' => true, 'added' => $added];
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log('Error setRolesForUser: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return ['success' => false, 'message' => 'No fue posible actualizar roles del usuario.'];
        }
    }

    /**
     * Listar roles disponibles (mapa id => nombre_rol).
     * @return array
     */
    public function listarRoles()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre_rol FROM roles ORDER BY nombre_rol ASC');
            $stmt->execute();
            $roles = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $roles[(int)$row['id']] = $row['nombre_rol'];
            }
            return $roles;
        } catch (PDOException $e) {
            error_log('Error al listar roles: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener usuarios activos asignados a un rol por nombre.
     * @param string $nombreRol
     * @return array Lista de usuarios.
     */
    public function obtenerUsuariosPorRolNombre($nombreRol)
    {
        try {
            $db = (new Conexion())->conectar();
            // Usar exclusivamente el pivot usuarios_roles (esquema normalizado)
            $sql = 'SELECT DISTINCT u.id, u.nombre, u.email, u.telefono
                    FROM usuarios u
                    INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id
                    INNER JOIN roles r ON r.id = ur.id_rol
                    WHERE r.nombre_rol = :rol AND u.activo = 1
                    ORDER BY u.nombre';
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':rol', $nombreRol, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener usuarios por rol: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener todos los roles de un usuario como arrays de ids y nombres.
     * Fallback: si no hay registros en pivot, usa u.id_rol.
     * @return array{ids:int[], nombres:string[]}
     */
    /**
     * Obtener roles de un usuario como arrays de ids y nombres.
     * Fallback: si pivot vacío, utiliza usuarios.id_rol.
     *
     * @param int $userId
     * @return array{ids:int[], nombres:string[]}
     */
    public function obtenerRolesDeUsuario(int $userId): array
    {
        $ids = [];
        $nombres = [];
        try {
            $db = (new Conexion())->conectar();
            // Intentar obtener desde pivot
            $q = $db->prepare('SELECT r.id, r.nombre_rol FROM usuarios_roles ur INNER JOIN roles r ON r.id = ur.id_rol WHERE ur.id_usuario = :uid');
            $q->bindValue(':uid', $userId, PDO::PARAM_INT);
            if ($q->execute()) {
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $ids[] = (int)$row['id'];
                    $nombres[] = $row['nombre_rol'];
                }
            }

            // Fallback: si no se encontraron roles en pivot, usar el campo simple de usuarios
            if (empty($ids)) {
                $q2 = $db->prepare('SELECT u.id_rol, r.nombre_rol FROM usuarios u LEFT JOIN roles r ON r.id = u.id_rol WHERE u.id = :uid');
                $q2->bindValue(':uid', $userId, PDO::PARAM_INT);
                if ($q2->execute()) {
                    $row = $q2->fetch(PDO::FETCH_ASSOC);
                    if ($row && !empty($row['id_rol'])) {
                        $ids[] = (int)$row['id_rol'];
                        if (!empty($row['nombre_rol'])) $nombres[] = $row['nombre_rol'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Error obtenerRolesDeUsuario: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
        }

        // Unificar valores únicos
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $nombres = array_values(array_unique(array_filter($nombres, function($v){ return $v !== null && $v !== ''; })));

        return ['ids' => $ids, 'nombres' => $nombres];
    }
}

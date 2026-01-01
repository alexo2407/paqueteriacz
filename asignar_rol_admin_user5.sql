-- Script para asignar rol de Administrador al usuario ID 5
-- Ejecutar este script en phpMyAdmin o MySQL Workbench

USE paqueteriacz;

-- Insertar o actualizar rol de Administrador para usuario 5
-- (id_rol = 1 es Administrador)
INSERT INTO usuarios_roles (id_usuario, id_rol) 
VALUES (5, 1)
ON DUPLICATE KEY UPDATE id_rol = 1;

-- Verificar que se asignó correctamente
SELECT 
    u.id,
    u.nombre,
    u.email,
    r.nombre as rol
FROM usuarios u
LEFT JOIN usuarios_roles ur ON u.id = ur.id_usuario
LEFT JOIN roles r ON ur.id_rol = r.id
WHERE u.id = 5;

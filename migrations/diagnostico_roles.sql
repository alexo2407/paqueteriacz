-- =====================================================
-- Queries de Diagnóstico: Verificar Roles y Permisos
-- =====================================================

-- 1. Verificar roles en la tabla roles
SELECT id, nombre_rol, descripcion 
FROM roles 
WHERE id IN (4, 5)
ORDER BY id;

-- Resultado esperado:
-- ID 4: Cliente
-- ID 5: Proveedor

-- =====================================================

-- 2. Verificar qué usuarios tienen rol Cliente (ID 4)
SELECT 
    u.id AS user_id,
    u.nombre,
    u.email,
    ur.id_rol,
    r.nombre_rol
FROM usuarios u
JOIN usuarios_roles ur ON u.id = ur.id_usuario
JOIN roles r ON ur.id_rol = r.id
WHERE ur.id_rol = 4;

-- =====================================================

-- 3. Verificar qué usuarios tienen rol Proveedor (ID 5)
SELECT 
    u.id AS user_id,
    u.nombre,
    u.email,
    ur.id_rol,
    r.nombre_rol
FROM usuarios u
JOIN usuarios_roles ur ON u.id = ur.id_usuario
JOIN roles r ON ur.id_rol = r.id
WHERE ur.id_rol = 5;

-- =====================================================

-- 4. Verificar TODOS los roles de un usuario específico
-- (Reemplaza 'TU_EMAIL' con el email del usuario que estás probando)
SELECT 
    u.id AS user_id,
    u.nombre,
    u.email,
    ur.id_rol,
    r.nombre_rol
FROM usuarios u
JOIN usuarios_roles ur ON u.id = ur.id_usuario
JOIN roles r ON ur.id_rol = r.id
WHERE u.email = 'TU_EMAIL_AQUI';

-- =====================================================

-- 5. Ver estructura completa de usuarios_roles
SELECT * FROM usuarios_roles ORDER BY id_usuario;

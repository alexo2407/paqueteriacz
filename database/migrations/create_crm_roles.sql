-- ============================================================================
-- Migración: Crear Roles Específicos para CRM
-- Fecha: 2026-01-14
-- Descripción: Crea roles proveedor_crm y cliente_crm para separar permisos
--              de CRM de los roles de Logística (proveedor y cliente)
-- ============================================================================

-- Verificar roles actuales
SELECT * FROM roles ORDER BY id;

-- Insertar nuevos roles CRM
INSERT INTO roles (nombre, descripcion) VALUES
('Proveedor CRM', 'Proveedor de leads para el sistema CRM. Puede crear leads, asignarlos a clientes y ver métricas de sus leads enviados.'),
('Cliente CRM', 'Cliente que recibe y gestiona leads del CRM. Puede ver leads asignados, cambiar su estado y hacer seguimiento.');

-- Verificar inserción
SELECT * FROM roles WHERE nombre LIKE '%CRM%';

-- Nota: Anotar los IDs asignados para actualizar las constantes en:
-- - config/config.php
-- - utils/permissions.php
--
-- Se espera que los IDs sean:
-- - Proveedor CRM: 5
-- - Cliente CRM: 6

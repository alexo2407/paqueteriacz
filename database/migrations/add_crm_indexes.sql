-- =====================================================
-- Optimización de Índices para CRM Leads
-- =====================================================
-- Este script agrega índices para optimizar las consultas
-- de la tabla DataTables en crm/listar

-- 1. Índice para filtro de estado (usado frecuentemente)
CREATE INDEX IF NOT EXISTS idx_crm_estado_actual 
ON crm_leads(estado_actual);

-- 2. Índice para filtro de fecha
CREATE INDEX IF NOT EXISTS idx_crm_fecha_hora 
ON crm_leads(fecha_hora);

-- 3. Índice para ordenamiento por fecha de creación (DESC)
CREATE INDEX IF NOT EXISTS idx_crm_created_at 
ON crm_leads(created_at DESC);

-- 4. Índice para JOIN con proveedores
CREATE INDEX IF NOT EXISTS idx_crm_proveedor_id 
ON crm_leads(proveedor_id);

-- 5. Índice para JOIN con clientes
CREATE INDEX IF NOT EXISTS idx_crm_cliente_id 
ON crm_leads(cliente_id);

-- 6. Índice compuesto para filtros combinados más comunes
-- (estado + fecha de creación)
CREATE INDEX IF NOT EXISTS idx_crm_estado_created 
ON crm_leads(estado_actual, created_at DESC);

-- 7. OPCIONAL: Índice FULLTEXT para búsquedas de texto
-- (solo si tienes MySQL 5.6+ y motor InnoDB)
-- Descomentar si quieres búsquedas más rápidas:
/*
ALTER TABLE crm_leads ADD FULLTEXT INDEX idx_crm_fulltext 
(nombre, telefono, proveedor_lead_id);
*/

-- =====================================================
-- Verificar índices creados
-- =====================================================
SHOW INDEX FROM crm_leads;

-- Optimización de índices para bulk lead status update
-- Ejecutar este script para mejorar el rendimiento de las queries

-- Verificar índices existentes
SHOW INDEX FROM crm_leads;

-- Crear índice en cliente_id si no existe (para ownership validation)
CREATE INDEX IF NOT EXISTS idx_crm_leads_cliente_id 
ON crm_leads(cliente_id);

-- Crear índice en estado_actual (para queries filtradas por estado)
CREATE INDEX IF NOT EXISTS idx_crm_leads_estado 
ON crm_leads(estado_actual);

-- Índice compuesto para la query principal (mayor impacto)
-- Este índice cubre: WHERE id IN (...) y acceso a cliente_id, estado_actual
CREATE INDEX IF NOT EXISTS idx_crm_leads_id_cliente_estado 
ON crm_leads(id, cliente_id, estado_actual);

-- Verificar que los índices fueron creados
SHOW INDEX FROM crm_leads WHERE Key_name LIKE 'idx_crm_leads%';

-- Estadísticas de la tabla para verificar si es necesaria optimización
ANALYZE TABLE crm_leads;

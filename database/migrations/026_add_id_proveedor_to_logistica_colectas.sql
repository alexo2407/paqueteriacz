-- =============================================================================
-- 026_add_id_proveedor_to_logistica_colectas.sql
-- Modelo Definitivo: Colecta = (Cliente + Proveedor + Fecha + Turno)
-- Presupone que la tabla logistica_colectas está vacía antes de su ejecución.
-- =============================================================================

ALTER TABLE `logistica_colectas` 
  ADD COLUMN `id_proveedor` INT(11) NOT NULL AFTER `id_cliente`,
  DROP INDEX `uk_colecta_cliente_fecha_turno`,
  ADD UNIQUE KEY `uk_colecta_cliente_proveedor_fecha_turno` (`id_cliente`, `id_proveedor`, `fecha`, `turno`),
  ADD CONSTRAINT `fk_colectas_proveedor` 
    FOREIGN KEY (`id_proveedor`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

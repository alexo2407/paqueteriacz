-- =============================================================================
-- Migración 024: Campo `mostrar_courier` en tabla `usuarios`
-- Permite configurar qué usuarios tipo Cliente pueden ver el nombre de la
-- paquetería/courier externo asignado (C807, LogisPro, etc.) o mantener marca blanca.
-- =============================================================================

SET @exist_col := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'usuarios' 
      AND column_name = 'mostrar_courier'
);

SET @sqlstmt := IF(
    @exist_col > 0, 
    'DO 0', 
    'ALTER TABLE usuarios ADD COLUMN mostrar_courier TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = puede ver nombre de courier externo; 0 = marca blanca (oculto)\''
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

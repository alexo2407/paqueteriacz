-- =============================================================================
-- Migración 025: Campo `alias_courier` en tabla `usuarios`
-- Permite configurar un alias o nombre personalizado del courier visible para
-- el cliente (ej. 'RutaEx Express' en vez de 'C807 Xpress').
-- =============================================================================

SET @exist_col := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'usuarios' 
      AND column_name = 'alias_courier'
);

SET @sqlstmt := IF(
    @exist_col > 0, 
    'DO 0', 
    'ALTER TABLE usuarios ADD COLUMN alias_courier VARCHAR(100) NULL DEFAULT NULL COMMENT \'Alias visible del courier para este cliente (marca blanca con nombre personalizado)\''
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

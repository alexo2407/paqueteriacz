-- =============================================================================
-- Migración 029: Campo `mostrar_tracking` en tabla `usuarios`
-- Permite configurar desde el Admin qué usuarios (Clientes / Proveedores)
-- pueden ver la etiqueta/número de tracking (`numero_traking`) y cuáles no.
-- =============================================================================

SET @exist_col := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'usuarios' 
      AND column_name = 'mostrar_tracking'
);

SET @sqlstmt := IF(
    @exist_col > 0, 
    'DO 0', 
    'ALTER TABLE usuarios ADD COLUMN mostrar_tracking TINYINT(1) NOT NULL DEFAULT 1 COMMENT \'1 = puede ver numero de tracking; 0 = oculto\''
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mantener oculto por defecto solo para las cuentas restringidas de EconGlobal Panamá / HL Express (21 y 26)
UPDATE usuarios 
SET mostrar_tracking = 0 
WHERE id IN (21, 26);

-- Asegurar explícitamente que el proveedor de Econglobal Uruguay pueda ver tracking y courier
UPDATE usuarios 
SET mostrar_tracking = 1,
    mostrar_courier = 1
WHERE email = 'proveedorrutaexteconglobaluyu@rutaexlatam.com'
   OR nombre LIKE '%Uruguay%'
   OR email LIKE '%econglobaluyu%';


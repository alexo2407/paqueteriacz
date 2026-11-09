-- =============================================================================
-- Migración 029: Campo `mostrar_tracking` en tabla `usuarios`
-- Permite configurar desde el Admin qué usuarios (Clientes / Proveedores)
-- pueden ver la etiqueta/número de tracking (`numero_traking`) y cuáles no.
-- Por defecto 0 (oculto, igual que mostrar_courier), y 1 para Uruguay.
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
    'ALTER TABLE usuarios MODIFY COLUMN mostrar_tracking TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = puede ver numero de tracking; 0 = oculto\'', 
    'ALTER TABLE usuarios ADD COLUMN mostrar_tracking TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 = puede ver numero de tracking; 0 = oculto\''
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Por defecto desactivado (0) para todos los usuarios
UPDATE usuarios SET mostrar_tracking = 0;

-- Habilitado (1) únicamente para las cuentas de Uruguay solicitadas (y las que el Admin active)
UPDATE usuarios 
SET mostrar_tracking = 1,
    mostrar_courier = 1
WHERE email = 'proveedorrutaexteconglobaluyu@rutaexlatam.com'
   OR nombre LIKE '%Uruguay%'
   OR email LIKE '%econglobaluyu%';

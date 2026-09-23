-- Migration 020: Configurar prefijo postal de México y dar de alta sus 32 estados

-- 1. Asignar prefijo postal 'MX' a México
UPDATE paises SET prefijo_postal = 'MX' WHERE id = 11;

-- 2. Asegurar que los 32 estados de México existan en la tabla departamentos
INSERT IGNORE INTO departamentos (nombre, id_pais) VALUES
('Aguascalientes', 11),
('Baja California', 11),
('Baja California Sur', 11),
('Campeche', 11),
('Chiapas', 11),
('Chihuahua', 11),
('Ciudad de México', 11),
('Coahuila', 11),
('Colima', 11),
('Durango', 11),
('Estado de México', 11),
('Guanajuato', 11),
('Guerrero', 11),
('Hidalgo', 11),
('Jalisco', 11),
('Michoacán', 11),
('Morelos', 11),
('Nayarit', 11),
('Nuevo León', 11),
('Oaxaca', 11),
('Puebla', 11),
('Querétaro', 11),
('Quintana Roo', 11),
('San Luis Potosí', 11),
('Sinaloa', 11),
('Sonora', 11),
('Tabasco', 11),
('Tamaulipas', 11),
('Tlaxcala', 11),
('Veracruz', 11),
('Yucatán', 11),
('Zacatecas', 11);

-- 3. Sincronizar id_pais en pedidos históricos huérfanos heredando del cliente o proveedor
UPDATE pedidos p
LEFT JOIN usuarios u_prov ON u_prov.id = p.id_proveedor
LEFT JOIN usuarios u_cli  ON u_cli.id  = p.id_cliente
SET p.id_pais = COALESCE(p.id_pais, u_prov.id_pais, u_cli.id_pais)
WHERE p.id_pais IS NULL 
  AND COALESCE(u_prov.id_pais, u_cli.id_pais) IS NOT NULL;

-- Migración: Agregar campo courier_service a la tabla pedidos
-- Fecha: 2026-07-13
-- Descripción: Almacena el nombre del servicio de mensajería/courier para el envío
--              (texto libre, ej: DHL, FedEx, UPS, Correos de CR, etc.)

ALTER TABLE pedidos
    ADD COLUMN courier_service VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Nombre del servicio courier para el envío (ej: DHL, FedEx, UPS)';

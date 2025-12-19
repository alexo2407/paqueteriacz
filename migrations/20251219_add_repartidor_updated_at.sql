-- Migration: Add repartidor_updated_at to pedidos table
-- Purpose: Track when a repartidor makes their one-time status update
-- Date: 2025-12-19

ALTER TABLE pedidos 
ADD COLUMN repartidor_updated_at DATETIME NULL 
COMMENT 'Timestamp when repartidor made their one-time status update';

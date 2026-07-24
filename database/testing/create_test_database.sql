-- =============================================================================
-- database/testing/create_test_database.sql
--
-- Crea la base de datos exclusiva para pruebas de integración.
--
-- ADVERTENCIA: este script es solo para entornos de desarrollo y CI.
--              NUNCA ejecutar en producción.
--              NO contiene DROP DATABASE.
--              NO contiene datos personales ni contraseñas.
--
-- Uso manual:
--   mysql -u root -p < database/testing/create_test_database.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS paquetes_apppack_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

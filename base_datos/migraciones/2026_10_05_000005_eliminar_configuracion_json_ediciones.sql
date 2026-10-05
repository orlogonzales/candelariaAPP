-- ==============================================================================
-- MIGRACIÓN: 2026_10_05_000005_eliminar_configuracion_json_ediciones.sql
-- DESCRIPCIÓN: Fase 2.1B - Saneamiento de modelo de Edición Candelaria.
--              Eliminación definitiva de la columna configuracion_json para evitar
--              un contenedor no gobernado de configuración arbitraria.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `ediciones_candelaria` DROP COLUMN `configuracion_json`;

SET FOREIGN_KEY_CHECKS = 1;

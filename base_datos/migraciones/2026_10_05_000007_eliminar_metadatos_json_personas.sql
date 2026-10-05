-- ==============================================================================
-- MIGRACIÓN: 2026_10_05_000007_eliminar_metadatos_json_personas.sql
-- DESCRIPCIÓN: Micro-Gate F2.2A-2 - Saneamiento del Modelo Maestro de Personas.
--              Eliminación definitiva de la columna metadatos_json en tabla
--              personas para garantizar un modelo relacional estrictamente
--              tipado, sin contenedores JSON genéricos ni bolsas no gobernadas.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `personas` DROP COLUMN `metadatos_json`;

SET FOREIGN_KEY_CHECKS = 1;

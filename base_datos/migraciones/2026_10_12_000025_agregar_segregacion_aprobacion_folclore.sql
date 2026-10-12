-- ==============================================================================
-- MIGRACIÓN: 2026_10_12_000025_agregar_segregacion_aprobacion_folclore.sql
-- DESCRIPCIÓN: Fase 3.1B-1 / DT-V11-006 - Segregación de identidades y Principio
--              de Cuatro Ojos en la aprobación y oficialización del Rol Folclórico.
--              Incorpora aprobado_por y aprobado_en a folclore_programaciones_oficiales.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. INCORPORACIÓN DE COLUMNAS DE APROBACIÓN Y SEGREGACIÓN DE IDENTIDADES
-- ------------------------------------------------------------------------------
ALTER TABLE `folclore_programaciones_oficiales`
    ADD COLUMN `aprobado_por` INT UNSIGNED NULL AFTER `creado_por`,
    ADD COLUMN `aprobado_en` DATETIME NULL AFTER `aprobado_por`,
    ADD CONSTRAINT `fk_prog_oficial_aprobado_por` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    ADD KEY `idx_prog_aprobado_por` (`aprobado_por`);

-- 2. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_12_000025_agregar_segregacion_aprobacion_folclore.sql', 23)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

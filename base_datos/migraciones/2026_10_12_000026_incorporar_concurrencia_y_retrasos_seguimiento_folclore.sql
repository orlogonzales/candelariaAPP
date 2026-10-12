-- ==============================================================================
-- MIGRACIÓN: 2026_10_12_000026_incorporar_concurrencia_y_retrasos_seguimiento_folclore.sql
-- DESCRIPCIÓN: Fase 3.1C / DT-V11-008 - Seguimiento Operativo y Cobertura Audiovisual.
--              Incorpora control de concurrencia optimista (version_bloqueo) y
--              registro de retraso operativo (retraso_minutos) en folclore_orden_presentacion.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. INCORPORACIÓN DE COLUMNAS EN folclore_orden_presentacion
-- ------------------------------------------------------------------------------
ALTER TABLE `folclore_orden_presentacion`
    ADD COLUMN `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `observaciones`,
    ADD COLUMN `retraso_minutos` INT NULL DEFAULT 0 AFTER `version_bloqueo`;

-- 2. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_12_000026_incorporar_concurrencia_y_retrasos_seguimiento_folclore.sql', 26)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

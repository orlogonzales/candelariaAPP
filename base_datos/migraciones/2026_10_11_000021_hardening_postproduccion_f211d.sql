-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000021_hardening_postproduccion_f211d.sql
-- DESCRIPCIÓN: Hardening de Postproducción F2.11D - Políticas de conservación y
--              retención, trazabilidad de supresión de datos (Ley 29733),
--              concurrencia optimista en entregas y estados canónicos.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. EVOLUCIÓN TABLA `postproduccion_archivos` (ESTADOS Y RETENCIÓN)
-- ------------------------------------------------------------------------------
ALTER TABLE `postproduccion_archivos` MODIFY COLUMN `estado` VARCHAR(30) NOT NULL DEFAULT 'ACTIVO';
UPDATE `postproduccion_archivos` SET `estado` = 'ACTIVO';
ALTER TABLE `postproduccion_archivos`
    MODIFY COLUMN `estado` ENUM('ACTIVO', 'ARCHIVADO', 'REVOCADO', 'ELIMINADO_SOLICITUD') NOT NULL DEFAULT 'ACTIVO';

DROP PROCEDURE IF EXISTS upgrade_postproduccion_archivos_retencion;
DELIMITER $$
CREATE PROCEDURE upgrade_postproduccion_archivos_retencion()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'postproduccion_archivos' AND column_name = 'fecha_retencion_hasta'
    ) THEN
        ALTER TABLE `postproduccion_archivos`
            ADD COLUMN `fecha_retencion_hasta` DATE DEFAULT NULL AFTER `duracion_segundos`;
    END IF;
END $$
DELIMITER ;
CALL upgrade_postproduccion_archivos_retencion();
DROP PROCEDURE IF EXISTS upgrade_postproduccion_archivos_retencion;

-- ------------------------------------------------------------------------------
-- 2. EVOLUCIÓN TABLA `postproduccion_entregas` (CONCURRENCIA OPTIMISTA)
-- ------------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS upgrade_postproduccion_entregas;
DELIMITER $$
CREATE PROCEDURE upgrade_postproduccion_entregas()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'postproduccion_entregas' AND column_name = 'version_bloqueo'
    ) THEN
        ALTER TABLE `postproduccion_entregas`
            ADD COLUMN `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `observaciones_cliente`;
    END IF;
END $$
DELIMITER ;
CALL upgrade_postproduccion_entregas();
DROP PROCEDURE IF EXISTS upgrade_postproduccion_entregas;

-- ------------------------------------------------------------------------------
-- 3. TABLA `postproduccion_solicitudes_supresion` (TRAZABILIDAD LEY 29733 HABEAS DATA)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `postproduccion_solicitudes_supresion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `archivo_id` BIGINT UNSIGNED NOT NULL,
    `solicitante_tipo` ENUM('CLIENTE', 'USUARIO') NOT NULL,
    `solicitante_id` INT UNSIGNED NOT NULL,
    `motivo` TEXT NOT NULL,
    `estado` ENUM('PENDIENTE', 'APROBADA_EJECUTADA', 'RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
    `autorizado_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_ejecucion` DATETIME DEFAULT NULL,
    `checksum_sha256_purgado` CHAR(64) DEFAULT NULL,
    `ip_solicitud` VARCHAR(45) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_supresion_org` (`organizacion_id`),
    KEY `idx_supresion_archivo` (`archivo_id`),
    KEY `idx_supresion_estado` (`organizacion_id`, `estado`),
    CONSTRAINT `fk_supresion_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_supresion_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `postproduccion_archivos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_supresion_autorizador` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trazabilidad probatoria de solicitudes de eliminación y supresión de datos Ley 29733';

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- MIGRACIÓN: 2026_10_02_000003_configuracion_y_organizacion.sql
-- DESCRIPCIÓN: Fase 1.2A - Modelo de Configuración y Organización
--              Extensión del perfil institucional de organizaciones y creación
--              de la tabla de parámetros tipados y gobernados por ámbito
--              (PLATAFORMA vs ORGANIZACIÓN) con constraint de integridad.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. EXTENSIÓN RELACIONAL DEL PERFIL DE ORGANIZACIONES
-- ------------------------------------------------------------------------------
ALTER TABLE `organizaciones`
    ADD COLUMN `tipo_documento_id` TINYINT UNSIGNED DEFAULT 2 AFTER `razon_social`,
    ADD COLUMN `direccion` VARCHAR(255) DEFAULT NULL AFTER `numero_documento`,
    ADD COLUMN `codigo_pais` CHAR(2) NOT NULL DEFAULT 'PE' AFTER `direccion`,
    ADD COLUMN `departamento` VARCHAR(100) DEFAULT NULL AFTER `codigo_pais`,
    ADD COLUMN `provincia` VARCHAR(100) DEFAULT NULL AFTER `departamento`,
    ADD COLUMN `distrito` VARCHAR(100) DEFAULT NULL AFTER `provincia`,
    ADD COLUMN `telefono_whatsapp` VARCHAR(30) DEFAULT NULL AFTER `telefono_contacto`,
    ADD COLUMN `sitio_web` VARCHAR(200) DEFAULT NULL AFTER `correo_contacto`,
    ADD COLUMN `contacto_nombre` VARCHAR(150) DEFAULT NULL AFTER `sitio_web`,
    ADD COLUMN `contacto_cargo` VARCHAR(100) DEFAULT NULL AFTER `contacto_nombre`,
    ADD COLUMN `isotipo_url` VARCHAR(255) DEFAULT NULL AFTER `logo_url`,
    ADD CONSTRAINT `fk_organizaciones_tipo_doc` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT;

-- ------------------------------------------------------------------------------
-- 2. TABLA DE PARÁMETROS TIPADOS Y GOBERNADOS (ÁMBITOS PLATAFORMA Y ORGANIZACIÓN)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parametros_configuracion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL para ámbito PLATAFORMA; ID de tenant para ámbito ORGANIZACION',
    `ambito` ENUM('PLATAFORMA', 'ORGANIZACION') NOT NULL DEFAULT 'PLATAFORMA',
    `codigo` VARCHAR(80) NOT NULL COMMENT 'Identificador canónico único del parámetro (ej. plataforma.monto_minimo_pago_pe)',
    `tipo_dato` ENUM('STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'DATE', 'DATETIME') NOT NULL DEFAULT 'STRING',
    `valor` TEXT DEFAULT NULL COMMENT 'Valor tipado almacenado como texto, validado y convertido en backend',
    `valor_defecto` TEXT DEFAULT NULL COMMENT 'Valor por defecto para contingencias',
    `etiqueta` VARCHAR(150) NOT NULL COMMENT 'Nombre legible para visualización',
    `descripcion` VARCHAR(255) DEFAULT NULL COMMENT 'Documentación del propósito del parámetro',
    `reglas_validacion_json` JSON DEFAULT NULL COMMENT 'Reglas de validación: min, max, regex, opciones',
    `es_sistema` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si es parámetro estructural protegido de eliminación',
    `es_publico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si puede ser expuesto públicamente sin autenticación',
    `es_editable` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 si solo es modificable por infraestructura o migración',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_param_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_param_ambito` (`ambito`),
    KEY `idx_param_codigo` (`codigo`),
    CONSTRAINT `chk_param_ambito_org` CHECK (
        (`ambito` = 'PLATAFORMA' AND `organizacion_id` IS NULL)
        OR
        (`ambito` = 'ORGANIZACION' AND `organizacion_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros gobernados y tipados de configuración por ámbito (Plataforma y Organización)';

-- ------------------------------------------------------------------------------
-- 3. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_02_000003_configuracion_y_organizacion.sql', 3);

SET FOREIGN_KEY_CHECKS = 1;

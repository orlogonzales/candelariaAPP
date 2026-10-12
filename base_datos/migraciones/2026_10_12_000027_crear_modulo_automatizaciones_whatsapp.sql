-- ==============================================================================
-- MIGRACIÓN 27: MOTOR DE AUTOMATIZACIONES Y DISPARADORES WHATSAPP (FASE 3.2)
-- CandelariaAPP V1.1 — Directiva Técnica DT-V11-009
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABLA `comunicacion_automatizacion_reglas`
CREATE TABLE IF NOT EXISTS `comunicacion_automatizacion_reglas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Identificador único de regla por tenant',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo de la automatización',
    `descripcion` VARCHAR(255) NULL,
    `evento_disparador` VARCHAR(80) NOT NULL COMMENT 'Código del evento de negocio disparador',
    `plantilla_id` INT UNSIGNED NOT NULL COMMENT 'Plantilla de WhatsApp a enviar',
    `tipo_comunicacion` ENUM('TRANSACCIONAL', 'PROMOCIONAL') NOT NULL DEFAULT 'TRANSACCIONAL',
    `minutos_diferidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutos de espera tras el evento (0 = inmediato)',
    `condiciones_json` JSON NULL COMMENT 'Filtros adicionales sobre el payload del evento',
    `mapeo_parametros_json` JSON NOT NULL COMMENT 'Mapeo de variables de evento a placeholders de plantilla',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `total_disparados` INT UNSIGNED NOT NULL DEFAULT 0,
    `ultimo_disparo_en` DATETIME NULL,
    `creado_por` INT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_aut_reglas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_aut_reglas_plantilla` FOREIGN KEY (`plantilla_id`, `organizacion_id`) REFERENCES `comunicacion_plantillas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_aut_reglas_org_codigo` UNIQUE (`organizacion_id`, `codigo`),
    KEY `idx_aut_reglas_evento` (`organizacion_id`, `evento_disparador`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reglas de automatización y disparadores de WhatsApp';

-- 2. VINCULAR OUTBOX CON REGLA DISPARADORA
SET @existeColumna = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'comunicacion_mensajes' 
      AND COLUMN_NAME = 'automatizacion_regla_id'
);

SET @sqlAlter = IF(@existeColumna = 0, 
    'ALTER TABLE `comunicacion_mensajes` ADD COLUMN `automatizacion_regla_id` INT UNSIGNED NULL AFTER `plantilla_id`, ADD CONSTRAINT `fk_mensajes_aut_regla` FOREIGN KEY (`automatizacion_regla_id`) REFERENCES `comunicacion_automatizacion_reglas` (`id`) ON DELETE SET NULL', 
    'SELECT 1'
);
PREPARE stmtAlter FROM @sqlAlter;
EXECUTE stmtAlter;
DEALLOCATE PREPARE stmtAlter;

-- 3. PERMISO RBAC OFICIAL PARA GESTIÓN DE AUTOMATIZACIONES
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`)
VALUES (12, 'comunicaciones.automatizaciones.gestionar', 'Gestionar Automatizaciones WhatsApp', 'Creación, configuración y prueba de disparadores automáticos')
ON DUPLICATE KEY UPDATE `modulo_id` = VALUES(`modulo_id`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ASIGNAR PERMISO A ROLES DE ADMINISTRACIÓN
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo IN ('superadmin_plataforma', 'admin_organizacion', 'ADMINISTRADOR') 
  AND p.codigo = 'comunicaciones.automatizaciones.gestionar';

-- 4. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 27)
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_12_000027_crear_modulo_automatizaciones_whatsapp.sql', 27)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

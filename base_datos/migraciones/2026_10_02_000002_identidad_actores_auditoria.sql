-- ==============================================================================
-- MIGRACIÓN: 2026_10_02_000002_identidad_actores_auditoria.sql
-- DESCRIPCIÓN: Fase 1.1A - Modelo de Identidad, Personas, Usuarios, Actores Técnicos,
--              Canales Extensibles y Auditoría Dual Inmutable con constraints.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. CATÁLOGO EXTENSIBLE DE TIPOS DE DOCUMENTO
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_documento` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'DNI, RUC, PASAPORTE, CARNET_EXTRANJERIA, CEDULA, OTRO',
    `nombre` VARCHAR(60) NOT NULL,
    `aplica_a` ENUM('NATURAL', 'JURIDICA', 'AMBOS') NOT NULL DEFAULT 'AMBOS',
    `longitud_exacta` TINYINT UNSIGNED DEFAULT NULL,
    `es_alfanumerico` TINYINT(1) NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tipos_documento_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo extensible de tipos de documentos de identidad';

-- ------------------------------------------------------------------------------
-- 2. REGISTRO CENTRAL DE PERSONAS (NATURALES Y JURÍDICAS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo_persona` ENUM('NATURAL', 'JURIDICA') NOT NULL DEFAULT 'NATURAL',
    `tipo_documento_id` TINYINT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `nombres` VARCHAR(100) DEFAULT NULL COMMENT 'Obligatorio si tipo_persona = NATURAL',
    `apellidos` VARCHAR(100) DEFAULT NULL COMMENT 'Obligatorio si tipo_persona = NATURAL',
    `razon_social` VARCHAR(200) DEFAULT NULL COMMENT 'Obligatorio si tipo_persona = JURIDICA',
    `nombre_comercial` VARCHAR(150) DEFAULT NULL COMMENT 'Nombre comercial opcional para naturales y jurídicas',
    `correo_electronico` VARCHAR(150) DEFAULT NULL,
    `telefono_movil` VARCHAR(30) DEFAULT NULL,
    `telefono_whatsapp` VARCHAR(30) DEFAULT NULL COMMENT 'Opcional a nivel persona general; regla de obligatoriedad comercial aplica en clientes',
    `direccion` VARCHAR(255) DEFAULT NULL,
    `ciudad` VARCHAR(100) DEFAULT NULL COMMENT 'Sin default hardcodeado a Puno',
    `codigo_pais` CHAR(2) NOT NULL DEFAULT 'PE' COMMENT 'Código ISO 3166-1 alpha-2',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `metadatos_json` JSON DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_personas_org_doc` (`organizacion_id`, `tipo_documento_id`, `numero_documento`),
    KEY `idx_personas_tipo` (`tipo_persona`),
    KEY `idx_personas_estado` (`estado`),
    KEY `idx_personas_apellidos` (`apellidos`),
    KEY `idx_personas_razon_social` (`razon_social`),
    CONSTRAINT `chk_personas_tipo_consistencia` CHECK (
        (`tipo_persona` = 'NATURAL' AND `nombres` IS NOT NULL AND `apellidos` IS NOT NULL AND `razon_social` IS NULL)
        OR
        (`tipo_persona` = 'JURIDICA' AND `razon_social` IS NOT NULL AND `nombres` IS NULL AND `apellidos` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro canónico de identidades físicas y jurídicas';

-- ------------------------------------------------------------------------------
-- 3. CATÁLOGO CONTROLADO DE ACTORES DE SISTEMA (ACTORES VIRTUALES)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actores_sistema` (
    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Identificador técnico estable y único',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `es_critico` TINYINT(1) NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_actores_sistema_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo controlado de identidades técnicas / actores virtuales del sistema';

-- ------------------------------------------------------------------------------
-- 4. CATÁLOGO EXTENSIBLE DE CANALES DE INGRESO
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `canales` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'APP, WEB, API, APP_MOVIL, WHATSAPP, IMPORTACION, API_PARTNER',
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_canales_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo extensible de canales de ingreso de operaciones';

-- ------------------------------------------------------------------------------
-- 5. ADECUACIÓN DE TABLA USUARIOS (VINCULACIÓN A PERSONA Y POLÍTICAS DE BLOQUEO)
-- ------------------------------------------------------------------------------
ALTER TABLE `usuarios`
    ADD COLUMN `persona_id` INT UNSIGNED NOT NULL AFTER `organizacion_id`,
    ADD COLUMN `nombre_usuario` VARCHAR(60) NOT NULL AFTER `persona_id`,
    ADD COLUMN `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `estado`,
    ADD COLUMN `bloqueado_hasta` DATETIME DEFAULT NULL AFTER `intentos_fallidos`,
    MODIFY COLUMN `telefono_whatsapp` VARCHAR(30) DEFAULT NULL,
    ADD CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `uk_usuarios_persona` UNIQUE KEY (`persona_id`),
    ADD CONSTRAINT `uk_usuarios_nombre_usuario` UNIQUE KEY (`nombre_usuario`);

-- ------------------------------------------------------------------------------
-- 6. ADECUACIÓN DE AUDITORÍA DE OPERACIONES (TRAZABILIDAD DUAL Y CHECK CONSTRAINTS)
-- ------------------------------------------------------------------------------
ALTER TABLE `auditoria_operaciones`
    ADD COLUMN `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO' AFTER `organizacion_id`,
    ADD COLUMN `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL AFTER `usuario_id`,
    ADD COLUMN `canal_id` TINYINT UNSIGNED NOT NULL AFTER `actor_sistema_id`,
    ADD COLUMN `correlacion_id` VARCHAR(64) NOT NULL AFTER `canal_id`,
    CHANGE COLUMN `direccion_ip` `origen_ip` VARCHAR(45) DEFAULT NULL,
    DROP FOREIGN KEY `auditoria_operaciones_ibfk_2`,
    ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_auditoria_actor_sistema` FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_auditoria_canal` FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    ADD KEY `idx_auditoria_correlacion` (`correlacion_id`),
    ADD KEY `idx_auditoria_actor_sistema` (`actor_sistema_id`),
    ADD KEY `idx_auditoria_canal` (`canal_id`),
    ADD KEY `idx_auditoria_entidad` (`entidad_tipo`, `entidad_id`),
    ADD CONSTRAINT `chk_auditoria_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    );

-- ------------------------------------------------------------------------------
-- 7. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_02_000002_identidad_actores_auditoria.sql', 2);

SET FOREIGN_KEY_CHECKS = 1;

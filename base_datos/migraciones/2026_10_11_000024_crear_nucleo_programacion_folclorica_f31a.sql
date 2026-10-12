-- ==============================================================================
-- MIGRACIÓN 24: 2026_10_11_000024_crear_nucleo_programacion_folclorica_f31a.sql
-- DESCRIPCIÓN: Módulo 07 - Núcleo Estructural de Programación Folclórica
--              Tipos de Danza, Conjuntos Folclóricos, Bloques, Inscripciones
--              por Edición, Actividades y Programación Oficial con Procedencia.
-- DIRECTIVA:   DT-V11-003 (Fase 3.1A)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABLA `tipos_danza` (CATÁLOGO MAESTRO DE TIPOS / GÉNEROS DE DANZA)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_danza` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'MORENADA, CAPORALES, DIABLADA, WACA_WACA, SIKURIS, etc.',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Normalizado en MAYÚSCULAS',
    `categoria_origen` ENUM('TRAJES_DE_LUCES', 'AUTOCTONO', 'AFROPERUANO', 'MESTIZO', 'OTRO') NOT NULL DEFAULT 'TRAJES_DE_LUCES',
    `descripcion` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_tipos_danza_org_cod` (`organizacion_id`, `codigo`),
    UNIQUE KEY `uk_tipos_danza_org_nom` (`organizacion_id`, `nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos y géneros de danza de la Festividad de la Candelaria';

-- 2. TABLA `conjuntos_folcloricos` (AGRUPACIONES HISTÓRICAS INDEPENDIENTES DE EDICIONES)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conjuntos_folcloricos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo_danza_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'CONJ-MORENADA-LAYKAKOTA, CONJ-BELLAVISTA, etc.',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre oficial en MAYÚSCULAS',
    `sigla` VARCHAR(30) NULL,
    `fecha_fundacion` DATE NULL,
    `nombre_presidente` VARCHAR(120) NULL,
    `telefono_contacto` VARCHAR(30) NULL,
    `color_emblematico_hex` VARCHAR(10) NULL COMMENT 'Color hexadecimal representativo',
    `resena_historica` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tipo_danza_id`) REFERENCES `tipos_danza` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_conjuntos_org_cod` (`organizacion_id`, `codigo`),
    UNIQUE KEY `uk_conjuntos_org_nom` (`organizacion_id`, `nombre`),
    KEY `idx_conjuntos_tipo` (`tipo_danza_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo histórico e institucional de conjuntos folclóricos';

-- 3. TABLA `bloques_conjunto` (SUBDIVISIONES, FILIALES Y ESCUADRAS DE CADA CONJUNTO)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloques_conjunto` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `conjunto_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'BLQ-AREQUIPA-01, BLQ-CENTRAL, etc.',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre de bloque en MAYÚSCULAS',
    `responsable_nombre` VARCHAR(120) NULL,
    `telefono_contacto` VARCHAR(30) NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`conjunto_id`) REFERENCES `conjuntos_folcloricos` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_bloques_conjunto_nom` (`conjunto_id`, `nombre`),
    UNIQUE KEY `uk_bloques_conjunto_cod` (`conjunto_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bloques y filiales de danzarines de un conjunto folclórico';

-- 4. TABLA `edicion_conjunto_inscripciones` (PARTICIPACIÓN FORMAL POR EDICIÓN ANUAL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edicion_conjunto_inscripciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `conjunto_id` BIGINT UNSIGNED NOT NULL,
    `numero_registro_oficial` VARCHAR(50) NULL COMMENT 'Inscripción en la Federación (FRFCP)',
    `nombre_delegado_edicion` VARCHAR(120) NULL,
    `telefono_delegado_edicion` VARCHAR(30) NULL,
    `cantidad_estimada_danzarines` INT UNSIGNED DEFAULT 0,
    `cantidad_bandas` TINYINT UNSIGNED DEFAULT 1,
    `estado` ENUM('INSCRITO', 'CONFIRMADO', 'RETIRADO', 'SANCIONADO') NOT NULL DEFAULT 'INSCRITO',
    `observaciones` TEXT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`conjunto_id`) REFERENCES `conjuntos_folcloricos` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_inscripcion_edicion_conjunto` (`edicion_id`, `conjunto_id`),
    KEY `idx_inscripcion_conjunto` (`conjunto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripción y participación de conjuntos en una edición anual';

-- 5. TABLA `folclore_actividades_edicion` (ESCENARIOS Y HITOS OFICIALES DE LA FESTIVIDAD)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folclore_actividades_edicion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'ESTADIO_CONCURSO_LUCES, PARADA_VENERACION_DIA_1, etc.',
    `titulo` VARCHAR(150) NOT NULL COMMENT 'Título oficial en MAYÚSCULAS',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `lugar_principal` VARCHAR(150) NOT NULL,
    `estado` ENUM('PROGRAMADA', 'EN_EJECUCION', 'CONCLUIDA', 'SUSPENDIDA') NOT NULL DEFAULT 'PROGRAMADA',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_actividades_edicion_cod` (`edicion_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actividades del calendario folclórico anual';

-- 6. TABLA `folclore_programaciones_oficiales` (VERSIONES Y PROCEDENCIA DEL ROL OFICIAL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folclore_programaciones_oficiales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `actividad_id` BIGINT UNSIGNED NOT NULL,
    `version_numero` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1, 2, 3...',
    `etiqueta_version` VARCHAR(50) NOT NULL COMMENT 'ej. Rol Oficial v1.0, Rol Rectificado v1.1',
    `fuente_origen` VARCHAR(120) NOT NULL COMMENT 'ej. Federación Regional de Folklore y Cultura de Puno (FRFCP)',
    `fecha_emision_fuente` DATE NOT NULL,
    `documento_referencia_url` VARCHAR(255) NULL,
    `estado_confirmacion` ENUM('BORRADOR', 'PRELIMINAR', 'OFICIAL_CONFIRMADO', 'RECTIFICADO', 'OBSOLETO') NOT NULL DEFAULT 'PRELIMINAR',
    `es_version_activa` TINYINT(1) NOT NULL DEFAULT 1,
    `notas_version` TEXT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actividad_id`) REFERENCES `folclore_actividades_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_programacion_version` (`actividad_id`, `version_numero`),
    KEY `idx_programacion_activa` (`actividad_id`, `es_version_activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versiones y procedencia de la programación folclórica oficial';

-- 7. TABLA `folclore_orden_presentacion` (TURNOS Y HORARIOS OFICIALES POR AGRUPACIÓN)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folclore_orden_presentacion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `programacion_oficial_id` BIGINT UNSIGNED NOT NULL,
    `conjunto_edicion_id` BIGINT UNSIGNED NOT NULL,
    `orden_sorteo` INT UNSIGNED NOT NULL COMMENT '1, 2, 3... número secuencial de presentación',
    `hora_estimada_partida` TIME NOT NULL,
    `hora_estimada_llegada` TIME NULL,
    `punto_partida_especifico` VARCHAR(150) NULL,
    `estado_turno` ENUM('PROGRAMADO', 'EN_PREPARACION', 'EN_ESCENARIO', 'EN_RUTA', 'FINALIZADO', 'NO_PRESENTO') NOT NULL DEFAULT 'PROGRAMADO',
    `hora_real_partida` DATETIME NULL,
    `hora_real_fin` DATETIME NULL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`programacion_oficial_id`) REFERENCES `folclore_programaciones_oficiales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`conjunto_edicion_id`) REFERENCES `edicion_conjunto_inscripciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_orden_presentacion_sorteo` (`programacion_oficial_id`, `orden_sorteo`),
    UNIQUE KEY `uk_orden_presentacion_conjunto` (`programacion_oficial_id`, `conjunto_edicion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orden y horarios oficiales de presentación de cada conjunto';

-- 8. INTEGRACIÓN RETROCOMPATIBLE CON MÓDULOS DE V1 (CAMPOS OPCIONALES)
-- ------------------------------------------------------------------------------
-- A. En `operacion_salidas`: vinculación opcional a un turno de presentación
SET @existe_col_salida = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operacion_salidas' AND COLUMN_NAME = 'orden_presentacion_id'
);
SET @sql_salida = IF(@existe_col_salida = 0, 
    'ALTER TABLE `operacion_salidas` ADD COLUMN `orden_presentacion_id` BIGINT UNSIGNED NULL AFTER `item_comercial_id`, ADD CONSTRAINT `fk_salida_orden_presentacion` FOREIGN KEY (`orden_presentacion_id`) REFERENCES `folclore_orden_presentacion` (`id`) ON DELETE SET NULL', 
    'SELECT 1'
);
PREPARE stmt_salida FROM @sql_salida;
EXECUTE stmt_salida;
DEALLOCATE PREPARE stmt_salida;

-- B. En `reserva_participantes`: vinculación opcional de danzarín a conjunto y bloque
SET @existe_col_part_conj = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reserva_participantes' AND COLUMN_NAME = 'conjunto_id'
);
SET @sql_part_conj = IF(@existe_col_part_conj = 0, 
    'ALTER TABLE `reserva_participantes` ADD COLUMN `conjunto_id` BIGINT UNSIGNED NULL AFTER `persona_id`, ADD COLUMN `bloque_id` BIGINT UNSIGNED NULL AFTER `conjunto_id`, ADD CONSTRAINT `fk_part_conjunto` FOREIGN KEY (`conjunto_id`) REFERENCES `conjuntos_folcloricos` (`id`) ON DELETE SET NULL, ADD CONSTRAINT `fk_part_bloque` FOREIGN KEY (`bloque_id`) REFERENCES `bloques_conjunto` (`id`) ON DELETE SET NULL', 
    'SELECT 1'
);
PREPARE stmt_part_conj FROM @sql_part_conj;
EXECUTE stmt_part_conj;
DEALLOCATE PREPARE stmt_part_conj;

-- 9. PERMISOS RBAC DEL MÓDULO 07: PROGRAMACIÓN FOLCLÓRICA
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(7, 'folclore.tipos_danza.ver', 'Ver Tipos de Danza', 'Permite consultar el catálogo de tipos y géneros folclóricos'),
(7, 'folclore.tipos_danza.gestionar', 'Gestionar Tipos de Danza', 'Permite crear y editar tipos de danza folclórica'),
(7, 'folclore.conjuntos.ver', 'Ver Conjuntos Folclóricos', 'Permite consultar las agrupaciones y comparsas registradas'),
(7, 'folclore.conjuntos.gestionar', 'Gestionar Conjuntos Folclóricos', 'Permite crear y modificar conjuntos y sus filiales/bloques'),
(7, 'folclore.inscripciones.gestionar', 'Gestionar Inscripciones por Edición', 'Permite registrar la participación de conjuntos en una edición anual'),
(7, 'folclore.programacion.ver', 'Ver Programación Oficial', 'Permite consultar el rol y orden de presentación de la festividad'),
(7, 'folclore.programacion.gestionar', 'Gestionar Programación Oficial', 'Permite registrar versiones y turnos de presentación folclórica'),
(7, 'folclore.programacion.aprobar', 'Aprobar y Oficializar Programación', 'Permite oficializar y activar versiones del rol de presentación')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a Roles (ADMINISTRADOR y SUPERADMIN)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo IN ('superadmin_plataforma', 'admin_organizacion', 'ADMINISTRADOR') AND p.modulo_id = 7;

-- Asignación de lectura a COORDINADOR y VENTAS
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permisos` p
WHERE r.codigo IN ('COORDINADOR', 'COORDINADOR_VENTAS', 'VENTAS', 'operador_produccion') 
  AND p.codigo IN ('folclore.tipos_danza.ver', 'folclore.conjuntos.ver', 'folclore.programacion.ver');

-- 10. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_11_000024_crear_nucleo_programacion_folclorica_f31a.sql', 22)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

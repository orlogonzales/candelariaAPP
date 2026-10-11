-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000020_crear_modulo_postproduccion_y_evolucion_contratos.sql
-- DESCRIPCIÓN: Fase F2.10E-1 & F2.11 - Ciclo de vida contractual íntegro,
--              inmutabilidad de documentos y Módulo de Postproducción Audiovisual.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `contratos_venta` (CICLO DE VIDA Y EVIDENCIA LEGAL CONTRACTUAL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contratos_venta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `codigo_contrato` VARCHAR(40) NOT NULL COMMENT 'Formato CON-YYYY-NNNNNN',
    `version_clausulas` VARCHAR(20) NOT NULL DEFAULT 'v1.0-2026',
    `clausulas_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del pliego de cláusulas normativas Ley 29571',
    `estado_contrato` ENUM('BORRADOR', 'EMITIDO', 'ACEPTADO', 'ANULADO') NOT NULL DEFAULT 'EMITIDO',
    `canal_emision` VARCHAR(30) NOT NULL DEFAULT 'BACKOFFICE',
    `emitido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `emitido_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `aceptado_en` DATETIME DEFAULT NULL,
    `aceptado_ip` VARCHAR(45) DEFAULT NULL,
    `aceptado_user_agent` VARCHAR(255) DEFAULT NULL,
    `aceptado_por_cliente_id` INT UNSIGNED DEFAULT NULL,
    `consentimiento_texto` TEXT DEFAULT NULL,
    `anulado_en` DATETIME DEFAULT NULL,
    `anulado_motivo` VARCHAR(255) DEFAULT NULL,
    `anulado_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_contrato_org_codigo` (`organizacion_id`, `codigo_contrato`),
    KEY `idx_contrato_venta` (`venta_id`),
    KEY `idx_contrato_cliente` (`cliente_id`),
    KEY `idx_contrato_estado` (`organizacion_id`, `estado_contrato`),
    CONSTRAINT `fk_contratos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_contratos_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_contratos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_contratos_usr_emisor` FOREIGN KEY (`emitido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_contratos_usr_anula` FOREIGN KEY (`anulado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ciclo de vida y consentimiento legal de Contratos Turísticos';

-- ------------------------------------------------------------------------------
-- 2. EVOLUCIÓN TABLA `documentos_emitidos` (INMUTABILIDAD Y VERSIONADO ESTRICTO)
-- ------------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS upgrade_documentos_emitidos;
DELIMITER $$
CREATE PROCEDURE upgrade_documentos_emitidos()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'documentos_emitidos' AND column_name = 'version'
    ) THEN
        ALTER TABLE `documentos_emitidos`
            ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `formato`,
            ADD COLUMN `es_vigente` TINYINT(1) NOT NULL DEFAULT 1 AFTER `version`,
            ADD COLUMN `estado_documento` ENUM('VIGENTE', 'SUPERSEDIDO', 'ANULADO') NOT NULL DEFAULT 'VIGENTE' AFTER `es_vigente`,
            ADD COLUMN `motivo_reemision` VARCHAR(255) DEFAULT NULL AFTER `estado_documento`;
    END IF;
END $$
DELIMITER ;
CALL upgrade_documentos_emitidos();
DROP PROCEDURE IF EXISTS upgrade_documentos_emitidos;

-- ------------------------------------------------------------------------------
-- 3. MÓDULO DE POSTPRODUCCIÓN Y MULTIMEDIA (F2.11)
-- ------------------------------------------------------------------------------

-- 3.1 Proyectos de Postproducción
CREATE TABLE IF NOT EXISTS `postproduccion_proyectos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `reserva_id` BIGINT UNSIGNED DEFAULT NULL,
    `prestacion_id` BIGINT UNSIGNED DEFAULT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `codigo_proyecto` VARCHAR(50) NOT NULL COMMENT 'Formato PRY-POST-YYYY-NNNN',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `estado` ENUM('PLANIFICACION', 'EN_EDICION', 'EN_REVISION', 'APROBADO', 'LISTO_ENTREGA', 'ENTREGADO', 'ARCHIVADO') NOT NULL DEFAULT 'EN_EDICION',
    `fotografo_usuario_id` INT UNSIGNED DEFAULT NULL,
    `editor_usuario_id` INT UNSIGNED DEFAULT NULL,
    `fecha_inicio_edicion` DATE DEFAULT NULL,
    `fecha_limite_entrega` DATE DEFAULT NULL,
    `total_archivos` INT UNSIGNED NOT NULL DEFAULT 0,
    `peso_total_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_post_pry_org_codigo` (`organizacion_id`, `codigo_proyecto`),
    KEY `idx_post_pry_reserva` (`reserva_id`),
    KEY `idx_post_pry_prestacion` (`prestacion_id`),
    KEY `idx_post_pry_cliente` (`cliente_id`),
    KEY `idx_post_pry_edicion` (`edicion_id`),
    KEY `idx_post_pry_estado` (`organizacion_id`, `estado`),
    CONSTRAINT `fk_post_pry_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_post_pry_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_post_pry_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_post_pry_prestacion` FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_post_pry_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_post_pry_fotografo` FOREIGN KEY (`fotografo_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_post_pry_editor` FOREIGN KEY (`editor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proyectos de postproducción audiovisual vinculados a reservas';

-- 3.2 Galerías y Álbumes temáticos
CREATE TABLE IF NOT EXISTS `postproduccion_galerias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proyecto_id` BIGINT UNSIGNED NOT NULL,
    `titulo` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(140) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `tipo_galeria` ENUM('FOTOGRAFIA', 'VIDEO', 'MIXTO') NOT NULL DEFAULT 'FOTOGRAFIA',
    `portada_archivo_id` BIGINT UNSIGNED DEFAULT NULL,
    `es_publica_cliente` TINYINT(1) NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_galeria_proyecto` (`proyecto_id`),
    CONSTRAINT `fk_galeria_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_galeria_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `postproduccion_proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Álbumes y galerías temáticas de un proyecto';

-- 3.3 Archivos Multimedia de Alta Resolución
CREATE TABLE IF NOT EXISTS `postproduccion_archivos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proyecto_id` BIGINT UNSIGNED NOT NULL,
    `galeria_id` BIGINT UNSIGNED NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_almacenamiento` VARCHAR(255) NOT NULL,
    `driver_almacenamiento` ENUM('LOCAL', 'S3') NOT NULL DEFAULT 'LOCAL',
    `ruta_privada` VARCHAR(255) NOT NULL,
    `tipo_archivo` ENUM('FOTO', 'VIDEO', 'DOCUMENTO') NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `peso_bytes` BIGINT UNSIGNED NOT NULL,
    `checksum_sha256` CHAR(64) NOT NULL,
    `ancho_px` INT UNSIGNED DEFAULT NULL,
    `alto_px` INT UNSIGNED DEFAULT NULL,
    `duracion_segundos` INT UNSIGNED DEFAULT NULL,
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('SUBIDO', 'EN_PROCESO', 'LISTO', 'RECHAZADO') NOT NULL DEFAULT 'LISTO',
    `es_destacado` TINYINT(1) NOT NULL DEFAULT 0,
    `subido_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_archivo_galeria` (`galeria_id`),
    KEY `idx_archivo_proyecto` (`proyecto_id`),
    CONSTRAINT `fk_arch_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_arch_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `postproduccion_proyectos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_arch_galeria` FOREIGN KEY (`galeria_id`) REFERENCES `postproduccion_galerias` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_arch_subido_usr` FOREIGN KEY (`subido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archivos fotográficos y audiovisuales de alta resolución';

-- 3.4 Entregas Parciales y Revisiones
CREATE TABLE IF NOT EXISTS `postproduccion_entregas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proyecto_id` BIGINT UNSIGNED NOT NULL,
    `numero_entrega` INT UNSIGNED NOT NULL DEFAULT 1,
    `titulo` VARCHAR(150) NOT NULL,
    `tipo_entrega` ENUM('PARCIAL', 'FINAL', 'REVISION') NOT NULL DEFAULT 'PARCIAL',
    `estado_entrega` ENUM('PREPARADA', 'PUBLICADA', 'APROBADA_CLIENTE', 'OBSERVADA_CLIENTE') NOT NULL DEFAULT 'PUBLICADA',
    `notas_entrega` TEXT DEFAULT NULL,
    `entregado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_caducidad_enlace` DATETIME DEFAULT NULL,
    `creado_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `aprobado_por_cliente_en` DATETIME DEFAULT NULL,
    `observaciones_cliente` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_entrega_proyecto` (`proyecto_id`),
    CONSTRAINT `fk_entrega_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_entrega_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `postproduccion_proyectos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_entrega_usr` FOREIGN KEY (`creado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hitos y paquetes de entregas parciales y finales al cliente';

-- 3.5 Relación Entrega - Archivos (Pivote)
CREATE TABLE IF NOT EXISTS `postproduccion_entrega_archivos` (
    `entrega_id` BIGINT UNSIGNED NOT NULL,
    `archivo_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`entrega_id`, `archivo_id`),
    CONSTRAINT `fk_ent_arch_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `postproduccion_entregas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ent_arch_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `postproduccion_archivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación entre entregas y archivos asociados';

-- 3.6 Auditoría de Streaming y Descargas
CREATE TABLE IF NOT EXISTS `postproduccion_descargas_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `archivo_id` BIGINT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED DEFAULT NULL,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `bytes_transferidos` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `descargado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_descarga_archivo` (`archivo_id`),
    KEY `idx_descarga_cliente` (`cliente_id`),
    CONSTRAINT `fk_descarga_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_descarga_archivo` FOREIGN KEY (`archivo_id`) REFERENCES `postproduccion_archivos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_descarga_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_descarga_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoría de streaming y descargas de material multimedia';

-- ------------------------------------------------------------------------------
-- 4. REGISTRO EN MODULOS Y PERMISOS RBAC (MÓDULO 24: POSTPRODUCCIÓN MULTIMEDIA)
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`)
VALUES (24, 'postproduccion', 'Postproducción y Galerías Multimedia', 'Gestión de proyectos audiovisuales, álbumes de fotos y video, entregas a clientes y descargas seguras', 'fa-solid fa-photo-film', 24, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`), `estado` = VALUES(`estado`);

INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`)
VALUES
(24, 'postproduccion.proyectos.ver', 'Ver Proyectos de Postproducción', 'Permite consultar y listar los proyectos y galerías multimedia'),
(24, 'postproduccion.proyectos.crear', 'Crear Proyecto de Postproducción', 'Permite registrar nuevos proyectos asociados a ediciones y prestaciones'),
(24, 'postproduccion.proyectos.editar', 'Editar Proyecto de Postproducción', 'Permite modificar metadatos y estados de proyectos audiovisuales'),
(24, 'postproduccion.archivos.subir', 'Subir Archivos Multimedia', 'Permite cargar fotografías y videos de alta resolución a las galerías'),
(24, 'postproduccion.archivos.descargar', 'Descargar Material Original', 'Permite descargar archivos audiovisuales maestros en alta calidad'),
(24, 'postproduccion.entregas.gestionar', 'Gestionar Entregas a Clientes', 'Permite crear entregas parciales/finales y publicarlas al cliente')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos a roles ADMINISTRADOR y COORDINADOR
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo IN ('ADMINISTRADOR', 'COORDINADOR')
  AND p.modulo_id = 24;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- MIGRACIÓN: 2026_10_01_000001_crear_tablas_fundacionales.sql
-- DESCRIPCIÓN: Creación de tablas de gobernanza, tenants, usuarios, roles,
--              ediciones candelaria, menú dinámico, auditoría y control.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `migraciones_control` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL,
    `lote` INT UNSIGNED NOT NULL DEFAULT 1,
    `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_migraciones_nombre` (`migracion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organizaciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre_comercial` VARCHAR(150) NOT NULL,
    `razon_social` VARCHAR(200) DEFAULT NULL,
    `numero_documento` VARCHAR(30) DEFAULT NULL,
    `correo_contacto` VARCHAR(150) DEFAULT NULL,
    `telefono_contacto` VARCHAR(50) DEFAULT NULL,
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `marca_configuracion_json` JSON DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_organizaciones_codigo` (`codigo`),
    KEY `idx_organizaciones_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `precio_mensual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_planes_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modulos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `icono_fontawesome` VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-cube',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `es_nucleo` TINYINT(1) NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    UNIQUE KEY `uk_modulos_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `capacidades_plan` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT UNSIGNED NOT NULL,
    `modulo_id` INT UNSIGNED NOT NULL,
    `configuracion_json` JSON DEFAULT NULL,
    FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_capacidades_plan_modulo` (`plan_id`, `modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL,
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `es_sistema` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_roles_org_codigo` (`organizacion_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permisos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `modulo_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(80) NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_permisos_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rol_permisos` (
    `rol_id` INT UNSIGNED NOT NULL,
    `permiso_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`rol_id`, `permiso_id`),
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL,
    `nombre_completo` VARCHAR(150) NOT NULL,
    `correo_electronico` VARCHAR(150) NOT NULL,
    `telefono_whatsapp` VARCHAR(30) NOT NULL,
    `contrasena_hash` VARCHAR(255) NOT NULL,
    `es_superadmin_plataforma` TINYINT(1) NOT NULL DEFAULT 0,
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
    `ultimo_acceso_en` DATETIME DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_usuarios_correo` (`correo_electronico`),
    KEY `idx_usuarios_organizacion` (`organizacion_id`),
    KEY `idx_usuarios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuario_roles` (
    `usuario_id` INT UNSIGNED NOT NULL,
    `rol_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`usuario_id`, `rol_id`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ediciones_candelaria` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `ano` SMALLINT UNSIGNED NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `lema` VARCHAR(255) DEFAULT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `fase_actual` ENUM('PREOPERACION', 'OPERACION', 'POSTPRODUCCION', 'CERRADA') NOT NULL DEFAULT 'PREOPERACION',
    `es_activa` TINYINT(1) NOT NULL DEFAULT 0,
    `flyer_oficial_url` VARCHAR(255) DEFAULT NULL,
    `configuracion_json` JSON DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_ediciones_org_ano` (`organizacion_id`, `ano`),
    KEY `idx_ediciones_fase` (`fase_actual`),
    KEY `idx_ediciones_activa` (`es_activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_opciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `padre_id` INT UNSIGNED DEFAULT NULL,
    `modulo_id` INT UNSIGNED NOT NULL,
    `titulo` VARCHAR(100) NOT NULL,
    `tooltip` VARCHAR(150) NOT NULL,
    `ruta` VARCHAR(200) NOT NULL,
    `icono_fontawesome` VARCHAR(80) NOT NULL DEFAULT 'fa-solid fa-circle',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `capacidad_requerida` VARCHAR(80) DEFAULT NULL,
    `permiso_requerido` VARCHAR(80) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`padre_id`) REFERENCES `menu_opciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    KEY `idx_menu_padre` (`padre_id`),
    KEY `idx_menu_orden` (`orden`),
    KEY `idx_menu_nivel` (`nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sesiones` (
    `id` VARCHAR(128) PRIMARY KEY,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `direccion_ip` VARCHAR(45) NOT NULL,
    `agente_usuario` TEXT DEFAULT NULL,
    `carga_util` LONGTEXT NOT NULL,
    `ultima_actividad` INT UNSIGNED NOT NULL,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    KEY `idx_sesiones_actividad` (`ultima_actividad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditoria_operaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `modulo` VARCHAR(60) NOT NULL,
    `accion` VARCHAR(60) NOT NULL,
    `entidad_tipo` VARCHAR(80) NOT NULL,
    `entidad_id` VARCHAR(80) NOT NULL,
    `datos_previos_json` JSON DEFAULT NULL,
    `datos_nuevos_json` JSON DEFAULT NULL,
    `direccion_ip` VARCHAR(45) NOT NULL,
    `agente_usuario` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    KEY `idx_auditoria_org` (`organizacion_id`),
    KEY `idx_auditoria_usuario` (`usuario_id`),
    KEY `idx_auditoria_modulo` (`modulo`),
    KEY `idx_auditoria_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_01_000001_crear_tablas_fundacionales.sql', 1);

SET FOREIGN_KEY_CHECKS = 1;

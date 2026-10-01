-- ==============================================================================
-- CANDELARIAAPP - ESQUEMA OFICIAL REPRODUCIBLE (esquema_base.sql)
-- ==============================================================================
-- Motor: MySQL 8.0+ / MariaDB 10.5+
-- Juego de caracteres: utf8mb4 / Colación: utf8mb4_unicode_ci
-- Arquitectura: SaaS-Ready, API-First, Foundation Fase 0
-- Convención: Nomenclatura en español estricta
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `migraciones_control`;
CREATE TABLE `migraciones_control` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL,
    `lote` INT UNSIGNED NOT NULL DEFAULT 1,
    `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_migraciones_nombre` (`migracion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control de versiones de migraciones ejecutadas';

-- ------------------------------------------------------------------------------
-- 2. ORGANIZACIONES / TENANTS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `organizaciones`;
CREATE TABLE `organizaciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Identificador único amigable del tenant',
    `nombre_comercial` VARCHAR(150) NOT NULL COMMENT 'Nombre comercial (ej. O.G. Estudio Creativo)',
    `razon_social` VARCHAR(200) DEFAULT NULL,
    `numero_documento` VARCHAR(30) DEFAULT NULL COMMENT 'RUC u otro identificador fiscal',
    `correo_contacto` VARCHAR(150) DEFAULT NULL,
    `telefono_contacto` VARCHAR(50) DEFAULT NULL,
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `marca_configuracion_json` JSON DEFAULT NULL COMMENT 'Colores, logos, membretes y personalización de marca',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_organizaciones_codigo` (`codigo`),
    KEY `idx_organizaciones_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenants u organizaciones suscriptoras';

-- ------------------------------------------------------------------------------
-- 3. PLANES Y CAPACIDADES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `planes`;
CREATE TABLE `planes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `precio_mensual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_planes_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planes de licenciamiento del software';

DROP TABLE IF EXISTS `modulos`;
CREATE TABLE `modulos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `icono_fontawesome` VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-cube',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `es_nucleo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es módulo esencial obligatorio',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    UNIQUE KEY `uk_modulos_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo oficial de dominios funcionales';

DROP TABLE IF EXISTS `capacidades_plan`;
CREATE TABLE `capacidades_plan` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT UNSIGNED NOT NULL,
    `modulo_id` INT UNSIGNED NOT NULL,
    `configuracion_json` JSON DEFAULT NULL COMMENT 'Límites, cuotas y parámetros por plan',
    FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_capacidades_plan_modulo` (`plan_id`, `modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Módulos y capacidades habilitadas por cada plan';

-- ------------------------------------------------------------------------------
-- 4. USUARIOS, ROLES Y PERMISOS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL para roles globales de plataforma',
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `es_sistema` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 para roles protegidos no editables',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_roles_org_codigo` (`organizacion_id`, `codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles de seguridad por tenant o plataforma';

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE `permisos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `modulo_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(80) NOT NULL COMMENT 'ej. clientes.ver, pagos.registrar',
    `nombre` VARCHAR(120) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_permisos_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permisos atómicos del sistema';

DROP TABLE IF EXISTS `rol_permisos`;
CREATE TABLE `rol_permisos` (
    `rol_id` INT UNSIGNED NOT NULL,
    `permiso_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`rol_id`, `permiso_id`),
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asociación de permisos a roles';

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL si es Superadmin de Plataforma',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas de usuario de la plataforma';

DROP TABLE IF EXISTS `usuario_roles`;
CREATE TABLE `usuario_roles` (
    `usuario_id` INT UNSIGNED NOT NULL,
    `rol_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`usuario_id`, `rol_id`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de roles a usuarios';

-- ------------------------------------------------------------------------------
-- 5. EDICIONES CANDELARIA
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ediciones_candelaria`;
CREATE TABLE `ediciones_candelaria` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `ano` SMALLINT UNSIGNED NOT NULL COMMENT 'Año de la edición (ej. 2027, 2028)',
    `nombre` VARCHAR(120) NOT NULL COMMENT 'ej. CANDELARIA 2027',
    `lema` VARCHAR(255) DEFAULT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `fase_actual` ENUM('PREOPERACION', 'OPERACION', 'POSTPRODUCCION', 'CERRADA') NOT NULL DEFAULT 'PREOPERACION',
    `es_activa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 para la edición en curso',
    `flyer_oficial_url` VARCHAR(255) DEFAULT NULL,
    `configuracion_json` JSON DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_ediciones_org_ano` (`organizacion_id`, `ano`),
    KEY `idx_ediciones_fase` (`fase_actual`),
    KEY `idx_ediciones_activa` (`es_activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ediciones anuales de la Festividad de la Candelaria';

-- ------------------------------------------------------------------------------
-- 6. MENÚ DINÁMICO
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `menu_opciones`;
CREATE TABLE `menu_opciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `padre_id` INT UNSIGNED DEFAULT NULL,
    `modulo_id` INT UNSIGNED NOT NULL,
    `titulo` VARCHAR(100) NOT NULL,
    `tooltip` VARCHAR(150) NOT NULL COMMENT 'Texto descriptivo para tooltip accesible',
    `ruta` VARCHAR(200) NOT NULL,
    `icono_fontawesome` VARCHAR(80) NOT NULL DEFAULT 'fa-solid fa-circle',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nivel 1, 2 o 3 máximo',
    `capacidad_requerida` VARCHAR(80) DEFAULT NULL,
    `permiso_requerido` VARCHAR(80) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`padre_id`) REFERENCES `menu_opciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
    KEY `idx_menu_padre` (`padre_id`),
    KEY `idx_menu_orden` (`orden`),
    KEY `idx_menu_nivel` (`nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opciones de navegación dinámica (hasta 3 niveles)';

-- ------------------------------------------------------------------------------
-- 7. SESIONES Y AUDITORÍA
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `sesiones`;
CREATE TABLE `sesiones` (
    `id` VARCHAR(128) PRIMARY KEY,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `direccion_ip` VARCHAR(45) NOT NULL,
    `agente_usuario` TEXT DEFAULT NULL,
    `carga_util` LONGTEXT NOT NULL,
    `ultima_actividad` INT UNSIGNED NOT NULL,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    KEY `idx_sesiones_actividad` (`ultima_actividad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Almacenamiento seguro de sesiones web';

DROP TABLE IF EXISTS `auditoria_operaciones`;
CREATE TABLE `auditoria_operaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `modulo` VARCHAR(60) NOT NULL,
    `accion` VARCHAR(60) NOT NULL COMMENT 'CREAR, ACTUALIZAR, ELIMINAR, AUTENTICAR, ETC.',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pista de auditoría inmutable de operaciones sensibles';

SET FOREIGN_KEY_CHECKS = 1;

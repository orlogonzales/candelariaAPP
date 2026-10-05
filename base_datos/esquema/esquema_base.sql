-- ==============================================================================
-- CANDELARIAAPP - ESQUEMA OFICIAL REPRODUCIBLE (esquema_base.sql)
-- ==============================================================================
-- Motor: MySQL 8.0+ / MariaDB 10.5+ (Probado en MySQL 8.4 LTS)
-- Juego de caracteres: utf8mb4 / Colación: utf8mb4_unicode_ci
-- Arquitectura: SaaS-Ready, API-First, Fase 1.1A
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
    `tipo_documento_id` TINYINT UNSIGNED DEFAULT 2,
    `numero_documento` VARCHAR(30) DEFAULT NULL COMMENT 'RUC u otro identificador fiscal',
    `direccion` VARCHAR(255) DEFAULT NULL,
    `codigo_pais` CHAR(2) NOT NULL DEFAULT 'PE' COMMENT 'Código ISO 3166-1 alpha-2',
    `departamento` VARCHAR(100) DEFAULT NULL,
    `provincia` VARCHAR(100) DEFAULT NULL,
    `distrito` VARCHAR(100) DEFAULT NULL,
    `correo_contacto` VARCHAR(150) DEFAULT NULL,
    `sitio_web` VARCHAR(200) DEFAULT NULL,
    `contacto_nombre` VARCHAR(150) DEFAULT NULL,
    `contacto_cargo` VARCHAR(100) DEFAULT NULL,
    `telefono_contacto` VARCHAR(50) DEFAULT NULL,
    `telefono_whatsapp` VARCHAR(30) DEFAULT NULL,
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `isotipo_url` VARCHAR(255) DEFAULT NULL,
    `marca_configuracion_json` JSON DEFAULT NULL COMMENT 'Colores, logos, membretes y personalización de marca',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT,
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
-- 4. SEGURIDAD: ROLES Y PERMISOS
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
    `codigo` VARCHAR(80) NOT NULL COMMENT 'ej. usuarios.ver, usuarios.crear',
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

-- ------------------------------------------------------------------------------
-- 5. IDENTIDAD: DOCUMENTOS Y PERSONAS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tipos_documento`;
CREATE TABLE `tipos_documento` (
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

DROP TABLE IF EXISTS `personas`;
CREATE TABLE `personas` (
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
-- 6. USUARIOS Y CUENTAS DE ACCESO
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL si es Superadmin de Plataforma',
    `persona_id` INT UNSIGNED NOT NULL,
    `nombre_usuario` VARCHAR(60) NOT NULL COMMENT 'Identificador único de login del usuario',
    `nombre_completo` VARCHAR(150) NOT NULL COMMENT 'Nombre desnormalizado para visualización rápida',
    `correo_electronico` VARCHAR(150) NOT NULL COMMENT 'Correo de acceso y notificaciones',
    `telefono_whatsapp` VARCHAR(30) DEFAULT NULL,
    `contrasena_hash` VARCHAR(255) NOT NULL,
    `es_superadmin_plataforma` TINYINT(1) NOT NULL DEFAULT 0,
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
    `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta` DATETIME DEFAULT NULL,
    `ultimo_acceso_en` DATETIME DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_usuarios_persona` (`persona_id`),
    UNIQUE KEY `uk_usuarios_nombre_usuario` (`nombre_usuario`),
    UNIQUE KEY `uk_usuarios_correo` (`correo_electronico`),
    KEY `idx_usuarios_organizacion` (`organizacion_id`),
    KEY `idx_usuarios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas de usuario vinculadas obligatoriamente a personas reales';

DROP TABLE IF EXISTS `usuario_roles`;
CREATE TABLE `usuario_roles` (
    `usuario_id` INT UNSIGNED NOT NULL,
    `rol_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`usuario_id`, `rol_id`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de roles a usuarios';

-- ------------------------------------------------------------------------------
-- 7. EDICIONES CANDELARIA
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
-- 8. MENÚ DINÁMICO
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
-- 9. SESIONES WEB
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

-- ------------------------------------------------------------------------------
-- 10. ACTORES DE SISTEMA Y CANALES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `actores_sistema`;
CREATE TABLE `actores_sistema` (
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

DROP TABLE IF EXISTS `canales`;
CREATE TABLE `canales` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'APP, WEB, API, APP_MOVIL, WHATSAPP, IMPORTACION, API_PARTNER',
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_canales_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo extensible de canales de ingreso de operaciones';

-- ------------------------------------------------------------------------------
-- 11. AUDITORÍA DE OPERACIONES (INMUTABLE Y DUAL CON RESTRICCIÓN ESTRUCTURAL)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `auditoria_operaciones`;
CREATE TABLE `auditoria_operaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED NOT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL COMMENT 'UUID v4 para rastreo transversal de operaciones',
    `modulo` VARCHAR(60) NOT NULL,
    `accion` VARCHAR(60) NOT NULL COMMENT 'CREAR, ACTUALIZAR, ELIMINAR, AUTENTICAR, ETC.',
    `entidad_tipo` VARCHAR(80) NOT NULL,
    `entidad_id` VARCHAR(80) NOT NULL,
    `datos_previos_json` JSON DEFAULT NULL,
    `datos_nuevos_json` JSON DEFAULT NULL,
    `origen_ip` VARCHAR(45) DEFAULT NULL COMMENT 'Nullable para CLI, crons y workers sin cliente HTTP',
    `agente_usuario` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    KEY `idx_auditoria_org` (`organizacion_id`),
    KEY `idx_auditoria_usuario` (`usuario_id`),
    KEY `idx_auditoria_actor_sistema` (`actor_sistema_id`),
    KEY `idx_auditoria_canal` (`canal_id`),
    KEY `idx_auditoria_correlacion` (`correlacion_id`),
    KEY `idx_auditoria_modulo` (`modulo`),
    KEY `idx_auditoria_entidad` (`entidad_tipo`, `entidad_id`),
    KEY `idx_auditoria_creado` (`creado_en`),
    CONSTRAINT `chk_auditoria_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pista de auditoría inmutable de operaciones sensibles con validación estructural de actor';

-- ------------------------------------------------------------------------------
-- 12. PARÁMETROS TIPADOS Y GOBERNADOS (ÁMBITOS PLATAFORMA Y ORGANIZACIÓN)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `parametros_configuracion`;
CREATE TABLE `parametros_configuracion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL para ámbito PLATAFORMA; id del tenant para ámbito ORGANIZACION',
    `ambito` ENUM('PLATAFORMA', 'ORGANIZACION') NOT NULL DEFAULT 'PLATAFORMA',
    `codigo` VARCHAR(80) NOT NULL COMMENT 'Identificador único y canónico del parámetro (ej. plataforma.monto_minimo_pago_pe)',
    `tipo_dato` ENUM('STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'DATE', 'DATETIME') NOT NULL DEFAULT 'STRING',
    `valor` TEXT DEFAULT NULL COMMENT 'Valor almacenado como cadena tipada, validado y casteado por backend',
    `valor_defecto` TEXT DEFAULT NULL COMMENT 'Valor por defecto de contingencia',
    `etiqueta` VARCHAR(150) NOT NULL COMMENT 'Nombre legible del parámetro',
    `descripcion` VARCHAR(255) DEFAULT NULL COMMENT 'Propósito y documentación del parámetro',
    `reglas_validacion_json` JSON DEFAULT NULL COMMENT 'Reglas de validación: min, max, regex, opciones',
    `es_sistema` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si es parámetro estructural inmutable en definición',
    `es_publico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si puede ser expuesto de forma segura sin autenticación',
    `es_editable` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 si solo puede modificarse por consola o migración',
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

SET FOREIGN_KEY_CHECKS = 1;

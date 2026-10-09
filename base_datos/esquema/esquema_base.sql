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
    `tipo_documento_id` TINYINT UNSIGNED DEFAULT NULL,
    `numero_documento` VARCHAR(30) DEFAULT NULL,
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
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_personas_org_doc` (`organizacion_id`, `tipo_documento_id`, `numero_documento`),
    KEY `idx_personas_tipo` (`tipo_persona`),
    KEY `idx_personas_estado` (`estado`),
    KEY `idx_personas_apellidos` (`apellidos`),
    KEY `idx_personas_razon_social` (`razon_social`),
    KEY `idx_personas_org_whatsapp` (`organizacion_id`, `telefono_whatsapp`),
    CONSTRAINT `chk_personas_tipo_consistencia` CHECK (
        (`tipo_persona` = 'NATURAL' AND `nombres` IS NOT NULL AND `apellidos` IS NOT NULL AND `razon_social` IS NULL)
        OR
        (`tipo_persona` = 'JURIDICA' AND `razon_social` IS NOT NULL AND `nombres` IS NULL AND `apellidos` IS NULL)
    ),
    CONSTRAINT `chk_personas_documento_coherencia` CHECK (
        (`tipo_documento_id` IS NULL AND `numero_documento` IS NULL)
        OR
        (`tipo_documento_id` IS NOT NULL AND `numero_documento` IS NOT NULL)
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
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Slug canónico único por tenant (ej. candelaria-2027)',
    `nombre` VARCHAR(120) NOT NULL COMMENT 'Nombre formal normalizado en MAYÚSCULAS',
    `anio` SMALLINT UNSIGNED NOT NULL COMMENT 'Año calendario de la edición (ej. 2027)',
    `estado` VARCHAR(30) NOT NULL DEFAULT 'PREOPERACION' COMMENT 'Ciclo de vida gobernado',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL COMMENT 'Lema o descripción general de la edición',
    `es_actual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es la edición operativa activa por defecto del tenant',
    `flyer_oficial_url` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta relativa de imagen publicitaria oficial',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_ediciones_org_anio` (`organizacion_id`, `anio`),
    UNIQUE KEY `uk_ediciones_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_ediciones_estado` (`estado`),
    KEY `idx_ediciones_actual` (`es_actual`),
    CONSTRAINT `chk_ediciones_fechas` CHECK (`fecha_inicio` <= `fecha_fin`),
    CONSTRAINT `chk_ediciones_anio` CHECK (`anio` >= 2000 AND `anio` <= 2100),
    CONSTRAINT `chk_ediciones_estado` CHECK (`estado` IN ('PREOPERACION', 'OPERACION', 'POSTPRODUCCION_ENTREGA', 'CERRADA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ediciones anuales de la Festividad de la Virgen de la Candelaria';

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

-- ------------------------------------------------------------------------------
-- 13. CLIENTES Y PERFILES COMERCIALES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED NOT NULL,
    `estado_comercial` ENUM('CONTACTO', 'PROSPECTO', 'CLIENTE', 'INACTIVO') NOT NULL DEFAULT 'CONTACTO',
    `consentimiento_operativo` TINYINT(1) NOT NULL DEFAULT 0,
    `consentimiento_operativo_en` DATETIME DEFAULT NULL,
    `consentimiento_promocional` TINYINT(1) NOT NULL DEFAULT 0,
    `consentimiento_promocional_en` DATETIME DEFAULT NULL,
    `notas_comerciales` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_clientes_org_persona` (`organizacion_id`, `persona_id`),
    UNIQUE KEY `uk_clientes_compuesta` (`id`, `organizacion_id`),
    KEY `idx_clientes_estado` (`estado_comercial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfil comercial único y transversal por persona y tenant';

-- ------------------------------------------------------------------------------
-- 14. CONSENTIMIENTOS DE CLIENTE (BITÁCORA APPEND-ONLY)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `consentimientos_cliente`;
CREATE TABLE `consentimientos_cliente` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `tipo` ENUM('OPERATIVO', 'PROMOCIONAL') NOT NULL,
    `accion` ENUM('OTORGAR', 'REVOCAR') NOT NULL,
    `canal` VARCHAR(50) NOT NULL COMMENT 'Canal de recepción del consentimiento (ej. WHATSAPP, WEB, PRESENCIAL)',
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` VARCHAR(20) NOT NULL COMMENT 'HUMANO o SISTEMA',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL COMMENT 'UUID v4 para correlación y trazabilidad',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    KEY `idx_consentimientos_cliente_tipo` (`cliente_id`, `tipo`),
    KEY `idx_consentimientos_org` (`organizacion_id`),
    KEY `idx_consentimientos_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_consentimientos_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de auditoría append-only para consentimientos operativos y promocionales';

-- ------------------------------------------------------------------------------
-- 15. CATÁLOGO DE ORÍGENES COMERCIALES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `origenes_comerciales`;
CREATE TABLE `origenes_comerciales` (
    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico en MAYÚSCULAS (ej. WEB_ORGANICA, REFERIDO)',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_origenes_comerciales_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_origenes_comerciales_activo` (`organizacion_id`, `activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo extensible de fuentes de captación comercial por organización';

-- ------------------------------------------------------------------------------
-- 16. OPORTUNIDADES COMERCIALES (PIPELINE DE EDICIÓN)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `crm_oportunidades`;
CREATE TABLE `crm_oportunidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `usuario_asignado_id` INT UNSIGNED DEFAULT NULL,
    `origen_comercial_id` SMALLINT UNSIGNED DEFAULT NULL,
    `titulo` VARCHAR(120) NOT NULL COMMENT 'Concepto descriptivo de la intención comercial',
    `etapa` ENUM('NUEVA','CONTACTADA','CALIFICADA','COTIZACION','NEGOCIACION','GANADA','PERDIDA') NOT NULL DEFAULT 'NUEVA',
    `valor_estimado` DECIMAL(12, 2) DEFAULT NULL,
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot ISO 4217 de la configuración institucional al momento de creación',
    `proximo_seguimiento_en` DATETIME DEFAULT NULL,
    `motivo_perdida` ENUM('PRECIO','COMPETENCIA','DESISTIO_VIAJE','FECHAS_INCOMPATIBLES','SIN_RESPUESTA','OTRO') DEFAULT NULL,
    `motivo_perdida_detalle` VARCHAR(255) DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_asignado_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`origen_comercial_id`) REFERENCES `origenes_comerciales` (`id`) ON DELETE RESTRICT,
    KEY `idx_crm_oportunidades_org_edicion_cliente` (`organizacion_id`, `edicion_id`, `cliente_id`),
    KEY `idx_crm_oportunidades_org_etapa` (`organizacion_id`, `etapa`),
    KEY `idx_crm_oportunidades_asignado` (`organizacion_id`, `usuario_asignado_id`, `etapa`),
    KEY `idx_crm_oportunidades_seguimiento` (`organizacion_id`, `proximo_seguimiento_en`),
    KEY `idx_crm_oportunidades_origen` (`organizacion_id`, `origen_comercial_id`),
    CONSTRAINT `chk_crm_oportunidades_valor` CHECK (`valor_estimado` IS NULL OR `valor_estimado` >= 0),
    CONSTRAINT `chk_crm_oportunidades_perdida` CHECK (
        (`etapa` <> 'PERDIDA' AND `motivo_perdida` IS NULL AND `motivo_perdida_detalle` IS NULL)
        OR
        (`etapa` = 'PERDIDA' AND `motivo_perdida` IS NOT NULL AND (`motivo_perdida` <> 'OTRO' OR (`motivo_perdida_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_perdida_detalle`)) > 0)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Oportunidades comerciales contextualizadas a una edición y cliente';

-- ------------------------------------------------------------------------------
-- 17. HISTORIAL DE ETAPAS DE OPORTUNIDADES (BITÁCORA APPEND-ONLY)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `crm_oportunidad_historial_etapas`;
CREATE TABLE `crm_oportunidad_historial_etapas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `oportunidad_id` BIGINT UNSIGNED NOT NULL,
    `etapa_anterior` VARCHAR(30) DEFAULT NULL,
    `etapa_nueva` VARCHAR(30) NOT NULL,
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oportunidad_id`) REFERENCES `crm_oportunidades` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    KEY `idx_crm_historial_oportunidad` (`organizacion_id`, `oportunidad_id`, `creado_en`),
    KEY `idx_crm_historial_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_crm_historial_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial append-only de transiciones de etapas en oportunidades';

-- ------------------------------------------------------------------------------
-- 18. INTERACCIONES COMERCIALES (BITÁCORA APPEND-ONLY)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `crm_interacciones`;
CREATE TABLE `crm_interacciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `oportunidad_id` BIGINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED NOT NULL,
    `tipo` ENUM('LLAMADA', 'WHATSAPP', 'CORREO', 'REUNION', 'NOTA_INTERNA') NOT NULL,
    `direccion` ENUM('ENTRANTE', 'SALIENTE', 'INTERNA') NOT NULL,
    `resumen` VARCHAR(255) NOT NULL,
    `detalle` TEXT DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oportunidad_id`) REFERENCES `crm_oportunidades` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    KEY `idx_crm_interacciones_cliente` (`organizacion_id`, `cliente_id`, `creado_en`),
    KEY `idx_crm_interacciones_oportunidad` (`organizacion_id`, `oportunidad_id`, `creado_en`),
    KEY `idx_crm_interacciones_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_crm_interacciones_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_crm_interacciones_tipo_direccion` CHECK (
        (`tipo` = 'NOTA_INTERNA' AND `direccion` = 'INTERNA')
        OR
        (`tipo` <> 'NOTA_INTERNA')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora inmutable de interacciones comerciales de clientes y oportunidades';

-- ------------------------------------------------------------------------------
-- 19. CATEGORÍAS DE CATÁLOGO (TAXONOMÍA COMERCIAL)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categorias_items`;
CREATE TABLE `categorias_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código taxonómico único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_categorias_items_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_categorias_items_org_estado` (`organizacion_id`, `estado`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías taxonómicas para ítems comerciales del tenant';

-- ------------------------------------------------------------------------------
-- 20. ÍTEMS COMERCIALES (MAESTRO DE BIENES Y PRESTACIONES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `items_comerciales`;
CREATE TABLE `items_comerciales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `categoria_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Código único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(150) NOT NULL,
    `tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL,
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'UNIDAD, PERSONA, NOCHE, HABITACION, TICKET, SERVICIO, TRAMO, DIA, HORA',
    `descripcion` TEXT DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias_items` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_items_comerciales_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_items_comerciales_org_categoria` (`organizacion_id`, `categoria_id`),
    KEY `idx_items_comerciales_org_tipo_estado` (`organizacion_id`, `tipo`, `estado`),
    CONSTRAINT `chk_items_comerciales_unidad` CHECK (`unidad_medida` IN ('UNIDAD', 'PERSONA', 'NOCHE', 'HABITACION', 'TICKET', 'SERVICIO', 'TRAMO', 'DIA', 'HORA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maestro de bienes y prestaciones comerciales por organización';

-- ------------------------------------------------------------------------------
-- 21. PAQUETES COMERCIALES Y COMPOSICIÓN DE ÍTEMS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `paquetes`;
CREATE TABLE `paquetes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Código único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_paquetes_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_paquetes_org_estado` (`organizacion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paquetes estructurados que agrupan ítems comerciales';

DROP TABLE IF EXISTS `paquete_items`;
CREATE TABLE `paquete_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `paquete_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_paquete_items_composicion` (`paquete_id`, `item_comercial_id`),
    KEY `idx_paquete_items_item` (`item_comercial_id`),
    CONSTRAINT `chk_paquete_items_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Composición de ítems incluidos dentro de un paquete';

-- ------------------------------------------------------------------------------
-- 22. OFERTAS DE ÍTEMS Y PAQUETES POR EDICIÓN FOLCLÓRICA
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ofertas_items_edicion`;
CREATE TABLE `ofertas_items_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `capacidad_referencial` INT UNSIGNED DEFAULT NULL COMMENT 'Dato informativo comercial, no inventario transaccional',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`),
    KEY `idx_ofertas_items_edicion_estado` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación comercial de un ítem para una edición folclórica';

DROP TABLE IF EXISTS `ofertas_paquetes_edicion`;
CREATE TABLE `ofertas_paquetes_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `paquete_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `capacidad_referencial` INT UNSIGNED DEFAULT NULL COMMENT 'Dato informativo comercial, no inventario transaccional',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`),
    KEY `idx_ofertas_paquetes_edicion_estado` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación comercial de un paquete para una edición folclórica';

-- ------------------------------------------------------------------------------
-- 23. TARIFAS VIGENTES E HISTORIAL APPEND-ONLY
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tarifas_items_edicion`;
CREATE TABLE `tarifas_items_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `oferta_item_id` INT UNSIGNED NOT NULL,
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_tarifas_items_oferta` (`oferta_item_id`),
    CONSTRAINT `chk_tarifas_items_precio` CHECK (`precio` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifa comercial vigente para una oferta de ítem en una edición';

DROP TABLE IF EXISTS `tarifas_paquetes_edicion`;
CREATE TABLE `tarifas_paquetes_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `oferta_paquete_id` INT UNSIGNED NOT NULL,
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_tarifas_paquetes_oferta` (`oferta_paquete_id`),
    CONSTRAINT `chk_tarifas_paquetes_precio` CHECK (`precio` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifa comercial vigente propia para una oferta de paquete en una edición';

DROP TABLE IF EXISTS `historial_tarifas_items`;
CREATE TABLE `historial_tarifas_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarifa_item_id` INT UNSIGNED NOT NULL,
    `precio_anterior` DECIMAL(12,2) NOT NULL,
    `precio_nuevo` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL,
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tarifa_item_id`) REFERENCES `tarifas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    KEY `idx_historial_tarifas_items_tarifa` (`tarifa_item_id`, `creado_en`),
    KEY `idx_historial_tarifas_items_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_historial_tarifas_items_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_historial_tarifas_items_precio_ant` CHECK (`precio_anterior` >= 0),
    CONSTRAINT `chk_historial_tarifas_items_precio_nue` CHECK (`precio_nuevo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial append-only de cambios de tarifa en ofertas de ítems';

DROP TABLE IF EXISTS `historial_tarifas_paquetes`;
CREATE TABLE `historial_tarifas_paquetes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarifa_paquete_id` INT UNSIGNED NOT NULL,
    `precio_anterior` DECIMAL(12,2) NOT NULL,
    `precio_nuevo` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL,
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tarifa_paquete_id`) REFERENCES `tarifas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    KEY `idx_historial_tarifas_paquetes_tarifa` (`tarifa_paquete_id`, `creado_en`),
    KEY `idx_historial_tarifas_paquetes_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_historial_tarifas_paquetes_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_historial_tarifas_paquetes_precio_ant` CHECK (`precio_anterior` >= 0),
    CONSTRAINT `chk_historial_tarifas_paquetes_precio_nue` CHECK (`precio_nuevo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial append-only de cambios de tarifa en ofertas de paquetes';

-- ------------------------------------------------------------------------------
-- 25. SECUENCIAS CORRELATIVAS DE COTIZACIONES
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizaciones_secuencias`;
CREATE TABLE `cotizaciones_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de cotización particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 26. COTIZACIONES Y PROPUESTAS ECONÓMICAS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizaciones`;
CREATE TABLE `cotizaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `oportunidad_id` BIGINT UNSIGNED DEFAULT NULL,
    `correlativo` VARCHAR(35) DEFAULT NULL COMMENT 'Identificador formal emitido (ej. COT-2026-000001 o COT-2026-000001-R2), NULL en BORRADOR',
    `correlativo_base` VARCHAR(30) DEFAULT NULL COMMENT 'Secuencia raíz compartida por la cadena de revisiones, NULL en BORRADOR sin emisión previa',
    `version_numero` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Número de revisión: 1 para original, 2, 3... para revisiones',
    `cotizacion_origen_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK autorreferencial a la cotización inmediata anterior de la que deriva',
    `cotizacion_raiz_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK autorreferencial a la cotización original R1 de la cadena',
    `titulo` VARCHAR(150) NOT NULL COMMENT 'Concepto o resumen de la propuesta comercial',
    `estado` ENUM('BORRADOR', 'EMITIDA', 'ACEPTADA', 'RECHAZADA', 'VENCIDA', 'ANULADA') NOT NULL DEFAULT 'BORRADOR',
    `fecha_emision` DATE DEFAULT NULL COMMENT 'Fecha de emisión formal, NULL mientras sea BORRADOR',
    `valido_hasta` DATE DEFAULT NULL COMMENT 'Fecha límite de validez de la oferta, NULL mientras sea BORRADOR',
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de subtotales netos de líneas',
    `descuento_global_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_global_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_global_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_global_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio cuando descuento_global_monto > 0',
    `descuento_lineas_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de descuentos de cada línea',
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal - descuento_global_monto',
    `terminos_condiciones` TEXT DEFAULT NULL,
    `notas_internas` TEXT DEFAULT NULL,
    `motivo_rechazo` ENUM('PRECIO_ELEVADO', 'COMPETENCIA', 'CAMBIO_FECHA', 'CAMBIO_REQUERIMIENTO', 'CLIENTE_DESISTIO', 'OTRO') DEFAULT NULL,
    `motivo_rechazo_detalle` VARCHAR(255) DEFAULT NULL,
    `motivo_anulacion` ENUM('SUPERADA_POR_REVISION', 'ERROR_DATOS', 'CAMBIO_CONDICIONES_ORGANIZACION', 'EXPIRACION_DEFINITIVA', 'OTRO') DEFAULT NULL,
    `motivo_anulacion_detalle` VARCHAR(255) DEFAULT NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL COMMENT 'Usuario emisor responsable',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oportunidad_id`) REFERENCES `crm_oportunidades` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cotizacion_origen_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_raiz_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_cotizaciones_correlativo` (`organizacion_id`, `correlativo`),
    KEY `idx_cotizaciones_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_cotizaciones_edicion` (`organizacion_id`, `edicion_id`, `estado`),
    KEY `idx_cotizaciones_oportunidad` (`oportunidad_id`),
    KEY `idx_cotizaciones_raiz` (`cotizacion_raiz_id`),
    CONSTRAINT `chk_cotizaciones_totales` CHECK (`subtotal` >= 0 AND `total` >= 0 AND `total` <= `subtotal`),
    CONSTRAINT `chk_cotizaciones_validez` CHECK (`valido_hasta` IS NULL OR `fecha_emision` IS NULL OR `valido_hasta` >= `fecha_emision`),
    CONSTRAINT `chk_cotizaciones_borrador` CHECK (
        (`estado` = 'BORRADOR' AND `correlativo` IS NULL AND `fecha_emision` IS NULL AND `valido_hasta` IS NULL)
        OR
        (`estado` <> 'BORRADOR' AND `correlativo` IS NOT NULL AND `fecha_emision` IS NOT NULL AND `valido_hasta` IS NOT NULL)
    ),
    CONSTRAINT `chk_cotizaciones_descuento_global` CHECK (
        (`descuento_global_tipo` = 'NINGUNO' AND `descuento_global_valor` = 0 AND `descuento_global_monto` = 0 AND `descuento_global_motivo` IS NULL)
        OR
        (`descuento_global_tipo` <> 'NINGUNO' AND `descuento_global_monto` >= 0 AND `descuento_global_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_global_motivo`)) > 0)
    ),
    CONSTRAINT `chk_cotizaciones_rechazo` CHECK (
        (`estado` <> 'RECHAZADA' AND `motivo_rechazo` IS NULL AND `motivo_rechazo_detalle` IS NULL)
        OR
        (`estado` = 'RECHAZADA' AND `motivo_rechazo` IS NOT NULL AND (`motivo_rechazo` <> 'OTRO' OR (`motivo_rechazo_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_rechazo_detalle`)) > 0)))
    ),
    CONSTRAINT `chk_cotizaciones_anulacion` CHECK (
        (`estado` <> 'ANULADA' AND `motivo_anulacion` IS NULL AND `motivo_anulacion_detalle` IS NULL)
        OR
        (`estado` = 'ANULADA' AND `motivo_anulacion` IS NOT NULL AND (`motivo_anulacion` <> 'OTRO' OR (`motivo_anulacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_anulacion_detalle`)) > 0)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de propuestas económicas y cotizaciones formalizadas';

-- ------------------------------------------------------------------------------
-- 27. LÍNEAS DE COTIZACIÓN
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizacion_lineas`;
CREATE TABLE `cotizacion_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cotizacion_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('ITEM', 'PAQUETE') NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL,
    `paquete_id` INT UNSIGNED DEFAULT NULL,
    `oferta_item_id` INT UNSIGNED DEFAULT NULL,
    `oferta_paquete_id` INT UNSIGNED DEFAULT NULL,
    `concepto_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot del código comercial',
    `concepto_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre de concepto',
    `concepto_descripcion` TEXT DEFAULT NULL COMMENT 'Snapshot de descripción comercial',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot de unidad canónica',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(12,2) NOT NULL COMMENT 'Precio unitario congelado',
    `descuento_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio si descuento_monto > 0',
    `subtotal` DECIMAL(12,2) NOT NULL COMMENT '(cantidad * precio_unitario) - descuento_monto',
    `moneda` CHAR(3) NOT NULL,
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `notas` VARCHAR(255) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    KEY `idx_cotizacion_lineas_cotizacion` (`cotizacion_id`, `orden`),
    CONSTRAINT `chk_cotizacion_lineas_tipo` CHECK (
        (`tipo_linea` = 'ITEM' AND `item_comercial_id` IS NOT NULL AND `oferta_item_id` IS NOT NULL AND `paquete_id` IS NULL AND `oferta_paquete_id` IS NULL)
        OR
        (`tipo_linea` = 'PAQUETE' AND `paquete_id` IS NOT NULL AND `oferta_paquete_id` IS NOT NULL AND `item_comercial_id` IS NULL AND `oferta_item_id` IS NULL)
    ),
    CONSTRAINT `chk_cotizacion_lineas_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_cotizacion_lineas_precio` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_cotizacion_lineas_descuento` CHECK (
        (`descuento_tipo` = 'NINGUNO' AND `descuento_valor` = 0 AND `descuento_monto` = 0 AND `descuento_motivo` IS NULL)
        OR
        (`descuento_tipo` <> 'NINGUNO' AND `descuento_monto` >= 0 AND `descuento_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_motivo`)) > 0)
    ),
    CONSTRAINT `chk_cotizacion_lineas_subtotal` CHECK (`subtotal` >= 0 AND `subtotal` <= (`cantidad` * `precio_unitario`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas económicas y detalle comercial de la cotización';

-- ------------------------------------------------------------------------------
-- 28. COMPONENTES CONGELADOS DE PAQUETES COTIZADOS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizacion_linea_componentes`;
CREATE TABLE `cotizacion_linea_componentes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cotizacion_linea_id` BIGINT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de origen (nullable para independencia histórica)',
    `item_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot inmutable del código del ítem',
    `item_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot inmutable del nombre del ítem',
    `item_tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL COMMENT 'Snapshot inmutable del tipo',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot inmutable de la unidad de medida',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Snapshot de cantidad incluida por paquete',
    `nota` VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot de especificación',
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cotizacion_linea_id`) REFERENCES `cotizacion_lineas` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE SET NULL,
    KEY `idx_cotizacion_comp_linea` (`cotizacion_linea_id`, `orden`),
    CONSTRAINT `chk_cotizacion_componentes_cant` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot relacional inmutable de los componentes de paquetes cotizados';

-- ------------------------------------------------------------------------------
-- 29. SECUENCIAS CORRELATIVAS DE VENTA
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ventas_secuencias`;
CREATE TABLE `ventas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de venta particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 30. VENTAS COMERCIALES CONFIRMADAS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `cotizacion_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK a cotización aprobada (nullable para habilitar futura venta directa)',
    `origen_tipo` ENUM('COTIZACION', 'DIRECTA') NOT NULL DEFAULT 'COTIZACION',
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador formal único emitido (ej. VTA-2026-000001)',
    `fecha_venta` DATE NOT NULL COMMENT 'Fecha de formalización de la venta',
    `estado` ENUM('CONFIRMADA', 'LIQUIDADA', 'CANCELADA', 'ANULADA') NOT NULL DEFAULT 'CONFIRMADA',

    -- Snapshot Inmutable del Cliente
    `cliente_nombre_completo` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre completo al formalizar',
    `cliente_tipo_documento` VARCHAR(20) DEFAULT NULL COMMENT 'Snapshot del tipo de documento',
    `cliente_numero_documento` VARCHAR(30) DEFAULT NULL COMMENT 'Snapshot del número de documento',
    `cliente_telefono` VARCHAR(30) DEFAULT NULL COMMENT 'Snapshot del teléfono principal',
    `cliente_email` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot del correo electrónico',

    -- Snapshot Económico y Condiciones
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de subtotales netos de líneas vendidas',
    `descuento_global_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_global_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_global_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_global_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio cuando descuento_global_monto > 0',
    `descuento_lineas_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de descuentos de cada línea',
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal - descuento_global_monto',
    `monto_pagado` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto acumulado efectivamente cobrado',
    `saldo_pendiente` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo por cobrar de la venta',
    `estado_financiero` ENUM('NO_PAGADA', 'PAGO_PARCIAL', 'PAGADA_TOTAL', 'SOBREPAGADA', 'NO_APLICA') NOT NULL DEFAULT 'NO_PAGADA',
    `terminos_condiciones` TEXT DEFAULT NULL COMMENT 'Snapshot inmutable de términos y condiciones acordados',
    `notas_comerciales` TEXT DEFAULT NULL COMMENT 'Notas comerciales de la venta',

    -- Motivos de Cancelación / Anulación
    `motivo_cancelacion` ENUM('DESISTIMIENTO_CLIENTE', 'FUERZA_MAYOR_CLIMA', 'PROBLEMAS_SALUD', 'INCUMPLIMIENTO_ORGANIZACION', 'OTRO') DEFAULT NULL,
    `motivo_cancelacion_detalle` VARCHAR(255) DEFAULT NULL,
    `motivo_anulacion` ENUM('ERROR_REGISTRO', 'DUPLICIDAD_VENTA', 'FRAUDE_SUPLANTACION', 'OTRO') DEFAULT NULL,
    `motivo_anulacion_detalle` VARCHAR(255) DEFAULT NULL,

    -- Control de Concurrencia y Auditoría
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL COMMENT 'Usuario emisor responsable',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_ventas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_ventas_cotizacion` (`organizacion_id`, `cotizacion_id`),
    UNIQUE KEY `uk_ventas_id_org_edic` (`id`, `organizacion_id`, `edicion_id`),
    UNIQUE KEY `uk_ventas_id_org` (`id`, `organizacion_id`),
    KEY `idx_ventas_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_ventas_edicion` (`organizacion_id`, `edicion_id`, `estado`),
    KEY `idx_ventas_fecha` (`organizacion_id`, `fecha_venta`),

    CONSTRAINT `chk_ventas_origen` CHECK (
        (`origen_tipo` = 'COTIZACION' AND `cotizacion_id` IS NOT NULL)
        OR
        (`origen_tipo` = 'DIRECTA' AND `cotizacion_id` IS NULL)
    ),
    CONSTRAINT `chk_ventas_totales` CHECK (`subtotal` >= 0 AND `total` >= 0 AND `total` <= `subtotal`),
    CONSTRAINT `chk_ventas_descuento_global` CHECK (
        (`descuento_global_tipo` = 'NINGUNO' AND `descuento_global_valor` = 0 AND `descuento_global_monto` = 0 AND `descuento_global_motivo` IS NULL)
        OR
        (`descuento_global_tipo` <> 'NINGUNO' AND `descuento_global_monto` >= 0 AND `descuento_global_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_global_motivo`)) > 0)
    ),
    CONSTRAINT `chk_ventas_cancelacion` CHECK (
        (`estado` <> 'CANCELADA' AND `motivo_cancelacion` IS NULL AND `motivo_cancelacion_detalle` IS NULL)
        OR
        (`estado` = 'CANCELADA' AND `motivo_cancelacion` IS NOT NULL AND (`motivo_cancelacion` <> 'OTRO' OR (`motivo_cancelacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_cancelacion_detalle`)) > 0)))
    ),
    CONSTRAINT `chk_ventas_anulacion` CHECK (
        (`estado` <> 'ANULADA' AND `motivo_anulacion` IS NULL AND `motivo_anulacion_detalle` IS NULL)
        OR
        (`estado` = 'ANULADA' AND `motivo_anulacion` IS NOT NULL AND (`motivo_anulacion` <> 'OTRO' OR (`motivo_anulacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_anulacion_detalle`)) > 0)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de ventas comerciales confirmadas';

-- ------------------------------------------------------------------------------
-- 31. DETALLE DE LÍNEAS COMERCIALES VENDIDAS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `venta_lineas`;
CREATE TABLE `venta_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `cotizacion_linea_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de la línea cotizada',
    `tipo_linea` ENUM('ITEM', 'PAQUETE') NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL,
    `paquete_id` INT UNSIGNED DEFAULT NULL,
    `oferta_item_id` INT UNSIGNED DEFAULT NULL,
    `oferta_paquete_id` INT UNSIGNED DEFAULT NULL,
    `concepto_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot del código comercial',
    `concepto_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre de concepto',
    `concepto_descripcion` TEXT DEFAULT NULL COMMENT 'Snapshot de descripción comercial',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot de unidad canónica',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(12,2) NOT NULL COMMENT 'Precio unitario congelado',
    `descuento_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio si descuento_monto > 0',
    `subtotal` DECIMAL(12,2) NOT NULL COMMENT '(cantidad * precio_unitario) - descuento_monto',
    `moneda` CHAR(3) NOT NULL,
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `notas` VARCHAR(255) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_linea_id`) REFERENCES `cotizacion_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    KEY `idx_venta_lineas_venta` (`venta_id`, `orden`),
    CONSTRAINT `chk_venta_lineas_tipo` CHECK (
        (`tipo_linea` = 'ITEM' AND `item_comercial_id` IS NOT NULL AND `oferta_item_id` IS NOT NULL AND `paquete_id` IS NULL AND `oferta_paquete_id` IS NULL)
        OR
        (`tipo_linea` = 'PAQUETE' AND `paquete_id` IS NOT NULL AND `oferta_paquete_id` IS NOT NULL AND `item_comercial_id` IS NULL AND `oferta_item_id` IS NULL)
    ),
    CONSTRAINT `chk_venta_lineas_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_venta_lineas_precio` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_venta_lineas_descuento` CHECK (
        (`descuento_tipo` = 'NINGUNO' AND `descuento_valor` = 0 AND `descuento_monto` = 0 AND `descuento_motivo` IS NULL)
        OR
        (`descuento_tipo` <> 'NINGUNO' AND `descuento_monto` >= 0 AND `descuento_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_motivo`)) > 0)
    ),
    CONSTRAINT `chk_venta_lineas_subtotal` CHECK (`subtotal` >= 0 AND `subtotal` <= (`cantidad` * `precio_unitario`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas económicas y detalle comercial de la venta';

-- ------------------------------------------------------------------------------
-- 32. COMPONENTES CONGELADOS DE PAQUETES VENDIDOS
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `venta_linea_componentes`;
CREATE TABLE `venta_linea_componentes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de origen (ítem comercial)',
    `item_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot inmutable del código del ítem',
    `item_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot inmutable del nombre del ítem',
    `item_tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL COMMENT 'Snapshot inmutable del tipo',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot inmutable de la unidad de medida',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Snapshot de cantidad incluida por paquete',
    `nota` VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot de especificación',
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    KEY `idx_venta_comp_linea` (`venta_linea_id`, `orden`),
    CONSTRAINT `chk_venta_comp_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot relacional inmutable de los componentes de paquetes vendidos';

-- ------------------------------------------------------------------------------
-- 33. TABLA `reservas_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS RSV)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservas_secuencias`;
CREATE TABLE `reservas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de reserva particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 34. TABLA `entregas_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS ENT)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entregas_secuencias`;
CREATE TABLE `entregas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de despacho particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 35. TABLA `item_configuracion_operativa` (SEMÁNTICA OPERATIVA EXPLÍCITA DEL CATÁLOGO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `item_configuracion_operativa`;
CREATE TABLE `item_configuracion_operativa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_comercial_id` INT UNSIGNED NOT NULL COMMENT 'Vínculo 1:1 con ítem comercial',
    `requiere_reserva` TINYINT(1) NOT NULL COMMENT '1 si genera compromiso de reserva; 0 si no (sin default)',
    `requiere_agendamiento` TINYINT(1) NOT NULL COMMENT '1 si requiere agendar fecha de servicio; 0 si no (sin default)',
    `requiere_participantes` TINYINT(1) NOT NULL COMMENT '1 si requiere nómina de pasajeros/beneficiarios; 0 si no (sin default)',
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL COMMENT 'Naturaleza funcional del aforo (sin default)',
    `es_accesorio` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es servicio accesorio/incluido dependiente de otra prestación',
    `duracion_estimada_minutos` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Duración referencial de la actividad',
    `punto_partida_predeterminado` VARCHAR(200) DEFAULT NULL COMMENT 'Muelle, terminal o punto de encuentro base',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_item_cfg_item` (`item_comercial_id`),
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_item_cfg_reserva` CHECK (`requiere_reserva` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_agendamiento` CHECK (`requiere_agendamiento` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_participantes` CHECK (`requiere_participantes` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_coherencia` CHECK (
        (`requiere_agendamiento` = 0) OR (`requiere_agendamiento` = 1 AND `requiere_reserva` = 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración operativa explícita desacoplada del catálogo comercial';

-- ------------------------------------------------------------------------------
-- 36. TABLA `reservas` (COMPROMISO ADMINISTRATIVO UNIFICADO CON EL CLIENTE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservas`;
CREATE TABLE `reservas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL COMMENT 'Venta origen confirmada (Relación 1:1)',
    `cliente_id` INT UNSIGNED NOT NULL COMMENT 'Cliente comercial titular',
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador formal único institucional (ej. RSV-2026-000001)',
    `estado` ENUM('REGISTRADA', 'PENDIENTE_DATOS', 'CONFIRMADA', 'CANCELADA') NOT NULL DEFAULT 'REGISTRADA' COMMENT 'Estado administrativo de la reserva',

    -- Snapshot de Contacto para Viaje (heredado de Venta / Cliente)
    `contacto_nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre de contacto o líder del grupo',
    `contacto_tipo_documento` VARCHAR(20) DEFAULT NULL,
    `contacto_numero_documento` VARCHAR(30) DEFAULT NULL,
    `contacto_telefono` VARCHAR(30) DEFAULT NULL,
    `contacto_email` VARCHAR(150) DEFAULT NULL,

    `notas_operativas` TEXT DEFAULT NULL COMMENT 'Instrucciones especiales para operaciones',
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_reservas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_reservas_venta` (`organizacion_id`, `venta_id`),
    KEY `idx_reservas_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_reservas_edicion` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de reservas de servicios vinculadas a ventas formalizadas';

-- ------------------------------------------------------------------------------
-- 37. TABLA `reserva_prestaciones` (PRESTACIONES AGENDABLES INDEPENDIENTES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_prestaciones`;
CREATE TABLE `reserva_prestaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL COMMENT 'Línea de venta de procedencia',
    `venta_linea_componente_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Componente de paquete de procedencia (si aplica)',
    `item_comercial_id` INT UNSIGNED NOT NULL COMMENT 'Ítem base del catálogo',

    -- Snapshots Inmutables del Servicio al Formalizar
    `concepto_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot del código comercial',
    `concepto_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre de concepto',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot de unidad canónica',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Cantidad de servicio contratada',
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL COMMENT 'Snapshot de aforo',
    `requiere_agendamiento` TINYINT(1) NOT NULL COMMENT 'Snapshot de si requiere fecha',
    `requiere_participantes` TINYINT(1) NOT NULL COMMENT 'Snapshot de si requiere nómina',

    -- Máquina de Agendamiento
    `estado_agendamiento` ENUM('PENDIENTE_PROGRAMAR', 'PROGRAMADA', 'CANCELADA') NOT NULL DEFAULT 'PENDIENTE_PROGRAMAR',
    `fecha_servicio` DATE DEFAULT NULL COMMENT 'Fecha calendario de ejecución acordada',
    `hora_servicio` TIME DEFAULT NULL COMMENT 'Hora o turno acordado',
    `punto_encuentro` VARCHAR(200) DEFAULT NULL COMMENT 'Punto de recojo acordado para esta prestación',
    `notas_prestacion` VARCHAR(255) DEFAULT NULL,

    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_componente_id`) REFERENCES `venta_linea_componentes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,

    KEY `idx_prestaciones_reserva` (`reserva_id`, `estado_agendamiento`),
    KEY `idx_prestaciones_fecha` (`fecha_servicio`),
    CONSTRAINT `chk_prestacion_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_prestacion_agendamiento` CHECK (
        (`estado_agendamiento` = 'PENDIENTE_PROGRAMAR' AND `fecha_servicio` IS NULL)
        OR
        (`estado_agendamiento` = 'PROGRAMADA' AND `fecha_servicio` IS NOT NULL)
        OR
        (`estado_agendamiento` = 'CANCELADA')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prestaciones agendables desglosadas de la reserva';

-- ------------------------------------------------------------------------------
-- 38. TABLA `reserva_participantes` (BENEFICIARIOS DE VIAJE CON MINIMIZACIÓN ZERO-PII)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_participantes`;
CREATE TABLE `reserva_participantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED DEFAULT NULL COMMENT 'Enlace opcional si ya existe en personas',
    `tipo_documento_id` TINYINT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `nombres` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(100) NOT NULL,
    `nacionalidad` CHAR(2) DEFAULT NULL COMMENT 'Código ISO alpha-2 (sin default forzado)',
    `rango_etario` ENUM('ADULTO', 'MENOR', 'INFANTE') DEFAULT NULL COMMENT 'Clasificación operativa (sin default forzado)',
    `telefono_contacto` VARCHAR(30) DEFAULT NULL,
    `talla_indumentaria` VARCHAR(10) DEFAULT NULL COMMENT 'Logística de vestuario/alquiler',
    `requiere_asistencia_movilidad` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bandera logística cerrada',
    `regimen_alimentario` ENUM('ESTANDAR', 'VEGETARIANO', 'CELIACO', 'OTRO_PARAMETRIZADO') DEFAULT NULL COMMENT 'Tag cerrado',
    `es_titular_reserva` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_reserva_participante_doc` (`reserva_id`, `tipo_documento_id`, `numero_documento`),
    KEY `idx_reserva_part_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Participantes y pasajeros beneficiarios de las prestaciones';

-- ------------------------------------------------------------------------------
-- 39. TABLA `prestacion_participantes` (ASIGNACIÓN M:N PRESTACIÓN <-> PARTICIPANTE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `prestacion_participantes`;
CREATE TABLE `prestacion_participantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `participante_id` BIGINT UNSIGNED NOT NULL,
    `asiento_asignado` VARCHAR(20) DEFAULT NULL COMMENT 'Butaca o asiento asignado si aplica',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`participante_id`) REFERENCES `reserva_participantes` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_prestacion_participante` (`prestacion_id`, `participante_id`),
    KEY `idx_prest_part_part` (`participante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de participantes a prestaciones específicas de la reserva';

-- ------------------------------------------------------------------------------
-- 40. TABLA `reserva_reprogramaciones` (HISTORIAL APPEND-ONLY DE CAMBIOS DE FECHA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_reprogramaciones`;
CREATE TABLE `reserva_reprogramaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `fecha_anterior` DATE DEFAULT NULL,
    `fecha_nueva` DATE NOT NULL,
    `hora_anterior` TIME DEFAULT NULL,
    `hora_nueva` TIME DEFAULT NULL,
    `motivo_categoria` ENUM('SOLICITUD_CLIENTE', 'CLIMA_FUERZA_MAYOR', 'LOGISTICA_OPERATIVA', 'OTRO') NOT NULL,
    `motivo_detalle` VARCHAR(255) NOT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_reprog_prestacion` (`prestacion_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro inmutable de reprogramaciones de prestaciones';

-- ------------------------------------------------------------------------------
-- 41. TABLA `entregas_productos` (FRONTERA DESACOPLADA DE DESPACHO PARA PRODUCTOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entregas_productos`;
CREATE TABLE `entregas_productos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL COMMENT 'Venta origen confirmada (Relación 1:1)',
    `cliente_id` INT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador institucional de entrega (ej. ENT-2026-000001)',
    `estado` ENUM('PENDIENTE_ENTREGA', 'ENTREGADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE_ENTREGA',

    -- Snapshot de Entrega
    `contacto_nombre` VARCHAR(150) NOT NULL,
    `contacto_telefono` VARCHAR(30) DEFAULT NULL,
    `direccion_entrega` VARCHAR(255) DEFAULT NULL COMMENT 'Hotel, domicilio o retiro en sede',
    `fecha_entrega` DATETIME DEFAULT NULL,
    `entregado_por` INT UNSIGNED DEFAULT NULL,
    `notas_despacho` VARCHAR(255) DEFAULT NULL,

    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`entregado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_entregas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_entregas_venta` (`organizacion_id`, `venta_id`),
    KEY `idx_entregas_cliente` (`organizacion_id`, `cliente_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Órdenes administrativas de despacho para bienes tangibles vendidos';

-- ------------------------------------------------------------------------------
-- 42. TABLA `entrega_items` (DETALLE DE BIENES TANGIBLES A ENTREGAR)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entrega_items`;
CREATE TABLE `entrega_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entrega_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_componente_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `concepto_codigo` VARCHAR(60) NOT NULL,
    `concepto_nombre` VARCHAR(150) NOT NULL,
    `unidad_medida` VARCHAR(30) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`entrega_id`) REFERENCES `entregas_productos` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_componente_id`) REFERENCES `venta_linea_componentes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,

    KEY `idx_entrega_items_entrega` (`entrega_id`),
    CONSTRAINT `chk_entrega_item_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de bienes tangibles desglosados para despacho';

-- ------------------------------------------------------------------------------
-- 43. TABLA `operacion_salidas_secuencias` (CORRELATIVOS MONOTÓNICOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salidas_secuencias`;
CREATE TABLE `operacion_salidas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` INT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas atómicas de salidas operativas';

-- ------------------------------------------------------------------------------
-- 44. TABLA `proveedores` (PROVEEDORES Y PRESTADORES EXTERNOS DE SERVICIO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED NOT NULL,
    `tipo_servicio_principal` VARCHAR(80) DEFAULT NULL COMMENT 'Especialidad operativa (ej. TRANSPORTE_LACUSTRE, GUIADO)',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
    `notas_contacto` TEXT DEFAULT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_proveedores_org_persona` (`organizacion_id`, `persona_id`),
    KEY `idx_proveedores_estado` (`organizacion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro maestro de proveedores de servicios operativos';

-- ------------------------------------------------------------------------------
-- 45. TABLA `operacion_recursos` (RECURSOS FÍSICOS Y FLOTA OPERATIVA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_recursos`;
CREATE TABLE `operacion_recursos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo_recurso` ENUM('VEHICULO_TERRESTRE', 'EMBARCACION_LACUSTRE', 'EQUIPO_LOGISTICO', 'OTRO') NOT NULL,
    `codigo_interno` VARCHAR(50) NOT NULL COMMENT 'Código identificador único (ej. LANCHA-01, BUS-02)',
    `nombre` VARCHAR(120) NOT NULL,
    `propiedad_tipo` ENUM('PROPIO', 'EXTERNO') NOT NULL,
    `proveedor_id` INT UNSIGNED DEFAULT NULL COMMENT 'Obligatorio si propiedad_tipo = EXTERNO',
    `capacidad_maxima` INT UNSIGNED NOT NULL DEFAULT 1,
    `identificacion_oficial` VARCHAR(60) DEFAULT NULL COMMENT 'Matrícula naval / placa vehicular',
    `estado` ENUM('DISPONIBLE', 'EN_MANTENIMIENTO', 'BAJA') NOT NULL DEFAULT 'DISPONIBLE',
    `notas` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_recursos_org_codigo` (`organizacion_id`, `codigo_interno`),
    KEY `idx_recursos_estado` (`organizacion_id`, `tipo_recurso`, `estado`),
    CONSTRAINT `chk_recursos_propiedad` CHECK (
        (`propiedad_tipo` = 'PROPIO' AND `proveedor_id` IS NULL)
        OR
        (`propiedad_tipo` = 'EXTERNO' AND `proveedor_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_recursos_capacidad` CHECK (`capacidad_maxima` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos físicos y flota operativa para ejecución de salidas';

-- ------------------------------------------------------------------------------
-- 46. TABLA `operacion_salidas` (CABECERA DE SALIDAS OPERATIVAS DE CAMPO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salidas`;
CREATE TABLE `operacion_salidas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador institucional (ej. SAL-2026-000001)',
    `titulo` VARCHAR(150) NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `hora_citacion` TIME NOT NULL,
    `hora_salida` TIME NOT NULL,
    `hora_inicio_real` DATETIME DEFAULT NULL,
    `hora_fin_real` DATETIME DEFAULT NULL,
    `punto_encuentro` VARCHAR(255) NOT NULL,
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL,
    `capacidad_maxima` INT UNSIGNED DEFAULT NULL,
    `estado` ENUM('PROGRAMADA', 'EN_CHECKIN', 'DESPACHADA', 'FINALIZADA', 'INTERRUMPIDA', 'CANCELADA') NOT NULL DEFAULT 'PROGRAMADA',
    `motivo_cancelacion_interrupcion` TEXT DEFAULT NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_salidas_correlativo` (`organizacion_id`, `correlativo`),
    KEY `idx_salidas_fecha_estado` (`organizacion_id`, `edicion_id`, `fecha_salida`, `estado`),
    CONSTRAINT `chk_salidas_capacidad` CHECK (
        (`tipo_capacidad` IN ('COLECTIVA', 'DISCRETA') AND `capacidad_maxima` IS NOT NULL AND `capacidad_maxima` > 0)
        OR
        (`tipo_capacidad` IN ('SIN_CONTROL', 'EXCLUSIVA'))
    ),
    CONSTRAINT `chk_salidas_horas` CHECK (`hora_citacion` <= `hora_salida`),
    CONSTRAINT `chk_salidas_cancelacion` CHECK (
        (`estado` NOT IN ('CANCELADA', 'INTERRUMPIDA') AND `motivo_cancelacion_interrupcion` IS NULL)
        OR
        (`estado` IN ('CANCELADA', 'INTERRUMPIDA') AND `motivo_cancelacion_interrupcion` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_cancelacion_interrupcion`)) > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de salidas operativas de campo';

-- ------------------------------------------------------------------------------
-- 47. TABLA `operacion_salida_recursos` (ASIGNACIÓN DE PERSONAL Y RECURSOS FÍSICOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salida_recursos`;
CREATE TABLE `operacion_salida_recursos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `recurso_fisico_id` INT UNSIGNED DEFAULT NULL,
    `persona_id` INT UNSIGNED DEFAULT NULL,
    `rol_operativo` ENUM('GUIA_PRINCIPAL', 'GUIA_ASISTENTE', 'CONDUCTOR', 'PATRON_LANCHA', 'COORDINADOR_CAMPO', 'EQUIPO_LOGISTICO') NOT NULL,
    `notas` VARCHAR(255) DEFAULT NULL,
    `asignado_por` INT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`recurso_fisico_id`) REFERENCES `operacion_recursos` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_salida_recursos_salida` (`salida_id`),
    KEY `idx_salida_recursos_persona` (`persona_id`),
    KEY `idx_salida_recursos_recurso` (`recurso_fisico_id`),
    CONSTRAINT `chk_salida_recursos_objetivo` CHECK (
        `recurso_fisico_id` IS NOT NULL OR `persona_id` IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos físicos y personal asignados a una salida operativa';

-- ------------------------------------------------------------------------------
-- 48. TABLA `operacion_salida_prestaciones` (AGRUPACIÓN 1:N DE PRESTACIONES EN SALIDA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salida_prestaciones`;
CREATE TABLE `operacion_salida_prestaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_pasajeros` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `asignado_por` INT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_salida_prestacion` (`salida_id`, `prestacion_id`),
    KEY `idx_salida_prestacion_prest` (`prestacion_id`),
    CONSTRAINT `chk_salida_prestacion_cantidad` CHECK (`cantidad_pasajeros` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agrupación de prestaciones de reserva asignadas a una salida';

-- ------------------------------------------------------------------------------
-- 49. TABLA `operacion_asistencias` (CHECK-IN INDIVIDUALIZADO Y CONTROL DE EMBARQUE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_asistencias`;
CREATE TABLE `operacion_asistencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `participante_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_asiento` VARCHAR(20) DEFAULT NULL,
    `estado_asistencia` ENUM('PENDIENTE', 'PRESENTE', 'NO_SHOW') NOT NULL DEFAULT 'PENDIENTE',
    `marcado_en` DATETIME DEFAULT NULL,
    `marcado_por` INT UNSIGNED DEFAULT NULL,
    `observacion` VARCHAR(255) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`participante_id`) REFERENCES `reserva_participantes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`marcado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_asistencias_salida_participante` (`salida_id`, `participante_id`),
    UNIQUE KEY `uk_asistencias_salida_asiento` (`salida_id`, `ubicacion_asiento`),
    KEY `idx_asistencias_prestacion` (`prestacion_id`),
    KEY `idx_asistencias_estado` (`salida_id`, `estado_asistencia`),
    CONSTRAINT `chk_asistencia_marcado` CHECK (
        (`estado_asistencia` = 'PENDIENTE' AND `marcado_en` IS NULL AND `marcado_por` IS NULL)
        OR
        (`estado_asistencia` IN ('PRESENTE', 'NO_SHOW') AND `marcado_en` IS NOT NULL AND `marcado_por` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control individual de asistencia y check-in por participante en salida';

-- ------------------------------------------------------------------------------
-- 50. TABLA `operacion_incidencias` (BITÁCORA ESTRUCTURADA DE INCIDENCIAS DE CAMPO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_incidencias`;
CREATE TABLE `operacion_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `tipo_incidencia` ENUM('CLIMA_FUERZA_MAYOR', 'FALLA_MECANICA_VEHICULO', 'DEMORA_TRANSPORTE', 'ACCIDENTE_SALUD', 'QUEJA_CLIENTE', 'CIERRE_VIAS_PUERTOS', 'OTRO') NOT NULL,
    `descripcion` TEXT NOT NULL,
    `acciones_tomadas` TEXT DEFAULT NULL,
    `afecto_continuidad` TINYINT(1) NOT NULL DEFAULT 0,
    `registrado_por` INT UNSIGNED NOT NULL,
    `registrado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_incidencias_salida` (`salida_id`),
    KEY `idx_incidencias_tipo` (`tipo_incidencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora inmutable de incidencias operativas ocurridas en salidas';

-- ------------------------------------------------------------------------------
-- 51. TABLA `pasarelas_pago` (CATÁLOGO DE PASARELAS INSTITUCIONALES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pasarelas_pago`;
CREATE TABLE `pasarelas_pago` (
    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `tipo_integracion` ENUM('CHECKOUT_WEB', 'API_TOKEN', 'QR_ESTATICO', 'MANUAL') NOT NULL,
    `protocolo_webhook` ENUM('HMAC_SHA256', 'BEARER_TOKEN', 'SIGNATURE_HEADER', 'QUERY_POLLING', 'NINGUNO') NOT NULL DEFAULT 'HMAC_SHA256',
    `soporta_reembolsos` TINYINT(1) NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de pasarelas de pago soportadas en la plataforma';

-- ------------------------------------------------------------------------------
-- 52. TABLA `organizacion_pasarelas` (CONFIGURACIÓN TENANT Y REGLA D-01)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `organizacion_pasarelas`;
CREATE TABLE `organizacion_pasarelas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pasarela_id` SMALLINT UNSIGNED NOT NULL,
    `modo` ENUM('TEST', 'PRODUCCION') NOT NULL DEFAULT 'TEST',
    `identificador_comercio` VARCHAR(150) NULL COMMENT 'Merchant ID o Public Key',
    `credencial_secreta_enc` TEXT NULL COMMENT 'API Key privada cifrada en AES-256-GCM',
    `webhook_secreto_enc` TEXT NULL COMMENT 'Secreto para verificación de webhook en AES-256-GCM',
    `porcentaje_comision` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `comision_fija` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `asume_comision` ENUM('ORGANIZACION', 'CLIENTE') NOT NULL DEFAULT 'ORGANIZACION',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_org_pasarelas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_org_pasarelas_pasarela` FOREIGN KEY (`pasarela_id`) REFERENCES `pasarelas_pago` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_org_pasarela` UNIQUE (`organizacion_id`, `pasarela_id`),
    CONSTRAINT `uk_org_pasarela_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuraciones de pasarela por organización';

-- ------------------------------------------------------------------------------
-- 53. TABLA `edicion_pasarelas` (ACTIVACIÓN SOBERANA POR EDICIÓN ANUAL)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `edicion_pasarelas`;
CREATE TABLE `edicion_pasarelas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `organizacion_pasarela_id` INT UNSIGNED NOT NULL,
    `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_edicion_pas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_edicion_pas_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_edicion_pas_org_pas` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`)
        REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_edicion_pasarela` UNIQUE (`organizacion_id`, `edicion_id`, `organizacion_pasarela_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación de pasarelas por edición anual';

-- ------------------------------------------------------------------------------
-- 54. TABLA `cuentas_bancarias_organizacion` (PADRÓN DE CUENTAS DE TRANSFERENCIA Y BILLETERAS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cuentas_bancarias_organizacion`;
CREATE TABLE `cuentas_bancarias_organizacion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `banco_nombre` VARCHAR(80) NOT NULL,
    `tipo_cuenta` ENUM('CORRIENTE', 'AHORROS', 'BILLETERA_DIGITAL') NOT NULL DEFAULT 'CORRIENTE',
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',
    `numero_cuenta` VARCHAR(50) NOT NULL,
    `cci` VARCHAR(50) NULL,
    `titular_nombre` VARCHAR(150) NOT NULL,
    `titular_documento` VARCHAR(30) NULL,
    `instrucciones_pago` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cuentas_bancarias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_cuentas_bancarias_num` UNIQUE (`organizacion_id`, `numero_cuenta`),
    CONSTRAINT `uk_cuentas_bancarias_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas bancarias institucionales para transferencias y depósitos';

-- ------------------------------------------------------------------------------
-- 55. TABLA `pagos_secuencias` (CORRELATIVOS DE PAGO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pagos_secuencias`;
CREATE TABLE `pagos_secuencias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` INT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pagos_sec_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_pagos_secuencia` UNIQUE (`organizacion_id`, `anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias de numeración anual para pagos';

-- ------------------------------------------------------------------------------
-- 56. TABLA `reembolsos_secuencias` (CORRELATIVOS DE REEMBOLSO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reembolsos_secuencias`;
CREATE TABLE `reembolsos_secuencias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` INT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reembolsos_sec_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_reembolsos_secuencia` UNIQUE (`organizacion_id`, `anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias de numeración anual para reembolsos';

-- ------------------------------------------------------------------------------
-- 57. TABLA `pagos` (LIBRO MAYOR INMUTABLE DE COBROS Y PAGOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'PAG-AAAA-000001',
    `metodo_pago` ENUM('EFECTIVO', 'TRANSFERENCIA_BANCARIA', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'BILLETERA_DIGITAL', 'PASARELA_ONLINE', 'DEPOSITO_VENTANILLA') NOT NULL,
    `estado` ENUM('PENDIENTE_VERIFICACION', 'APROBADO', 'RECHAZADO', 'ANULADO') NOT NULL DEFAULT 'PENDIENTE_VERIFICACION',
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',

    `monto_cobrado_cliente` DECIMAL(12,2) NOT NULL,
    `monto_comision_pasarela` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_neto_recibido` DECIMAL(12,2) NOT NULL,
    `monto_aplicado_venta` DECIMAL(12,2) NOT NULL,
    `monto_excedente` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_reembolsado_acumulado` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    `cuenta_bancaria_id` INT UNSIGNED NULL,
    `organizacion_pasarela_id` INT UNSIGNED NULL,
    `transaccion_externa_id` VARCHAR(150) NULL,
    `numero_operacion_bancaria` VARCHAR(60) NULL,
    `boucher_archivo_url` VARCHAR(255) NULL,
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `verificado_por` INT UNSIGNED NULL,
    `verificado_en` DATETIME NULL,
    `notas_operativas` TEXT NULL,
    `clave_idempotencia` VARCHAR(150) NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_pagos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_venta` FOREIGN KEY (`venta_id`, `organizacion_id`, `edicion_id`)
        REFERENCES `ventas` (`id`, `organizacion_id`, `edicion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_cuenta` FOREIGN KEY (`cuenta_bancaria_id`, `organizacion_id`)
        REFERENCES `cuentas_bancarias_organizacion` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_org_pasarela` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`)
        REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_verificador` FOREIGN KEY (`verificado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    CONSTRAINT `uk_pagos_correlativo` UNIQUE (`organizacion_id`, `correlativo`),
    CONSTRAINT `uk_pagos_idempotencia` UNIQUE (`organizacion_id`, `clave_idempotencia`),
    CONSTRAINT `uk_pagos_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Libro mayor inmutable de pagos y cobros de ventas';

-- ------------------------------------------------------------------------------
-- 58. TABLA `pago_intentos_pasarela` (TRAZABILIDAD DE WEBHOOKS Y SESIONES CHECKOUT)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pago_intentos_pasarela`;
CREATE TABLE `pago_intentos_pasarela` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `organizacion_pasarela_id` INT UNSIGNED NOT NULL,
    `transaccion_externa_id` VARCHAR(150) NULL,
    `orden_checkout_id` VARCHAR(150) NULL,
    `monto` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',
    `estado_intento` ENUM('INICIADO', 'PROCESANDO', 'EXITOSO', 'FALLIDO', 'EXPIRADO') NOT NULL DEFAULT 'INICIADO',
    `codigo_respuesta_pasarela` VARCHAR(50) NULL,
    `mensaje_respuesta_pasarela` VARCHAR(255) NULL,
    `payload_solicitud_sanitizado_json` JSON NULL,
    `payload_respuesta_sanitizado_json` JSON NULL,
    `tarjeta_marca` VARCHAR(30) NULL,
    `tarjeta_ultimos_cuatro` CHAR(4) NULL,
    `ip_origen` VARCHAR(45) NULL,
    `firma_webhook_recibida` VARCHAR(255) NULL,
    `clave_idempotencia_webhook` VARCHAR(150) NULL UNIQUE,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_intentos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_pago` FOREIGN KEY (`pago_id`, `organizacion_id`) REFERENCES `pagos` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_venta` FOREIGN KEY (`venta_id`, `organizacion_id`) REFERENCES `ventas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_org_pas` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`)
        REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos y eventos de pasarela web/webhook';

-- ------------------------------------------------------------------------------
-- 59. TABLA `pago_reembolsos` (ASIENTOS COMPENSATORIOS DE REEMBOLSO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pago_reembolsos`;
CREATE TABLE `pago_reembolsos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'REEM-AAAA-000001',
    `monto_reembolsado` DECIMAL(12,2) NOT NULL,
    `estado` ENUM('SOLICITADO', 'APROBADO', 'EJECUTADO', 'RECHAZADO', 'FALLIDO') NOT NULL DEFAULT 'EJECUTADO',
    `motivo` ENUM('DESISTIMIENTO_CLIENTE', 'FUERZA_MAYOR_CLIMA', 'ERROR_DUPLICIDAD_PAGO', 'AJUSTE_COMERCIAL') NOT NULL,
    `motivo_detalle` VARCHAR(255) NOT NULL,
    `transaccion_reembolso_externa_id` VARCHAR(150) NULL,
    `autorizado_por` INT UNSIGNED NOT NULL,
    `ejecutado_en` DATETIME NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reembolsos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_pago` FOREIGN KEY (`pago_id`, `organizacion_id`) REFERENCES `pagos` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_venta` FOREIGN KEY (`venta_id`, `organizacion_id`) REFERENCES `ventas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_autorizado` FOREIGN KEY (`autorizado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_reembolsos_correlativo` UNIQUE (`organizacion_id`, `correlativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asientos compensatorios de reembolso y extorno';

-- Semillas de pasarelas y RBAC módulo 11
INSERT INTO `pasarelas_pago` (`id`, `codigo`, `nombre`, `descripcion`, `tipo_integracion`, `protocolo_webhook`, `soporta_reembolsos`, `activo`)
VALUES
    (1, 'CULQI', 'Culqi Online', 'Pasarela de pagos con tarjetas peruanas y billeteras', 'CHECKOUT_WEB', 'HMAC_SHA256', 1, 1),
    (2, 'NIUBIZ', 'Niubiz Pago Web', 'Pasarela adquirente oficial de Visa y Mastercard en Perú', 'CHECKOUT_WEB', 'BEARER_TOKEN', 1, 1),
    (3, 'STRIPE', 'Stripe Payments', 'Procesador internacional de tarjetas y pagos globales', 'CHECKOUT_WEB', 'HMAC_SHA256', 1, 1),
    (4, 'MERCADOPAGO', 'Mercado Pago Perú', 'Checkout web y cobro QR de Mercado Libre', 'CHECKOUT_WEB', 'SIGNATURE_HEADER', 1, 1),
    (5, 'YAPE_PLIN_MANUAL', 'Billeteras Móviles Manual', 'Cobro manual mediante QR estático y confirmación de boucher', 'MANUAL', 'NINGUNO', 0, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
    (72, 11, 'pagos.ver', 'Ver Libro de Pagos', 'Consulta de pagos, transacciones y estados de cuenta de ventas'),
    (73, 11, 'pagos.registrar_manual', 'Registrar Cobros Manuales', 'Registro de cobros en efectivo, transferencias bancarias y POS'),
    (74, 11, 'pagos.verificar', 'Verificar Depósitos', 'Validación y aprobación/rechazo de comprobantes de transferencia'),
    (75, 11, 'pagos.reembolsar', 'Emitir Reembolsos', 'Autorización y registro de asientos compensatorios de reembolso'),
    (76, 11, 'pasarelas.gestionar', 'Gestionar Pasarelas', 'Configuración de credenciales de pasarelas y comisiones del tenant'),
    (77, 11, 'cuentas_bancarias.gestionar', 'Gestionar Cuentas Bancarias', 'Padrón de cuentas bancarias y billeteras de la organización')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (1, 72), (1, 73), (1, 74), (1, 75), (1, 76), (1, 77),
    (2, 72), (2, 73), (2, 74), (2, 75), (2, 76), (2, 77),
    (3, 72), (3, 73);

-- ------------------------------------------------------------------------------
-- MÓDULO 12: COMUNICACIONES Y MENSAJERÍA TRAZABLE WHATSAPP (F2.8B)
-- ------------------------------------------------------------------------------

-- 60. TABLA `comunicacion_proveedores` (CATÁLOGO DE PROVEEDORES)
DROP TABLE IF EXISTS `comunicacion_proveedores`;
CREATE TABLE `comunicacion_proveedores` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_integracion` ENUM('DIRECTA_META', 'BSP_PARTNER', 'LOCAL_SIMULADOR') NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uk_comunicacion_proveedores_codigo` UNIQUE (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de proveedores de WhatsApp y simulación';

-- 61. TABLA `organizacion_comunicacion_config` (CONFIGURACIÓN MULTITENANT)
DROP TABLE IF EXISTS `organizacion_comunicacion_config`;
CREATE TABLE `organizacion_comunicacion_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proveedor_id` TINYINT UNSIGNED NOT NULL,
    `modo` ENUM('SIMULADOR', 'PRODUCCION') NOT NULL DEFAULT 'SIMULADOR',
    `numero_telefono_identificador` VARCHAR(30) NOT NULL COMMENT 'Formato internacional E.164 (+51...)',
    `meta_phone_number_id` VARCHAR(60) NULL,
    `meta_waba_id` VARCHAR(60) NULL COMMENT 'WhatsApp Business Account ID',
    `meta_app_id` VARCHAR(60) NULL,
    `token_acceso_cifrado` TEXT NULL COMMENT 'Cifrado simétrico AES-256-GCM',
    `webhook_secret_cifrado` TEXT NULL COMMENT 'Cifrado simétrico AES-256-GCM',
    `webhook_verify_token_hash` VARCHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token para verificación de Meta',
    `limite_mensajes_por_segundo` TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `presupuesto_mensual_limite_usd` DECIMAL(10,2) NOT NULL DEFAULT 50.00 COMMENT 'Tope de seguridad por defecto',
    `gasto_acumulado_mes_actual_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_org_comunicacion_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_org_comunicacion_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `comunicacion_proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_org_comunicacion_config` UNIQUE (`organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros de conectividad y límites de mensajería del tenant';

-- 62. TABLA `comunicacion_tarifas` (TARIFAS POR MENSAJE)
DROP TABLE IF EXISTS `comunicacion_tarifas`;
CREATE TABLE `comunicacion_tarifas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proveedor_id` TINYINT UNSIGNED NOT NULL,
    `codigo_pais` VARCHAR(5) NOT NULL DEFAULT 'PE' COMMENT 'ISO alpha-2 o prefijo E.164',
    `categoria_plantilla` ENUM('UTILITY', 'MARKETING', 'AUTHENTICATION', 'SERVICE_CONVERSATION') NOT NULL,
    `costo_unidad_usd` DECIMAL(8,5) NOT NULL,
    `moneda_base` CHAR(3) NOT NULL DEFAULT 'USD',
    `vigente_desde` DATE NOT NULL,
    `vigente_hasta` DATE NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tarifas_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `comunicacion_proveedores` (`id`) ON DELETE RESTRICT,
    KEY `idx_tarifas_busqueda` (`proveedor_id`, `codigo_pais`, `categoria_plantilla`, `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifas de Meta/BSP versionadas en el tiempo';

-- 63. TABLA `comunicacion_plantillas` (PLANTILLAS VERSIONADAS)
DROP TABLE IF EXISTS `comunicacion_plantillas`;
CREATE TABLE `comunicacion_plantillas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Identificador canónico en Meta (snake_case)',
    `idioma` VARCHAR(10) NOT NULL DEFAULT 'es_PE',
    `categoria` ENUM('UTILITY', 'MARKETING', 'AUTHENTICATION') NOT NULL,
    `meta_template_id` VARCHAR(80) NULL,
    `estado_meta` ENUM('DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'PAUSED') NOT NULL DEFAULT 'DRAFT',
    `cuerpo_texto` TEXT NOT NULL,
    `encabezado_tipo` ENUM('NINGUNO', 'TEXTO', 'IMAGEN', 'DOCUMENTO') NOT NULL DEFAULT 'NINGUNO',
    `pie_texto` VARCHAR(120) NULL,
    `parametros_mapeo_json` JSON NOT NULL COMMENT 'Mapeo de variables posicionales {{1}}, {{2}}',
    `version_local` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_plantillas_organizacion` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_plantilla_org_nombre_idioma` UNIQUE (`organizacion_id`, `nombre`, `idioma`),
    CONSTRAINT `uk_comunicacion_plantillas_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plantillas autorizadas y versionadas por tenant';

-- 64. TABLA `comunicacion_consentimientos_canal` (OPT-IN / OPT-OUT)
DROP TABLE IF EXISTS `comunicacion_consentimientos_canal`;
CREATE TABLE `comunicacion_consentimientos_canal` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `canal` VARCHAR(30) NOT NULL DEFAULT 'WHATSAPP',
    `telefono_destino` VARCHAR(30) NOT NULL,
    `finalidad` ENUM('TRANSACCIONAL_OPERATIVO', 'PROMOCIONAL_MARKETING') NOT NULL,
    `estado` ENUM('CONCEDIDO', 'REVOCADO') NOT NULL DEFAULT 'CONCEDIDO',
    `origen_evidencia` VARCHAR(50) NOT NULL COMMENT 'WEB_CHECKBOX, PRESENCIAL_CONTRATO, WHATSAPP_INBOUND',
    `texto_clausula_aceptada` TEXT NULL,
    `direccion_ip_registro` VARCHAR(45) NULL,
    `actor_tipo` VARCHAR(20) NOT NULL COMMENT 'HUMANO o SISTEMA',
    `usuario_id` INT UNSIGNED NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revocado_en` DATETIME NULL,
    CONSTRAINT `fk_consentimiento_canal_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_consentimiento_canal_cli` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_consentimiento_canal_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    KEY `idx_consentimiento_canal_resolucion` (`organizacion_id`, `cliente_id`, `canal`, `finalidad`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evidencias de opt-in y opt-out específicas por canal';

-- 65. TABLA `comunicacion_conversaciones` (HILOS Y SESIONES 24H)
DROP TABLE IF EXISTS `comunicacion_conversaciones`;
CREATE TABLE `comunicacion_conversaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `telefono_cliente` VARCHAR(30) NOT NULL,
    `numero_conversacion` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incrementable al cerrar y reabrir sesión',
    `operador_usuario_id` INT UNSIGNED NULL,
    `estado` ENUM('ABIERTA', 'EN_ATENCION', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    `ultimo_mensaje_cliente_en` DATETIME NULL COMMENT 'Inicio de ventana de 24 horas',
    `ventana_servicio_expira_en` DATETIME NULL,
    `total_mensajes` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_conversaciones_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conversaciones_cli` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conversaciones_operador` FOREIGN KEY (`operador_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `uk_conversacion_activa` UNIQUE (`organizacion_id`, `telefono_cliente`, `numero_conversacion`),
    CONSTRAINT `uk_comunicacion_conversaciones_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hilos y sesiones de atención al cliente';

-- 66. TABLA `comunicacion_campanas` (CAMPAÑAS MASIVAS DESACOPLADAS CON SoD)
DROP TABLE IF EXISTS `comunicacion_campanas`;
CREATE TABLE `comunicacion_campanas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NULL,
    `criterios_segmentacion_json` JSON NOT NULL,
    `estado` ENUM('BORRADOR', 'APROBADA', 'PROGRAMADA', 'EN_EJECUCION', 'PAUSADA', 'COMPLETADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `total_destinatarios` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_enviados` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_entregados` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_fallidos` INT UNSIGNED NOT NULL DEFAULT 0,
    `presupuesto_asignado_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `gasto_ejecutado_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `programada_para` DATETIME NULL,
    `iniciada_en` DATETIME NULL,
    `finalizada_en` DATETIME NULL,
    `aprobado_por` INT UNSIGNED NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_campanas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_plantilla` FOREIGN KEY (`plantilla_id`, `organizacion_id`) REFERENCES `comunicacion_plantillas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_aprobado` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_creado` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_campana_sod` CHECK (`aprobado_por` IS NULL OR `aprobado_por` != `creado_por`),
    CONSTRAINT `uk_comunicacion_campanas_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campañas masivas desacopladas con control SoD';

-- 67. TABLA `comunicacion_mensajes` (OUTBOX TRANSACCIONAL)
DROP TABLE IF EXISTS `comunicacion_mensajes`;
CREATE TABLE `comunicacion_mensajes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `conversacion_id` BIGINT UNSIGNED NULL,
    `campana_id` INT UNSIGNED NULL,
    `tipo_mensaje` ENUM('TRANSACCIONAL', 'PROMOCIONAL', 'RESPUESTA_OPERADOR', 'ENTRANTE_CLIENTE') NOT NULL,
    `direccion` ENUM('SALIENTE', 'ENTRANTE') NOT NULL,
    `canal` VARCHAR(30) NOT NULL DEFAULT 'WHATSAPP',
    `destinatario_telefono` VARCHAR(30) NOT NULL,
    `destinatario_nombre` VARCHAR(150) NOT NULL,
    `cliente_id` INT UNSIGNED NULL,
    `venta_id` BIGINT UNSIGNED NULL,
    `reserva_id` BIGINT UNSIGNED NULL,
    `plantilla_id` INT UNSIGNED NULL,
    `contenido_texto` TEXT NOT NULL COMMENT 'Texto formateado minimizado',
    `parametros_enviados_json` JSON NULL COMMENT 'Valores de variables no sensibles',
    `estado` ENUM('ENCOLADO', 'EN_PROCESO', 'ENVIADO', 'ENTREGADO', 'LEIDO', 'FALLIDO', 'CANCELADO') NOT NULL DEFAULT 'ENCOLADO',
    `peso_estado` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT '10:ENCOLADO, 20:EN_PROCESO, 30:ENVIADO, 40:ENTREGADO, 50:LEIDO, 90:FALLIDO, 95:CANCELADO',
    `wamid` VARCHAR(120) NULL COMMENT 'WhatsApp Message ID',
    `idempotency_key` VARCHAR(64) NOT NULL,
    `costo_estimado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `costo_calculado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `costo_conciliado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `fecha_conciliacion` DATE NULL,
    `intentos_realizados` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_intentos` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `proximo_intento_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `bloqueado_hasta` DATETIME NULL COMMENT 'Lock atómico temporal del worker',
    `creado_por` INT UNSIGNED NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mensajes_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_conversacion` FOREIGN KEY (`conversacion_id`, `organizacion_id`) REFERENCES `comunicacion_conversaciones` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_campana` FOREIGN KEY (`campana_id`, `organizacion_id`) REFERENCES `comunicacion_campanas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_cliente` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_plantilla` FOREIGN KEY (`plantilla_id`, `organizacion_id`) REFERENCES `comunicacion_plantillas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_mensajes_idempotencia_org` UNIQUE (`organizacion_id`, `idempotency_key`),
    CONSTRAINT `uk_comunicacion_mensajes_compuesta` UNIQUE (`id`, `organizacion_id`),
    KEY `idx_mensajes_outbox` (`organizacion_id`, `estado`, `proximo_intento_en`),
    KEY `idx_mensajes_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbox e historial de mensajería con integridad multitenant compuesta';

-- 68. TABLA `comunicacion_intentos_envio` (INTENTOS Y TELEMETRÍA)
DROP TABLE IF EXISTS `comunicacion_intentos_envio`;
CREATE TABLE `comunicacion_intentos_envio` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `mensaje_id` BIGINT UNSIGNED NOT NULL,
    `intento_numero` TINYINT UNSIGNED NOT NULL,
    `http_status` SMALLINT UNSIGNED NULL,
    `meta_error_code` INT NULL,
    `meta_error_subcode` INT NULL,
    `meta_error_message` VARCHAR(255) NULL,
    `latencia_ms` INT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_intentos_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `comunicacion_mensajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos y telemetría de despacho';

-- 69. TABLA `comunicacion_webhook_eventos` (DEDUPLICACIÓN DE WEBHOOKS)
DROP TABLE IF EXISTS `comunicacion_webhook_eventos`;
CREATE TABLE `comunicacion_webhook_eventos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `event_id` VARCHAR(120) NOT NULL COMMENT 'ID único de evento o SHA-256 de payload',
    `tipo_evento` VARCHAR(50) NOT NULL COMMENT 'messages, message_status, etc.',
    `wamid` VARCHAR(120) NULL,
    `payload_resumido_json` JSON NOT NULL,
    `procesado` TINYINT(1) NOT NULL DEFAULT 0,
    `error_proceso` VARCHAR(255) NULL,
    `recibido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `procesado_en` DATETIME NULL,
    CONSTRAINT `fk_webhook_eventos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_webhook_event_org` UNIQUE (`organizacion_id`, `event_id`),
    KEY `idx_webhook_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deduplicación e idempotencia estricta de webhooks entrantes';

-- 70. TABLA `comunicacion_secuencias` (CORRELATIVOS ATÓMICOS)
DROP TABLE IF EXISTS `comunicacion_secuencias`;
CREATE TABLE `comunicacion_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo` VARCHAR(20) NOT NULL COMMENT 'MENSAJE, CAMPANA',
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`organizacion_id`, `tipo`),
    CONSTRAINT `fk_com_secuencias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generador atómico de correlativos de mensajería';

-- Semillas de comunicaciones y RBAC módulo 12
INSERT INTO `comunicacion_proveedores` (`id`, `codigo`, `nombre`, `tipo_integracion`, `activo`)
VALUES
    (1, 'SIMULADOR_SANDBOX', 'Simulador WhatsApp Local (Sandbox)', 'LOCAL_SIMULADOR', 1),
    (2, 'META_CLOUD_API', 'Meta WhatsApp Business Cloud API', 'DIRECTA_META', 1),
    (3, 'TWILIO_BSP', 'Twilio Messaging API (BSP)', 'BSP_PARTNER', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `tipo_integracion` = VALUES(`tipo_integracion`);

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
    (78, 12, 'comunicaciones.ver', 'Ver Mensajes y Conversaciones', 'Consulta de bandeja de mensajes, estados y conversaciones'),
    (79, 12, 'comunicaciones.enviar_individual', 'Enviar Mensajes Individuales', 'Envío manual de notificaciones transaccionales o respuestas de operador'),
    (80, 12, 'comunicaciones.gestionar_campanas', 'Crear y Segmentar Campañas', 'Creación y configuración de campañas promocionales para clientes con opt-in'),
    (81, 12, 'comunicaciones.aprobar_campanas', 'Aprobar Campañas Masivas', 'Autorización formal y liberación de presupuesto para ejecución de campañas'),
    (82, 12, 'comunicaciones.gestionar_plantillas', 'Gestionar Plantillas de WhatsApp', 'Creación y versionado de plantillas oficiales asociadas a Meta'),
    (83, 12, 'comunicaciones.configurar_proveedor', 'Configurar Proveedor WhatsApp', 'Administración de credenciales de Meta, webhooks, presupuestos y modo')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (1, 78), (1, 79), (1, 80), (1, 81), (1, 82), (1, 83),
    (2, 78), (2, 79), (2, 80), (2, 81), (2, 82), (2, 83),
    (3, 78), (3, 79);

INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_09_000017_crear_modulo_comunicaciones_whatsapp_rbac.sql', 15)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;


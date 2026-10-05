-- ==============================================================================
-- MIGRACIÓN: 2026_10_05_000006_crear_modulo_clientes_consentimientos.sql
-- DESCRIPCIÓN: Fase 2.2A - Modelo Maestro de Personas y Fundamentos Clientes/CRM
--              1. Documento opcional y coherente en personas (ambos NULL o ambos presentes)
--              2. Índice de búsqueda de personas por WhatsApp dentro de la organización
--              3. Creación de tabla 'clientes' (perfil comercial único por persona/tenant)
--              4. Creación de tabla 'consentimientos_cliente' (trazabilidad append-only)
--              5. Permisos RBAC atómicos de Personas y Clientes para roles del sistema
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. ACTUALIZACIÓN ESTRUCTURAL DE `personas`
-- ------------------------------------------------------------------------------
ALTER TABLE `personas`
    MODIFY COLUMN `tipo_documento_id` TINYINT UNSIGNED NULL,
    MODIFY COLUMN `numero_documento` VARCHAR(30) NULL;

-- Agregar restricción de coherencia en documento y búsqueda rápida por WhatsApp
ALTER TABLE `personas`
    ADD CONSTRAINT `chk_personas_documento_coherencia` CHECK (
        (`tipo_documento_id` IS NULL AND `numero_documento` IS NULL)
        OR
        (`tipo_documento_id` IS NOT NULL AND `numero_documento` IS NOT NULL)
    ),
    ADD KEY `idx_personas_org_whatsapp` (`organizacion_id`, `telefono_whatsapp`);

-- ------------------------------------------------------------------------------
-- 2. TABLA `clientes` (PERFIL COMERCIAL SOBERANO Y UNIFICADO)
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
    `origen_captacion` VARCHAR(50) DEFAULT NULL COMMENT 'ej. WHATSAPP, FERIA, RECOMENDADO, ORGANICO',
    `notas_comerciales` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_clientes_org_persona` (`organizacion_id`, `persona_id`),
    KEY `idx_clientes_estado` (`estado_comercial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfil comercial único y transversal por persona y tenant';

-- ------------------------------------------------------------------------------
-- 3. TABLA `consentimientos_cliente` (BITÁCORA INMUTABLE APPEND-ONLY)
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
-- 4. PERMISOS ATÓMICOS DE SEGURIDAD (MÓDULO 5: CLIENTES / CRM)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(19, 5, 'personas.ver', 'Ver Personas', 'Permite consultar el padrón de identidades y personas'),
(20, 5, 'personas.crear', 'Crear Persona', 'Permite registrar nuevas identidades naturales y jurídicas'),
(21, 5, 'personas.editar', 'Editar Persona', 'Permite actualizar datos de contacto e identidad de personas'),
(22, 5, 'clientes.ver', 'Ver Clientes', 'Permite consultar la cartera comercial, estados y consentimientos'),
(23, 5, 'clientes.crear', 'Crear Cliente', 'Permite incorporar personas al ciclo comercial como contacto o cliente'),
(24, 5, 'clientes.editar', 'Editar Cliente', 'Permite modificar datos comerciales y consentimientos de clientes'),
(25, 5, 'clientes.desactivar', 'Desactivar Cliente', 'Permite transicionar clientes al estado INACTIVO sin borrado físico')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 5. ASIGNACIÓN DE PRIVILEGIOS A ROLES DE SISTEMA
-- ------------------------------------------------------------------------------
-- Superadministrador de Plataforma (acceso pleno)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 19), (1, 20), (1, 21), (1, 22), (1, 23), (1, 24), (1, 25);

-- Administrador de Organización (gestión completa de personas y clientes en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 19), (2, 20), (2, 21), (2, 22), (2, 23), (2, 24), (2, 25);

-- Operador de Producción (solo consulta)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 19), (3, 22);

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- MIGRACIÓN: 2026_10_05_000008_crear_nucleo_crm_oportunidades_interacciones.sql
-- DESCRIPCIÓN: Fase 2.2B - Servicios de CRM y Gestión Comercial
--              1. Saneamiento: Eliminar clientes.origen_captacion (sin duplicar fuentes)
--              2. Tabla 'origenes_comerciales' (catálogo relacional administrable por tenant)
--              3. Semillas de orígenes comerciales para tenants existentes
--              4. Tabla 'crm_oportunidades' (pipeline comercial, snapshot de moneda, version_bloqueo)
--              5. Tabla 'crm_oportunidad_historial_etapas' (append-only de transiciones)
--              6. Tabla 'crm_interacciones' (bitácora de comunicaciones con actor soberano)
--              7. Permisos RBAC de Oportunidades e Interacciones CRM
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. SANEAMIENTO DE `clientes`: ELIMINAR `origen_captacion`
-- ------------------------------------------------------------------------------
ALTER TABLE `clientes` DROP COLUMN `origen_captacion`;

-- ------------------------------------------------------------------------------
-- 2. TABLA `origenes_comerciales` (CATÁLOGO EXTENSIBLE POR ORGANIZACIÓN)
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
-- 3. SEMILLAS INICIALES DE ORÍGENES COMERCIALES
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `origenes_comerciales` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `activo`, `orden`)
SELECT o.`id`, s.`codigo`, s.`nombre`, s.`descripcion`, 1, s.`orden`
FROM `organizaciones` o
CROSS JOIN (
    SELECT 'WEB_ORGANICA' AS `codigo`, 'Web Orgánica' AS `nombre`, 'Búsqueda directa u orgánica en sitio web' AS `descripcion`, 1 AS `orden` UNION ALL
    SELECT 'REDES_SOCIALES', 'Redes Sociales', 'Contacto proveniente de Facebook, Instagram o TikTok', 2 UNION ALL
    SELECT 'CAMPANA_PUBLICITARIA', 'Campaña Publicitaria', 'Pauta digital o anuncios de pago (Ads)', 3 UNION ALL
    SELECT 'REFERIDO', 'Referido', 'Recomendación de cliente anterior o contacto personal', 4 UNION ALL
    SELECT 'FERIA_EVENTO', 'Feria / Evento', 'Captación presencial en ferias turísticas o activaciones', 5 UNION ALL
    SELECT 'PROSPECCION_DIRECTA', 'Prospección Directa', 'Contacto directo por asesor comercial', 6 UNION ALL
    SELECT 'CONVENIO_INSTITUCIONAL', 'Convenio Institucional', 'Acuerdos corporativos o alianzas estratégicas', 7 UNION ALL
    SELECT 'OTRO', 'Otro Origen', 'Fuente de captación no tipada en catálogo inicial', 8
) s;

-- ------------------------------------------------------------------------------
-- 4. TABLA `crm_oportunidades` (PIPELINE COMERCIAL DE EDICIÓN)
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
-- 5. TABLA `crm_oportunidad_historial_etapas` (BITÁCORA APPEND-ONLY DE ETAPAS)
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
-- 6. TABLA `crm_interacciones` (BITÁCORA APPEND-ONLY DE INTERACCIONES COMERCIALES)
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
-- 7. PERMISOS ATÓMICOS DE SEGURIDAD (MÓDULO 2: CRM / PROSPECTOS)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(26, 2, 'crm.oportunidades.ver', 'Ver Oportunidades Comerciales', 'Permite consultar el pipeline y fichas de oportunidades comerciales'),
(27, 2, 'crm.oportunidades.crear', 'Crear Oportunidad Comercial', 'Permite registrar nuevas intenciones comerciales y prospectos en una edición'),
(28, 2, 'crm.oportunidades.editar', 'Editar Oportunidad Comercial', 'Permite actualizar datos comerciales, valor estimado y seguimiento de oportunidades'),
(29, 2, 'crm.oportunidades.cambiar_etapa', 'Cambiar Etapa de Oportunidad', 'Permite avanzar o cerrar oportunidades en el pipeline comercial'),
(30, 2, 'crm.oportunidades.asignar', 'Asignar Asesor a Oportunidad', 'Permite asignar o reasignar el responsable comercial de una oportunidad'),
(31, 2, 'crm.interacciones.ver', 'Ver Interacciones Comerciales', 'Permite consultar la bitácora de llamadas, notas y mensajes de CRM'),
(32, 2, 'crm.interacciones.crear', 'Registrar Interacción Comercial', 'Permite añadir llamadas, reuniones, minutas y notas de seguimiento en CRM')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 8. ASIGNACIÓN DE PRIVILEGIOS A ROLES DE SISTEMA
-- ------------------------------------------------------------------------------
-- Superadministrador de Plataforma (acceso pleno a CRM)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 26), (1, 27), (1, 28), (1, 29), (1, 30), (1, 31), (1, 32);

-- Administrador de Organización (gestión completa de CRM en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 26), (2, 27), (2, 28), (2, 29), (2, 30), (2, 31), (2, 32);

-- Operador de Producción (solo consulta de CRM)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 26), (3, 31);

SET FOREIGN_KEY_CHECKS = 1;

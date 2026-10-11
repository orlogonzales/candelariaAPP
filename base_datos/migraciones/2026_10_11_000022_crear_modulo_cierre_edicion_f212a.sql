-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000022_crear_modulo_cierre_edicion_f212a.sql
-- DESCRIPCIÓN: Fase 2.12A - Cierre Administrativo y Financiero de Edición
--              Crea las tablas `edicion_cierres` y `edicion_cierre_reaperturas`
--              para el congelamiento inmutable de estados financieros,
--              diagnóstico de incidencias por severidad (bloqueantes vs observaciones),
--              trazabilidad de cierres condicionados y procedimiento excepcional
--              de reversibilidad/reapertura con auditoría.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 85. TABLA `edicion_cierres` (CONGELAMIENTO FINANCIERO Y AUDITORÍA DE CIERRE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `edicion_cierres`;
CREATE TABLE `edicion_cierres` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `tipo_cierre` ENUM('DEFINITIVO', 'CONDICIONADO') NOT NULL DEFAULT 'DEFINITIVO',
    `estado` ENUM('VIGENTE', 'REABIERTO_EXCEPCIONAL') NOT NULL DEFAULT 'VIGENTE',
    `usuario_cierre_id` INT UNSIGNED NOT NULL,
    `fecha_cierre` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resumen_financiero_json` JSON NOT NULL COMMENT 'Snapshot congelado de ventas, cobros, saldos e ingresos',
    `diagnostico_incidencias_json` JSON NOT NULL COMMENT 'Incidencias y observaciones evaluadas durante el cierre',
    `justificacion_condicionado` TEXT DEFAULT NULL COMMENT 'Obligatorio en cierres condicionados con saldos o entregas pendientes',
    `autorizado_por_usuario_id` INT UNSIGNED DEFAULT NULL COMMENT 'Usuario con facultades superiores que autoriza el cierre condicionado',
    `hash_integridad_cierre` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 criptográfico inmutable del snapshot',
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cierre_org_edicion` (`organizacion_id`, `edicion_id`),
    KEY `idx_cierre_estado` (`organizacion_id`, `estado`),
    CONSTRAINT `fk_cierre_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cierre_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cierre_usuario` FOREIGN KEY (`usuario_cierre_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cierre_autorizador` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actas de cierre administrativo y financiero oficial de festividades';

-- ------------------------------------------------------------------------------
-- 86. TABLA `edicion_cierre_reaperturas` (REVERSIBILIDAD EXCEPCIONAL AUDITADA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `edicion_cierre_reaperturas`;
CREATE TABLE `edicion_cierre_reaperturas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cierre_id` BIGINT UNSIGNED NOT NULL,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `solicitado_por_usuario_id` INT UNSIGNED NOT NULL,
    `autorizado_por_usuario_id` INT UNSIGNED NOT NULL,
    `motivo_excepcional` TEXT NOT NULL COMMENT 'Causa formal y fundamentada para rectificación extraordinaria',
    `estado_anterior_edicion` VARCHAR(30) NOT NULL DEFAULT 'CERRADA',
    `nuevo_estado_edicion` VARCHAR(30) NOT NULL DEFAULT 'POSTPRODUCCION_ENTREGA',
    `fecha_reapertura` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `hash_integridad_previo` CHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_reapertura_cierre` (`cierre_id`),
    KEY `idx_reapertura_org_edicion` (`organizacion_id`, `edicion_id`),
    CONSTRAINT `fk_reapertura_cierre` FOREIGN KEY (`cierre_id`) REFERENCES `edicion_cierres` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reapertura_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reapertura_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reapertura_solicitante` FOREIGN KEY (`solicitado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reapertura_autorizador` FOREIGN KEY (`autorizado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora auditada de reaperturas extraordinarias de ediciones cerradas';

-- ------------------------------------------------------------------------------
-- PERMISOS RBAC PARA CIERRE Y REAPERTURA DE EDICIONES (MÓDULO 3)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(3, 'ediciones.cerrar', 'Cerrar Edición Administrativa y Financieramente', 'Permite ejecutar el asistente y asentar el acta oficial de cierre de una festividad'),
(3, 'ediciones.reabrir_excepcional', 'Reabrir Edición Excepcionalmente', 'Permite autorizar la reapertura formal y justificada de una edición previamente cerrada')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar nuevos permisos al rol ADMINISTRADOR
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'ADMINISTRADOR'
  AND p.codigo IN ('ediciones.cerrar', 'ediciones.reabrir_excepcional');

-- Registrar migración en la bitácora de control
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_11_000022_crear_modulo_cierre_edicion_f212a.sql', 20)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

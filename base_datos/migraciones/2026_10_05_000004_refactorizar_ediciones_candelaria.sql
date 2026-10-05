-- ==============================================================================
-- MIGRACIÓN: 2026_10_05_000004_refactorizar_ediciones_candelaria.sql
-- DESCRIPCIÓN: Fase 2.1A - Modelo de Dominio Edición Candelaria y Ciclo de Vida
--              Refactorización de la tabla ediciones_candelaria para soporte
--              estricto de dominio: columna anio (sustituye ano), columna codigo,
--              estado VARCHAR con CHECK constraint, índices de unicidad y
--              permisos RBAC atómicos para el Módulo 3 (ediciones).
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. REFACTORIZACIÓN ESTRUCTURAL DE `ediciones_candelaria`
-- ------------------------------------------------------------------------------
-- Como la tabla se encuentra con 0 registros (Fase 0/1), realizamos la
-- normalización formal de columnas, restricciones e índices.
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
    `configuracion_json` JSON DEFAULT NULL COMMENT 'Parámetros e hitos específicos de la edición',
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
-- 2. PERMISOS ATÓMICOS DE SEGURIDAD (MÓDULO 3: EDICIONES CANDELARIA)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(14, 3, 'ediciones.ver', 'Ver Ediciones', 'Permite consultar el catálogo general, histórico y detalles de ediciones'),
(15, 3, 'ediciones.crear', 'Crear Edición', 'Permite dar de alta una nueva edición Candelaria en la organización'),
(16, 3, 'ediciones.editar', 'Editar Edición', 'Permite modificar información general, lema y fechas de la edición'),
(17, 3, 'ediciones.cambiar_estado', 'Cambiar Estado de Edición', 'Permite transicionar entre fases del ciclo de vida de la edición'),
(18, 3, 'ediciones.seleccionar_actual', 'Seleccionar Edición Actual', 'Permite establecer la edición operativa activa por defecto de la organización')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 3. ASIGNACIÓN DE PRIVILEGIOS A ROLES DE SISTEMA
-- ------------------------------------------------------------------------------
-- Superadministrador de Plataforma (acceso pleno)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 14), (1, 15), (1, 16), (1, 17), (1, 18);

-- Administrador de Organización (gestión completa de su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 14), (2, 15), (2, 16), (2, 17), (2, 18);

-- Operador de Producción (solo lectura del catálogo de ediciones)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 14);

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN INCREMENTAL
-- ==============================================================================
-- Archivo: 2026_10_05_000010_agregar_permiso_catalogo_tarifas_ver_historial.sql
-- Propósito: Formalización del permiso RBAC catalogo.tarifas.ver_historial (ID 40)
-- Módulo: Catálogo Comercial (Módulo 4)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. REGISTRO DEL PERMISO SOBERANO catalogo.tarifas.ver_historial (ID 40)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(40, 4, 'catalogo.tarifas.ver_historial', 'Ver Historial de Tarifas Comerciales', 'Permite consultar la bitácora histórica y transiciones de tarifas de ítems y paquetes')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 2. ASIGNACIÓN DE ROLES
-- Separación de privilegios: Solo roles administrativos consultan historial financiero
-- Rol 1: Superadministrador de Plataforma
-- Rol 2: Administrador de Organización
-- (Rol 3 Operador de Producción mantiene solo 'catalogo.ver')
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 40),
(2, 40);

-- ------------------------------------------------------------------------------
-- 3. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 8)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_05_000010_agregar_permiso_catalogo_tarifas_ver_historial.sql', 8)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- MIGRACIÓN 000012: AJUSTE Y HARDENING DE CONTRATOS SOBERANOS DE COTIZACIONES
-- FASE F2.4B-1: PROCEDENCIA DE OFERTAS, RBAC SOBERANO, GOBERNANZA DE DESCUENTOS Y ESTADOS
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. LIMPIEZA DE PERMISOS NO SOBERANOS O RENOMBRADOS
-- ------------------------------------------------------------------------------
DELETE rp FROM `rol_permisos` rp
JOIN `permisos` p ON rp.`permiso_id` = p.`id`
WHERE p.`codigo` IN ('cotizaciones.eliminar', 'cotizaciones.revisar');

DELETE FROM `permisos` 
WHERE `codigo` IN ('cotizaciones.eliminar', 'cotizaciones.revisar');

-- ------------------------------------------------------------------------------
-- 2. ASEGURAR EXACTAMENTE LOS 9 PERMISOS SOBERANOS DEL MÓDULO 20
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(41, 20, 'cotizaciones.ver', 'Ver Cotizaciones', 'Permite consultar listados, fichas y trazabilidad de cotizaciones comerciales'),
(42, 20, 'cotizaciones.crear', 'Crear Cotización', 'Permite dar de alta borradores de cotización para clientes en una edición'),
(43, 20, 'cotizaciones.editar', 'Editar Cotización', 'Permite actualizar líneas, conceptos y condiciones en cotizaciones borrador'),
(44, 20, 'cotizaciones.emitir', 'Emitir Cotización', 'Permite congelar snapshot y asignar correlativo formal a la cotización'),
(45, 20, 'cotizaciones.crear_revision', 'Crear Revisión de Cotización', 'Permite generar una nueva revisión editable a partir de una cotización emitida'),
(46, 20, 'cotizaciones.aceptar', 'Aceptar Cotización', 'Permite registrar la aceptación comercial formal del cliente'),
(47, 20, 'cotizaciones.rechazar', 'Rechazar Cotización', 'Permite registrar el rechazo del cliente con motivo obligatorio justificado'),
(48, 20, 'cotizaciones.anular', 'Anular Cotización', 'Permite anular administrativamente una cotización o sustituirla por revisión'),
(49, 20, 'cotizaciones.aplicar_descuento', 'Aplicar Descuento en Cotización', 'Permite otorgar descuentos globales o por línea con motivo justificado')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador (1) y Administrador (2): Todos los 9 permisos
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 41), (1, 42), (1, 43), (1, 44), (1, 45), (1, 46), (1, 47), (1, 48), (1, 49),
(2, 41), (2, 42), (2, 43), (2, 44), (2, 45), (2, 46), (2, 47), (2, 48), (2, 49);

-- Operador (3): Solo lectura
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 41);

-- ------------------------------------------------------------------------------
-- 3. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 10)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_06_000012_ajustar_contratos_soberanos_cotizaciones.sql', 10)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- SEMILLA: 2026_10_02_000002_semillas_identidad_actores.sql
-- DESCRIPCIÓN: Datos iniciales de catálogos técnicos: Tipos de Documento,
--              Actores de Sistema Oficiales, Canales de Operación y Permisos de Usuarios.
--              PROHIBIDO: No introducir datos ficticios de negocio.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TIPOS DE DOCUMENTO OFICIALES
-- ------------------------------------------------------------------------------
INSERT INTO `tipos_documento` (`id`, `codigo`, `nombre`, `aplica_a`, `longitud_exacta`, `es_alfanumerico`, `activo`) VALUES
(1, 'DNI', 'DOCUMENTO NACIONAL DE IDENTIDAD', 'NATURAL', 8, 0, 1),
(2, 'RUC', 'REGISTRO ÚNICO DE CONTRIBUYENTES', 'AMBOS', 11, 0, 1),
(3, 'PASAPORTE', 'PASAPORTE INTERNACIONAL', 'NATURAL', NULL, 1, 1),
(4, 'CARNET_EXTRANJERIA', 'CARNÉ DE EXTRANJERÍA', 'NATURAL', 12, 1, 1),
(5, 'CEDULA', 'CÉDULA DE IDENTIDAD', 'NATURAL', NULL, 1, 1),
(6, 'OTRO', 'OTRO DOCUMENTO OFICIAL', 'AMBOS', NULL, 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `aplica_a` = VALUES(`aplica_a`);

-- ------------------------------------------------------------------------------
-- 2. ACTORES DE SISTEMA OFICIALES (IDENTIDADES TÉCNICAS CONTROLADAS)
-- ------------------------------------------------------------------------------
INSERT INTO `actores_sistema` (`id`, `codigo`, `nombre`, `descripcion`, `es_critico`, `activo`) VALUES
(1, 'LANDING_CANDELARIA', 'Landing Page Candelaria', 'Formularios y reservas desde la web pública', 0, 1),
(2, 'CHECKOUT_PASARELA', 'Pasarela de Pagos', 'Confirmaciones asíncronas y webhooks de pasarela de pagos', 1, 1),
(3, 'WORKER_CONCILIACION', 'Worker de Conciliación', 'Proceso automático nocturno de conciliación bancaria', 1, 1),
(4, 'IMPORTADOR_DATOS', 'Importador de Datos', 'Procesos masivos de migración e importación', 0, 1),
(5, 'SISTEMA_CLI', 'Consola del Sistema (CLI)', 'Comandos de mantenimiento ejecutados por terminal CLI', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 3. CANALES DE OPERACIÓN OFICIALES (EXTENSIBLES)
-- ------------------------------------------------------------------------------
INSERT INTO `canales` (`id`, `codigo`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'APP', 'Aplicación Interna', 'Panel web administrativo de gestión Alina', 1),
(2, 'WEB', 'Web Pública / Landing', 'Portales y landing pages públicas de clientes', 1),
(3, 'API', 'Interfaz de Programación (API)', 'Consumos de servicios web externos y microservicios', 1),
(4, 'APP_MOVIL', 'Aplicación Móvil', 'Futuras aplicaciones Android e iOS', 1),
(5, 'WHATSAPP', 'Canal WhatsApp', 'Integración mediante API oficial de mensajería', 1),
(6, 'IMPORTACION', 'Importación Masiva', 'Cargas por archivos CSV/Excel/CLI', 1),
(7, 'API_PARTNER', 'API de Aliados / Socios', 'Integraciones B2B con terceros autorizados', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 4. PERMISOS ATÓMICOS DE ADMINISTRACIÓN DE USUARIOS (MÓDULO 16: ADMINISTRACIÓN)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(1, 16, 'usuarios.ver', 'Ver Usuarios', 'Permite consultar el padrón de usuarios y sus perfiles'),
(2, 16, 'usuarios.crear', 'Crear Usuario', 'Permite dar de alta nuevos usuarios vinculados a personas'),
(3, 16, 'usuarios.editar', 'Editar Usuario', 'Permite modificar información y credenciales de usuarios'),
(4, 16, 'usuarios.desactivar', 'Activar/Desactivar Usuario', 'Permite suspender o habilitar cuentas de usuario'),
(5, 16, 'usuarios.roles', 'Asignar Roles', 'Permite modificar la asignación de roles de seguridad'),
(6, 16, 'usuarios.restablecer_clave', 'Restablecer Contraseña', 'Permite generar o forzar restablecimiento de clave')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación inicial de permisos a roles técnicos
-- Rol 1: SUPERADMINISTRADOR DE PLATAFORMA (Acceso total)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6);

-- Rol 2: ADMINISTRADOR DE ORGANIZACIÓN (Gestión de usuarios de su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6);

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- SEMILLA: 2026_10_01_000001_semillas_fundacionales.sql
-- DESCRIPCIÓN: Datos iniciales estrictamente técnicos y fundacionales del sistema.
--              (Catálogo de módulos oficiales, roles base, permisos del sistema)
--              PROHIBIDO: No introducir datos ficticios de clientes, pagos o ventas.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. CATÁLOGO DE MÓDULOS OFICIALES (19 DOMINIOS DE CANDELARIAAPP)
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`) VALUES
(1, 'dashboard', 'DASHBOARD', 'Panel principal de estadísticas y métricas', 'fa-solid fa-gauge-high', 1, 1, 'ACTIVO'),
(2, 'crm_prospectos', 'PRE-CANDELARIA / CRM', 'Preagenda anual de prospectos y conversiones', 'fa-solid fa-user-plus', 2, 0, 'ACTIVO'),
(3, 'ediciones', 'EDICIONES CANDELARIA', 'Gestión histórica y activa de ediciones anuales', 'fa-solid fa-calendar-check', 3, 1, 'ACTIVO'),
(4, 'catalogo', 'CATÁLOGO COMERCIAL', 'Paquetes, prestaciones, tarifas y condiciones', 'fa-solid fa-tags', 4, 0, 'ACTIVO'),
(5, 'clientes', 'CLIENTES', 'Padrón unificado de clientes con WhatsApp', 'fa-solid fa-users', 5, 1, 'ACTIVO'),
(6, 'ventas_reservas', 'RESERVAS Y VENTAS', 'Contratos, participaciones y reservas comerciales', 'fa-solid fa-file-invoice-dollar', 6, 0, 'ACTIVO'),
(7, 'programacion_folclorica', 'PROGRAMACIÓN FOLCLÓRICA', 'Conjuntos, bloques, paradas y veneración', 'fa-solid fa-masks-theater', 7, 0, 'ACTIVO'),
(8, 'agenda', 'AGENDA', 'Citas, reuniones y puntos de encuentro en Puno', 'fa-solid fa-calendar-days', 8, 0, 'ACTIVO'),
(9, 'operaciones_cobertura', 'OPERACIONES Y COBERTURA', 'Despliegue en campo y cobertura audiovisual', 'fa-solid fa-video', 9, 0, 'ACTIVO'),
(10, 'identificacion_activos', 'IDENTIFICACIÓN Y ACTIVOS', 'Dispositivos, pines, credenciales y trazabilidad', 'fa-solid fa-id-badge', 10, 0, 'ACTIVO'),
(11, 'pagos_caja', 'PAGOS Y CAJA', 'Registro de pagos, saldos, pasarelas y control de caja', 'fa-solid fa-cash-register', 11, 0, 'ACTIVO'),
(12, 'comunicaciones', 'COMUNICACIONES', 'Mensajería individual WhatsApp, segmentos y plantillas', 'fa-solid fa-comments', 12, 0, 'ACTIVO'),
(13, 'postproduccion_entregas', 'POSTPRODUCCIÓN Y ENTREGAS', 'Edición audiovisual, galerías y entregas de material', 'fa-solid fa-photo-film', 13, 0, 'ACTIVO'),
(14, 'resenas', 'RESEÑAS', 'Calificaciones, testimonios y reputación', 'fa-solid fa-star', 14, 0, 'ACTIVO'),
(15, 'reportes', 'REPORTES', 'Inteligencia de negocio y reportes ejecutivos', 'fa-solid fa-chart-line', 15, 0, 'ACTIVO'),
(16, 'administracion', 'ADMINISTRACIÓN', 'Usuarios, roles, permisos y bitácora de auditoría', 'fa-solid fa-user-shield', 16, 1, 'ACTIVO'),
(17, 'organizacion_config', 'CONFIGURACIÓN DE ORGANIZACIÓN', 'Identidad de tenant, logotipos y parámetros', 'fa-solid fa-building', 17, 1, 'ACTIVO'),
(18, 'plataforma_admin', 'ADMINISTRACIÓN DE PLATAFORMA', 'Ámbito exclusivo del superadministrador', 'fa-solid fa-server', 18, 1, 'ACTIVO'),
(19, 'saas_evolucion', 'PLATAFORMA SAAS', 'Evolución multiempresa y licenciamiento', 'fa-solid fa-cloud', 19, 1, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono_fontawesome` = VALUES(`icono_fontawesome`);

-- 2. PLANES BASE DEL SISTEMA
INSERT INTO `planes` (`id`, `codigo`, `nombre`, `descripcion`, `precio_mensual`, `estado`) VALUES
(1, 'plan_estudio_v1', 'PLAN V1 ESTUDIO', 'Plan base para operación inicial de O.G. Estudio Creativo', 0.00, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 3. ROLES DE SISTEMA
INSERT INTO `roles` (`id`, `organizacion_id`, `codigo`, `nombre`, `descripcion`, `es_sistema`) VALUES
(1, NULL, 'superadmin_plataforma', 'SUPERADMINISTRADOR DE PLATAFORMA', 'Acceso irrestricto al núcleo y administración de plataforma', 1),
(2, NULL, 'admin_organizacion', 'ADMINISTRADOR DE ORGANIZACIÓN', 'Gestión integral del tenant y sus operaciones', 1),
(3, NULL, 'operador_produccion', 'OPERADOR DE PRODUCCIÓN', 'Operador de campo, cobertura y entregas audiovisuales', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

SET FOREIGN_KEY_CHECKS = 1;

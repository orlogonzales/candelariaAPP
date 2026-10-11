-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000023_crear_permisos_reportes_y_dashboard_f212bc.sql
-- DESCRIPCIÓN: Fase 2.12B-1 / F2.12C - Permisos RBAC granulares para consulta y
--              exportación de reportes oficiales (Principio de Mínimo Privilegio)
--              y acceso al Dashboard Ejecutivo de Ediciones.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- REGISTRO DE PERMISOS ATÓMICOS PARA REPORTES Y DASHBOARD (MÓDULO 3)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(3, 'ediciones.reportes.ingresos.ver', 'Ver Libro Oficial de Ingresos y Cobranzas', 'Permite consultar en pantalla los totales y registros financieros de ventas y recaudación'),
(3, 'ediciones.reportes.ingresos.exportar', 'Exportar Libro Oficial de Ingresos', 'Permite descargar en CSV, XLSX o PDF el libro financiero de ingresos y cobranzas'),
(3, 'ediciones.reportes.salidas.ver', 'Ver Manifiesto de Salidas Operativas', 'Permite consultar el manifiesto operativo de campo, flota y guías'),
(3, 'ediciones.reportes.salidas.exportar', 'Exportar Manifiesto de Salidas Operativas', 'Permite descargar el manifiesto operativo en CSV, XLSX o PDF'),
(3, 'ediciones.reportes.asistencia.ver', 'Ver Padrón de Asistencia Ley 29733', 'Permite consultar el padrón de participantes y asistencia con datos minimizados'),
(3, 'ediciones.reportes.asistencia.exportar', 'Exportar Padrón de Asistencia Ley 29733', 'Permite descargar el padrón de participantes bajo estricto cumplimiento de protección de datos personales'),
(3, 'dashboard.ejecutivo.ver', 'Ver Dashboard Ejecutivo de Ediciones', 'Permite acceder al panel de control ejecutivo con KPIs estratégicos, ventas, cobranzas y ocupación')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar nuevos permisos a los roles administrativos
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo IN ('ADMINISTRADOR', 'admin_organizacion', 'superadmin_plataforma')
  AND p.codigo IN (
      'ediciones.reportes.ingresos.ver',
      'ediciones.reportes.ingresos.exportar',
      'ediciones.reportes.salidas.ver',
      'ediciones.reportes.salidas.exportar',
      'ediciones.reportes.asistencia.ver',
      'ediciones.reportes.asistencia.exportar',
      'dashboard.ejecutivo.ver'
  );

-- Asignar permisos operativos de solo lectura al operador de producción
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo IN ('OPERADOR', 'operador_produccion')
  AND p.codigo IN (
      'ediciones.reportes.salidas.ver',
      'ediciones.reportes.asistencia.ver'
  );

-- Registrar migración en la bitácora de control
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_11_000023_crear_permisos_reportes_y_dashboard_f212bc.sql', 21)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

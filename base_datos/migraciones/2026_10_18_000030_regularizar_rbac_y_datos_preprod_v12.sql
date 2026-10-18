-- ==============================================================================
-- CANDELARIAAPP V1.2 - MIGRACIÓN 000030 (DT-V12-009)
-- REGULARIZACIÓN DE MATRIZ RBAC (M03, M13, M24) SEGÚN COMPETENCIAS MÍNIMAS
-- Y SANEAMIENTO DE CÓDIGOS HISTÓRICOS DE CATEGORÍAS DE CATÁLOGO
-- ==============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------------------
-- 1. H-01: ASIGNACIÓN RBAC A ROLES ADMINISTRATIVOS (superadmin_plataforma, admin_organizacion)
--    Reciben los 13 permisos huérfanos de M03 (Cierre), M13 (Documentos) y M24 (Postproducción)
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo IN ('superadmin_plataforma', 'admin_organizacion')
  AND p.codigo IN (
      -- Módulo 13: Documentos e Impresión Oficial (5 permisos)
      'documentos.cotizaciones.descargar',
      'documentos.contratos.descargar',
      'documentos.recibos.emitir',
      'documentos.recibos.descargar',
      'documentos.boletos.descargar',
      -- Módulo 24: Postproducción y Galerías Multimedia (6 permisos)
      'postproduccion.proyectos.ver',
      'postproduccion.proyectos.crear',
      'postproduccion.proyectos.editar',
      'postproduccion.archivos.subir',
      'postproduccion.archivos.descargar',
      'postproduccion.entregas.gestionar',
      -- Módulo 03: Cierre y Reapertura Excepcional de Edición (2 permisos)
      'ediciones.cerrar',
      'ediciones.reabrir_excepcional'
  );

-- ------------------------------------------------------------------------------
-- 2. H-01: MATRIZ DE COMPETENCIAS MÍNIMA PARA ROL OPERATIVO (operador_produccion)
--    Definición del rol: "Operador de campo, cobertura y entregas audiovisuales"
--    ALLOW (6 permisos operativos):
--      - documentos.cotizaciones.descargar (coherente con cotizaciones.ver = 1)
--      - documentos.boletos.descargar (impresión de boleto QR para control de campo/embarque)
--      - postproduccion.proyectos.ver (consulta de proyectos audiovisuales asignados)
--      - postproduccion.archivos.subir (carga de fotografías y videos de cobertura)
--      - postproduccion.archivos.descargar (verificación técnica de material audiovisual)
--      - postproduccion.entregas.gestionar (preparación y publicación de entregas audiovisuales)
--    DENY (7 permisos administrativos/financieros/directivos):
--      - documentos.contratos.descargar, documentos.recibos.emitir, documentos.recibos.descargar
--      - postproduccion.proyectos.crear, postproduccion.proyectos.editar
--      - ediciones.cerrar, ediciones.reabrir_excepcional
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'operador_produccion'
  AND p.codigo IN (
      'documentos.cotizaciones.descargar',
      'documentos.boletos.descargar',
      'postproduccion.proyectos.ver',
      'postproduccion.archivos.subir',
      'postproduccion.archivos.descargar',
      'postproduccion.entregas.gestionar'
  );

-- Garantía defensiva idempotente: revocar explícitamente de operador_produccion
-- cualquiera de los 7 permisos administrativos/financieros si existiera
DELETE rp
FROM `rol_permisos` rp
INNER JOIN `roles` r ON r.id = rp.rol_id
INNER JOIN `permisos` p ON p.id = rp.permiso_id
WHERE r.codigo = 'operador_produccion'
  AND p.codigo IN (
      'documentos.contratos.descargar',
      'documentos.recibos.emitir',
      'documentos.recibos.descargar',
      'postproduccion.proyectos.crear',
      'postproduccion.proyectos.editar',
      'ediciones.cerrar',
      'ediciones.reabrir_excepcional'
  );

-- ------------------------------------------------------------------------------
-- 3. H-07: SANEAMIENTO DE DATOS HISTÓRICOS EN categorias_items
--    Alinea códigos con guiones medios (p. ej. CAT-TEST-01) al contrato
--    soberano /^[A-Z0-9_]{2,50}$/ (CAT_TEST_01) sin debilitar el validador.
-- ------------------------------------------------------------------------------
UPDATE `categorias_items`
SET `codigo` = UPPER(REPLACE(`codigo`, '-', '_'))
WHERE `codigo` LIKE '%-%';

-- ------------------------------------------------------------------------------
-- 4. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_18_000030_regularizar_rbac_y_datos_preprod_v12.sql', 30)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

-- ==============================================================================
-- CANDELARIAAPP V1.1 — MIGRACIÓN 29: SELECCIÓN FOTOGRÁFICA EN PORTAL DEL CLIENTE
-- Directiva: DT-V11-014 (Fase 3.4)
-- Propósito: Tabla persistente para selección de fotografías por clientes en el portal,
--            gestión de favoritos y permiso RBAC 115 para postproducción.
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla de selecciones fotográficas del cliente
CREATE TABLE IF NOT EXISTS `postproduccion_selecciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proyecto_id` BIGINT UNSIGNED NOT NULL,
    `galeria_id` BIGINT UNSIGNED NOT NULL,
    `archivo_id` BIGINT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('SELECCIONADA', 'DESCARTADA') NOT NULL DEFAULT 'SELECCIONADA',
    `notas_cliente` VARCHAR(255) DEFAULT NULL,
    `seleccionado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`proyecto_id`) REFERENCES `postproduccion_proyectos` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`galeria_id`) REFERENCES `postproduccion_galerias` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`archivo_id`) REFERENCES `postproduccion_archivos` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,

    UNIQUE KEY `uk_post_sel_cliente_archivo` (`cliente_id`, `archivo_id`),
    KEY `idx_post_sel_galeria` (`galeria_id`),
    KEY `idx_post_sel_proyecto` (`proyecto_id`),
    KEY `idx_post_sel_org_estado` (`organizacion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Selección persistente de fotos por el cliente en el portal';

-- 2. Permiso RBAC 115: postproduccion.selecciones.ver
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`)
SELECT m.id, 'postproduccion.selecciones.ver', 'Ver Selecciones de Clientes', 'Permite consultar las fotografías seleccionadas por los clientes en el portal de autoservicio'
FROM `modulos` m WHERE m.codigo = 'postproduccion' LIMIT 1
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 3. Asignar permiso a roles administrativos
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permisos` p ON p.codigo = 'postproduccion.selecciones.ver'
WHERE r.codigo IN ('superadmin_plataforma', 'admin_organizacion', 'operador_produccion');

-- 4. Registro formal en migraciones_control
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES (
    '2026_10_13_000029_crear_modulo_seleccion_fotografica_portal.sql',
    1
) ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

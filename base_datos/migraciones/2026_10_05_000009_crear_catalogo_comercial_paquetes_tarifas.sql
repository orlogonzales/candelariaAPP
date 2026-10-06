-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN OFICIAL
-- ARCHIVO: 2026_10_05_000009_crear_catalogo_comercial_paquetes_tarifas.sql
-- FASE: 2.3B - Dominio y Persistencia del Catálogo Comercial y Paquetes
-- FECHA: 2026-10-05
-- ==============================================================================
-- DESCRIPCIÓN:
-- 1. Categorías taxonómicas maestras por organización (`categorias_items`).
-- 2. Maestro de bienes y prestaciones comerciales (`items_comerciales`).
-- 3. Paquetes comerciales estructurados (`paquetes`).
-- 4. Composición de ítems incluidos en paquetes (`paquete_items`).
-- 5. Ofertas de ítems contextualizadas por edición folclórica (`ofertas_items_edicion`).
-- 6. Ofertas de paquetes contextualizadas por edición folclórica (`ofertas_paquetes_edicion`).
-- 7. Tarifas vigentes para ofertas de ítems (`tarifas_items_edicion`).
-- 8. Tarifas vigentes propias para ofertas de paquetes (`tarifas_paquetes_edicion`).
-- 9. Historial append-only de modificaciones de tarifas de ítems (`historial_tarifas_items`).
-- 10. Historial append-only de modificaciones de tarifas de paquetes (`historial_tarifas_paquetes`).
-- 11. Permisos RBAC de catálogo bajo módulo 4 (`catalogo`) y asignación a roles.
-- 12. Registro en `migraciones_control`.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `categorias_items` (TAXONOMÍA COMERCIAL MAESTRA POR TENANT)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categorias_items`;
CREATE TABLE `categorias_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código taxonómico único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_categorias_items_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_categorias_items_org_estado` (`organizacion_id`, `estado`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías taxonómicas para ítems comerciales del tenant';

-- ------------------------------------------------------------------------------
-- 2. TABLA `items_comerciales` (MAESTRO DE BIENES Y PRESTACIONES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `items_comerciales`;
CREATE TABLE `items_comerciales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `categoria_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Código único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(150) NOT NULL,
    `tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL,
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'UNIDAD, PERSONA, NOCHE, HABITACION, TICKET, SERVICIO, TRAMO, DIA, HORA',
    `descripcion` TEXT DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias_items` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_items_comerciales_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_items_comerciales_org_categoria` (`organizacion_id`, `categoria_id`),
    KEY `idx_items_comerciales_org_tipo_estado` (`organizacion_id`, `tipo`, `estado`),
    CONSTRAINT `chk_items_comerciales_unidad` CHECK (`unidad_medida` IN ('UNIDAD', 'PERSONA', 'NOCHE', 'HABITACION', 'TICKET', 'SERVICIO', 'TRAMO', 'DIA', 'HORA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maestro de bienes y prestaciones comerciales por organización';

-- ------------------------------------------------------------------------------
-- 3. TABLA `paquetes` (MAESTRO DE PAQUETES ESTRUCTURADOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `paquetes`;
CREATE TABLE `paquetes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Código único por tenant en MAYÚSCULAS',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_paquetes_org_codigo` (`organizacion_id`, `codigo`),
    KEY `idx_paquetes_org_estado` (`organizacion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paquetes estructurados que agrupan ítems comerciales';

-- ------------------------------------------------------------------------------
-- 4. TABLA `paquete_items` (COMPOSICIÓN DE ÍTEMS INCLUIDOS EN PAQUETES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `paquete_items`;
CREATE TABLE `paquete_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `paquete_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_paquete_items_composicion` (`paquete_id`, `item_comercial_id`),
    KEY `idx_paquete_items_item` (`item_comercial_id`),
    CONSTRAINT `chk_paquete_items_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Composición de ítems incluidos dentro de un paquete';

-- ------------------------------------------------------------------------------
-- 5. TABLA `ofertas_items_edicion` (OFERTA DE ÍTEM EN EDICIÓN FOLCLÓRICA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ofertas_items_edicion`;
CREATE TABLE `ofertas_items_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `capacidad_referencial` INT UNSIGNED DEFAULT NULL COMMENT 'Dato informativo comercial, no inventario transaccional',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`),
    KEY `idx_ofertas_items_edicion_estado` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación comercial de un ítem para una edición folclórica';

-- ------------------------------------------------------------------------------
-- 6. TABLA `ofertas_paquetes_edicion` (OFERTA DE PAQUETE EN EDICIÓN FOLCLÓRICA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ofertas_paquetes_edicion`;
CREATE TABLE `ofertas_paquetes_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `paquete_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `capacidad_referencial` INT UNSIGNED DEFAULT NULL COMMENT 'Dato informativo comercial, no inventario transaccional',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`),
    KEY `idx_ofertas_paquetes_edicion_estado` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación comercial de un paquete para una edición folclórica';

-- ------------------------------------------------------------------------------
-- 7. TABLA `tarifas_items_edicion` (TARIFA VIGENTE PARA OFERTA DE ÍTEM)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tarifas_items_edicion`;
CREATE TABLE `tarifas_items_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `oferta_item_id` INT UNSIGNED NOT NULL,
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_tarifas_items_oferta` (`oferta_item_id`),
    CONSTRAINT `chk_tarifas_items_precio` CHECK (`precio` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifa comercial vigente para una oferta de ítem en una edición';

-- ------------------------------------------------------------------------------
-- 8. TABLA `tarifas_paquetes_edicion` (TARIFA VIGENTE PROPIA PARA OFERTA DE PAQUETE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `tarifas_paquetes_edicion`;
CREATE TABLE `tarifas_paquetes_edicion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `oferta_paquete_id` INT UNSIGNED NOT NULL,
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_tarifas_paquetes_oferta` (`oferta_paquete_id`),
    CONSTRAINT `chk_tarifas_paquetes_precio` CHECK (`precio` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifa comercial vigente propia para una oferta de paquete en una edición';

-- ------------------------------------------------------------------------------
-- 9. TABLA `historial_tarifas_items` (BITÁCORA APPEND-ONLY DE TARIFAS DE ÍTEMS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `historial_tarifas_items`;
CREATE TABLE `historial_tarifas_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarifa_item_id` INT UNSIGNED NOT NULL,
    `precio_anterior` DECIMAL(12,2) NOT NULL,
    `precio_nuevo` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL,
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tarifa_item_id`) REFERENCES `tarifas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    KEY `idx_historial_tarifas_items_tarifa` (`tarifa_item_id`, `creado_en`),
    KEY `idx_historial_tarifas_items_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_historial_tarifas_items_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_historial_tarifas_items_precio_ant` CHECK (`precio_anterior` >= 0),
    CONSTRAINT `chk_historial_tarifas_items_precio_nue` CHECK (`precio_nuevo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial append-only de cambios de tarifa en ofertas de ítems';

-- ------------------------------------------------------------------------------
-- 10. TABLA `historial_tarifas_paquetes` (BITÁCORA APPEND-ONLY DE TARIFAS DE PAQUETES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `historial_tarifas_paquetes`;
CREATE TABLE `historial_tarifas_paquetes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarifa_paquete_id` INT UNSIGNED NOT NULL,
    `precio_anterior` DECIMAL(12,2) NOT NULL,
    `precio_nuevo` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL,
    `motivo` VARCHAR(255) DEFAULT NULL,
    `actor_tipo` ENUM('HUMANO', 'SISTEMA') NOT NULL DEFAULT 'HUMANO',
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `actor_sistema_id` SMALLINT UNSIGNED DEFAULT NULL,
    `canal_id` TINYINT UNSIGNED DEFAULT NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tarifa_paquete_id`) REFERENCES `tarifas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`actor_sistema_id`) REFERENCES `actores_sistema` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`canal_id`) REFERENCES `canales` (`id`) ON DELETE RESTRICT,
    KEY `idx_historial_tarifas_paquetes_tarifa` (`tarifa_paquete_id`, `creado_en`),
    KEY `idx_historial_tarifas_paquetes_correlacion` (`correlacion_id`),
    CONSTRAINT `chk_historial_tarifas_paquetes_actor` CHECK (
        (`actor_tipo` = 'HUMANO' AND `usuario_id` IS NOT NULL AND `actor_sistema_id` IS NULL)
        OR
        (`actor_tipo` = 'SISTEMA' AND `usuario_id` IS NULL AND `actor_sistema_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_historial_tarifas_paquetes_precio_ant` CHECK (`precio_anterior` >= 0),
    CONSTRAINT `chk_historial_tarifas_paquetes_precio_nue` CHECK (`precio_nuevo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial append-only de cambios de tarifa en ofertas de paquetes';

-- ------------------------------------------------------------------------------
-- 11. PERMISOS RBAC DEL MÓDULO 4 (CATÁLOGO COMERCIAL)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(34, 4, 'catalogo.ver', 'Ver Catálogo Comercial', 'Permite consultar categorías, ítems comerciales, paquetes, ofertas y tarifas'),
(35, 4, 'catalogo.categorias.gestionar', 'Gestionar Categorías de Catálogo', 'Permite crear, editar y activar/desactivar categorías de ítems comerciales'),
(36, 4, 'catalogo.items.gestionar', 'Gestionar Ítems Comerciales', 'Permite crear, editar y activar/desactivar productos y servicios en el catálogo'),
(37, 4, 'catalogo.paquetes.gestionar', 'Gestionar Paquetes Comerciales', 'Permite crear, editar paquetes y definir su composición de ítems incluidos'),
(38, 4, 'catalogo.ofertas.gestionar', 'Gestionar Ofertas por Edición', 'Permite habilitar, deshabilitar y fijar capacidad referencial de ítems y paquetes por edición'),
(39, 4, 'catalogo.tarifas.gestionar', 'Gestionar Tarifas Comerciales', 'Permite asignar tarifas vigentes y registrar cambios de precio con trazabilidad')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador de Plataforma (acceso total a catálogo)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 34), (1, 35), (1, 36), (1, 37), (1, 38), (1, 39);

-- Administrador de Organización (gestión completa de catálogo en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 34), (2, 35), (2, 36), (2, 37), (2, 38), (2, 39);

-- Operador de Producción (solo consulta de catálogo)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 34);

-- ------------------------------------------------------------------------------
-- 12. REGISTRO EN CONTROL DE MIGRACIONES
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_05_000009_crear_catalogo_comercial_paquetes_tarifas.sql', 7)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

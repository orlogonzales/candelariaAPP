-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN OFICIAL
-- ARCHIVO: 2026_10_06_000013_crear_modulo_ventas_dominio_y_rbac.sql
-- FASE: 2.5B - Dominio y Persistencia de Ventas
-- FECHA: 2026-10-06
-- ==============================================================================
-- DESCRIPCIÓN:
-- 1. Tabla de secuencias correlativas concurrent-safe (`ventas_secuencias`).
-- 2. Tabla maestra de cabecera de ventas (`ventas`).
-- 3. Tabla de detalle y líneas comerciales vendidas (`venta_lineas`).
-- 4. Tabla de snapshot relacional inmutable de componentes de paquetes (`venta_linea_componentes`).
-- 5. Módulo 21 (ventas) y 4 permisos RBAC atómicos (`ventas.ver`, `ventas.crear_desde_cotizacion`, `ventas.cancelar`, `ventas.anular`).
-- 6. Registro en `migraciones_control` (Lote 11).
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `ventas_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ventas_secuencias`;
CREATE TABLE `ventas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de venta particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 2. TABLA `ventas` (CABECERA DE VENTAS COMERCIALES CONFIRMADAS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `cotizacion_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK a cotización aprobada (nullable para habilitar futura venta directa)',
    `origen_tipo` ENUM('COTIZACION', 'DIRECTA') NOT NULL DEFAULT 'COTIZACION',
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador formal único emitido (ej. VTA-2026-000001)',
    `fecha_venta` DATE NOT NULL COMMENT 'Fecha de formalización de la venta',
    `estado` ENUM('CONFIRMADA', 'LIQUIDADA', 'CANCELADA', 'ANULADA') NOT NULL DEFAULT 'CONFIRMADA',

    -- Snapshot Inmutable del Cliente
    `cliente_nombre_completo` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre completo al formalizar',
    `cliente_tipo_documento` VARCHAR(20) DEFAULT NULL COMMENT 'Snapshot del tipo de documento',
    `cliente_numero_documento` VARCHAR(30) DEFAULT NULL COMMENT 'Snapshot del número de documento',
    `cliente_telefono` VARCHAR(30) DEFAULT NULL COMMENT 'Snapshot del teléfono principal',
    `cliente_email` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot del correo electrónico',

    -- Snapshot Económico y Condiciones
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de subtotales netos de líneas vendidas',
    `descuento_global_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_global_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_global_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_global_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio cuando descuento_global_monto > 0',
    `descuento_lineas_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de descuentos de cada línea',
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal - descuento_global_monto',
    `terminos_condiciones` TEXT DEFAULT NULL COMMENT 'Snapshot inmutable de términos y condiciones acordados',
    `notas_comerciales` TEXT DEFAULT NULL COMMENT 'Notas comerciales de la venta',

    -- Motivos de Cancelación / Anulación
    `motivo_cancelacion` ENUM('DESISTIMIENTO_CLIENTE', 'FUERZA_MAYOR_CLIMA', 'PROBLEMAS_SALUD', 'INCUMPLIMIENTO_ORGANIZACION', 'OTRO') DEFAULT NULL,
    `motivo_cancelacion_detalle` VARCHAR(255) DEFAULT NULL,
    `motivo_anulacion` ENUM('ERROR_REGISTRO', 'DUPLICIDAD_VENTA', 'FRAUDE_SUPLANTACION', 'OTRO') DEFAULT NULL,
    `motivo_anulacion_detalle` VARCHAR(255) DEFAULT NULL,

    -- Control de Concurrencia y Auditoría
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL COMMENT 'Usuario emisor responsable',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_ventas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_ventas_cotizacion` (`organizacion_id`, `cotizacion_id`),
    KEY `idx_ventas_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_ventas_edicion` (`organizacion_id`, `edicion_id`, `estado`),
    KEY `idx_ventas_fecha` (`organizacion_id`, `fecha_venta`),

    CONSTRAINT `chk_ventas_origen` CHECK (
        (`origen_tipo` = 'COTIZACION' AND `cotizacion_id` IS NOT NULL)
        OR
        (`origen_tipo` = 'DIRECTA' AND `cotizacion_id` IS NULL)
    ),
    CONSTRAINT `chk_ventas_totales` CHECK (`subtotal` >= 0 AND `total` >= 0 AND `total` <= `subtotal`),
    CONSTRAINT `chk_ventas_descuento_global` CHECK (
        (`descuento_global_tipo` = 'NINGUNO' AND `descuento_global_valor` = 0 AND `descuento_global_monto` = 0 AND `descuento_global_motivo` IS NULL)
        OR
        (`descuento_global_tipo` <> 'NINGUNO' AND `descuento_global_monto` >= 0 AND `descuento_global_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_global_motivo`)) > 0)
    ),
    CONSTRAINT `chk_ventas_cancelacion` CHECK (
        (`estado` <> 'CANCELADA' AND `motivo_cancelacion` IS NULL AND `motivo_cancelacion_detalle` IS NULL)
        OR
        (`estado` = 'CANCELADA' AND `motivo_cancelacion` IS NOT NULL AND (`motivo_cancelacion` <> 'OTRO' OR (`motivo_cancelacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_cancelacion_detalle`)) > 0)))
    ),
    CONSTRAINT `chk_ventas_anulacion` CHECK (
        (`estado` <> 'ANULADA' AND `motivo_anulacion` IS NULL AND `motivo_anulacion_detalle` IS NULL)
        OR
        (`estado` = 'ANULADA' AND `motivo_anulacion` IS NOT NULL AND (`motivo_anulacion` <> 'OTRO' OR (`motivo_anulacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_anulacion_detalle`)) > 0)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de ventas comerciales confirmadas';

-- ------------------------------------------------------------------------------
-- 3. TABLA `venta_lineas` (DETALLE COMERCIAL Y PROCEDENCIA ESTRICTA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `venta_lineas`;
CREATE TABLE `venta_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `cotizacion_linea_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de la línea cotizada',
    `tipo_linea` ENUM('ITEM', 'PAQUETE') NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL,
    `paquete_id` INT UNSIGNED DEFAULT NULL,
    `oferta_item_id` INT UNSIGNED DEFAULT NULL,
    `oferta_paquete_id` INT UNSIGNED DEFAULT NULL,
    `concepto_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot del código comercial',
    `concepto_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre de concepto',
    `concepto_descripcion` TEXT DEFAULT NULL COMMENT 'Snapshot de descripción comercial',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot de unidad canónica',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(12,2) NOT NULL COMMENT 'Precio unitario congelado',
    `descuento_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio si descuento_monto > 0',
    `subtotal` DECIMAL(12,2) NOT NULL COMMENT '(cantidad * precio_unitario) - descuento_monto',
    `moneda` CHAR(3) NOT NULL,
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `notas` VARCHAR(255) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_linea_id`) REFERENCES `cotizacion_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    KEY `idx_venta_lineas_venta` (`venta_id`, `orden`),
    CONSTRAINT `chk_venta_lineas_tipo` CHECK (
        (`tipo_linea` = 'ITEM' AND `item_comercial_id` IS NOT NULL AND `oferta_item_id` IS NOT NULL AND `paquete_id` IS NULL AND `oferta_paquete_id` IS NULL)
        OR
        (`tipo_linea` = 'PAQUETE' AND `paquete_id` IS NOT NULL AND `oferta_paquete_id` IS NOT NULL AND `item_comercial_id` IS NULL AND `oferta_item_id` IS NULL)
    ),
    CONSTRAINT `chk_venta_lineas_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_venta_lineas_precio` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_venta_lineas_descuento` CHECK (
        (`descuento_tipo` = 'NINGUNO' AND `descuento_valor` = 0 AND `descuento_monto` = 0 AND `descuento_motivo` IS NULL)
        OR
        (`descuento_tipo` <> 'NINGUNO' AND `descuento_monto` >= 0 AND `descuento_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_motivo`)) > 0)
    ),
    CONSTRAINT `chk_venta_lineas_subtotal` CHECK (`subtotal` >= 0 AND `subtotal` <= (`cantidad` * `precio_unitario`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas económicas y detalle comercial de la venta';

-- ------------------------------------------------------------------------------
-- 4. TABLA `venta_linea_componentes` (SNAPSHOT DE COMPONENTES DE PAQUETES VENDIDOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `venta_linea_componentes`;
CREATE TABLE `venta_linea_componentes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de origen (ítem comercial)',
    `item_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot inmutable del código del ítem',
    `item_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot inmutable del nombre del ítem',
    `item_tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL COMMENT 'Snapshot inmutable del tipo',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot inmutable de la unidad de medida',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Snapshot de cantidad incluida por paquete',
    `nota` VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot de especificación',
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    KEY `idx_venta_comp_linea` (`venta_linea_id`, `orden`),
    CONSTRAINT `chk_venta_comp_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot relacional inmutable de los componentes de paquetes vendidos';

-- ------------------------------------------------------------------------------
-- 5. MÓDULO 21 Y EXACTAMENTE LOS 4 PERMISOS RBAC SOBERANOS DEL MÓDULO VENTAS
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`) VALUES
(21, 'ventas', 'VENTAS', 'Ventas comerciales confirmadas, conversión de cotizaciones y liquidación', 'fa-solid fa-cash-register', 6, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(50, 21, 'ventas.ver', 'Ver Ventas', 'Permite consultar listados, fichas y trazabilidad de ventas comerciales'),
(51, 21, 'ventas.crear_desde_cotizacion', 'Crear Venta desde Cotización', 'Permite convertir formalmente una cotización aceptada en venta'),
(52, 21, 'ventas.cancelar', 'Cancelar Venta', 'Permite registrar la cancelación comercial de una venta con motivo obligatorio'),
(53, 21, 'ventas.anular', 'Anular Venta', 'Permite anular administrativamente una venta con motivo justificado')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador de Plataforma (acceso total a ventas)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 50), (1, 51), (1, 52), (1, 53);

-- Administrador de Organización (gestión completa en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 50), (2, 51), (2, 52), (2, 53);

-- Operador de Producción (solo consulta de ventas)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 50);

-- ------------------------------------------------------------------------------
-- 6. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 11)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_06_000013_crear_modulo_ventas_dominio_y_rbac.sql', 11)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

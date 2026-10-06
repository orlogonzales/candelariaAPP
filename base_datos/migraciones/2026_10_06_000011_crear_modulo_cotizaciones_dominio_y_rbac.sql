-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN OFICIAL
-- ARCHIVO: 2026_10_06_000011_crear_modulo_cotizaciones_dominio_y_rbac.sql
-- FASE: 2.4B - Dominio y Persistencia de Cotizaciones
-- FECHA: 2026-10-06
-- ==============================================================================
-- DESCRIPCIÓN:
-- 1. Tabla de secuencias correlativas concurrent-safe (`cotizaciones_secuencias`).
-- 2. Tabla maestra de cabecera de cotizaciones (`cotizaciones`).
-- 3. Tabla de detalle y líneas comerciales con procedencia estricta (`cotizacion_lineas`).
-- 4. Tabla de snapshot relacional inmutable de componentes de paquetes (`cotizacion_linea_componentes`).
-- 5. Módulo 20 (cotizaciones) y 9 permisos RBAC atómicos (`cotizaciones.*`).
-- 6. Registro en `migraciones_control` (Lote 9).
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `cotizaciones_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizaciones_secuencias`;
CREATE TABLE `cotizaciones_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de cotización particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 2. TABLA `cotizaciones` (CABECERA DE PROPUESTAS ECONÓMICAS Y REVISIONES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizaciones`;
CREATE TABLE `cotizaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `oportunidad_id` BIGINT UNSIGNED DEFAULT NULL,
    `correlativo` VARCHAR(35) DEFAULT NULL COMMENT 'Identificador formal emitido (ej. COT-2026-000001 o COT-2026-000001-R2), NULL en BORRADOR',
    `correlativo_base` VARCHAR(30) DEFAULT NULL COMMENT 'Secuencia raíz compartida por la cadena de revisiones, NULL en BORRADOR sin emisión previa',
    `version_numero` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Número de revisión: 1 para original, 2, 3... para revisiones',
    `cotizacion_origen_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK autorreferencial a la cotización inmediata anterior de la que deriva',
    `cotizacion_raiz_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK autorreferencial a la cotización original R1 de la cadena',
    `titulo` VARCHAR(150) NOT NULL COMMENT 'Concepto o resumen de la propuesta comercial',
    `estado` ENUM('BORRADOR', 'EMITIDA', 'ACEPTADA', 'RECHAZADA', 'VENCIDA', 'ANULADA') NOT NULL DEFAULT 'BORRADOR',
    `fecha_emision` DATE DEFAULT NULL COMMENT 'Fecha de emisión formal, NULL mientras sea BORRADOR',
    `valido_hasta` DATE DEFAULT NULL COMMENT 'Fecha límite de validez de la oferta, NULL mientras sea BORRADOR',
    `moneda` CHAR(3) NOT NULL COMMENT 'Snapshot inmutable de plataforma.moneda_principal',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de subtotales netos de líneas',
    `descuento_global_tipo` ENUM('NINGUNO', 'PORCENTAJE', 'MONTO_FIJO') NOT NULL DEFAULT 'NINGUNO',
    `descuento_global_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_global_monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_global_motivo` VARCHAR(255) DEFAULT NULL COMMENT 'Motivo obligatorio cuando descuento_global_monto > 0',
    `descuento_lineas_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de descuentos de cada línea',
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal - descuento_global_monto',
    `terminos_condiciones` TEXT DEFAULT NULL,
    `notas_internas` TEXT DEFAULT NULL,
    `motivo_rechazo` ENUM('PRECIO_ELEVADO', 'COMPETENCIA', 'CAMBIO_FECHA', 'CAMBIO_REQUERIMIENTO', 'CLIENTE_DESISTIO', 'OTRO') DEFAULT NULL,
    `motivo_rechazo_detalle` VARCHAR(255) DEFAULT NULL,
    `motivo_anulacion` ENUM('SUPERADA_POR_REVISION', 'ERROR_DATOS', 'CAMBIO_CONDICIONES_ORGANIZACION', 'EXPIRACION_DEFINITIVA', 'OTRO') DEFAULT NULL,
    `motivo_anulacion_detalle` VARCHAR(255) DEFAULT NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL COMMENT 'Usuario emisor responsable',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oportunidad_id`) REFERENCES `crm_oportunidades` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cotizacion_origen_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cotizacion_raiz_id`) REFERENCES `cotizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_cotizaciones_correlativo` (`organizacion_id`, `correlativo`),
    KEY `idx_cotizaciones_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_cotizaciones_edicion` (`organizacion_id`, `edicion_id`, `estado`),
    KEY `idx_cotizaciones_oportunidad` (`oportunidad_id`),
    KEY `idx_cotizaciones_raiz` (`cotizacion_raiz_id`),
    CONSTRAINT `chk_cotizaciones_totales` CHECK (`subtotal` >= 0 AND `total` >= 0 AND `total` <= `subtotal`),
    CONSTRAINT `chk_cotizaciones_validez` CHECK (`valido_hasta` IS NULL OR `fecha_emision` IS NULL OR `valido_hasta` >= `fecha_emision`),
    CONSTRAINT `chk_cotizaciones_borrador` CHECK (
        (`estado` = 'BORRADOR' AND `correlativo` IS NULL AND `fecha_emision` IS NULL AND `valido_hasta` IS NULL)
        OR
        (`estado` <> 'BORRADOR' AND `correlativo` IS NOT NULL AND `fecha_emision` IS NOT NULL AND `valido_hasta` IS NOT NULL)
    ),
    CONSTRAINT `chk_cotizaciones_descuento_global` CHECK (
        (`descuento_global_tipo` = 'NINGUNO' AND `descuento_global_valor` = 0 AND `descuento_global_monto` = 0 AND `descuento_global_motivo` IS NULL)
        OR
        (`descuento_global_tipo` <> 'NINGUNO' AND `descuento_global_monto` >= 0 AND `descuento_global_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_global_motivo`)) > 0)
    ),
    CONSTRAINT `chk_cotizaciones_rechazo` CHECK (
        (`estado` <> 'RECHAZADA' AND `motivo_rechazo` IS NULL AND `motivo_rechazo_detalle` IS NULL)
        OR
        (`estado` = 'RECHAZADA' AND `motivo_rechazo` IS NOT NULL AND (`motivo_rechazo` <> 'OTRO' OR (`motivo_rechazo_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_rechazo_detalle`)) > 0)))
    ),
    CONSTRAINT `chk_cotizaciones_anulacion` CHECK (
        (`estado` <> 'ANULADA' AND `motivo_anulacion` IS NULL AND `motivo_anulacion_detalle` IS NULL)
        OR
        (`estado` = 'ANULADA' AND `motivo_anulacion` IS NOT NULL AND (`motivo_anulacion` <> 'OTRO' OR (`motivo_anulacion_detalle` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_anulacion_detalle`)) > 0)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de propuestas económicas y cotizaciones formalizadas';

-- ------------------------------------------------------------------------------
-- 3. TABLA `cotizacion_lineas` (DETALLE COMERCIAL Y PROCEDENCIA ESTRICTA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizacion_lineas`;
CREATE TABLE `cotizacion_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cotizacion_id` BIGINT UNSIGNED NOT NULL,
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
    FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_item_id`) REFERENCES `ofertas_items_edicion` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`oferta_paquete_id`) REFERENCES `ofertas_paquetes_edicion` (`id`) ON DELETE RESTRICT,
    KEY `idx_cotizacion_lineas_cotizacion` (`cotizacion_id`, `orden`),
    CONSTRAINT `chk_cotizacion_lineas_tipo` CHECK (
        (`tipo_linea` = 'ITEM' AND `item_comercial_id` IS NOT NULL AND `oferta_item_id` IS NOT NULL AND `paquete_id` IS NULL AND `oferta_paquete_id` IS NULL)
        OR
        (`tipo_linea` = 'PAQUETE' AND `paquete_id` IS NOT NULL AND `oferta_paquete_id` IS NOT NULL AND `item_comercial_id` IS NULL AND `oferta_item_id` IS NULL)
    ),
    CONSTRAINT `chk_cotizacion_lineas_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_cotizacion_lineas_precio` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_cotizacion_lineas_descuento` CHECK (
        (`descuento_tipo` = 'NINGUNO' AND `descuento_valor` = 0 AND `descuento_monto` = 0 AND `descuento_motivo` IS NULL)
        OR
        (`descuento_tipo` <> 'NINGUNO' AND `descuento_monto` >= 0 AND `descuento_motivo` IS NOT NULL AND CHAR_LENGTH(TRIM(`descuento_motivo`)) > 0)
    ),
    CONSTRAINT `chk_cotizacion_lineas_subtotal` CHECK (`subtotal` >= 0 AND `subtotal` <= (`cantidad` * `precio_unitario`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas económicas y detalle comercial de la cotización';

-- ------------------------------------------------------------------------------
-- 4. TABLA `cotizacion_linea_componentes` (SNAPSHOT DE COMPOSICIÓN DE PAQUETES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `cotizacion_linea_componentes`;
CREATE TABLE `cotizacion_linea_componentes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cotizacion_linea_id` BIGINT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED DEFAULT NULL COMMENT 'Procedencia de origen (nullable para independencia histórica)',
    `item_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot inmutable del código del ítem',
    `item_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot inmutable del nombre del ítem',
    `item_tipo` ENUM('PRODUCTO', 'SERVICIO') NOT NULL COMMENT 'Snapshot inmutable del tipo',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot inmutable de la unidad de medida',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Snapshot de cantidad incluida por paquete',
    `nota` VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot de especificación',
    `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cotizacion_linea_id`) REFERENCES `cotizacion_lineas` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE SET NULL,
    KEY `idx_cotizacion_comp_linea` (`cotizacion_linea_id`, `orden`),
    CONSTRAINT `chk_cotizacion_componentes_cant` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot relacional inmutable de los componentes de paquetes cotizados';

-- ------------------------------------------------------------------------------
-- 5. MÓDULO 20 Y PERMISOS RBAC DEL MÓDULO COTIZACIONES (9 PERMISOS CANÓNICOS)
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`) VALUES
(20, 'cotizaciones', 'COTIZACIONES', 'Propuestas económicas, presupuestos y revisiones comerciales', 'fa-solid fa-file-invoice-dollar', 5, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

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
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador de Plataforma (acceso total a cotizaciones)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 41), (1, 42), (1, 43), (1, 44), (1, 45), (1, 46), (1, 47), (1, 48), (1, 49);

-- Administrador de Organización (gestión completa en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 41), (2, 42), (2, 43), (2, 44), (2, 45), (2, 46), (2, 47), (2, 48), (2, 49);

-- Operador de Producción (solo consulta de cotizaciones)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 41);

-- ------------------------------------------------------------------------------
-- 6. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 9)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_06_000011_crear_modulo_cotizaciones_dominio_y_rbac.sql', 9)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

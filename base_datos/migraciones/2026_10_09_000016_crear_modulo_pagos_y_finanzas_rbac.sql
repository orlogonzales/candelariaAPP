-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN OFICIAL
-- ARCHIVO: 2026_10_09_000016_crear_modulo_pagos_y_finanzas_rbac.sql
-- FASE: 2.7C - Dominio Financiero, Pagos, Pasarelas y Liquidación de Ventas
-- FECHA: 2026-10-09
-- ==============================================================================
-- DESCRIPCIÓN:
-- 1. Incorporación no destructiva de claves compuestas multitenant en `ediciones_candelaria` y `ventas`.
-- 2. Incorporación de columnas de proyección financiera en `ventas` con backfill determinista.
-- 3. Catálogo de pasarelas institucionales de plataforma (`pasarelas_pago`).
-- 4. Configuración de pasarelas por tenant (`organizacion_pasarelas`) con regla D-01.
-- 5. Habilitación de pasarelas por edición anual (`edicion_pasarelas`).
-- 6. Cuentas bancarias y billeteras de la organización (`cuentas_bancarias_organizacion`).
-- 7. Secuencias correlativas concurrent-safe (`pagos_secuencias`, `reembolsos_secuencias`).
-- 8. Libro mayor financiero inmutable append-only (`pagos`).
-- 9. Trazabilidad técnica de intentos y webhooks (`pago_intentos_pasarela`).
-- 10. Asientos compensatorios de reembolso (`pago_reembolsos`).
-- 11. Permisos RBAC para Módulo 11 (`pagos_caja`) y asignación a roles.
-- 12. Registro en `migraciones_control` (Lote 14).
-- ==============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------------------
-- 1. REFUERZO DE CLAVES COMPUESTAS MULTITENANT EN TABLAS EXISTENTES
-- ------------------------------------------------------------------------------
-- Asegura que las relaciones foráneas financieras exijan coincidencia estricta de tenant y edición
ALTER TABLE `ediciones_candelaria`
    ADD UNIQUE KEY `uk_ediciones_id_org` (`id`, `organizacion_id`);

ALTER TABLE `ventas`
    ADD UNIQUE KEY `uk_ventas_id_org_edic` (`id`, `organizacion_id`, `edicion_id`),
    ADD UNIQUE KEY `uk_ventas_id_org` (`id`, `organizacion_id`);

-- ------------------------------------------------------------------------------
-- 2. COLUMNAS PROYECTIVAS FINANCIERAS EN `ventas` Y BACKFILL DETERMINISTA
-- ------------------------------------------------------------------------------
ALTER TABLE `ventas`
    ADD COLUMN `monto_pagado` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total`,
    ADD COLUMN `saldo_pendiente` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `monto_pagado`,
    ADD COLUMN `estado_financiero` ENUM('NO_PAGADA', 'PAGO_PARCIAL', 'PAGADA_TOTAL', 'SOBREPAGADA', 'NO_APLICA') NOT NULL DEFAULT 'NO_PAGADA' AFTER `saldo_pendiente`;

-- Backfill determinista: ventas activas inician con saldo_pendiente = total; canceladas/anuladas con 0.00
UPDATE `ventas`
SET
    `monto_pagado` = 0.00,
    `saldo_pendiente` = CASE
        WHEN `estado` IN ('CANCELADA', 'ANULADA') THEN 0.00
        ELSE `total`
    END,
    `estado_financiero` = CASE
        WHEN `estado` IN ('CANCELADA', 'ANULADA') THEN 'NO_APLICA'
        ELSE 'NO_PAGADA'
    END;

-- ------------------------------------------------------------------------------
-- 3. TABLA `pasarelas_pago` (CATÁLOGO DE PASARELAS INSTITUCIONALES)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pasarelas_pago` (
    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,       -- CULQI, NIUBIZ, STRIPE, MERCADOPAGO, YAPE_PLIN_MANUAL
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `tipo_integracion` ENUM('CHECKOUT_WEB', 'API_TOKEN', 'QR_ESTATICO', 'MANUAL') NOT NULL,
    `protocolo_webhook` ENUM('HMAC_SHA256', 'BEARER_TOKEN', 'SIGNATURE_HEADER', 'QUERY_POLLING', 'NINGUNO') NOT NULL DEFAULT 'HMAC_SHA256',
    `soporta_reembolsos` TINYINT(1) NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de pasarelas de pago soportadas en la plataforma';

-- ------------------------------------------------------------------------------
-- 4. TABLA `organizacion_pasarelas` (CONFIGURACIÓN TENANT Y REGLA D-01)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizacion_pasarelas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pasarela_id` SMALLINT UNSIGNED NOT NULL,
    `modo` ENUM('TEST', 'PRODUCCION') NOT NULL DEFAULT 'TEST',
    `identificador_comercio` VARCHAR(150) NULL COMMENT 'Merchant ID o Public Key',
    `credencial_secreta_enc` TEXT NULL COMMENT 'API Key privada cifrada en AES-256-GCM',
    `webhook_secreto_enc` TEXT NULL COMMENT 'Secreto para verificación de webhook en AES-256-GCM',
    `porcentaje_comision` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `comision_fija` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `asume_comision` ENUM('ORGANIZACION', 'CLIENTE') NOT NULL DEFAULT 'ORGANIZACION', -- Regla D-01
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_org_pasarelas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_org_pasarelas_pasarela` FOREIGN KEY (`pasarela_id`) REFERENCES `pasarelas_pago` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_org_pasarela` UNIQUE (`organizacion_id`, `pasarela_id`),
    CONSTRAINT `uk_org_pasarela_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuraciones de pasarela por organización';

-- ------------------------------------------------------------------------------
-- 5. TABLA `edicion_pasarelas` (ACTIVACIÓN SOBERANA POR EDICIÓN ANUAL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `edicion_pasarelas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `organizacion_pasarela_id` INT UNSIGNED NOT NULL,
    `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_edic_pasarelas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_edic_pasarelas_edicion` FOREIGN KEY (`edicion_id`, `organizacion_id`) REFERENCES `ediciones_candelaria` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_edic_pasarelas_org_pas` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`) REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_edicion_org_pasarela` UNIQUE (`edicion_id`, `organizacion_pasarela_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitación de pasarelas por edición anual';

-- ------------------------------------------------------------------------------
-- 6. TABLA `cuentas_bancarias_organizacion` (PADRÓN BANCARIO Y BILLETERAS TENANT)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_bancarias_organizacion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `banco_nombre` VARCHAR(80) NOT NULL COMMENT 'BCP, BBVA, INTERBANK, SCOTIABANK, BANCO_NACION, YAPE, PLIN',
    `tipo_cuenta` ENUM('CORRIENTE', 'AHORROS', 'BILLETERA_DIGITAL') NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',
    `titular_nombre` VARCHAR(150) NOT NULL,
    `numero_cuenta` VARCHAR(50) NOT NULL,
    `codigo_interbancario` VARCHAR(50) NULL COMMENT 'CCI para transferencias interbancarias',
    `alias_identificador` VARCHAR(60) NULL COMMENT 'Nombre corto identificador',
    `qr_imagen_url` VARCHAR(255) NULL COMMENT 'URL a imagen QR para cobro presencial o web',
    `instrucciones_pago` TEXT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cuentas_bancarias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_cuentas_id_org` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas bancarias y billeteras de cobro por organización';

-- ------------------------------------------------------------------------------
-- 7. TABLAS DE SECUENCIAS CORRELATIVAS (CONCURRENT-SAFE)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    CONSTRAINT `fk_pagos_secuencias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias de correlativos de pagos por tenant y año';

CREATE TABLE IF NOT EXISTS `reembolsos_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    CONSTRAINT `fk_reem_secuencias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias de correlativos de reembolsos por tenant y año';

-- ------------------------------------------------------------------------------
-- 8. TABLA `pagos` (LIBRO MAYOR FINANCIERO INMUTABLE APPEND-ONLY)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'PAG-AAAA-000001',
    `metodo_pago` ENUM('EFECTIVO', 'TRANSFERENCIA_BANCARIA', 'BILLETERA_DIGITAL', 'TARJETA_PASARELA', 'POS_FISICO') NOT NULL,
    `estado` ENUM('PENDIENTE_VERIFICACION', 'APROBADO', 'RECHAZADO', 'ANULADO') NOT NULL DEFAULT 'PENDIENTE_VERIFICACION',
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',

    -- Desglose Financiero Estricto de 4 Importes + Excedente
    `monto_cobrado_cliente` DECIMAL(12,2) NOT NULL COMMENT 'Monto total desembolsado por el cliente',
    `comision_porcentaje_aplicada` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `comision_fija_aplicada` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `comision_pasarela` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo financiero deducido por pasarela/canal',
    `monto_neto_recibido` DECIMAL(12,2) NOT NULL COMMENT 'Monto líquido acreditado a la organización',
    `monto_aplicado_venta` DECIMAL(12,2) NOT NULL COMMENT 'Monto que amortiza la deuda comercial de la venta',
    `monto_excedente` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sobrepago a favor del cliente',
    `comision_asumida_por` ENUM('ORGANIZACION', 'CLIENTE') NOT NULL DEFAULT 'ORGANIZACION',

    -- Control Append-Only de Reembolsos Ejecutados
    `monto_reembolsado_acumulado` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de reembolsos con estado EJECUTADO',

    `fecha_pago` DATETIME NOT NULL,
    `cuenta_bancaria_id` INT UNSIGNED NULL,
    `organizacion_pasarela_id` INT UNSIGNED NULL,
    `numero_operacion_bancaria` VARCHAR(60) NULL COMMENT 'Número de operación / boucher de transferencia',
    `boucher_comprobante_url` VARCHAR(255) NULL COMMENT 'Ruta relativa segura a la captura del boucher',
    `notas_operativas` TEXT NULL,
    `clave_idempotencia` VARCHAR(64) NULL COMMENT 'Hash SHA-256 para evitar doble cobro',
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `verificado_por` INT UNSIGNED NULL COMMENT 'Usuario que verificó el depósito bancario',
    `verificado_en` DATETIME NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Integridad Relacional y Multitenant con RESTRICT
    CONSTRAINT `fk_pagos_organizacion` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_venta_org_edic` FOREIGN KEY (`venta_id`, `organizacion_id`, `edicion_id`)
        REFERENCES `ventas` (`id`, `organizacion_id`, `edicion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`, `organizacion_id`)
        REFERENCES `cuentas_bancarias_organizacion` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_org_pasarela` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`)
        REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_verificado_por` FOREIGN KEY (`verificado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pagos_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    CONSTRAINT `uk_pagos_correlativo_org` UNIQUE (`organizacion_id`, `correlativo`),
    CONSTRAINT `uk_pagos_idempotencia` UNIQUE (`organizacion_id`, `clave_idempotencia`),
    CONSTRAINT `uk_pagos_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Libro mayor inmutable de cobros financieros';

-- ------------------------------------------------------------------------------
-- 9. TABLA `pago_intentos_pasarela` (TRAZABILIDAD DE WEBHOOKS Y SESIONES CHECKOUT)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pago_intentos_pasarela` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `organizacion_pasarela_id` INT UNSIGNED NOT NULL,
    `transaccion_externa_id` VARCHAR(150) NULL,
    `orden_checkout_id` VARCHAR(150) NULL,
    `monto` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',
    `estado_intento` ENUM('INICIADO', 'PROCESANDO', 'EXITOSO', 'FALLIDO', 'EXPIRADO') NOT NULL DEFAULT 'INICIADO',
    `codigo_respuesta_pasarela` VARCHAR(50) NULL,
    `mensaje_respuesta_pasarela` VARCHAR(255) NULL,

    -- Payloads Sanitizados (Cero PAN, CVV o Secretos PCI)
    `payload_solicitud_sanitizado_json` JSON NULL,
    `payload_respuesta_sanitizado_json` JSON NULL,
    `tarjeta_marca` VARCHAR(30) NULL,
    `tarjeta_ultimos_cuatro` CHAR(4) NULL,

    `ip_origen` VARCHAR(45) NULL,
    `firma_webhook_recibida` VARCHAR(255) NULL,
    `clave_idempotencia_webhook` VARCHAR(150) NULL UNIQUE,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_intentos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_pago` FOREIGN KEY (`pago_id`, `organizacion_id`) REFERENCES `pagos` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_venta` FOREIGN KEY (`venta_id`, `organizacion_id`) REFERENCES `ventas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_intentos_org_pas` FOREIGN KEY (`organizacion_pasarela_id`, `organizacion_id`)
        REFERENCES `organizacion_pasarelas` (`id`, `organizacion_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos y eventos de pasarela web/webhook';

-- ------------------------------------------------------------------------------
-- 10. TABLA `pago_reembolsos` (ASIENTOS COMPENSATORIOS DE REEMBOLSO)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pago_reembolsos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'REEM-AAAA-000001',
    `monto_reembolsado` DECIMAL(12,2) NOT NULL,
    `estado` ENUM('SOLICITADO', 'APROBADO', 'EJECUTADO', 'RECHAZADO', 'FALLIDO') NOT NULL DEFAULT 'EJECUTADO',
    `motivo` ENUM('DESISTIMIENTO_CLIENTE', 'FUERZA_MAYOR_CLIMA', 'ERROR_DUPLICIDAD_PAGO', 'AJUSTE_COMERCIAL') NOT NULL,
    `motivo_detalle` VARCHAR(255) NOT NULL,
    `transaccion_reembolso_externa_id` VARCHAR(150) NULL,
    `autorizado_por` INT UNSIGNED NOT NULL,
    `ejecutado_en` DATETIME NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reembolsos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_pago` FOREIGN KEY (`pago_id`, `organizacion_id`) REFERENCES `pagos` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_venta` FOREIGN KEY (`venta_id`, `organizacion_id`) REFERENCES `ventas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reembolsos_autorizado` FOREIGN KEY (`autorizado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_reembolsos_correlativo` UNIQUE (`organizacion_id`, `correlativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asientos compensatorios de reembolso y extorno';

-- ------------------------------------------------------------------------------
-- 11. SEMILLAS INSTITUCIONALES DE PASARELAS Y PERMISOS RBAC
-- ------------------------------------------------------------------------------
INSERT INTO `pasarelas_pago` (`codigo`, `nombre`, `descripcion`, `tipo_integracion`, `protocolo_webhook`, `soporta_reembolsos`, `activo`)
VALUES
    ('CULQI', 'Culqi Online', 'Pasarela de pagos con tarjetas peruanas y billeteras', 'CHECKOUT_WEB', 'HMAC_SHA256', 1, 1),
    ('NIUBIZ', 'Niubiz Pago Web', 'Pasarela adquirente oficial de Visa y Mastercard en Perú', 'CHECKOUT_WEB', 'BEARER_TOKEN', 1, 1),
    ('STRIPE', 'Stripe Payments', 'Procesador internacional de tarjetas y pagos globales', 'CHECKOUT_WEB', 'HMAC_SHA256', 1, 1),
    ('MERCADOPAGO', 'Mercado Pago Perú', 'Checkout web y cobro QR de Mercado Libre', 'CHECKOUT_WEB', 'SIGNATURE_HEADER', 1, 1),
    ('YAPE_PLIN_MANUAL', 'Billeteras Móviles Manual', 'Cobro manual mediante QR estático y confirmación de boucher', 'MANUAL', 'NINGUNO', 0, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Permisos RBAC para Módulo 11 (pagos_caja)
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
    (72, 11, 'pagos.ver', 'Ver Libro de Pagos', 'Consulta de pagos, transacciones y estados de cuenta de ventas'),
    (73, 11, 'pagos.registrar_manual', 'Registrar Cobros Manuales', 'Registro de cobros en efectivo, transferencias bancarias y POS'),
    (74, 11, 'pagos.verificar', 'Verificar Depósitos', 'Validación y aprobación/rechazo de comprobantes de transferencia'),
    (75, 11, 'pagos.reembolsar', 'Emitir Reembolsos', 'Autorización y registro de asientos compensatorios de reembolso'),
    (76, 11, 'pasarelas.gestionar', 'Gestionar Pasarelas', 'Configuración de credenciales de pasarelas y comisiones del tenant'),
    (77, 11, 'cuentas_bancarias.gestionar', 'Gestionar Cuentas Bancarias', 'Padrón de cuentas bancarias y billeteras de la organización')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos a administradores de plataforma (rol_id = 1) y de organización (rol_id = 2)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (1, 72), (1, 73), (1, 74), (1, 75), (1, 76), (1, 77),
    (2, 72), (2, 73), (2, 74), (2, 75), (2, 76), (2, 77);

-- Operadores (rol_id = 3): permiso para registrar cobros manuales y ver pagos
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (3, 72), (3, 73);

-- ------------------------------------------------------------------------------
-- 12. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 14)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_09_000016_crear_modulo_pagos_y_finanzas_rbac.sql', 14)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000018_portal_publico_bloqueos_y_sesiones_clientes.sql
-- DESCRIPCIÓN: Fase F2.9B - Bloqueos temporales de cupos para checkout público,
--              sesiones segregadas de clientes en portal público y códigos de acceso
--              passwordless (OTP / Magic Link) con trazabilidad y protección anti-IDOR.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `reserva_bloqueos_temporales` (CONTROL ATÓMICO DE CUPOS CON TTL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reserva_bloqueos_temporales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `salida_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Vínculo opcional con salida operativa programada',
    `token_bloqueo` VARCHAR(64) NOT NULL COMMENT 'Token aleatorio criptográfico CSPRNG',
    `identificador_sesion` VARCHAR(100) NOT NULL COMMENT 'Fingerprint o token de cliente',
    `cupos_bloqueados` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `fecha_servicio` DATE DEFAULT NULL,
    `hora_servicio` TIME DEFAULT NULL,
    `estado` ENUM('ACTIVO', 'RETENCION_VOUCHER', 'CONSUMIDO', 'EXPIRADO', 'LIBERADO_MANUAL') NOT NULL DEFAULT 'ACTIVO',
    `expira_en` DATETIME NOT NULL COMMENT 'Timestamp de caducidad del bloqueo',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_bloqueo_token` (`token_bloqueo`),
    KEY `idx_bloqueo_disponibilidad` (`organizacion_id`, `item_comercial_id`, `salida_id`, `estado`, `expira_en`),
    KEY `idx_bloqueo_sesion` (`identificador_sesion`, `estado`),
    CONSTRAINT `fk_bloqueo_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bloqueo_edicion` FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bloqueo_item` FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bloqueo_salida` FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bloqueos temporales de cupos para checkout público anti-sobreventa';

-- ------------------------------------------------------------------------------
-- 2. TABLA `cliente_sesiones` (SESIONES SEGREGADAS PARA EL PORTAL DEL CLIENTE)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_sesiones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `token_sesion_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token de cookie candelaria_cliente_sesion',
    `ip_creacion` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `expira_en` DATETIME NOT NULL,
    `revocado` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultimo_acceso_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_cliente_sesion_hash` (`token_sesion_hash`),
    KEY `idx_cliente_sesion_valida` (`organizacion_id`, `cliente_id`, `revocado`, `expira_en`),
    CONSTRAINT `fk_sesion_cli_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sesion_cli_cliente` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sesiones seguras de clientes en portal público sin acceso a backoffice';

-- ------------------------------------------------------------------------------
-- 3. TABLA `cliente_codigos_acceso` (MAGIC LINKS Y OTPS DE ACCESO PASSWORDLESS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_codigos_acceso` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `tipo_acceso` ENUM('OTP_WHATSAPP', 'MAGIC_LINK_EMAIL') NOT NULL,
    `codigo_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del código numérico o token del enlace',
    `destino` VARCHAR(150) NOT NULL,
    `utilizado` TINYINT(1) NOT NULL DEFAULT 0,
    `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expira_en` DATETIME NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_codigos_resolucion` (`organizacion_id`, `cliente_id`, `tipo_acceso`, `utilizado`, `expira_en`),
    CONSTRAINT `fk_codigos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_codigos_cli` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Códigos OTP y tokens de acceso passwordless para clientes';

-- ------------------------------------------------------------------------------
-- 4. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 16)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_11_000018_portal_publico_bloqueos_y_sesiones_clientes.sql', 16);

SET FOREIGN_KEY_CHECKS = 1;

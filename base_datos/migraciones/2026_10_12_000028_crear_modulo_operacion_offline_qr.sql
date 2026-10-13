-- ==============================================================================
-- CANDELARIAAPP V1.1 — MIGRACIÓN 28: OPERACIÓN MÓVIL Y VALIDACIÓN QR OFFLINE
-- Directiva: DT-V11-010 (Fase 3.3)
-- Propósito: Estructuras relacionales para lotes y eventos de sincronización offline,
--            conciliación de accesos, resolución de duplicados y permiso RBAC 114.
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla de lotes de sincronización offline
CREATE TABLE IF NOT EXISTS `operacion_offline_lotes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `operador_id` INT UNSIGNED NOT NULL,
    `lote_uuid` VARCHAR(64) NOT NULL,
    `dispositivo_info` VARCHAR(255) DEFAULT NULL,
    `total_escaneos` INT NOT NULL DEFAULT 0,
    `total_confirmados` INT NOT NULL DEFAULT 0,
    `total_conflictos` INT NOT NULL DEFAULT 0,
    `total_rechazados` INT NOT NULL DEFAULT 0,
    `estado` ENUM('RECIBIDO', 'PROCESADO', 'CON_CONFLICTOS') NOT NULL DEFAULT 'RECIBIDO',
    `ip_origen` VARCHAR(45) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `procesado_en` DATETIME DEFAULT NULL,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_offline_lote_uuid` (`lote_uuid`),
    KEY `idx_offline_lotes_org_edicion` (`organizacion_id`, `edicion_id`),
    KEY `idx_offline_lotes_operador` (`operador_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lotes de sincronización de validaciones offline de campo';

-- 2. Tabla de eventos individuales sincronizados desde dispositivos
CREATE TABLE IF NOT EXISTS `operacion_offline_eventos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lote_id` BIGINT UNSIGNED NOT NULL,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `operador_id` INT UNSIGNED NOT NULL,
    `idempotencia_hash` CHAR(64) NOT NULL,
    `token_qr_hash` CHAR(64) NOT NULL,
    `tipo_credencial` ENUM('BOLETO_RESERVA', 'PARTICIPANTE_SALIDA', 'ACREDITACION_FOLCLORE', 'PASE_PERSONAL') NOT NULL DEFAULT 'BOLETO_RESERVA',
    `referencia_id` BIGINT UNSIGNED DEFAULT NULL,
    `resultado_local` VARCHAR(32) NOT NULL DEFAULT 'VALIDO_PROVISIONAL',
    `resultado_servidor` ENUM('CONFIRMADO', 'CONFLICTO_DUPLICADO', 'RECHAZADO_REVOCADO', 'RECHAZADO_EXPIRADO', 'RECHAZADO_INEXISTENTE') NOT NULL,
    `motivo_resolucion` VARCHAR(255) DEFAULT NULL,
    `escaneado_en` DATETIME NOT NULL,
    `sincronizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `metadatos_json` JSON DEFAULT NULL,

    FOREIGN KEY (`lote_id`) REFERENCES `operacion_offline_lotes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_offline_evento_idempotencia` (`idempotencia_hash`),
    KEY `idx_offline_eventos_lote` (`lote_id`),
    KEY `idx_offline_eventos_qr_hash` (`organizacion_id`, `token_qr_hash`),
    KEY `idx_offline_eventos_operador` (`operador_id`, `escaneado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eventos individuales de escaneo QR sincronizados desde dispositivos de campo';

-- 3. Permiso RBAC 114: operacion.offline.validar
INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`)
SELECT m.id, 'operacion.offline.validar', 'Validar QR y Operar Offline', 'Permite acceso a la interfaz móvil de validación QR sin conexión y sincronización de campo'
FROM `modulos` m WHERE m.codigo = 'operaciones' LIMIT 1
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 4. Asignación a roles operativos y de administración
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permisos` p ON p.codigo = 'operacion.offline.validar'
WHERE r.codigo IN ('superadmin_plataforma', 'admin_organizacion', 'operador_produccion');

-- 5. Registro formal en el control de migraciones
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES (
    '2026_10_12_000028_crear_modulo_operacion_offline_qr.sql',
    1
) ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

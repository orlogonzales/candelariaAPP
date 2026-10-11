-- ==============================================================================
-- MIGRACIÓN: 2026_10_11_000019_crear_modulo_documentos_pdf_y_recibos_caja.sql
-- DESCRIPCIÓN: Fase F2.10 - Infraestructura documental PDF, numeración correlativa
--              de Recibos Internos de Caja y registro inmutable de documentos emitidos.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `recibos_caja_secuencias` (CORRELATIVO ESTRICTO POR TENANT Y AÑO)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibos_caja_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`organizacion_id`, `anio`),
    CONSTRAINT `fk_recibos_sec_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de recibos internos de caja';

-- ------------------------------------------------------------------------------
-- 2. TABLA `recibos_caja` (COMPROBANTES INTERNOS DE CAJA)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibos_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYY-NNNNNN',
    `monto_recibido` DECIMAL(12,2) NOT NULL,
    `moneda` CHAR(3) NOT NULL DEFAULT 'PEN',
    `metodo_pago` VARCHAR(40) NOT NULL,
    `numero_operacion_bancaria` VARCHAR(60) DEFAULT NULL,
    `cajero_usuario_id` INT UNSIGNED NOT NULL,
    `titular_nombre` VARCHAR(150) NOT NULL,
    `titular_documento` VARCHAR(30) DEFAULT NULL,
    `concepto` VARCHAR(255) NOT NULL,
    `observaciones` TEXT DEFAULT NULL,
    `emitido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `hash_sha256` CHAR(64) DEFAULT NULL,
    UNIQUE KEY `uk_recibo_org_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_recibo_pago` (`pago_id`),
    KEY `idx_recibo_venta` (`venta_id`),
    CONSTRAINT `fk_recibos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_recibos_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_recibos_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_recibos_cajero` FOREIGN KEY (`cajero_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recibos de caja internos (sin valor tributario SUNAT)';

-- ------------------------------------------------------------------------------
-- 3. TABLA `documentos_emitidos` (BITÁCORA Y REGISTRO DE ARCHIVOS PDF EMITIDOS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_emitidos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo_documento` ENUM('COTIZACION', 'CONTRATO', 'RECIBO_CAJA', 'BOLETO_A4', 'BOLETO_TICKET_80MM') NOT NULL,
    `entidad_tipo` ENUM('COTIZACION', 'VENTA', 'RESERVA', 'PAGO') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `correlativo_documento` VARCHAR(60) NOT NULL,
    `formato` ENUM('A4', 'TICKET_80MM') NOT NULL DEFAULT 'A4',
    `ruta_archivo_privado` VARCHAR(255) NOT NULL,
    `peso_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
    `hash_sha256` CHAR(64) NOT NULL,
    `generado_por_usuario_id` INT UNSIGNED DEFAULT NULL,
    `cliente_titular_id` INT UNSIGNED DEFAULT NULL,
    `generado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_doc_entidad` (`organizacion_id`, `entidad_tipo`, `entidad_id`),
    KEY `idx_doc_tipo` (`organizacion_id`, `tipo_documento`),
    CONSTRAINT `fk_docs_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_docs_usuario` FOREIGN KEY (`generado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_docs_cliente` FOREIGN KEY (`cliente_titular_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro y trazabilidad de documentos PDF generados';

-- ------------------------------------------------------------------------------
-- 4. REGISTRO EN MODULOS Y PERMISOS RBAC (MÓDULO 13: DOCUMENTOS E IMPRESIÓN)
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`)
VALUES (13, 'documentos', 'Documentos e Impresión Oficial', 'Generación de cotizaciones, contratos, recibos de caja y boletos imprimibles', 'fa-solid fa-file-pdf', 13, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`), `estado` = VALUES(`estado`);

INSERT INTO `permisos` (`modulo_id`, `codigo`, `nombre`, `descripcion`)
VALUES
(13, 'documentos.cotizaciones.descargar', 'Descargar Cotización PDF', 'Permite descargar el archivo PDF oficial de una cotización'),
(13, 'documentos.contratos.descargar', 'Descargar Contrato PDF', 'Permite descargar el contrato de servicios de una venta'),
(13, 'documentos.recibos.emitir', 'Emitir Recibo de Caja', 'Permite generar formalmente un recibo de caja numerado por un pago aprobado'),
(13, 'documentos.recibos.descargar', 'Descargar Recibo de Caja PDF', 'Permite descargar el recibo interno de caja en PDF'),
(13, 'documentos.boletos.descargar', 'Descargar Boleto Imprimible', 'Permite generar y descargar boletos QR en A4 o ticket 80mm')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos a roles ADMINISTRADOR y CAJERO/VENTAS
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'ADMINISTRADOR'
  AND p.modulo_id = 13;

INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo IN ('VENTAS', 'CAJERO', 'COORDINADOR_VENTAS')
  AND p.codigo IN ('documentos.cotizaciones.descargar', 'documentos.contratos.descargar', 'documentos.recibos.emitir', 'documentos.recibos.descargar', 'documentos.boletos.descargar');

-- ------------------------------------------------------------------------------
-- 5. REGISTRAR MIGRACIÓN EN CONTROL
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`, `ejecutado_en`)
VALUES ('2026_10_11_000019_crear_modulo_documentos_pdf_y_recibos_caja.sql', 1, NOW())
ON DUPLICATE KEY UPDATE `ejecutado_en` = VALUES(`ejecutado_en`);

SET FOREIGN_KEY_CHECKS = 1;

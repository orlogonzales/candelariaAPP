-- ==============================================================================
-- CANDELARIAAPP — MIGRACIÓN 000017
-- MÓDULO 12: COMUNICACIONES Y MENSAJERÍA TRAZABLE WHATSAPP
-- FASE F2.8B: CONTRATO DE GOBERNANZA, OUTBOX Y RBAC (INCREMENTAL NO DESTRUCTIVO)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. SOPORTE DE INTEGRIDAD COMPUESTA EN CLIENTES
-- ------------------------------------------------------------------------------
-- Permite que las tablas del subsistema de comunicaciones hagan referencia a
-- (cliente_id, organizacion_id) garantizando aislamiento multitenant estricto.
SET @existe_idx = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'clientes' AND index_name = 'uk_clientes_compuesta');
SET @sql_idx = IF(@existe_idx = 0, 'ALTER TABLE `clientes` ADD UNIQUE KEY `uk_clientes_compuesta` (`id`, `organizacion_id`)', 'SELECT 1');
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------------------
-- 2. CATÁLOGO DE PROVEEDORES DE MENSAJERÍA
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_proveedores` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_integracion` ENUM('DIRECTA_META', 'BSP_PARTNER', 'LOCAL_SIMULADOR') NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uk_comunicacion_proveedores_codigo` UNIQUE (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de proveedores de WhatsApp y simulación';

-- ------------------------------------------------------------------------------
-- 3. CONFIGURACIÓN MULTITENANT DE CONECTIVIDAD WHATSAPP
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizacion_comunicacion_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `proveedor_id` TINYINT UNSIGNED NOT NULL,
    `modo` ENUM('SIMULADOR', 'PRODUCCION') NOT NULL DEFAULT 'SIMULADOR',
    `numero_telefono_identificador` VARCHAR(30) NOT NULL COMMENT 'Formato internacional E.164 (+51...)',
    `meta_phone_number_id` VARCHAR(60) NULL,
    `meta_waba_id` VARCHAR(60) NULL COMMENT 'WhatsApp Business Account ID',
    `meta_app_id` VARCHAR(60) NULL,
    `token_acceso_cifrado` TEXT NULL COMMENT 'Cifrado simétrico AES-256-GCM',
    `webhook_secret_cifrado` TEXT NULL COMMENT 'Cifrado simétrico AES-256-GCM',
    `webhook_verify_token_hash` VARCHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token para verificación de Meta',
    `limite_mensajes_por_segundo` TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `presupuesto_mensual_limite_usd` DECIMAL(10,2) NOT NULL DEFAULT 50.00 COMMENT 'Tope de seguridad por defecto',
    `gasto_acumulado_mes_actual_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_org_comunicacion_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_org_comunicacion_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `comunicacion_proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_org_comunicacion_config` UNIQUE (`organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros de conectividad y límites de mensajería del tenant';

-- ------------------------------------------------------------------------------
-- 4. CATÁLOGO VERSIONADO DE TARIFAS POR MENSAJE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_tarifas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proveedor_id` TINYINT UNSIGNED NOT NULL,
    `codigo_pais` VARCHAR(5) NOT NULL DEFAULT 'PE' COMMENT 'ISO alpha-2 o prefijo E.164',
    `categoria_plantilla` ENUM('UTILITY', 'MARKETING', 'AUTHENTICATION', 'SERVICE_CONVERSATION') NOT NULL,
    `costo_unidad_usd` DECIMAL(8,5) NOT NULL,
    `moneda_base` CHAR(3) NOT NULL DEFAULT 'USD',
    `vigente_desde` DATE NOT NULL,
    `vigente_hasta` DATE NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tarifas_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `comunicacion_proveedores` (`id`) ON DELETE RESTRICT,
    KEY `idx_tarifas_busqueda` (`proveedor_id`, `codigo_pais`, `categoria_plantilla`, `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tarifas de Meta/BSP versionadas en el tiempo';

-- ------------------------------------------------------------------------------
-- 5. PLANTILLAS OFICIALES VERSIONADAS DE WHATSAPP
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_plantillas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Identificador canónico en Meta (snake_case)',
    `idioma` VARCHAR(10) NOT NULL DEFAULT 'es_PE',
    `categoria` ENUM('UTILITY', 'MARKETING', 'AUTHENTICATION') NOT NULL,
    `meta_template_id` VARCHAR(80) NULL,
    `estado_meta` ENUM('DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'PAUSED') NOT NULL DEFAULT 'DRAFT',
    `cuerpo_texto` TEXT NOT NULL,
    `encabezado_tipo` ENUM('NINGUNO', 'TEXTO', 'IMAGEN', 'DOCUMENTO') NOT NULL DEFAULT 'NINGUNO',
    `pie_texto` VARCHAR(120) NULL,
    `parametros_mapeo_json` JSON NOT NULL COMMENT 'Mapeo de variables posicionales {{1}}, {{2}}',
    `version_local` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_plantillas_organizacion` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_plantilla_org_nombre_idioma` UNIQUE (`organizacion_id`, `nombre`, `idioma`),
    CONSTRAINT `uk_comunicacion_plantillas_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plantillas autorizadas y versionadas por tenant';

-- ------------------------------------------------------------------------------
-- 6. CONSENTIMIENTO ESPECÍFICO POR CANAL (TRAZABILIDAD Y REGISTRO LEGAL)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_consentimientos_canal` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `canal` VARCHAR(30) NOT NULL DEFAULT 'WHATSAPP',
    `telefono_destino` VARCHAR(30) NOT NULL,
    `finalidad` ENUM('TRANSACCIONAL_OPERATIVO', 'PROMOCIONAL_MARKETING') NOT NULL,
    `estado` ENUM('CONCEDIDO', 'REVOCADO') NOT NULL DEFAULT 'CONCEDIDO',
    `origen_evidencia` VARCHAR(50) NOT NULL COMMENT 'WEB_CHECKBOX, PRESENCIAL_CONTRATO, WHATSAPP_INBOUND',
    `texto_clausula_aceptada` TEXT NULL,
    `direccion_ip_registro` VARCHAR(45) NULL,
    `actor_tipo` VARCHAR(20) NOT NULL COMMENT 'HUMANO o SISTEMA',
    `usuario_id` INT UNSIGNED NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revocado_en` DATETIME NULL,
    CONSTRAINT `fk_consentimiento_canal_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_consentimiento_canal_cli` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_consentimiento_canal_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    KEY `idx_consentimiento_canal_resolucion` (`organizacion_id`, `cliente_id`, `canal`, `finalidad`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evidencias de opt-in y opt-out específicas por canal';

-- ------------------------------------------------------------------------------
-- 7. HILOS DE CONVERSACIÓN Y SESIONES DE ATENCIÓN (VENTANA 24H)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_conversaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `telefono_cliente` VARCHAR(30) NOT NULL,
    `numero_conversacion` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incrementable al cerrar y reabrir sesión',
    `operador_usuario_id` INT UNSIGNED NULL,
    `estado` ENUM('ABIERTA', 'EN_ATENCION', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    `ultimo_mensaje_cliente_en` DATETIME NULL COMMENT 'Inicio de ventana de 24 horas',
    `ventana_servicio_expira_en` DATETIME NULL,
    `total_mensajes` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_conversaciones_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conversaciones_cli` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conversaciones_operador` FOREIGN KEY (`operador_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `uk_conversacion_activa` UNIQUE (`organizacion_id`, `telefono_cliente`, `numero_conversacion`),
    CONSTRAINT `uk_comunicacion_conversaciones_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hilos y sesiones de atención al cliente';

-- ------------------------------------------------------------------------------
-- 8. SUBMÓDULO DE CAMPAÑAS DESACOPLADAS (SEGREGACIÓN DE DEBERES)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_campanas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NULL,
    `criterios_segmentacion_json` JSON NOT NULL,
    `estado` ENUM('BORRADOR', 'APROBADA', 'PROGRAMADA', 'EN_EJECUCION', 'PAUSADA', 'COMPLETADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `total_destinatarios` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_enviados` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_entregados` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_fallidos` INT UNSIGNED NOT NULL DEFAULT 0,
    `presupuesto_asignado_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `gasto_ejecutado_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `programada_para` DATETIME NULL,
    `iniciada_en` DATETIME NULL,
    `finalizada_en` DATETIME NULL,
    `aprobado_por` INT UNSIGNED NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_campanas_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_plantilla` FOREIGN KEY (`plantilla_id`, `organizacion_id`) REFERENCES `comunicacion_plantillas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_aprobado` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_campanas_creado` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_campana_sod` CHECK (`aprobado_por` IS NULL OR `aprobado_por` != `creado_por`),
    CONSTRAINT `uk_comunicacion_campanas_compuesta` UNIQUE (`id`, `organizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campañas masivas desacopladas con control SoD';

-- ------------------------------------------------------------------------------
-- 9. MENSAJES OFICIALES (PATRÓN OUTBOX TRANSACCIONAL CON INTEGRIDAD COMPUESTA)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_mensajes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `conversacion_id` BIGINT UNSIGNED NULL,
    `campana_id` INT UNSIGNED NULL,
    `tipo_mensaje` ENUM('TRANSACCIONAL', 'PROMOCIONAL', 'RESPUESTA_OPERADOR', 'ENTRANTE_CLIENTE') NOT NULL,
    `direccion` ENUM('SALIENTE', 'ENTRANTE') NOT NULL,
    `canal` VARCHAR(30) NOT NULL DEFAULT 'WHATSAPP',
    `destinatario_telefono` VARCHAR(30) NOT NULL,
    `destinatario_nombre` VARCHAR(150) NOT NULL,
    `cliente_id` INT UNSIGNED NULL,
    `venta_id` BIGINT UNSIGNED NULL,
    `reserva_id` BIGINT UNSIGNED NULL,
    `plantilla_id` INT UNSIGNED NULL,
    `contenido_texto` TEXT NOT NULL COMMENT 'Texto formateado minimizado',
    `parametros_enviados_json` JSON NULL COMMENT 'Valores de variables no sensibles',
    `estado` ENUM('ENCOLADO', 'EN_PROCESO', 'REINTENTO_PROGRAMADO', 'ENVIADO', 'ENTREGADO', 'LEIDO', 'FALLIDO', 'CANCELADO') NOT NULL DEFAULT 'ENCOLADO',
    `peso_estado` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT '10:ENCOLADO, 20:EN_PROCESO, 25:REINTENTO_PROGRAMADO, 30:ENVIADO, 40:ENTREGADO, 50:LEIDO, 90:FALLIDO, 95:CANCELADO',
    `wamid` VARCHAR(120) NULL COMMENT 'WhatsApp Message ID',
    `idempotency_key` VARCHAR(64) NOT NULL,
    `costo_estimado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `costo_calculado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `costo_conciliado_usd` DECIMAL(8,5) NOT NULL DEFAULT 0.00,
    `fecha_conciliacion` DATE NULL,
    `intentos_realizados` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_intentos` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `proximo_intento_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `bloqueado_hasta` DATETIME NULL COMMENT 'Lock atómico temporal del worker',
    `creado_por` INT UNSIGNED NULL,
    `correlacion_id` VARCHAR(64) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mensajes_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_conversacion` FOREIGN KEY (`conversacion_id`, `organizacion_id`) REFERENCES `comunicacion_conversaciones` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_campana` FOREIGN KEY (`campana_id`, `organizacion_id`) REFERENCES `comunicacion_campanas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_cliente` FOREIGN KEY (`cliente_id`, `organizacion_id`) REFERENCES `clientes` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mensajes_plantilla` FOREIGN KEY (`plantilla_id`, `organizacion_id`) REFERENCES `comunicacion_plantillas` (`id`, `organizacion_id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_mensajes_idempotencia_org` UNIQUE (`organizacion_id`, `idempotency_key`),
    CONSTRAINT `uk_comunicacion_mensajes_compuesta` UNIQUE (`id`, `organizacion_id`),
    KEY `idx_mensajes_outbox` (`organizacion_id`, `estado`, `proximo_intento_en`),
    KEY `idx_mensajes_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbox e historial de mensajería con integridad multitenant compuesta';

-- ------------------------------------------------------------------------------
-- 10. BITÁCORA DE INTENTOS DE ENVÍO
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_intentos_envio` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `mensaje_id` BIGINT UNSIGNED NOT NULL,
    `intento_numero` TINYINT UNSIGNED NOT NULL,
    `http_status` SMALLINT UNSIGNED NULL,
    `meta_error_code` INT NULL,
    `meta_error_subcode` INT NULL,
    `meta_error_message` VARCHAR(255) NULL,
    `latencia_ms` INT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_intentos_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `comunicacion_mensajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos y telemetría de despacho';

-- ------------------------------------------------------------------------------
-- 11. TABLA DE DEDUPLICACIÓN DE WEBHOOKS (IDEMPOTENCIA DE EVENTOS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_webhook_eventos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `event_id` VARCHAR(120) NOT NULL COMMENT 'ID único de evento o SHA-256 de payload',
    `tipo_evento` VARCHAR(50) NOT NULL COMMENT 'messages, message_status, etc.',
    `wamid` VARCHAR(120) NULL,
    `payload_resumido_json` JSON NOT NULL,
    `procesado` TINYINT(1) NOT NULL DEFAULT 0,
    `error_proceso` VARCHAR(255) NULL,
    `recibido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `procesado_en` DATETIME NULL,
    CONSTRAINT `fk_webhook_eventos_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `uk_webhook_event_org` UNIQUE (`organizacion_id`, `event_id`),
    KEY `idx_webhook_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deduplicación e idempotencia estricta de webhooks entrantes';

-- ------------------------------------------------------------------------------
-- 12. SECUENCIAS ATÓMICAS DE COMUNICACIONES
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicacion_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo` VARCHAR(20) NOT NULL COMMENT 'MENSAJE, CAMPANA',
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`organizacion_id`, `tipo`),
    CONSTRAINT `fk_com_secuencias_org` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generador atómico de correlativos de mensajería';

-- ------------------------------------------------------------------------------
-- 13. SEMILLAS INSTITUCIONALES DE PROVEEDORES
-- ------------------------------------------------------------------------------
INSERT INTO `comunicacion_proveedores` (`id`, `codigo`, `nombre`, `tipo_integracion`, `activo`)
VALUES
    (1, 'SIMULADOR_SANDBOX', 'Simulador WhatsApp Local (Sandbox)', 'LOCAL_SIMULADOR', 1),
    (2, 'META_CLOUD_API', 'Meta WhatsApp Business Cloud API', 'DIRECTA_META', 1),
    (3, 'TWILIO_BSP', 'Twilio Messaging API (BSP)', 'BSP_PARTNER', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `tipo_integracion` = VALUES(`tipo_integracion`);

-- ------------------------------------------------------------------------------
-- 14. PERMISOS ATÓMICOS RBAC PARA MÓDULO 12 (comunicaciones)
-- ------------------------------------------------------------------------------
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
    (78, 12, 'comunicaciones.ver', 'Ver Mensajes y Conversaciones', 'Consulta de bandeja de mensajes, estados y conversaciones'),
    (79, 12, 'comunicaciones.enviar_individual', 'Enviar Mensajes Individuales', 'Envío manual de notificaciones transaccionales o respuestas de operador'),
    (80, 12, 'comunicaciones.gestionar_campanas', 'Crear y Segmentar Campañas', 'Creación y configuración de campañas promocionales para clientes con opt-in'),
    (81, 12, 'comunicaciones.aprobar_campanas', 'Aprobar Campañas Masivas', 'Autorización formal y liberación de presupuesto para ejecución de campañas'),
    (82, 12, 'comunicaciones.gestionar_plantillas', 'Gestionar Plantillas de WhatsApp', 'Creación y versionado de plantillas oficiales asociadas a Meta'),
    (83, 12, 'comunicaciones.configurar_proveedor', 'Configurar Proveedor WhatsApp', 'Administración de credenciales de Meta, webhooks, presupuestos y modo'),
    (84, 12, 'comunicaciones.gestionar_consentimientos', 'Gestionar Consentimientos de Canal', 'Registro formal, evidencia legal y revocación de opt-in/opt-out por canal')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a Superadministrador (rol_id = 1) y Administrador de Organización (rol_id = 2)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (1, 78), (1, 79), (1, 80), (1, 81), (1, 82), (1, 83), (1, 84),
    (2, 78), (2, 79), (2, 80), (2, 81), (2, 82), (2, 83), (2, 84);

-- Operadores (rol_id = 3): permiso para ver y responder mensajes individuales
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
    (3, 78), (3, 79);

-- ------------------------------------------------------------------------------
-- 15. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 15)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`)
VALUES ('2026_10_09_000017_crear_modulo_comunicaciones_whatsapp_rbac.sql', 15)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

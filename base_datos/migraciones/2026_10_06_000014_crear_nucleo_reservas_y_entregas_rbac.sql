-- ==============================================================================
-- CANDELARIAAPP - MIGRACIÓN OFICIAL
-- ARCHIVO: 2026_10_06_000014_crear_nucleo_reservas_y_entregas_rbac.sql
-- FASE: 2.6C - Implementación del Núcleo de Reservas, Prestaciones,
--              Participantes, Reprogramaciones y Entregas Desacopladas
-- FECHA: 2026-10-06
-- ==============================================================================
-- DESCRIPCIÓN:
-- 1. Secuencias correlativas de Reservas (`reservas_secuencias`).
-- 2. Secuencias correlativas de Órdenes de Entrega (`entregas_secuencias`).
-- 3. Configuración operativa explícita del catálogo (`item_configuracion_operativa`).
--    (Sin defaults semánticos peligrosos: Fail-Closed si no está configurado).
-- 4. Cabecera administrativa de Reservas (`reservas`) con relación 1:1 formal con Ventas.
-- 5. Prestaciones agendables de la reserva (`reserva_prestaciones`) con snapshots.
-- 6. Beneficiarios de viaje (`reserva_participantes`), con minimización ZERO-PII.
-- 7. Asignación M:N participante a prestaciones (`prestacion_participantes`).
-- 8. Historial append-only de reprogramaciones (`reserva_reprogramaciones`).
-- 9. Frontera desacoplada de despacho para bienes tangibles (`entregas_productos`).
-- 10. Detalle de ítems de entrega (`entrega_items`).
-- 11. Módulo 22 (`reservas`) y 6 permisos RBAC canónicos.
-- 12. Registro en `migraciones_control` (Lote 12).
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `reservas_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS RSV)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservas_secuencias`;
CREATE TABLE `reservas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de reserva particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 2. TABLA `entregas_secuencias` (GENERACIÓN CONCURRENTE DE CORRELATIVOS ENT)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entregas_secuencias`;
CREATE TABLE `entregas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` SMALLINT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas de despacho particionadas por tenant y año';

-- ------------------------------------------------------------------------------
-- 3. TABLA `item_configuracion_operativa` (SEMÁNTICA OPERATIVA EXPLÍCITA DEL CATÁLOGO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `item_configuracion_operativa`;
CREATE TABLE `item_configuracion_operativa` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_comercial_id` INT UNSIGNED NOT NULL COMMENT 'Vínculo 1:1 con ítem comercial',
    `requiere_reserva` TINYINT(1) NOT NULL COMMENT '1 si genera compromiso de reserva; 0 si no (sin default)',
    `requiere_agendamiento` TINYINT(1) NOT NULL COMMENT '1 si requiere agendar fecha de servicio; 0 si no (sin default)',
    `requiere_participantes` TINYINT(1) NOT NULL COMMENT '1 si requiere nómina de pasajeros/beneficiarios; 0 si no (sin default)',
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL COMMENT 'Naturaleza funcional del aforo (sin default)',
    `es_accesorio` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es servicio accesorio/incluido dependiente de otra prestación',
    `duracion_estimada_minutos` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Duración referencial de la actividad',
    `punto_partida_predeterminado` VARCHAR(200) DEFAULT NULL COMMENT 'Muelle, terminal o punto de encuentro base',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_item_cfg_item` (`item_comercial_id`),
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_item_cfg_reserva` CHECK (`requiere_reserva` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_agendamiento` CHECK (`requiere_agendamiento` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_participantes` CHECK (`requiere_participantes` IN (0, 1)),
    CONSTRAINT `chk_item_cfg_coherencia` CHECK (
        (`requiere_agendamiento` = 0) OR (`requiere_agendamiento` = 1 AND `requiere_reserva` = 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración operativa explícita desacoplada del catálogo comercial';

-- ------------------------------------------------------------------------------
-- 4. TABLA `reservas` (COMPROMISO ADMINISTRATIVO UNIFICADO CON EL CLIENTE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reservas`;
CREATE TABLE `reservas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL COMMENT 'Venta origen confirmada (Relación 1:1)',
    `cliente_id` INT UNSIGNED NOT NULL COMMENT 'Cliente comercial titular',
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador formal único institucional (ej. RSV-2026-000001)',
    `estado` ENUM('REGISTRADA', 'PENDIENTE_DATOS', 'CONFIRMADA', 'CANCELADA') NOT NULL DEFAULT 'REGISTRADA' COMMENT 'Estado administrativo de la reserva',

    -- Snapshot de Contacto para Viaje (heredado de Venta / Cliente)
    `contacto_nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre de contacto o líder del grupo',
    `contacto_tipo_documento` VARCHAR(20) DEFAULT NULL,
    `contacto_numero_documento` VARCHAR(30) DEFAULT NULL,
    `contacto_telefono` VARCHAR(30) DEFAULT NULL,
    `contacto_email` VARCHAR(150) DEFAULT NULL,

    `notas_operativas` TEXT DEFAULT NULL COMMENT 'Instrucciones especiales para operaciones',
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_reservas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_reservas_venta` (`organizacion_id`, `venta_id`),
    KEY `idx_reservas_cliente` (`organizacion_id`, `cliente_id`, `estado`),
    KEY `idx_reservas_edicion` (`organizacion_id`, `edicion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de reservas de servicios vinculadas a ventas formalizadas';

-- ------------------------------------------------------------------------------
-- 5. TABLA `reserva_prestaciones` (PRESTACIONES AGENDABLES INDEPENDIENTES)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_prestaciones`;
CREATE TABLE `reserva_prestaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL COMMENT 'Línea de venta de procedencia',
    `venta_linea_componente_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Componente de paquete de procedencia (si aplica)',
    `item_comercial_id` INT UNSIGNED NOT NULL COMMENT 'Ítem base del catálogo',

    -- Snapshots Inmutables del Servicio al Formalizar
    `concepto_codigo` VARCHAR(60) NOT NULL COMMENT 'Snapshot del código comercial',
    `concepto_nombre` VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre de concepto',
    `unidad_medida` VARCHAR(30) NOT NULL COMMENT 'Snapshot de unidad canónica',
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Cantidad de servicio contratada',
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL COMMENT 'Snapshot de aforo',
    `requiere_agendamiento` TINYINT(1) NOT NULL COMMENT 'Snapshot de si requiere fecha',
    `requiere_participantes` TINYINT(1) NOT NULL COMMENT 'Snapshot de si requiere nómina',

    -- Máquina de Agendamiento
    `estado_agendamiento` ENUM('PENDIENTE_PROGRAMAR', 'PROGRAMADA', 'CANCELADA') NOT NULL DEFAULT 'PENDIENTE_PROGRAMAR',
    `fecha_servicio` DATE DEFAULT NULL COMMENT 'Fecha calendario de ejecución acordada',
    `hora_servicio` TIME DEFAULT NULL COMMENT 'Hora o turno acordado',
    `punto_encuentro` VARCHAR(200) DEFAULT NULL COMMENT 'Punto de recojo acordado para esta prestación',
    `notas_prestacion` VARCHAR(255) DEFAULT NULL,

    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Control de concurrencia optimista',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_componente_id`) REFERENCES `venta_linea_componentes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,

    KEY `idx_prestaciones_reserva` (`reserva_id`, `estado_agendamiento`),
    KEY `idx_prestaciones_fecha` (`fecha_servicio`),
    CONSTRAINT `chk_prestacion_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_prestacion_agendamiento` CHECK (
        (`estado_agendamiento` = 'PENDIENTE_PROGRAMAR' AND `fecha_servicio` IS NULL)
        OR
        (`estado_agendamiento` = 'PROGRAMADA' AND `fecha_servicio` IS NOT NULL)
        OR
        (`estado_agendamiento` = 'CANCELADA')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prestaciones agendables desglosadas de la reserva';

-- ------------------------------------------------------------------------------
-- 6. TABLA `reserva_participantes` (BENEFICIARIOS DE VIAJE CON MINIMIZACIÓN ZERO-PII)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_participantes`;
CREATE TABLE `reserva_participantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED DEFAULT NULL COMMENT 'Enlace opcional si ya existe en personas',
    `tipo_documento_id` TINYINT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `nombres` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(100) NOT NULL,
    `nacionalidad` CHAR(2) DEFAULT NULL COMMENT 'Código ISO alpha-2 (sin default forzado)',
    `rango_etario` ENUM('ADULTO', 'MENOR', 'INFANTE') DEFAULT NULL COMMENT 'Clasificación operativa (sin default forzado)',
    `telefono_contacto` VARCHAR(30) DEFAULT NULL,
    `talla_indumentaria` VARCHAR(10) DEFAULT NULL COMMENT 'Logística de vestuario/alquiler',
    `requiere_asistencia_movilidad` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bandera logística cerrada',
    `regimen_alimentario` ENUM('ESTANDAR', 'VEGETARIANO', 'CELIACO', 'OTRO_PARAMETRIZADO') DEFAULT NULL COMMENT 'Tag cerrado',
    `es_titular_reserva` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_reserva_participante_doc` (`reserva_id`, `tipo_documento_id`, `numero_documento`),
    KEY `idx_reserva_part_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Participantes y pasajeros beneficiarios de las prestaciones';

-- ------------------------------------------------------------------------------
-- 7. TABLA `prestacion_participantes` (ASIGNACIÓN M:N PRESTACIÓN <-> PARTICIPANTE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `prestacion_participantes`;
CREATE TABLE `prestacion_participantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `participante_id` BIGINT UNSIGNED NOT NULL,
    `asiento_asignado` VARCHAR(20) DEFAULT NULL COMMENT 'Butaca o asiento asignado si aplica',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`participante_id`) REFERENCES `reserva_participantes` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_prestacion_participante` (`prestacion_id`, `participante_id`),
    KEY `idx_prest_part_part` (`participante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de participantes a prestaciones específicas de la reserva';

-- ------------------------------------------------------------------------------
-- 8. TABLA `reserva_reprogramaciones` (HISTORIAL APPEND-ONLY DE CAMBIOS DE FECHA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reserva_reprogramaciones`;
CREATE TABLE `reserva_reprogramaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `fecha_anterior` DATE DEFAULT NULL,
    `fecha_nueva` DATE NOT NULL,
    `hora_anterior` TIME DEFAULT NULL,
    `hora_nueva` TIME DEFAULT NULL,
    `motivo_categoria` ENUM('SOLICITUD_CLIENTE', 'CLIMA_FUERZA_MAYOR', 'LOGISTICA_OPERATIVA', 'OTRO') NOT NULL,
    `motivo_detalle` VARCHAR(255) NOT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_reprog_prestacion` (`prestacion_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro inmutable de reprogramaciones de prestaciones';

-- ------------------------------------------------------------------------------
-- 9. TABLA `entregas_productos` (FRONTERA DESACOPLADA DE DESPACHO PARA PRODUCTOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entregas_productos`;
CREATE TABLE `entregas_productos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `venta_id` BIGINT UNSIGNED NOT NULL COMMENT 'Venta origen confirmada (Relación 1:1)',
    `cliente_id` INT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador institucional de entrega (ej. ENT-2026-000001)',
    `estado` ENUM('PENDIENTE_ENTREGA', 'ENTREGADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE_ENTREGA',

    -- Snapshot de Entrega
    `contacto_nombre` VARCHAR(150) NOT NULL,
    `contacto_telefono` VARCHAR(30) DEFAULT NULL,
    `direccion_entrega` VARCHAR(255) DEFAULT NULL COMMENT 'Hotel, domicilio o retiro en sede',
    `fecha_entrega` DATETIME DEFAULT NULL,
    `entregado_por` INT UNSIGNED DEFAULT NULL,
    `notas_despacho` VARCHAR(255) DEFAULT NULL,

    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`entregado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_entregas_correlativo` (`organizacion_id`, `correlativo`),
    UNIQUE KEY `uk_entregas_venta` (`organizacion_id`, `venta_id`),
    KEY `idx_entregas_cliente` (`organizacion_id`, `cliente_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Órdenes administrativas de despacho para bienes tangibles vendidos';

-- ------------------------------------------------------------------------------
-- 10. TABLA `entrega_items` (DETALLE DE BIENES TANGIBLES A ENTREGAR)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `entrega_items`;
CREATE TABLE `entrega_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entrega_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_id` BIGINT UNSIGNED NOT NULL,
    `venta_linea_componente_id` BIGINT UNSIGNED DEFAULT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `concepto_codigo` VARCHAR(60) NOT NULL,
    `concepto_nombre` VARCHAR(150) NOT NULL,
    `unidad_medida` VARCHAR(30) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`entrega_id`) REFERENCES `entregas_productos` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_id`) REFERENCES `venta_lineas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`venta_linea_componente_id`) REFERENCES `venta_linea_componentes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,

    KEY `idx_entrega_items_entrega` (`entrega_id`),
    CONSTRAINT `chk_entrega_item_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de bienes tangibles desglosados para despacho';

-- ------------------------------------------------------------------------------
-- 11. MÓDULO 22 Y PERMISOS RBAC DEL MÓDULO RESERVAS (6 PERMISOS CANÓNICOS)
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`) VALUES
(22, 'reservas', 'RESERVAS', 'Compromisos de servicio, programación de turnos, participantes y reprogramaciones', 'fa-solid fa-calendar-check', 7, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(54, 22, 'reservas.ver', 'Ver Reservas', 'Permite consultar listados, vouchers y fichas de reservas y compromisos de servicio'),
(55, 22, 'reservas.crear_desde_venta', 'Crear Reserva desde Venta', 'Permite formalizar compromisos de servicio a partir de ventas confirmadas'),
(56, 22, 'reservas.programar', 'Programar Prestación', 'Permite asignar fecha de servicio a prestaciones pendientes de agendamiento'),
(57, 22, 'reservas.reprogramar', 'Reprogramar Prestación', 'Permite reprogramar fechas de servicio con registro obligatorio de motivo e historial'),
(58, 22, 'reservas.gestionar_participantes', 'Gestionar Participantes', 'Permite registrar y asignar nóminas de participantes a las prestaciones de la reserva'),
(59, 22, 'reservas.cancelar', 'Cancelar Reserva', 'Permite registrar la cancelación de reservas y prestaciones antes de su despacho')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador de Plataforma (acceso total a reservas)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 54), (1, 55), (1, 56), (1, 57), (1, 58), (1, 59);

-- Administrador de Organización (gestión completa en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 54), (2, 55), (2, 56), (2, 57), (2, 58), (2, 59);

-- Operador de Producción (consulta, programación y asignación de participantes)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 54), (3, 56), (3, 58);

-- ------------------------------------------------------------------------------
-- 12. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 12)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_06_000014_crear_nucleo_reservas_y_entregas_rbac.sql', 12)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- MIGRACIÓN 000015: CREAR MÓDULO DE OPERACIÓN DE CAMPO, SALIDAS Y RECURSOS
-- ==============================================================================
-- CandelariaAPP V1 — Microfase F2.6D
-- Implementa la autoridad soberana de ejecución física y logística:
-- 1. operacion_salidas_secuencias (correlativos atómicos SAL-YYYY-NNNNNN)
-- 2. proveedores (personas jurídicas/naturales con persona_id desacoplada)
-- 3. operacion_recursos (vehículos terrestres, embarcaciones, equipo)
-- 4. operacion_salidas (unidad de ejecución logística de campo)
-- 5. operacion_salida_recursos (asignación de recursos físicos y personal operativo)
-- 6. operacion_salida_prestaciones (agrupación 1:N de prestaciones de reserva)
-- 7. operacion_asistencias (check-in individualizado, no-show y asignación de asiento)
-- 8. operacion_incidencias (registro estructurado de incidencias operativas)
-- 9. Módulo 23 'operaciones' y 12 permisos canónicos (IDs 60 a 71)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABLA `operacion_salidas_secuencias` (CORRELATIVOS MONOTÓNICOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salidas_secuencias`;
CREATE TABLE `operacion_salidas_secuencias` (
    `organizacion_id` INT UNSIGNED NOT NULL,
    `anio` INT UNSIGNED NOT NULL,
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`organizacion_id`, `anio`),
    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secuencias correlativas atómicas de salidas operativas';

-- ------------------------------------------------------------------------------
-- 2. TABLA `proveedores` (PROVEEDORES Y PRESTADORES EXTERNOS DE SERVICIO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `persona_id` INT UNSIGNED NOT NULL,
    `tipo_servicio_principal` VARCHAR(80) DEFAULT NULL COMMENT 'Especialidad operativa (ej. TRANSPORTE_LACUSTRE, GUIADO)',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
    `notas_contacto` TEXT DEFAULT NULL,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_proveedores_org_persona` (`organizacion_id`, `persona_id`),
    KEY `idx_proveedores_estado` (`organizacion_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro maestro de proveedores de servicios operativos';

-- ------------------------------------------------------------------------------
-- 3. TABLA `operacion_recursos` (RECURSOS FÍSICOS Y FLOTA OPERATIVA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_recursos`;
CREATE TABLE `operacion_recursos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `tipo_recurso` ENUM('VEHICULO_TERRESTRE', 'EMBARCACION_LACUSTRE', 'EQUIPO_LOGISTICO', 'OTRO') NOT NULL,
    `codigo_interno` VARCHAR(50) NOT NULL COMMENT 'Código identificador único (ej. LANCHA-01, BUS-02)',
    `nombre` VARCHAR(120) NOT NULL,
    `propiedad_tipo` ENUM('PROPIO', 'EXTERNO') NOT NULL,
    `proveedor_id` INT UNSIGNED DEFAULT NULL COMMENT 'Obligatorio si propiedad_tipo = EXTERNO',
    `capacidad_maxima` INT UNSIGNED NOT NULL DEFAULT 1,
    `identificacion_oficial` VARCHAR(60) DEFAULT NULL COMMENT 'Matrícula naval / placa vehicular',
    `estado` ENUM('DISPONIBLE', 'EN_MANTENIMIENTO', 'BAJA') NOT NULL DEFAULT 'DISPONIBLE',
    `notas` TEXT DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_recursos_org_codigo` (`organizacion_id`, `codigo_interno`),
    KEY `idx_recursos_estado` (`organizacion_id`, `tipo_recurso`, `estado`),
    CONSTRAINT `chk_recursos_propiedad` CHECK (
        (`propiedad_tipo` = 'PROPIO' AND `proveedor_id` IS NULL)
        OR
        (`propiedad_tipo` = 'EXTERNO' AND `proveedor_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_recursos_capacidad` CHECK (`capacidad_maxima` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos físicos y flota operativa para ejecución de salidas';

-- ------------------------------------------------------------------------------
-- 4. TABLA `operacion_salidas` (CABECERA DE SALIDAS OPERATIVAS DE CAMPO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salidas`;
CREATE TABLE `operacion_salidas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organizacion_id` INT UNSIGNED NOT NULL,
    `edicion_id` INT UNSIGNED NOT NULL,
    `item_comercial_id` INT UNSIGNED NOT NULL,
    `correlativo` VARCHAR(35) NOT NULL COMMENT 'Identificador institucional (ej. SAL-2026-000001)',
    `titulo` VARCHAR(150) NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `hora_citacion` TIME NOT NULL,
    `hora_salida` TIME NOT NULL,
    `hora_inicio_real` DATETIME DEFAULT NULL,
    `hora_fin_real` DATETIME DEFAULT NULL,
    `punto_encuentro` VARCHAR(255) NOT NULL,
    `tipo_capacidad` ENUM('SIN_CONTROL', 'COLECTIVA', 'DISCRETA', 'EXCLUSIVA') NOT NULL,
    `capacidad_maxima` INT UNSIGNED DEFAULT NULL,
    `estado` ENUM('PROGRAMADA', 'EN_CHECKIN', 'DESPACHADA', 'FINALIZADA', 'INTERRUMPIDA', 'CANCELADA') NOT NULL DEFAULT 'PROGRAMADA',
    `motivo_cancelacion_interrupcion` TEXT DEFAULT NULL,
    `version_bloqueo` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por` INT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`edicion_id`) REFERENCES `ediciones_candelaria` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`item_comercial_id`) REFERENCES `items_comerciales` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_salidas_correlativo` (`organizacion_id`, `correlativo`),
    KEY `idx_salidas_fecha_estado` (`organizacion_id`, `edicion_id`, `fecha_salida`, `estado`),
    CONSTRAINT `chk_salidas_capacidad` CHECK (
        (`tipo_capacidad` IN ('COLECTIVA', 'DISCRETA') AND `capacidad_maxima` IS NOT NULL AND `capacidad_maxima` > 0)
        OR
        (`tipo_capacidad` IN ('SIN_CONTROL', 'EXCLUSIVA'))
    ),
    CONSTRAINT `chk_salidas_horas` CHECK (`hora_citacion` <= `hora_salida`),
    CONSTRAINT `chk_salidas_cancelacion` CHECK (
        (`estado` NOT IN ('CANCELADA', 'INTERRUMPIDA') AND `motivo_cancelacion_interrupcion` IS NULL)
        OR
        (`estado` IN ('CANCELADA', 'INTERRUMPIDA') AND `motivo_cancelacion_interrupcion` IS NOT NULL AND CHAR_LENGTH(TRIM(`motivo_cancelacion_interrupcion`)) > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de salidas operativas de campo';

-- ------------------------------------------------------------------------------
-- 5. TABLA `operacion_salida_recursos` (ASIGNACIÓN DE PERSONAL Y RECURSOS FÍSICOS)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salida_recursos`;
CREATE TABLE `operacion_salida_recursos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `recurso_fisico_id` INT UNSIGNED DEFAULT NULL,
    `persona_id` INT UNSIGNED DEFAULT NULL,
    `rol_operativo` ENUM('GUIA_PRINCIPAL', 'GUIA_ASISTENTE', 'CONDUCTOR', 'PATRON_LANCHA', 'COORDINADOR_CAMPO', 'EQUIPO_LOGISTICO') NOT NULL,
    `notas` VARCHAR(255) DEFAULT NULL,
    `asignado_por` INT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`recurso_fisico_id`) REFERENCES `operacion_recursos` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_salida_recursos_salida` (`salida_id`),
    KEY `idx_salida_recursos_persona` (`persona_id`),
    KEY `idx_salida_recursos_recurso` (`recurso_fisico_id`),
    CONSTRAINT `chk_salida_recursos_objetivo` CHECK (
        `recurso_fisico_id` IS NOT NULL OR `persona_id` IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recursos físicos y personal asignados a una salida operativa';

-- ------------------------------------------------------------------------------
-- 6. TABLA `operacion_salida_prestaciones` (AGRUPACIÓN 1:N DE PRESTACIONES EN SALIDA)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_salida_prestaciones`;
CREATE TABLE `operacion_salida_prestaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_pasajeros` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `asignado_por` INT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_salida_prestacion` (`salida_id`, `prestacion_id`),
    KEY `idx_salida_prestacion_prest` (`prestacion_id`),
    CONSTRAINT `chk_salida_prestacion_cantidad` CHECK (`cantidad_pasajeros` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agrupación de prestaciones de reserva asignadas a una salida';

-- ------------------------------------------------------------------------------
-- 7. TABLA `operacion_asistencias` (CHECK-IN INDIVIDUALIZADO Y CONTROL DE EMBARQUE)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_asistencias`;
CREATE TABLE `operacion_asistencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `prestacion_id` BIGINT UNSIGNED NOT NULL,
    `participante_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_asiento` VARCHAR(20) DEFAULT NULL,
    `estado_asistencia` ENUM('PENDIENTE', 'PRESENTE', 'NO_SHOW') NOT NULL DEFAULT 'PENDIENTE',
    `marcado_en` DATETIME DEFAULT NULL,
    `marcado_por` INT UNSIGNED DEFAULT NULL,
    `observacion` VARCHAR(255) DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`prestacion_id`) REFERENCES `reserva_prestaciones` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`participante_id`) REFERENCES `reserva_participantes` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`marcado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    UNIQUE KEY `uk_asistencias_salida_participante` (`salida_id`, `participante_id`),
    UNIQUE KEY `uk_asistencias_salida_asiento` (`salida_id`, `ubicacion_asiento`),
    KEY `idx_asistencias_prestacion` (`prestacion_id`),
    KEY `idx_asistencias_estado` (`salida_id`, `estado_asistencia`),
    CONSTRAINT `chk_asistencia_marcado` CHECK (
        (`estado_asistencia` = 'PENDIENTE' AND `marcado_en` IS NULL AND `marcado_por` IS NULL)
        OR
        (`estado_asistencia` IN ('PRESENTE', 'NO_SHOW') AND `marcado_en` IS NOT NULL AND `marcado_por` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control individual de asistencia y check-in por participante en salida';

-- ------------------------------------------------------------------------------
-- 8. TABLA `operacion_incidencias` (BITÁCORA ESTRUCTURADA DE INCIDENCIAS DE CAMPO)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `operacion_incidencias`;
CREATE TABLE `operacion_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `salida_id` BIGINT UNSIGNED NOT NULL,
    `tipo_incidencia` ENUM('CLIMA_FUERZA_MAYOR', 'FALLA_MECANICA_VEHICULO', 'DEMORA_TRANSPORTE', 'ACCIDENTE_SALUD', 'QUEJA_CLIENTE', 'CIERRE_VIAS_PUERTOS', 'OTRO') NOT NULL,
    `descripcion` TEXT NOT NULL,
    `acciones_tomadas` TEXT DEFAULT NULL,
    `afecto_continuidad` TINYINT(1) NOT NULL DEFAULT 0,
    `registrado_por` INT UNSIGNED NOT NULL,
    `registrado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`salida_id`) REFERENCES `operacion_salidas` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,

    KEY `idx_incidencias_salida` (`salida_id`),
    KEY `idx_incidencias_tipo` (`tipo_incidencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora inmutable de incidencias operativas ocurridas en salidas';

-- ------------------------------------------------------------------------------
-- 9. MÓDULO 23 Y PERMISOS RBAC DEL MÓDULO OPERACIONES (12 PERMISOS CANÓNICOS)
-- ------------------------------------------------------------------------------
INSERT INTO `modulos` (`id`, `codigo`, `nombre`, `descripcion`, `icono_fontawesome`, `orden`, `es_nucleo`, `estado`) VALUES
(23, 'operaciones', 'OPERACIONES Y SALIDAS', 'Salidas de campo, manifiestos de embarque, asignación de guías, transporte y check-in', 'fa-solid fa-person-hiking', 8, 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(60, 23, 'operacion.ver', 'Ver Operaciones', 'Permite consultar listados, hojas de ruta y manifiestos de salidas operativas'),
(61, 23, 'operacion.gestionar_salidas', 'Gestionar Salidas', 'Permite crear, programar, modificar y cancelar salidas operativas de campo'),
(62, 23, 'operacion.asignar_prestaciones', 'Asignar Prestaciones', 'Permite incorporar y desasignar prestaciones de reservas a una salida'),
(63, 23, 'operacion.asignar_recursos', 'Asignar Recursos Operativos', 'Permite asignar personal operativo (guías, choferes) y vehículos/lanchas a una salida'),
(64, 23, 'operacion.checkin', 'Gestionar Check-in y Asistencia', 'Permite registrar la presencia, abordaje o no-show de participantes'),
(65, 23, 'operacion.ejecutar', 'Ejecutar Salida', 'Permite despachar, iniciar, interrumpir y finalizar la ejecución física de la salida'),
(66, 23, 'operacion.registrar_incidencias', 'Registrar Incidencias', 'Permite asentar reportes estructurados de incidencias durante la salida'),
(67, 23, 'proveedores.ver', 'Ver Proveedores', 'Permite consultar el catálogo maestro de proveedores operativos'),
(68, 23, 'proveedores.gestionar', 'Gestionar Proveedores', 'Permite registrar, actualizar y suspender proveedores operativos'),
(69, 23, 'recursos.ver', 'Ver Recursos Físicos', 'Permite consultar la flota vehicular, lacustre y equipo logístico'),
(70, 23, 'recursos.gestionar', 'Gestionar Recursos Físicos', 'Permite registrar y gestionar flota física y recursos operativos'),
(71, 23, 'entregas.despachar', 'Despachar Entregas', 'Permite gestionar el despacho y entrega de órdenes de productos físicos')
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`), `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Superadministrador de Plataforma (acceso total)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 60), (1, 61), (1, 62), (1, 63), (1, 64), (1, 65), (1, 66), (1, 67), (1, 68), (1, 69), (1, 70), (1, 71);

-- Administrador de Organización (gestión completa en su tenant)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 60), (2, 61), (2, 62), (2, 63), (2, 64), (2, 65), (2, 66), (2, 67), (2, 68), (2, 69), (2, 70), (2, 71);

-- Operador de Campo / Producción (consulta, check-in, ejecución, incidencias, recursos y despacho)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(3, 60), (3, 64), (3, 65), (3, 66), (3, 67), (3, 69), (3, 71);

-- Vendedor / Comercial (consulta de salidas, recursos y proveedores)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(4, 60), (4, 67), (4, 69);

-- ------------------------------------------------------------------------------
-- 10. REGISTRO EN CONTROL DE MIGRACIONES (LOTE 13)
-- ------------------------------------------------------------------------------
INSERT INTO `migraciones_control` (`migracion`, `lote`) VALUES
('2026_10_07_000015_crear_modulo_operaciones_y_recursos_rbac.sql', 13)
ON DUPLICATE KEY UPDATE `ejecutado_en` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;

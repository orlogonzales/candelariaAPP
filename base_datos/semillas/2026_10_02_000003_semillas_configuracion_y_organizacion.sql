-- ==============================================================================
-- SEMILLA: 2026_10_02_000003_semillas_configuracion_y_organizacion.sql
-- DESCRIPCIÓN: Datos iniciales de configuración y organización:
--              1. Perfil institucional de la organización 10000 (O.G. ESTUDIO CREATIVO)
--              2. Permisos RBAC para Gestión de Organización y Configuración (Módulos 17 y 18)
--              3. Asignación rigurosa de privilegios a roles (sin bypass de plataforma)
--              4. Parámetros gobernados y tipados iniciales para PLATAFORMA y ORGANIZACIÓN
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. PERFIL INSTITUCIONAL OFICIAL DE LA ORGANIZACIÓN 10000
-- ------------------------------------------------------------------------------
UPDATE `organizaciones`
SET
    `tipo_documento_id` = 2,
    `numero_documento`  = '20601234567',
    `razon_social`      = 'O.G. ESTUDIO CREATIVO S.A.C.',
    `nombre_comercial`  = 'O.G. ESTUDIO CREATIVO',
    `direccion`         = 'JR. LIMA 450, INTERIOR 2B',
    `codigo_pais`       = 'PE',
    `departamento`      = 'PUNO',
    `provincia`         = 'PUNO',
    `distrito`          = 'PUNO',
    `telefono_contacto` = '+51951000000',
    `telefono_whatsapp` = '+51951234567',
    `correo_contacto`   = 'contacto@ogestudiocreativo.com',
    `sitio_web`         = 'https://ogestudiocreativo.com',
    `contacto_nombre`   = 'ORLANDO GONZALES',
    `contacto_cargo`    = 'DIRECTOR GENERAL',
    `estado`            = 'ACTIVO'
WHERE `id` = 10000;

-- ------------------------------------------------------------------------------
-- 2. PERMISOS ATÓMICOS PARA CONFIGURACIÓN Y ORGANIZACIÓN
-- ------------------------------------------------------------------------------
-- Módulo 17: CONFIGURACIÓN DE ORGANIZACIÓN
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(7, 17, 'organizacion.ver', 'Ver Datos de Organización', 'Permite consultar el perfil institucional, datos fiscales y de contacto'),
(8, 17, 'organizacion.editar', 'Editar Organización', 'Permite modificar los datos fiscales, ubicación y contacto de la empresa'),
(9, 17, 'branding.editar', 'Editar Branding', 'Permite actualizar logotipos, isotipos y elementos de marca'),
(10, 17, 'configuracion_organizacion.ver', 'Ver Configuración de Organización', 'Permite consultar los parámetros operativos del tenant'),
(11, 17, 'configuracion_organizacion.editar', 'Editar Configuración de Organización', 'Permite modificar los parámetros operativos del tenant')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Módulo 18: ADMINISTRACIÓN DE PLATAFORMA (Soberano)
INSERT INTO `permisos` (`id`, `modulo_id`, `codigo`, `nombre`, `descripcion`) VALUES
(12, 18, 'configuracion_plataforma.ver', 'Ver Configuración de Plataforma', 'Permite consultar parámetros soberanos del núcleo del sistema'),
(13, 18, 'configuracion_plataforma.editar', 'Editar Configuración de Plataforma', 'Permite modificar parámetros soberanos y límites del sistema')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ------------------------------------------------------------------------------
-- 3. ASIGNACIÓN RIGUROSA DE PERMISOS A ROLES DE SISTEMA
-- ------------------------------------------------------------------------------
-- Rol 1: SUPERADMINISTRADOR DE PLATAFORMA (Acceso pleno a ambos ámbitos)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(1, 7), (1, 8), (1, 9), (1, 10), (1, 11), (1, 12), (1, 13);

-- Rol 2: ADMINISTRADOR DE ORGANIZACIÓN (Ámbito estricto de Organización; NUNCA Plataforma)
INSERT IGNORE INTO `rol_permisos` (`rol_id`, `permiso_id`) VALUES
(2, 7), (2, 8), (2, 9), (2, 10), (2, 11);

-- ------------------------------------------------------------------------------
-- 4. PARÁMETROS GOBERNADOS Y TIPADOS INICIALES
-- ------------------------------------------------------------------------------
-- Ámbito A: PLATAFORMA (organizacion_id = NULL)
INSERT INTO `parametros_configuracion` (
    `organizacion_id`, `ambito`, `codigo`, `tipo_dato`, `valor`, `valor_defecto`,
    `etiqueta`, `descripcion`, `reglas_validacion_json`, `es_sistema`, `es_publico`, `es_editable`, `estado`
) VALUES
(
    NULL, 'PLATAFORMA', 'plataforma.monto_minimo_pago_pe', 'DECIMAL', '50.00', '50.00',
    'Monto Mínimo de Pago', 'Monto mínimo admitido para pagos y transacciones en soles (PEN)',
    '{"min": 1.0, "max": 10000.0, "precision": 2}', 1, 1, 1, 'ACTIVO'
),
(
    NULL, 'PLATAFORMA', 'plataforma.zona_horaria', 'STRING', 'America/Lima', 'America/Lima',
    'Zona Horaria Oficial', 'Huso horario oficial para cálculo de fechas y timestamps',
    '{"opciones": ["America/Lima", "UTC"]}', 1, 1, 1, 'ACTIVO'
),
(
    NULL, 'PLATAFORMA', 'plataforma.moneda_principal', 'STRING', 'PEN', 'PEN',
    'Moneda Soberana Principal', 'Código ISO 4217 de la divisa principal de la plataforma',
    '{"longitud_exacta": 3}', 1, 1, 0, 'ACTIVO'
),
(
    NULL, 'PLATAFORMA', 'plataforma.permitir_registro_publico', 'BOOLEAN', '0', '0',
    'Permitir Registro Público', 'Determina si usuarios externos pueden registrarse autónomamente',
    NULL, 1, 0, 1, 'ACTIVO'
),
(
    NULL, 'PLATAFORMA', 'plataforma.max_intentos_login', 'INTEGER', '5', '5',
    'Intentos Máximos de Login', 'Número de fallos consecutivos antes del bloqueo temporal',
    '{"min": 3, "max": 10}', 1, 0, 1, 'ACTIVO'
),
(
    NULL, 'PLATAFORMA', 'plataforma.minutos_bloqueo_login', 'INTEGER', '15', '15',
    'Minutos de Bloqueo por Fallos', 'Duración de la penalización temporal tras superar límite de intentos',
    '{"min": 5, "max": 1440}', 1, 0, 1, 'ACTIVO'
)
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`),
    `etiqueta` = VALUES(`etiqueta`),
    `descripcion` = VALUES(`descripcion`);

-- Ámbito B: ORGANIZACIÓN (organizacion_id = 10000)
INSERT INTO `parametros_configuracion` (
    `organizacion_id`, `ambito`, `codigo`, `tipo_dato`, `valor`, `valor_defecto`,
    `etiqueta`, `descripcion`, `reglas_validacion_json`, `es_sistema`, `es_publico`, `es_editable`, `estado`
) VALUES
(
    10000, 'ORGANIZACION', 'organizacion.notificar_whatsapp', 'BOOLEAN', '1', '1',
    'Notificaciones por WhatsApp', 'Habilita envío de confirmaciones automáticas al cliente por WhatsApp',
    NULL, 0, 0, 1, 'ACTIVO'
),
(
    10000, 'ORGANIZACION', 'organizacion.dias_validez_cotizacion', 'INTEGER', '7', '7',
    'Días de Validez de Cotizaciones', 'Plazo de vigencia para propuestas y cotizaciones comerciales',
    '{"min": 1, "max": 60}', 0, 0, 1, 'ACTIVO'
),
(
    10000, 'ORGANIZACION', 'organizacion.porcentaje_reserva_minimo', 'DECIMAL', '30.00', '30.00',
    'Porcentaje Mínimo de Reserva', 'Porcentaje inicial del contrato requerido para confirmar una reserva',
    '{"min": 10.0, "max": 100.0, "precision": 2}', 0, 1, 1, 'ACTIVO'
)
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`),
    `etiqueta` = VALUES(`etiqueta`),
    `descripcion` = VALUES(`descripcion`);

SET FOREIGN_KEY_CHECKS = 1;

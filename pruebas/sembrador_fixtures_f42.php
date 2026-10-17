<?php
declare(strict_types=1);

function sembrarFixturesCertificacionPositiva(PDO $pdo, string $nombreBdAislada, string $sufijo = '', string $tokenClienteAPlano = '', string $tokenCsrfMaestro = ''): array {
$sufijo = $sufijo !== '' ? $sufijo : strtoupper(bin2hex(random_bytes(3)));
$sufijoMinuscula = strtolower($sufijo);

// 1. Organizaciones
$pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `estado`) VALUES (:cod, 'Candelaria Cert A', 'Org Certificación A SAC', 2, :ruc, 'ACTIVO')")->execute(['cod' => 'org_a_' . $sufijoMinuscula, 'ruc' => '20881111111']);
$orgAId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `estado`) VALUES (:cod, 'Candelaria Cert B', 'Org Certificación B SAC', 2, :ruc, 'ACTIVO')")->execute(['cod' => 'org_b_' . $sufijoMinuscula, 'ruc' => '20882222222']);
$orgBId = (int) $pdo->lastInsertId();

// 1b. Configuración de Plataforma y Organización
$tablasOrigen = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('parametros_configuracion', $tablasOrigen, true)) {
    $pdo->exec("INSERT IGNORE INTO `{$nombreBdAislada}`.`parametros_configuracion` SELECT * FROM `app_candelaria`.`parametros_configuracion` WHERE `organizacion_id` IS NULL");
}
$configsCriticas = [
    ['plataforma.moneda_principal', 'STRING', 'PEN', 'Moneda principal de la plataforma'],
    ['sistema.moneda_defecto', 'STRING', 'PEN', 'Moneda por defecto del sistema'],
    ['ventas.permitir_pago_mixto', 'BOOLEAN', '1', 'Permitir pagos mixtos'],
    ['ventas.porcentaje_igv', 'DECIMAL', '18.00', 'Tasa IGV oficial'],
    ['ventas.dias_validez_cotizacion', 'INTEGER', '15', 'Días de validez para cotizaciones'],
];
foreach ($configsCriticas as [$cod, $tipo, $val, $desc]) {
    $pdo->prepare("INSERT IGNORE INTO `parametros_configuracion` (`organizacion_id`, `ambito`, `codigo`, `etiqueta`, `tipo_dato`, `valor`, `descripcion`) VALUES (:org, 'ORGANIZACION', :cod, :etq, :tipo, :val, :desc)")
        ->execute(['org' => $orgAId, 'cod' => $cod, 'etq' => $desc, 'tipo' => $tipo, 'val' => $val, 'desc' => $desc]);
    $pdo->prepare("INSERT IGNORE INTO `parametros_configuracion` (`organizacion_id`, `ambito`, `codigo`, `etiqueta`, `tipo_dato`, `valor`, `descripcion`) VALUES (NULL, 'GLOBAL', :cod, :etq, :tipo, :val, :desc)")
        ->execute(['cod' => $cod, 'etq' => $desc, 'tipo' => $tipo, 'val' => $val, 'desc' => $desc]);
}

// 2. Ediciones
$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición A 2026', 2026, '2026-02-01', '2026-02-15', 'OPERACION', 1)")->execute(['org' => $orgAId, 'cod' => 'cert-a-2026']);
$edicionAId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición B 2026', 2026, '2026-02-01', '2026-02-15', 'OPERACION', 1)")->execute(['org' => $orgBId, 'cod' => 'cert-b-2026']);
$edicionBId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición Cerrada 2025', 2025, '2025-02-01', '2025-02-15', 'CERRADA', 0)")->execute(['org' => $orgAId, 'cod' => 'cert-cerrada-2025']);
$edicionCerradaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición Para Cerrar 2024', 2024, '2024-02-01', '2024-02-15', 'POSTPRODUCCION_ENTREGA', 0)")->execute(['org' => $orgAId, 'cod' => 'cert-pcierre-2024']);
$edicionParaCerrarId = (int) $pdo->lastInsertId();

// Edición dedicada para cambio de estado
$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición Cambio Estado 2028', 2028, '2028-02-01', '2028-02-15', 'PREOPERACION', 0)")->execute(['org' => $orgAId, 'cod' => 'cert-cambio-estado-2028']);
$edicionCambioEstadoId = (int) $pdo->lastInsertId();

// Edición dedicada para reapertura
$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`, `es_actual`) VALUES (:org, :cod, 'Edición Para Reabrir 2023', 2023, '2023-02-01', '2023-02-15', 'CERRADA', 0)")->execute(['org' => $orgAId, 'cod' => 'cert-reabrir-2023']);
$edicionParaReabrirId = (int) $pdo->lastInsertId();

// 3. Personas y Usuarios
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88111111', 'Auditor', 'Contratos A', :correo, '+51951111111', '+51951111111')")->execute(['org' => $orgAId, 'correo' => "auditor.a.{$sufijo}@candelaria.pe"]);
$personaAId = (int) $pdo->lastInsertId();

$clavePlana = 'CertV12Secret!';
$usrLoginA = "auditor_a_{$sufijo}";
$pdo->prepare("INSERT INTO `usuarios` (`organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `estado`, `es_superadmin_plataforma`) VALUES (:org, :per, :usr, 'Auditor Contratos A', :correo, :hash, 'ACTIVO', 1)")->execute([
    'org'    => $orgAId,
    'per'    => $personaAId,
    'usr'    => $usrLoginA,
    'correo' => "auditor.a.{$sufijo}@candelaria.pe",
    'hash'   => password_hash($clavePlana, PASSWORD_BCRYPT),
]);
$usuarioAId = (int) $pdo->lastInsertId();

$rolSuperAdminId = (int) ($pdo->query("SELECT id FROM roles WHERE codigo = 'super_admin' LIMIT 1")->fetchColumn() ?: 1);
$pdo->prepare("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usr, :rol)")->execute(['usr' => $usuarioAId, 'rol' => $rolSuperAdminId]);

// Token dedicado para endpoint de logout (se generará vía $authSvc)
$tokenLogoutPlano = '';

// Usuario Modificable dedicado
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88333333', 'Usuario', 'Modificable', :correo, '+51951333333', '+51951333333')")->execute(['org' => $orgAId, 'correo' => "modificable.{$sufijo}@candelaria.pe"]);
$personaModificableId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `usuarios` (`organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `estado`, `es_superadmin_plataforma`) VALUES (:org, :per, :usr, 'Usuario Modificable', :correo, :hash, 'ACTIVO', 0)")->execute([
    'org'    => $orgAId,
    'per'    => $personaModificableId,
    'usr'    => "modificable_{$sufijo}",
    'correo' => "modificable.{$sufijo}@candelaria.pe",
    'hash'   => password_hash($clavePlana, PASSWORD_BCRYPT),
]);
$usuarioModificableId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usr, :rol)")->execute(['usr' => $usuarioModificableId, 'rol' => $rolSuperAdminId]);

// Usuario Autorizador independiente (para segregación de funciones SoD)
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88666666', 'Director', 'Autorizador', :correo, '+51951666666', '+51951666666')")->execute(['org' => $orgAId, 'correo' => "autorizador.{$sufijo}@candelaria.pe"]);
$personaAutorizadorId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `usuarios` (`organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `estado`, `es_superadmin_plataforma`) VALUES (:org, :per, :usr, 'Director Autorizador', :correo, :hash, 'ACTIVO', 1)")->execute([
    'org'    => $orgAId,
    'per'    => $personaAutorizadorId,
    'usr'    => "autorizador_{$sufijo}",
    'correo' => "autorizador.{$sufijo}@candelaria.pe",
    'hash'   => password_hash($clavePlana, PASSWORD_BCRYPT),
]);
$usuarioAutorizadorId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usr, :rol)")->execute(['usr' => $usuarioAutorizadorId, 'rol' => $rolSuperAdminId]);

$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88777777', 'Persona', 'Disponible', :correo, '+51951777777', '+51951777777')")->execute(['org' => $orgAId, 'correo' => "disponible.{$sufijo}@candelaria.pe"]);
$personaDisponibleId = (int) $pdo->lastInsertId();

// Usuario sin permisos RBAC en Organización A (para prueba negativa 403)
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88555555', 'Operador', 'Sin Permisos', :correo, '+51951555555', '+51951555555')")->execute(['org' => $orgAId, 'correo' => "sinperm.{$sufijo}@candelaria.pe"]);
$personaSinPermId = (int) $pdo->lastInsertId();

$usrSinPermLogin = "sin_perm_{$sufijo}";
$pdo->prepare("INSERT INTO `usuarios` (`organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `estado`, `es_superadmin_plataforma`) VALUES (:org, :per, :usr, 'Operador Sin Permisos', :correo, :hash, 'ACTIVO', 0)")->execute([
    'org'    => $orgAId,
    'per'    => $personaSinPermId,
    'usr'    => $usrSinPermLogin,
    'correo' => "sinperm.{$sufijo}@candelaria.pe",
    'hash'   => password_hash($clavePlana, PASSWORD_BCRYPT),
]);
$usuarioSinPermId = (int) $pdo->lastInsertId();

$authSvc = new \Aplicacion\Seguridad\AutenticacionServicio(pdo: $pdo);
$resAuthLogout = $authSvc->autenticar($usrLoginA, $clavePlana, '127.0.0.1', 'CLI-Logout');
$tokenLogoutPlano = (string) $resAuthLogout->tokenSesion;

$resAuthSinPerm = $authSvc->autenticar($usrSinPermLogin, $clavePlana, '127.0.0.1', 'CertificadorContratosV12');
$tokenSinPermPlano = (string) $resAuthSinPerm->tokenSesion;

// 4. Clientes y Portal
$pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org, :per, 'CLIENTE', 1)")->execute(['org' => $orgAId, 'per' => $personaAId]);
$clienteAId = (int) $pdo->lastInsertId();

// Cliente B en Organización B (para prueba Anti-IDOR inter-tenant)
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88222222', 'Cliente', 'Ajeno B', :correo, '+51951222222', '+51951222222')")->execute(['org' => $orgBId, 'correo' => "cliente.b.{$sufijo}@candelaria.pe"]);
$personaBId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org, :per, 'CLIENTE', 1)")->execute(['org' => $orgBId, 'per' => $personaBId]);
$clienteBId = (int) $pdo->lastInsertId();

// Cliente C en Organización A (para prueba Anti-IDOR intra-tenant)
$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88888888', 'Cliente', 'Otro Titular C', :correo, '+51951888888', '+51951888888')")->execute(['org' => $orgAId, 'correo' => "cliente.c.{$sufijo}@candelaria.pe"]);
$personaCId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org, :per, 'CLIENTE', 1)")->execute(['org' => $orgAId, 'per' => $personaCId]);
$clienteCId = (int) $pdo->lastInsertId();

// Venta de Cliente C
$pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`,
        `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`,
        `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`,
        `estado_financiero`, `creado_por`
    ) VALUES (
        :org, :ed, :cli, NULL, 'DIRECTA',
        :cod, CURDATE(), 'CONFIRMADA', 'Cliente Otro Titular C',
        'PEN', 500.00, 0.00, 500.00, 400.00, 100.00,
        'PAGO_PARCIAL', :usr
    )
")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cli' => $clienteCId,
    'cod' => 'VTA-IDOR-' . $sufijo,
    'usr' => $usuarioAId,
]);
$ventaAjenaCId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88444444', 'Cliente', 'Modificable', :correo, '+51951444444', '+51951444444')")->execute(['org' => $orgAId, 'correo' => "cli_mod.{$sufijo}@candelaria.pe"]);
$personaCliModId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org, :per, 'PROSPECTO', 1)")->execute(['org' => $orgAId, 'per' => $personaCliModId]);
$clienteModificableId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `personas` (`organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `correo_electronico`, `telefono_movil`, `telefono_whatsapp`) VALUES (:org, 'NATURAL', 1, '88444455', 'Cliente', 'Estado', :correo, '+51951444455', '+51951444455')")->execute(['org' => $orgAId, 'correo' => "cli_est.{$sufijo}@candelaria.pe"]);
$personaCliEstId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `clientes` (`organizacion_id`, `persona_id`, `estado_comercial`, `consentimiento_operativo`) VALUES (:org, :per, 'PROSPECTO', 1)")->execute(['org' => $orgAId, 'per' => $personaCliEstId]);
$clienteCambioEstadoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `consentimientos_cliente` (`organizacion_id`, `cliente_id`, `tipo`, `accion`, `canal`, `actor_tipo`, `usuario_id`, `correlacion_id`) VALUES (:org, :cli, 'OPERATIVO', 'OTORGAR', 'WEB', 'HUMANO', :usr, :corr)")->execute([
    'org'  => $orgAId,
    'cli'  => $clienteAId,
    'usr'  => $usuarioAId,
    'corr' => 'CORR-CONS-' . $sufijo,
]);

$codigoAccesoPortal = '123456';
$pdo->prepare("INSERT INTO `cliente_codigos_acceso` (`organizacion_id`, `cliente_id`, `tipo_acceso`, `codigo_hash`, `destino`, `expira_en`, `utilizado`) VALUES (:org, :cli, 'MAGIC_LINK_EMAIL', :hash, :dst, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)")->execute([
    'org'  => $orgAId,
    'cli'  => $clienteAId,
    'hash' => hash('sha256', $codigoAccesoPortal),
    'dst'  => "auditor.a.{$sufijo}@candelaria.pe",
]);

$tokenClienteAPlano = $tokenClienteAPlano !== '' ? $tokenClienteAPlano : ('cli_ses_' . bin2hex(random_bytes(24)));
$pdo->prepare("INSERT INTO `cliente_sesiones` (`organizacion_id`, `cliente_id`, `token_sesion_hash`, `ip_creacion`, `user_agent`, `expira_en`, `revocado`) VALUES (:org, :cli, :hash, '127.0.0.1', 'CertificadorContratosV12', DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)")->execute([
    'org'  => $orgAId,
    'cli'  => $clienteAId,
    'hash' => hash('sha256', $tokenClienteAPlano),
]);
$clienteSesionAId = (int) $pdo->lastInsertId();

// 5. CRM
$pdo->prepare("INSERT INTO `origenes_comerciales` (`organizacion_id`, `codigo`, `nombre`, `activo`) VALUES (:org, :cod, 'Web Oficial', 1)")->execute(['org' => $orgAId, 'cod' => 'WEB_OFICIAL_' . $sufijo]);
$origenId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `crm_oportunidades` (`organizacion_id`, `edicion_id`, `cliente_id`, `origen_comercial_id`, `usuario_asignado_id`, `titulo`, `etapa`, `valor_estimado`, `moneda`) VALUES (:org, :ed, :cli, :ori, :usr, 'Oportunidad Paquete VIP', 'NUEVA', 1200.00, 'PEN')")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cli' => $clienteAId,
    'ori' => $origenId,
    'usr' => $usuarioAId,
]);
$oportunidadId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `crm_interacciones` (`organizacion_id`, `oportunidad_id`, `cliente_id`, `canal_id`, `tipo`, `direccion`, `resumen`, `detalle`, `actor_tipo`, `usuario_id`, `correlacion_id`) VALUES (:org, :op, :cli, 1, 'LLAMADA', 'SALIENTE', 'Llamada de asesoría', 'El cliente confirma interés en el paquete', 'HUMANO', :usr, :corr)")->execute([
    'org'  => $orgAId,
    'op'   => $oportunidadId,
    'cli'  => $clienteAId,
    'usr'  => $usuarioAId,
    'corr' => 'CORR-CRM-' . $sufijo,
]);
$interaccionId = (int) $pdo->lastInsertId();

// 6. Catálogo
$pdo->prepare("INSERT INTO `categorias_items` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `orden`, `estado`) VALUES (:org, :cod, 'Asientos Gradería', 'Categoría de graderías y butacas', 1, 'ACTIVO')")->execute(['org' => $orgAId, 'cod' => 'CAT_GRAD_' . $sufijo]);
$categoriaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `items_comerciales` (`organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `descripcion`, `estado`) VALUES (:org, :cat, :cod, 'Butaca Preferencial Sector A', 'PRODUCTO', 'UNIDAD', 'Asiento numerado', 'ACTIVO')")->execute([
    'org' => $orgAId,
    'cat' => $categoriaId,
    'cod' => 'ITM_BUTACA_' . $sufijo,
]);
$itemId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `item_configuracion_operativa` (`item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`, `tipo_capacidad`, `punto_partida_predeterminado`) VALUES (:itm, 1, 1, 1, 'COLECTIVA', 'Plaza de Armas')")->execute(['itm' => $itemId]);

// Item y Paquete disponibles para ofertar (POST /catalogo/ofertas/items y /paquetes)
$pdo->prepare("INSERT INTO `items_comerciales` (`organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `descripcion`, `estado`) VALUES (:org, :cat, :cod, 'Item Para Ofertar', 'PRODUCTO', 'UNIDAD', 'Item sin oferta', 'ACTIVO')")->execute([
    'org' => $orgAId, 'cat' => $categoriaId, 'cod' => 'ITM_OFR_' . $sufijo,
]);
$itemParaOfertarId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `item_configuracion_operativa` (`item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`, `tipo_capacidad`, `punto_partida_predeterminado`) VALUES (:itm, 1, 1, 1, 'COLECTIVA', 'Plaza de Armas')")->execute(['itm' => $itemParaOfertarId]);

$pdo->prepare("INSERT INTO `paquetes` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `estado`) VALUES (:org, :cod, 'Paquete Para Ofertar', 'Paquete sin oferta', 'ACTIVO')")->execute([
    'org' => $orgAId, 'cod' => 'PK_OFR_' . $sufijo,
]);
$paqueteParaOfertarId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`) VALUES (:pk, :itm, 1.00, 1)")->execute(['pk' => $paqueteParaOfertarId, 'itm' => $itemId]);

// Item y Paquete con oferta PERO sin tarifa asignada (POST /catalogo/tarifas/items/inicial y /paquetes/inicial)
$pdo->prepare("INSERT INTO `items_comerciales` (`organizacion_id`, `categoria_id`, `codigo`, `nombre`, `tipo`, `unidad_medida`, `descripcion`, `estado`) VALUES (:org, :cat, :cod, 'Item Sin Tarifa', 'PRODUCTO', 'UNIDAD', 'Item con oferta sin tarifa', 'ACTIVO')")->execute([
    'org' => $orgAId, 'cat' => $categoriaId, 'cod' => 'ITM_SNT_' . $sufijo,
]);
$itemSinTarifaId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `item_configuracion_operativa` (`item_comercial_id`, `requiere_reserva`, `requiere_agendamiento`, `requiere_participantes`, `tipo_capacidad`, `punto_partida_predeterminado`) VALUES (:itm, 1, 1, 1, 'COLECTIVA', 'Plaza de Armas')")->execute(['itm' => $itemSinTarifaId]);

$pdo->prepare("INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (:org, :ed, :itm, 'ACTIVO')")->execute(['org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemSinTarifaId]);
$ofertaItemSinTarifaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `paquetes` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `estado`) VALUES (:org, :cod, 'Paquete Sin Tarifa', 'Paquete con oferta sin tarifa', 'ACTIVO')")->execute([
    'org' => $orgAId, 'cod' => 'PK_SNT_' . $sufijo,
]);
$paqueteSinTarifaId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`) VALUES (:pk, :itm, 1.00, 1)")->execute(['pk' => $paqueteSinTarifaId, 'itm' => $itemId]);

$pdo->prepare("INSERT INTO `ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (:org, :ed, :pk, 'ACTIVO')")->execute(['org' => $orgAId, 'ed' => $edicionAId, 'pk' => $paqueteSinTarifaId]);
$ofertaPaqueteSinTarifaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `paquetes` (`organizacion_id`, `codigo`, `nombre`, `descripcion`, `estado`) VALUES (:org, :cod, 'Paquete Candelaria Total VIP', 'Incluye butaca y transporte', 'ACTIVO')")->execute(['org' => $orgAId, 'cod' => 'PK_VIP_' . $sufijo]);
$paqueteId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `paquete_items` (`paquete_id`, `item_comercial_id`, `cantidad`, `orden`) VALUES (:pk, :itm, 1.00, 1)")->execute(['pk' => $paqueteId, 'itm' => $itemId]);

$pdo->prepare("INSERT INTO `ofertas_items_edicion` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `estado`) VALUES (:org, :ed, :itm, 'ACTIVO')")->execute(['org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId]);
$ofertaItemId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `tarifas_items_edicion` (`oferta_item_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (:of, 'PEN', 250.00, 1)")->execute(['of' => $ofertaItemId]);
$tarifaItemId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `ofertas_paquetes_edicion` (`organizacion_id`, `edicion_id`, `paquete_id`, `estado`) VALUES (:org, :ed, :pk, 'ACTIVO')")->execute(['org' => $orgAId, 'ed' => $edicionAId, 'pk' => $paqueteId]);
$ofertaPaqueteId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `tarifas_paquetes_edicion` (`oferta_paquete_id`, `moneda`, `precio`, `version_bloqueo`) VALUES (:of, 'PEN', 450.00, 1)")->execute(['of' => $ofertaPaqueteId]);
$tarifaPaqueteId = (int) $pdo->lastInsertId();

// 7. Cotizaciones
$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `titulo`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, 'Cotización Butacas VIP', 1, 'BORRADOR', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cli' => $clienteAId,
    'op'  => $oportunidadId,
    'usr' => $usuarioAId,
]);
$cotizacionBorradorId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA', 'Butaca Sector A', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'cot' => $cotizacionBorradorId,
    'itm' => $itemId,
    'of'  => $ofertaItemId,
]);
$cotizacionLineaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA_DEL', 'Butaca Sector A Del', 'Asiento numerado', 'UNIDAD', 1.00, 250.00, 250.00, 'PEN', 2)")->execute([
    'cot' => $cotizacionBorradorId,
    'itm' => $itemId,
    'of'  => $ofertaItemId,
]);
$cotizacionLineaEliminarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `titulo`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, 'Cotización Para Emitir', 1, 'BORRADOR', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId,
]);
$cotizacionParaEmitirId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA_PEMI', 'Butaca Sector A Para Emitir', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'cot' => $cotizacionParaEmitirId, 'itm' => $itemId, 'of' => $ofertaItemId,
]);

$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Emitida Butacas VIP', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'cli'  => $clienteAId,
    'op'   => $oportunidadId,
    'usr'  => $usuarioAId,
    'corr' => 'COT-EMI-' . $sufijo,
]);
$cotizacionEmitidaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA_EMI', 'Butaca Sector A Emitida', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'cot' => $cotizacionEmitidaId,
    'itm' => $itemId,
    'of'  => $ofertaItemId,
]);

// Cotizaciones dedicadas para acciones mutadoras
$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Para Rechazar', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId, 'corr' => 'COT-RECH-' . $sufijo,
]);
$cotizacionParaRechazarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Para Anular', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId, 'corr' => 'COT-ANUL-' . $sufijo,
]);
$cotizacionParaAnularId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Para Aceptar', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId, 'corr' => 'COT-ACPT-' . $sufijo,
]);
$cotizacionParaAceptarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Para Revisión', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId, 'corr' => 'COT-REVI-' . $sufijo,
]);
$cotizacionParaRevisionId = (int) $pdo->lastInsertId();

// Cotización inmutable para documentos y PDF
$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Documental VIP', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'EMITIDA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'op' => $oportunidadId, 'usr' => $usuarioAId, 'corr' => 'COT-DOC-' . $sufijo,
]);
$cotizacionDocId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA_DOC', 'Butaca Sector A Doc', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'cot' => $cotizacionDocId, 'itm' => $itemId, 'of' => $ofertaItemId,
]);

// Cotización aceptada para crear venta desde cotización
$pdo->prepare("INSERT INTO `cotizaciones` (`organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`, `creado_por`, `correlativo`, `titulo`, `fecha_emision`, `valido_hasta`, `version_numero`, `estado`, `moneda`, `subtotal`, `descuento_global_monto`, `total`) VALUES (:org, :ed, :cli, :op, :usr, :corr, 'Cotización Aceptada Butacas VIP', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'ACEPTADA', 'PEN', 500.00, 0.00, 500.00)")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'cli'  => $clienteAId,
    'op'   => $oportunidadId,
    'usr'  => $usuarioAId,
    'corr' => 'COT-ACEP-' . $sufijo,
]);
$cotizacionAceptadaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cotizacion_lineas` (`cotizacion_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:cot, 'ITEM', :itm, :of, 'ITM_BUTACA_ACEP', 'Butaca Sector A Aceptada', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'cot' => $cotizacionAceptadaId,
    'itm' => $itemId,
    'of'  => $ofertaItemId,
]);

// 8. Ventas, Pagos, Contratos, Recibos
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'COTIZACION', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 500.00, 0.00, 500.00, 500.00, 0.00, 'PAGADA_TOTAL', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cli' => $clienteAId,
    'cot' => $cotizacionEmitidaId,
    'cod' => 'VTA-CERT-' . $sufijo,
    'usr' => $usuarioAId,
]);
$ventaAId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `venta_lineas` (`venta_id`, `cotizacion_linea_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:vta, :cotl, 'ITEM', :itm, :of, 'ITM_BUTACA_VTA', 'Butaca Sector A Venta', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'vta'  => $ventaAId,
    'cotl' => $cotizacionLineaId,
    'itm'  => $itemId,
    'of'   => $ofertaItemId,
]);
$ventaLineaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `cuentas_bancarias_organizacion` (`organizacion_id`, `banco_nombre`, `tipo_cuenta`, `moneda`, `titular_nombre`, `numero_cuenta`, `activo`) VALUES (:org, 'BCP', 'CORRIENTE', 'PEN', 'Candelaria SAC', '191-88888888-0-99', 1)")->execute(['org' => $orgAId]);
$cuentaBancariaId = (int) $pdo->lastInsertId();

// Ventas dedicadas para cancelar y anular
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'DIRECTA', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 500.00, 0.00, 500.00, 0.00, 500.00, 'NO_PAGADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'cot' => null, 'cod' => 'VTA-CANC-' . $sufijo, 'usr' => $usuarioAId,
]);
$ventaParaCancelarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'DIRECTA', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 500.00, 0.00, 500.00, 0.00, 500.00, 'NO_PAGADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'cot' => null, 'cod' => 'VTA-ANUL-' . $sufijo, 'usr' => $usuarioAId,
]);
$ventaParaAnularId = (int) $pdo->lastInsertId();

// Venta dedicada para liquidar (POST /ventas/{id}/liquidar)
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'DIRECTA', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 500.00, 0.00, 500.00, 500.00, 0.00, 'PAGADA_TOTAL', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'cot' => null, 'cod' => 'VTA-LIQ-' . $sufijo, 'usr' => $usuarioAId,
]);
$ventaParaLiquidarId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `venta_lineas` (`venta_id`, `cotizacion_linea_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:vta, null, 'ITEM', :itm, :of, 'ITM_BUTACA_LIQ', 'Butaca Sector A Liq', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'vta'  => $ventaParaLiquidarId, 'itm' => $itemId, 'of' => $ofertaItemId,
]);

// Venta disponible sin reserva previa (POST /reservas/desde-venta/{ventaId})
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'DIRECTA', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 250.00, 0.00, 250.00, 250.00, 0.00, 'PAGADA_TOTAL', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'cot' => null, 'cod' => 'VTA-SNRSV-' . $sufijo, 'usr' => $usuarioAId,
]);
$ventaSinReservaId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO `venta_lineas` (`venta_id`, `cotizacion_linea_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:vta, null, 'ITEM', :itm, :of, 'ITM_BUTACA_SNRSV', 'Butaca Sector A SnRsv', 'Asiento numerado', 'UNIDAD', 1.00, 250.00, 250.00, 'PEN', 1)")->execute([
    'vta'  => $ventaSinReservaId, 'itm' => $itemId, 'of' => $ofertaItemId,
]);

// Venta inmutable para documentos y PDF
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, :cot, 'COTIZACION', :cod, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 500.00, 0.00, 500.00, 500.00, 0.00, 'PAGADA_TOTAL', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'cli' => $clienteAId, 'cot' => $cotizacionDocId, 'cod' => 'VTA-DOC-' . $sufijo, 'usr' => $usuarioAId,
]);
$ventaDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `venta_lineas` (`venta_id`, `cotizacion_linea_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:vta, :cotl, 'ITEM', :itm, :of, 'ITM_BUTACA_DOCV', 'Butaca Sector A DocV', 'Asiento numerado', 'UNIDAD', 2.00, 250.00, 500.00, 'PEN', 1)")->execute([
    'vta'  => $ventaDocId, 'cotl' => $cotizacionLineaId, 'itm' => $itemId, 'of' => $ofertaItemId,
]);

$pdo->prepare("INSERT INTO `contratos_venta` (`organizacion_id`, `venta_id`, `cliente_id`, `codigo_contrato`, `clausulas_hash`, `estado_contrato`, `emitido_por_usuario_id`) VALUES (:org, :vta, :cli, :cod, :hash, 'EMITIDO', :usr)")->execute([
    'org'  => $orgAId,
    'vta'  => $ventaAId,
    'cli'  => $clienteAId,
    'cod'  => 'CON-2026-' . $sufijo,
    'hash' => hash('sha256', 'clausulas_base_2026'),
    'usr'  => $usuarioAId,
]);
$contratoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `contratos_venta` (`organizacion_id`, `venta_id`, `cliente_id`, `codigo_contrato`, `clausulas_hash`, `estado_contrato`, `emitido_por_usuario_id`) VALUES (:org, :vta, :cli, :cod, :hash, 'EMITIDO', :usr)")->execute([
    'org'  => $orgAId,
    'vta'  => $ventaDocId,
    'cli'  => $clienteAId,
    'cod'  => 'CON-DOC-' . $sufijo,
    'hash' => hash('sha256', 'clausulas_doc_2026'),
    'usr'  => $usuarioAId,
]);
$contratoDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `pagos` (`organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `cuenta_bancaria_id`, `numero_operacion_bancaria`, `verificado_por`, `creado_por`, `version_bloqueo`) VALUES (:org, :ed, :vta, :corr, 'TRANSFERENCIA_BANCARIA', 'APROBADO', 'PEN', 500.00, 500.00, 500.00, NOW(), :cta, 'OP-123456', :verif, :usr, 1)")->execute([
    'org'   => $orgAId,
    'ed'    => $edicionAId,
    'vta'   => $ventaAId,
    'corr'  => 'PAG-' . $sufijo,
    'cta'   => $cuentaBancariaId,
    'verif' => $usuarioAId,
    'usr'   => $usuarioAId,
]);
$pagoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `recibos_caja` (`organizacion_id`, `pago_id`, `venta_id`, `correlativo`, `monto_recibido`, `moneda`, `metodo_pago`, `cajero_usuario_id`, `titular_nombre`, `concepto`) VALUES (:org, :pag, :vta, :corr, 500.00, 'PEN', 'TRANSFERENCIA_BANCARIA', :usr, 'Auditor Contratos A', 'Pago Butacas Candelaria')")->execute([
    'org'  => $orgAId,
    'pag'  => $pagoId,
    'vta'  => $ventaAId,
    'corr' => 'REC-2026-' . $sufijo,
    'usr'  => $usuarioAId,
]);
$reciboId = (int) $pdo->lastInsertId();

// Pagos dedicados: para verificar y para reembolsar
$pdo->prepare("INSERT INTO `pagos` (`organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `cuenta_bancaria_id`, `numero_operacion_bancaria`, `creado_por`, `version_bloqueo`) VALUES (:org, :ed, :vta, :corr, 'TRANSFERENCIA_BANCARIA', 'PENDIENTE_VERIFICACION', 'PEN', 250.00, 250.00, 250.00, NOW(), :cta, 'OP-VERIF-1', :usr, 1)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaAId, 'corr' => 'PAG-VERIF-' . $sufijo, 'cta' => $cuentaBancariaId, 'usr' => $usuarioAId,
]);
$pagoParaVerificarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `pagos` (`organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `cuenta_bancaria_id`, `numero_operacion_bancaria`, `verificado_por`, `creado_por`, `version_bloqueo`) VALUES (:org, :ed, :vta, :corr, 'TRANSFERENCIA_BANCARIA', 'APROBADO', 'PEN', 200.00, 200.00, 200.00, NOW(), :cta, 'OP-REEMB-1', :verif, :usr, 1)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaAId, 'corr' => 'PAG-REEM-' . $sufijo, 'cta' => $cuentaBancariaId, 'verif' => $usuarioAId, 'usr' => $usuarioAId,
]);
$pagoParaReembolsarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `pagos` (`organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `cuenta_bancaria_id`, `numero_operacion_bancaria`, `verificado_por`, `creado_por`, `version_bloqueo`) VALUES (:org, :ed, :vta, :corr, 'TRANSFERENCIA_BANCARIA', 'APROBADO', 'PEN', 500.00, 500.00, 500.00, NOW(), :cta, 'OP-LIQ-1', :verif, :usr, 1)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaParaLiquidarId, 'corr' => 'PAG-LIQ-' . $sufijo, 'cta' => $cuentaBancariaId, 'verif' => $usuarioAId, 'usr' => $usuarioAId,
]);

$pdo->prepare("INSERT INTO `pagos` (`organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`, `fecha_pago`, `cuenta_bancaria_id`, `numero_operacion_bancaria`, `verificado_por`, `creado_por`, `version_bloqueo`) VALUES (:org, :ed, :vta, :corr, 'TRANSFERENCIA_BANCARIA', 'APROBADO', 'PEN', 500.00, 500.00, 500.00, NOW(), :cta, 'OP-DOC-1', :verif, :usr, 1)")->execute([
    'org'  => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaDocId, 'corr' => 'PAG-DOC-' . $sufijo, 'cta' => $cuentaBancariaId, 'verif' => $usuarioAId, 'usr' => $usuarioAId,
]);
$pagoDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `recibos_caja` (`organizacion_id`, `pago_id`, `venta_id`, `correlativo`, `monto_recibido`, `moneda`, `metodo_pago`, `cajero_usuario_id`, `titular_nombre`, `concepto`) VALUES (:org, :pag, :vta, :corr, 500.00, 'PEN', 'TRANSFERENCIA_BANCARIA', :usr, 'Auditor Contratos A', 'Pago Butacas Candelaria Doc')")->execute([
    'org'  => $orgAId, 'pag' => $pagoDocId, 'vta' => $ventaDocId, 'corr' => 'REC-DOC-' . $sufijo, 'usr' => $usuarioAId,
]);
$reciboDocId = (int) $pdo->lastInsertId();

// 9. Reservas
$pdo->prepare("INSERT INTO `reservas` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `contacto_numero_documento`, `contacto_telefono`, `contacto_email`, `creado_por`) VALUES (:org, :ed, :vta, :cli, :cod, 'CONFIRMADA', 'Auditor Contratos A', '88111111', '+51951111111', :correo, :usr)")->execute([
    'org'    => $orgAId,
    'ed'     => $edicionAId,
    'vta'    => $ventaAId,
    'cli'    => $clienteAId,
    'cod'    => 'RSV-' . $sufijo,
    'correo' => "auditor.a.{$sufijo}@candelaria.pe",
    'usr'    => $usuarioAId,
]);
$reservaAId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reservas` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `contacto_numero_documento`, `contacto_telefono`, `contacto_email`, `creado_por`) VALUES (:org, :ed, :vta, :cli, :cod, 'CONFIRMADA', 'Auditor Contratos A', '88111111', '+51951111111', :correo, :usr)")->execute([
    'org'    => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaParaCancelarId, 'cli' => $clienteAId, 'cod' => 'RSV-CANC-' . $sufijo, 'correo' => "auditor.a.{$sufijo}@candelaria.pe", 'usr' => $usuarioAId,
]);
$reservaParaCancelarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reservas` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `contacto_numero_documento`, `contacto_telefono`, `contacto_email`, `creado_por`) VALUES (:org, :ed, :vta, :cli, :cod, 'CONFIRMADA', 'Auditor Contratos A', '88111111', '+51951111111', :correo, :usr)")->execute([
    'org'    => $orgAId, 'ed' => $edicionAId, 'vta' => $ventaDocId, 'cli' => $clienteAId, 'cod' => 'RSV-DOC-' . $sufijo, 'correo' => "auditor.a.{$sufijo}@candelaria.pe", 'usr' => $usuarioAId,
]);
$reservaDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_participantes` (`reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `es_titular_reserva`) VALUES (:rsv, 1, '88111111', 'Auditor', 'Contratos A', 1)")->execute([
    'rsv' => $reservaAId,
]);
$participanteId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_participantes` (`reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `es_titular_reserva`) VALUES (:rsv, 1, '88111111', 'Auditor', 'Contratos A', 1)")->execute([
    'rsv' => $reservaDocId,
]);
$participanteDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_participantes` (`reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `es_titular_reserva`) VALUES (:rsv, 1, '88998899', 'Auditor', 'Eliminable', 0)")->execute([
    'rsv' => $reservaDocId,
]);
$participanteEliminarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_prestaciones` (`reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`, `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`, `fecha_servicio`, `hora_servicio`, `punto_encuentro`, `version_bloqueo`) VALUES (:rsv, :vl, :itm, 'ITM_BUTACA', 'Butaca Sector A', 'UNIDAD', 1.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-05', '08:00:00', 'Plaza de Armas', 1)")->execute([
    'rsv' => $reservaAId,
    'vl'  => $ventaLineaId,
    'itm' => $itemId,
]);
$prestacionId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_prestaciones` (`reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`, `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`, `fecha_servicio`, `hora_servicio`, `punto_encuentro`, `version_bloqueo`) VALUES (:rsv, :vl, :itm, 'ITM_BUTACA_PRG', 'Butaca Sector A Prog', 'UNIDAD', 1.00, 'COLECTIVA', 1, 1, 'PENDIENTE_PROGRAMAR', null, null, 'Plaza de Armas', 1)")->execute([
    'rsv' => $reservaAId,
    'vl'  => $ventaLineaId,
    'itm' => $itemId,
]);
$prestacionParaProgramarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_prestaciones` (`reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`, `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`, `fecha_servicio`, `hora_servicio`, `punto_encuentro`, `version_bloqueo`) VALUES (:rsv, :vl, :itm, 'ITM_BUTACA', 'Butaca Sector A Rep', 'UNIDAD', 1.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-05', '08:00:00', 'Plaza de Armas', 1)")->execute([
    'rsv' => $reservaAId,
    'vl'  => $ventaLineaId,
    'itm' => $itemId,
]);
$prestacionParaReprogramarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `reserva_prestaciones` (`reserva_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `tipo_capacidad`, `requiere_agendamiento`, `requiere_participantes`, `estado_agendamiento`, `fecha_servicio`, `hora_servicio`, `punto_encuentro`, `version_bloqueo`) VALUES (:rsv, :vl, :itm, 'ITM_BUTACA', 'Butaca Sector A Doc', 'UNIDAD', 1.00, 'COLECTIVA', 1, 1, 'PROGRAMADA', '2026-02-05', '08:00:00', 'Plaza de Armas', 1)")->execute([
    'rsv' => $reservaDocId,
    'vl'  => $ventaLineaId,
    'itm' => $itemId,
]);
$prestacionDocId = (int) $pdo->lastInsertId();

// 10. Operaciones
$pdo->prepare("INSERT INTO `proveedores` (`organizacion_id`, `persona_id`, `tipo_servicio_principal`, `estado`, `creado_por`) VALUES (:org, :per, 'TRANSPORTE', 'ACTIVO', :usr)")->execute([
    'org' => $orgAId,
    'per' => $personaAId,
    'usr' => $usuarioAId,
]);
$proveedorId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_recursos` (`organizacion_id`, `tipo_recurso`, `codigo_interno`, `nombre`, `propiedad_tipo`, `estado`) VALUES (:org, 'VEHICULO_TERRESTRE', :cod, 'Bus Mercedes Benz Sprinter', 'PROPIO', 'DISPONIBLE')")->execute([
    'org' => $orgAId,
    'cod' => 'BUS-01-' . $sufijo,
]);
$recursoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Butacas Oficial', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'PROGRAMADA', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'itm' => $itemId,
    'cod' => 'SAL-01-' . $sufijo,
    'usr' => $usuarioAId,
]);
$salidaId = (int) $pdo->lastInsertId();

// Salidas dedicadas para mutaciones de ciclo operativo
$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Despacho', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'PROGRAMADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId, 'cod' => 'SAL-DESP-' . $sufijo, 'usr' => $usuarioAId,
]);
$salidaDespacharId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Finalizar', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'DESPACHADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId, 'cod' => 'SAL-FIN-' . $sufijo, 'usr' => $usuarioAId,
]);
$salidaFinalizarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Interrumpir', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'DESPACHADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId, 'cod' => 'SAL-INT-' . $sufijo, 'usr' => $usuarioAId,
]);
$salidaInterrumpirId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Cancelar', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'PROGRAMADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId, 'cod' => 'SAL-CANC-' . $sufijo, 'usr' => $usuarioAId,
]);
$salidaCancelarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `capacidad_maxima`, `estado`, `creado_por`) VALUES (:org, :ed, :itm, :cod, 'Salida Doc Inmutable', '2026-02-05', '08:00:00', '08:30:00', 'Plaza de Armas', 'COLECTIVA', 30, 'PROGRAMADA', :usr)")->execute([
    'org' => $orgAId, 'ed' => $edicionAId, 'itm' => $itemId, 'cod' => 'SAL-DOC-' . $sufijo, 'usr' => $usuarioAId,
]);
$salidaDocId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_salida_recursos` (`salida_id`, `recurso_fisico_id`, `rol_operativo`, `asignado_por`) VALUES (:sal, :rec, 'CONDUCTOR', :usr)")->execute([
    'sal' => $salidaId,
    'rec' => $recursoId,
    'usr' => $usuarioAId,
]);
$asignacionRecursoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_asistencias` (`salida_id`, `prestacion_id`, `participante_id`, `estado_asistencia`, `ubicacion_asiento`) VALUES (:sal, :pres, :part, 'PENDIENTE', 'A-01')")->execute([
    'sal'  => $salidaId,
    'pres' => $prestacionId,
    'part' => $participanteId,
]);
$asistenciaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `operacion_incidencias` (`salida_id`, `tipo_incidencia`, `descripcion`, `registrado_por`) VALUES (:sal, 'OTRO', 'Retraso leve por tráfico', :usr)")->execute([
    'sal' => $salidaId,
    'usr' => $usuarioAId,
]);
$incidenciaId = (int) $pdo->lastInsertId();

// Entrega para consultas
// Entrega para consultas
$pdo->prepare("INSERT INTO `entregas_productos` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `creado_por`) VALUES (:org, :ed, :vta, :cli, :corr, 'PENDIENTE_ENTREGA', 'Juan Perez', :usr)")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'vta'  => $ventaAId,
    'cli'  => $clienteAId,
    'corr' => 'ENT-' . $sufijo,
    'usr'  => $usuarioAId,
]);
$entregaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `entrega_items` (`entrega_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`) VALUES (:ent, :lin, :itm, 'KIT-CAN', 'Kit Festividad Candelaria', 'UNIDAD', 1.00)")->execute([
    'ent' => $entregaId,
    'lin' => $ventaLineaId,
    'itm' => $itemId,
]);

// Venta y entrega dedicada para despachar
$pdo->prepare("INSERT INTO `ventas` (`organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `estado`, `cliente_nombre_completo`, `moneda`, `subtotal`, `descuento_global_monto`, `total`, `monto_pagado`, `saldo_pendiente`, `estado_financiero`, `creado_por`) VALUES (:org, :ed, :cli, NULL, 'DIRECTA', :corr, CURDATE(), 'CONFIRMADA', 'Auditor Contratos A', 'PEN', 100.00, 0.00, 100.00, 100.00, 0.00, 'PAGADA_TOTAL', :usr)")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'cli'  => $clienteAId,
    'corr' => 'VTA-ENT-DSP-' . $sufijo,
    'usr'  => $usuarioAId,
]);
$ventaEntregaDespId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `venta_lineas` (`venta_id`, `tipo_linea`, `item_comercial_id`, `oferta_item_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `orden`) VALUES (:vta, 'ITEM', :itm, :of, 'SRV-PAQ', 'Paquete Despacho', 'UNIDAD', 1, 100.00, 100.00, 'PEN', 1)")->execute([
    'vta' => $ventaEntregaDespId,
    'itm' => $itemId,
    'of'  => $ofertaItemId,
]);
$lineaEntregaDespId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `entregas_productos` (`organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`, `correlativo`, `estado`, `contacto_nombre`, `creado_por`) VALUES (:org, :ed, :vta, :cli, :corr, 'PENDIENTE_ENTREGA', 'Juan Perez', :usr)")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'vta'  => $ventaEntregaDespId,
    'cli'  => $clienteAId,
    'corr' => 'ENT-DSP-' . $sufijo,
    'usr'  => $usuarioAId,
]);
$entregaDespacharId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `entrega_items` (`entrega_id`, `venta_linea_id`, `item_comercial_id`, `concepto_codigo`, `concepto_nombre`, `unidad_medida`, `cantidad`) VALUES (:ent, :lin, :itm, 'KIT-CAN', 'Kit Festividad Candelaria', 'UNIDAD', 1.00)")->execute([
    'ent' => $entregaDespacharId,
    'lin' => $lineaEntregaDespId,
    'itm' => $itemId,
]);

// 11. Folclore
$pdo->prepare("INSERT INTO `tipos_danza` (`organizacion_id`, `codigo`, `nombre`, `activo`) VALUES (:org, :cod, 'MORENADA PUNEÑA', 1)")->execute(['org' => $orgAId, 'cod' => 'TD_MORENADA_' . $sufijo]);
$tipoDanzaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`) VALUES (:org, :td, :cod, 'CONJUNTO MORENADA CENTRAL', 1, :usr)")->execute([
    'org' => $orgAId,
    'td'  => $tipoDanzaId,
    'cod' => 'CJ_MORENADA_' . $sufijo,
    'usr' => $usuarioAId,
]);
$conjuntoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`) VALUES (:org, :td, :cod, 'CONJUNTO MORENADA DOS', 1, :usr)")->execute([
    'org' => $orgAId,
    'td'  => $tipoDanzaId,
    'cod' => 'CJ_MORENADA_2_' . $sufijo,
    'usr' => $usuarioAId,
]);
$conjunto2Id = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`) VALUES (:org, :td, :cod, 'CONJUNTO CAPORALES CENTRAL', 1, :usr)")->execute([
    'org' => $orgAId,
    'td'  => $tipoDanzaId,
    'cod' => 'CJ_CAPORAL_' . $sufijo,
    'usr' => $usuarioAId,
]);
$conjuntoParaInscribirId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `bloques_conjunto` (`organizacion_id`, `conjunto_id`, `codigo`, `nombre`, `activo`) VALUES (:org, :cj, :cod, 'BLOQUE CHOLITAS', 1)")->execute([
    'org' => $orgAId,
    'cj'  => $conjuntoId,
    'cod' => 'BLQ-01-' . $sufijo,
]);
$bloqueId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `edicion_conjunto_inscripciones` (`organizacion_id`, `edicion_id`, `conjunto_id`, `cantidad_estimada_danzarines`, `estado`, `creado_por`) VALUES (:org, :ed, :cj, 250, 'CONFIRMADO', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cj'  => $conjuntoId,
    'usr' => $usuarioAId,
]);
$inscripcionId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `edicion_conjunto_inscripciones` (`organizacion_id`, `edicion_id`, `conjunto_id`, `cantidad_estimada_danzarines`, `estado`, `creado_por`) VALUES (:org, :ed, :cj, 150, 'CONFIRMADO', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cj'  => $conjunto2Id,
    'usr' => $usuarioAId,
]);
$inscripcion2Id = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`) VALUES (:org, :td, :cod, 'CONJUNTO PARA TURNO', 1, :usr)")->execute([
    'org' => $orgAId,
    'td'  => $tipoDanzaId,
    'cod' => 'CJ_TURNO_' . $sufijo,
    'usr' => $usuarioAId,
]);
$conjuntoParaTurnoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `edicion_conjunto_inscripciones` (`organizacion_id`, `edicion_id`, `conjunto_id`, `cantidad_estimada_danzarines`, `estado`, `creado_por`) VALUES (:org, :ed, :cj, 180, 'CONFIRMADO', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cj'  => $conjuntoParaTurnoId,
    'usr' => $usuarioAId,
]);
$inscripcionParaTurnoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `folclore_actividades_edicion` (`organizacion_id`, `edicion_id`, `codigo`, `titulo`, `fecha_inicio`, `fecha_fin`, `lugar_principal`) VALUES (:org, :ed, :cod, 'Concurso de Danzas Estadio', '2026-02-08', '2026-02-08', 'Estadio Enrique Torres Belón')")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cod' => 'ACT-EST-' . $sufijo,
]);
$actividadId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `folclore_programaciones_oficiales` (`organizacion_id`, `edicion_id`, `actividad_id`, `etiqueta_version`, `fuente_origen`, `fecha_emision_fuente`, `creado_por`) VALUES (:org, :ed, :act, 'V1.0', 'Federación Folclórica', CURDATE(), :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'act' => $actividadId,
    'usr' => $usuarioAutorizadorId,
]);
$programacionId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `folclore_orden_presentacion` (`programacion_oficial_id`, `conjunto_edicion_id`, `orden_sorteo`, `hora_estimada_partida`, `version_bloqueo`) VALUES (:prg, :insc, 1, '08:30:00', 1)")->execute([
    'prg'  => $programacionId,
    'insc' => $inscripcionId,
]);
$turnoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `folclore_orden_presentacion` (`programacion_oficial_id`, `conjunto_edicion_id`, `orden_sorteo`, `hora_estimada_partida`, `version_bloqueo`) VALUES (:prg, :insc, 99, '12:30:00', 1)")->execute([
    'prg'  => $programacionId,
    'insc' => $inscripcion2Id,
]);
$turnoEliminarId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `conjuntos_folcloricos` (`organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `activo`, `creado_por`) VALUES (:org, :td, :cod, 'CONJUNTO SEGUIMIENTO', 1, :usr)")->execute([
    'org' => $orgAId,
    'td'  => $tipoDanzaId,
    'cod' => 'CJ_SEG_' . $sufijo,
    'usr' => $usuarioAId,
]);
$conjuntoSeguimientoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `edicion_conjunto_inscripciones` (`organizacion_id`, `edicion_id`, `conjunto_id`, `cantidad_estimada_danzarines`, `estado`, `creado_por`) VALUES (:org, :ed, :cj, 200, 'CONFIRMADO', :usr)")->execute([
    'org' => $orgAId,
    'ed'  => $edicionAId,
    'cj'  => $conjuntoSeguimientoId,
    'usr' => $usuarioAId,
]);
$inscripcionSeguimientoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `folclore_orden_presentacion` (`programacion_oficial_id`, `conjunto_edicion_id`, `orden_sorteo`, `hora_estimada_partida`, `version_bloqueo`) VALUES (:prg, :insc, 5, '10:00:00', 1)")->execute([
    'prg'  => $programacionId,
    'insc' => $inscripcionSeguimientoId,
]);
$turnoParaSeguimientoId = (int) $pdo->lastInsertId();

// 12. Postproducción
$pdo->prepare("INSERT INTO `postproduccion_proyectos` (`organizacion_id`, `edicion_id`, `cliente_id`, `reserva_id`, `prestacion_id`, `codigo_proyecto`, `titulo`, `estado`, `fecha_limite_entrega`) VALUES (:org, :ed, :cli, :rsv, :pres, :cod, 'Fotografías Oficiales Candelaria 2026', 'LISTO_ENTREGA', DATE_ADD(CURDATE(), INTERVAL 30 DAY))")->execute([
    'org'  => $orgAId,
    'ed'   => $edicionAId,
    'cli'  => $clienteAId,
    'rsv'  => $reservaDocId,
    'pres' => $prestacionDocId,
    'cod'  => 'PROY-FOTO-' . $sufijo,
]);
$proyectoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `postproduccion_galerias` (`organizacion_id`, `proyecto_id`, `titulo`, `slug`) VALUES (:org, :proy, 'Galería Concurso Estadio', :slug)")->execute([
    'org'  => $orgAId,
    'proy' => $proyectoId,
    'slug' => 'gal-estadio-' . $sufijo,
]);
$galeriaId = (int) $pdo->lastInsertId();

$dirMultimedia = dirname(__DIR__) . '/almacenamiento/privado/multimedia/galerias';
if (!is_dir($dirMultimedia)) {
    @mkdir($dirMultimedia, 0755, true);
}
file_put_contents($dirMultimedia . '/foto_001.jpg', 'BINARY_IMAGE_DATA_V12');

$pdo->prepare("INSERT INTO `postproduccion_archivos` (`organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`, `nombre_almacenamiento`, `ruta_privada`, `tipo_archivo`, `mime_type`, `peso_bytes`, `checksum_sha256`) VALUES (:org, :proy, :gal, 'foto_001.jpg', 'arch_001.jpg', 'galerias/foto_001.jpg', 'FOTO', 'image/jpeg', 204800, :sha)")->execute([
    'org'  => $orgAId,
    'proy' => $proyectoId,
    'gal'  => $galeriaId,
    'sha'  => hash('sha256', 'foto_001_bytes'),
]);
$archivoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `postproduccion_entregas` (`organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`, `tipo_entrega`, `estado_entrega`, `version_bloqueo`) VALUES (:org, :proy, 1, 'Entrega Final Fotografías Alta Resolución', 'FINAL', 'PUBLICADA', 1)")->execute([
    'org'  => $orgAId,
    'proy' => $proyectoId,
]);
$entregaMultimediaId = (int) $pdo->lastInsertId();

// 13. Pasarelas
$pasarelaId = (int) ($pdo->query("SELECT id FROM pasarelas_pago WHERE codigo = 'CULQI' LIMIT 1")->fetchColumn() ?: 1);
$secretoCulqi = 'whsec_culqi_' . $sufijo;
$cifradorFin = new \Aplicacion\Finanzas\CifradorFinanciero();
$secretoEnc = $cifradorFin->cifrar($secretoCulqi);

$pdo->prepare("INSERT INTO `organizacion_pasarelas` (`organizacion_id`, `pasarela_id`, `modo`, `activo`, `identificador_comercio`, `credencial_secreta_enc`, `webhook_secreto_enc`) VALUES (:org, :pas, 'TEST', 1, 'pk_test_culqi', :cred, :sec)")->execute([
    'org'  => $orgAId,
    'pas'  => $pasarelaId,
    'cred' => $cifradorFin->cifrar('sk_test_culqi'),
    'sec'  => $secretoEnc,
]);
$orgPasarelaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT IGNORE INTO `edicion_pasarelas` (`organizacion_id`, `edicion_id`, `organizacion_pasarela_id`, `habilitado`) VALUES (:org, :ed, :orgpas, 1)")->execute([
    'org'    => $orgAId,
    'ed'     => $edicionAId,
    'orgpas' => $orgPasarelaId,
]);

// 13b. Bloqueo temporal de cupos para checkout público
$tokenBloqueo = 'tok_bloq_' . $sufijo;
$expiraBloqueoStr = date('Y-m-d H:i:s', time() + 86400);
$pdo->prepare("INSERT INTO `reserva_bloqueos_temporales` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `salida_id`, `token_bloqueo`, `identificador_sesion`, `cupos_bloqueados`, `fecha_servicio`, `hora_servicio`, `estado`, `expira_en`) VALUES (:org, :ed, :itm, :sal, :tok, :ses, 1, '2026-02-05', '08:00:00', 'ACTIVO', :expira)")->execute([
    'org'    => $orgAId,
    'ed'     => $edicionAId,
    'itm'    => $itemId,
    'sal'    => $salidaId,
    'tok'    => $tokenBloqueo,
    'ses'    => 'ses_' . $sufijo,
    'expira' => $expiraBloqueoStr,
]);

// 15. Cierre asentado para la edición cerrada
$resumenJsonStr = json_encode(['total_ventas' => 500.00, 'total_ingresos' => 500.00, 'moneda' => 'PEN']);
$fechaCierreStr = '2025-02-28 23:59:59';
$hashCierre = hash('sha256', $resumenJsonStr . $fechaCierreStr . (string)$edicionCerradaId . (string)$orgAId);

$pdo->prepare("
    INSERT INTO `edicion_cierres` (
        `organizacion_id`, `edicion_id`, `tipo_cierre`, `estado`, `usuario_cierre_id`,
        `fecha_cierre`, `resumen_financiero_json`, `diagnostico_incidencias_json`, `hash_integridad_cierre`
    ) VALUES (
        :org, :ed, 'DEFINITIVO', 'VIGENTE', :usr,
        :fecha, :resumen, '[]', :hash
    )
")->execute([
    'org'     => $orgAId,
    'ed'      => $edicionCerradaId,
    'usr'     => $usuarioAId,
    'fecha'   => $fechaCierreStr,
    'resumen' => $resumenJsonStr,
    'hash'    => $hashCierre,
]);
$cierreVigenteId = (int) $pdo->lastInsertId();

$resumenReabrirJson = json_encode(['total_ventas' => 200.00, 'total_ingresos' => 200.00, 'moneda' => 'PEN']);
$fechaReabrirStr = '2023-02-28 23:59:59';
$hashReabrir = hash('sha256', $resumenReabrirJson . $fechaReabrirStr . (string)$edicionParaReabrirId . (string)$orgAId);
$pdo->prepare("INSERT INTO `edicion_cierres` (`organizacion_id`, `edicion_id`, `tipo_cierre`, `estado`, `usuario_cierre_id`, `fecha_cierre`, `resumen_financiero_json`, `diagnostico_incidencias_json`, `hash_integridad_cierre`) VALUES (:org, :ed, 'DEFINITIVO', 'VIGENTE', :usr, :fecha, :resumen, '[]', :hash)")->execute([
    'org'     => $orgAId,
    'ed'      => $edicionParaReabrirId,
    'usr'     => $usuarioAId,
    'fecha'   => $fechaReabrirStr,
    'resumen' => $resumenReabrirJson,
    'hash'    => $hashReabrir,
]);

// 16. Comunicaciones
$cifradorCom = new \Aplicacion\Comunicaciones\CifradorComunicacion();
$secretoWa = 'whsec_whatsapp_2026';
$pdo->prepare("INSERT INTO `organizacion_comunicacion_config` (`organizacion_id`, `proveedor_id`, `modo`, `numero_telefono_identificador`, `webhook_verify_token_hash`, `webhook_secret_cifrado`, `activo`) VALUES (:org, 1, 'SIMULADOR', '+51999888777', :hash, :sec, 1)")->execute([
    'org'  => $orgAId,
    'hash' => hash('sha256', 'candelaria_webhook_token_2026'),
    'sec'  => $cifradorCom->cifrar($secretoWa),
]);

$pdo->prepare("INSERT INTO `comunicacion_plantillas` (`organizacion_id`, `nombre`, `idioma`, `categoria`, `estado_meta`, `cuerpo_texto`, `parametros_mapeo_json`) VALUES (:org, 'notificacion_general', 'es_PE', 'UTILITY', 'APPROVED', 'Hola {{1}}, su código es {{2}}', '[]')")->execute(['org' => $orgAId]);
$plantillaId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `comunicacion_consentimientos_canal` (`organizacion_id`, `cliente_id`, `canal`, `telefono_destino`, `finalidad`, `estado`, `origen_evidencia`, `actor_tipo`, `usuario_id`, `correlacion_id`) VALUES (:org, :cli, 'WHATSAPP', '+51951111111', 'TRANSACCIONAL_OPERATIVO', 'CONCEDIDO', 'WEB_CHECKBOX', 'HUMANO', :usr, :corr)")->execute([
    'org'  => $orgAId,
    'cli'  => $clienteAId,
    'usr'  => $usuarioAId,
    'corr' => 'corr_cons_' . $sufijo,
]);
$consentimientoId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `comunicacion_conversaciones` (`organizacion_id`, `cliente_id`, `telefono_cliente`, `numero_conversacion`, `estado`, `ventana_servicio_expira_en`) VALUES (:org, :cli, '+51951111111', 1, 'ABIERTA', DATE_ADD(NOW(), INTERVAL 24 HOUR))")->execute([
    'org' => $orgAId,
    'cli' => $clienteAId,
]);
$conversacionId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `comunicacion_mensajes` (`organizacion_id`, `conversacion_id`, `tipo_mensaje`, `direccion`, `canal`, `destinatario_telefono`, `destinatario_nombre`, `contenido_texto`, `idempotency_key`, `correlacion_id`, `estado`, `peso_estado`) VALUES (:org, :conv, 'TRANSACCIONAL', 'SALIENTE', 'WHATSAPP', '+51951111111', 'Auditor', 'Hola auditor', :idemp, :corr, 'ENTREGADO', 40)")->execute([
    'org'   => $orgAId,
    'conv'  => $conversacionId,
    'idemp' => 'idemp_' . $sufijo,
    'corr'  => 'corr_msg_' . $sufijo,
]);
$mensajeId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `comunicacion_campanas` (`organizacion_id`, `nombre`, `plantilla_id`, `criterios_segmentacion_json`, `estado`, `creado_por`) VALUES (:org, 'Campana Test', :plan, '[]', 'BORRADOR', :usr)")->execute([
    'org'  => $orgAId,
    'plan' => $plantillaId,
    'usr'  => $usuarioAId,
]);
$campanaId = (int) $pdo->lastInsertId();

// Campaña SoD: Creada por el autorizador para que pueda ser aprobada por usuarioAId
$pdo->prepare("INSERT INTO `comunicacion_campanas` (`organizacion_id`, `nombre`, `plantilla_id`, `criterios_segmentacion_json`, `estado`, `creado_por`) VALUES (:org, 'Campana SoD Aprobacion', :plan, '[]', 'BORRADOR', :usr)")->execute([
    'org'  => $orgAId,
    'plan' => $plantillaId,
    'usr'  => $usuarioAutorizadorId,
]);
$campanaSoDId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO `comunicacion_automatizacion_reglas` (`organizacion_id`, `codigo`, `nombre`, `evento_disparador`, `plantilla_id`, `mapeo_parametros_json`, `activo`, `creado_por`) VALUES (:org, :cod, 'Regla Pago', 'PAGO_CONFIRMADO', :plan, '[]', 1, :usr)")->execute([
    'org'  => $orgAId,
    'cod'  => 'REG_AUT_' . $sufijo,
    'plan' => $plantillaId,
    'usr'  => $usuarioAId,
]);
$automatizacionId = (int) $pdo->lastInsertId();

$f = [
    'sufijo'                      => $sufijo,
    'orgAId'                      => $orgAId,
    'orgBId'                      => $orgBId,
    'edicionAId'                  => $edicionAId,
    'edicionBId'                  => $edicionBId,
    'edicionCerradaId'            => $edicionCerradaId,
    'edicionParaCerrarId'         => $edicionParaCerrarId,
    'edicionCambioEstadoId'       => $edicionCambioEstadoId,
    'edicionParaReabrirId'        => $edicionParaReabrirId,
    'cierreVigenteId'             => $cierreVigenteId,
    'personaAId'                  => $personaAId,
    'usuarioAId'                  => $usuarioAId,
    'usuarioSinPermId'            => $usuarioSinPermId,
    'tokenSinPermPlano'           => $tokenSinPermPlano,
    'usuarioAutorizadorId'        => $usuarioAutorizadorId,
    'personaDisponibleId'         => $personaDisponibleId,
    'usuarioModificableId'        => $usuarioModificableId,
    'tokenLogoutPlano'            => $tokenLogoutPlano,
    'clienteAId'                  => $clienteAId,
    'clienteBId'                  => $clienteBId,
    'clienteCId'                  => $clienteCId,
    'ventaAjenaCId'               => $ventaAjenaCId,
    'clienteModificableId'        => $clienteModificableId,
    'clienteCambioEstadoId'       => $clienteCambioEstadoId,
    'clienteSesionAId'            => $clienteSesionAId,
    'tokenClienteAPlano'          => $tokenClienteAPlano,
    'codigoAccesoPortal'          => $codigoAccesoPortal,
    'origenId'                    => $origenId,
    'oportunidadId'               => $oportunidadId,
    'interaccionId'               => $interaccionId,
    'categoriaId'                 => $categoriaId,
    'itemId'                      => $itemId,
    'itemParaOfertarId'           => $itemParaOfertarId,
    'paqueteId'                   => $paqueteId,
    'paqueteParaOfertarId'        => $paqueteParaOfertarId,
    'tarifaItemId'                => $tarifaItemId,
    'tarifaPaqueteId'             => $tarifaPaqueteId,
    'ofertaItemId'                => $ofertaItemId,
    'ofertaItemSinTarifaId'       => $ofertaItemSinTarifaId,
    'ofertaPaqueteId'             => $ofertaPaqueteId,
    'ofertaPaqueteSinTarifaId'    => $ofertaPaqueteSinTarifaId,
    'cotizacionBorradorId'        => $cotizacionBorradorId,
    'cotizacionParaEmitirId'      => $cotizacionParaEmitirId,
    'cotizacionEmitidaId'         => $cotizacionEmitidaId,
    'cotizacionAceptadaId'        => $cotizacionAceptadaId,
    'cotizacionParaRechazarId'    => $cotizacionParaRechazarId,
    'cotizacionParaAnularId'      => $cotizacionParaAnularId,
    'cotizacionParaAceptarId'     => $cotizacionParaAceptarId,
    'cotizacionParaRevisionId'    => $cotizacionParaRevisionId,
    'cotizacionDocId'             => $cotizacionDocId,
    'cotizacionLineaId'           => $cotizacionLineaId,
    'cotizacionLineaEliminarId'   => $cotizacionLineaEliminarId,
    'ventaAId'                    => $ventaAId,
    'ventaParaLiquidarId'         => $ventaParaLiquidarId,
    'ventaSinReservaId'           => $ventaSinReservaId,
    'ventaParaCancelarId'         => $ventaParaCancelarId,
    'ventaParaAnularId'           => $ventaParaAnularId,
    'ventaDocId'                  => $ventaDocId,
    'ventaLineaId'                => $ventaLineaId,
    'contratoId'                  => $contratoId,
    'contratoDocId'               => $contratoDocId,
    'pagoId'                      => $pagoId,
    'pagoParaVerificarId'         => $pagoParaVerificarId,
    'pagoParaReembolsarId'        => $pagoParaReembolsarId,
    'pagoDocId'                   => $pagoDocId,
    'reciboId'                    => $reciboId,
    'reciboDocId'                 => $reciboDocId,
    'cuentaBancariaId'            => $cuentaBancariaId,
    'reservaAId'                  => $reservaAId,
    'reservaParaCancelarId'       => $reservaParaCancelarId,
    'reservaDocId'                => $reservaDocId,
    'participanteId'              => $participanteId,
    'participanteEliminarId'      => $participanteEliminarId,
    'prestacionId'                => $prestacionId,
    'prestacionParaProgramarId'   => $prestacionParaProgramarId,
    'prestacionParaReprogramarId' => $prestacionParaReprogramarId,
    'prestacionDocId'             => $prestacionDocId,
    'proveedorId'                 => $proveedorId,
    'recursoId'                   => $recursoId,
    'salidaId'                    => $salidaId,
    'salidaDespacharId'           => $salidaDespacharId,
    'salidaFinalizarId'           => $salidaFinalizarId,
    'salidaInterrumpirId'         => $salidaInterrumpirId,
    'salidaCancelarId'            => $salidaCancelarId,
    'salidaDocId'                 => $salidaDocId,
    'asignacionRecursoId'         => $asignacionRecursoId,
    'asistenciaId'                => $asistenciaId,
    'incidenciaId'                => $incidenciaId,
    'entregaId'                   => $entregaId,
    'entregaDespacharId'          => $entregaDespacharId,
    'tipoDanzaId'                 => $tipoDanzaId,
    'conjuntoId'                  => $conjuntoId,
    'conjunto2Id'                 => $conjunto2Id,
    'conjuntoParaInscribirId'     => $conjuntoParaInscribirId,
    'bloqueId'                    => $bloqueId,
    'inscripcionId'               => $inscripcionId,
    'inscripcion2Id'              => $inscripcion2Id,
    'inscripcionParaTurnoId'      => $inscripcionParaTurnoId,
    'actividadId'                 => $actividadId,
    'programacionId'              => $programacionId,
    'turnoId'                     => $turnoId,
    'turnoParaSeguimientoId'      => $turnoParaSeguimientoId,
    'turnoEliminarId'             => $turnoEliminarId,
    'proyectoId'                  => $proyectoId,
    'galeriaId'                   => $galeriaId,
    'archivoId'                   => $archivoId,
    'entregaMultimediaId'         => $entregaMultimediaId,
    'tokenBloqueo'                => $tokenBloqueo,
    'secretoCulqi'                => $secretoCulqi,
    'secretoWa'                   => $secretoWa,
    'plantillaId'                 => $plantillaId,
    'consentimientoId'            => $consentimientoId,
    'conversacionId'              => $conversacionId,
    'mensajeId'                   => $mensajeId,
    'campanaId'                   => $campanaId,
    'campanaSoDId'                => $campanaSoDId,
    'automatizacionId'            => $automatizacionId,
];

    return $f;
}

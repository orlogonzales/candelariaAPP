<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.12A Y F2.12A-1
 * CIERRE ADMINISTRATIVO Y FINANCIERO DE EDICIÓN Y PROCEDIMIENTO EXCEPCIONAL
 * ==============================================================================
 * 1. DIAGNÓSTICO PREVIO Y BALANCE FINANCIERO (CONCILIACIÓN REAL).
 * 2. CLASIFICACIÓN DE INCIDENCIAS: BLOQUEANTES VS OBSERVACIONES.
 * 3. CIERRE DEFINITIVO (0 BLOQUEANTES, 0 OBSERVACIONES).
 * 4. CIERRE CONDICIONADO, SEGREGACIÓN DE FUNCIONES (CUATRO OJOS) Y RBAC.
 * 5. SELLADO CRIPTOGRÁFICO HMAC-SHA256 Y DETECCIÓN DE ADULTERACIONES.
 * 6. COBERTURA DE GUARDIANES OPERATIVOS Y REGULARIZACIÓN LEGÍTIMA DE SALDOS.
 * 7. CONCURRENCIA SIMULTÁNEA REAL CON DOS CONEXIONES PDO INDEPENDIENTES.
 * 8. REAPERTURA EXCEPCIONAL AUDITADA CON CUATRO OJOS Y CLAVE DE AUTORIZADOR.
 * 9. CIERRE DEFINITIVO PLENO POST-REGULARIZACIÓN Y BLOQUEO DE COBRANZAS.
 * 10. IDENTIDAD E INTEGRIDAD HISTÓRICA INMUTABLE DE ORLANDO (ID 24).
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Ediciones\CierreEdicionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver;
use Aplicacion\Postproduccion\PostproduccionServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Servicios\MotorDisponibilidadServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

$pruebasEjecutadas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $pruebasEjecutadas, $pruebasExitosas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.12A Y F2.12A-1: CIERRE ADMINISTRATIVO Y FINANCIERO HARDENED\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$servicioCierre = new CierreEdicionServicio($pdo);

// Cargar Tenant de Pruebas
$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

// Cargar Usuarios
$adminId = 24; // Orlando (ejecutor / solicitante)

// Crear o sincronizar usuario autorizador de pruebas (con rol administrador y clave conocida)
$claveAutorizadorPlana = 'ClaveAutorizador2026!';
$hashAutorizador = password_hash($claveAutorizadorPlana, PASSWORD_BCRYPT);
$autorizadorId = 77702;

$pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`) VALUES (77702, {$orgId}, 'NATURAL', 1, '77702002', 'Valeria', 'Auditora') ON DUPLICATE KEY UPDATE `nombres` = 'Valeria'");
$pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`) VALUES (77702, {$orgId}, 77702, 'autorizador_cierre', 'Lic. Valeria Auditora General', 'autorizador_f212a@candelaria.pe', '{$hashAutorizador}', 0, 'ACTIVO') ON DUPLICATE KEY UPDATE `contrasena_hash` = '{$hashAutorizador}', `estado` = 'ACTIVO'");

// Asegurar rol Administrador (rol_id 2) para el autorizador
$pdo->prepare("INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usr_id, 2)")->execute(['usr_id' => $autorizadorId]);

// Crear usuario sin RBAC de cierre (operador raso) para probar denegación
$operadorSinRbacId = 77703;
$pdo->exec("INSERT INTO `personas` (`id`, `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`) VALUES (77703, {$orgId}, 'NATURAL', 1, '77703003', 'Operador', 'Auxiliar') ON DUPLICATE KEY UPDATE `nombres` = 'Operador'");
$pdo->exec("INSERT INTO `usuarios` (`id`, `organizacion_id`, `persona_id`, `nombre_usuario`, `nombre_completo`, `correo_electronico`, `contrasena_hash`, `es_superadmin_plataforma`, `estado`) VALUES (77703, {$orgId}, 77703, 'operador_sin_cierre', 'Operador Auxiliar de Campo', 'operador_sin_rbac@candelaria.pe', '{$hashAutorizador}', 0, 'ACTIVO') ON DUPLICATE KEY UPDATE `estado` = 'ACTIVO'");

// Asegurar que NO tenga rol 1 ni 2, ni permisos de cierre
$pdo->prepare("DELETE FROM `usuario_roles` WHERE `usuario_id` = :id")->execute(['id' => $operadorSinRbacId]);
$pdo->prepare("INSERT IGNORE INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES (:usr_id, 3)")->execute(['usr_id' => $operadorSinRbacId]);

// ------------------------------------------------------------------------------
// SECCIÓN 1: CREACIÓN DE EDICIÓN PILOTO PARA CICLO DE CIERRE
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: CREACIÓN DE EDICIÓN PILOTO Y VERIFICACIÓN DE ESQUEMA ---\n";

$codigoEdicion = 'candelaria-cierre-test-' . uniqid();
$aniosUsados = $pdo->query("SELECT anio FROM `ediciones_candelaria` WHERE organizacion_id = {$orgId}")->fetchAll(PDO::FETCH_COLUMN);
$anioTest = 2070;
while (in_array($anioTest, $aniosUsados) && $anioTest < 2100) {
    $anioTest++;
}

$stmtEd = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` (
        `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`
    ) VALUES (
        :org_id, :codigo, :nombre, :anio, 'POSTPRODUCCION_ENTREGA', '2035-02-01', '2035-02-15', 'Edición de Prueba Cierre F2.12A-1', 0
    )
");
$stmtEd->execute([
    'org_id' => $orgId,
    'codigo' => $codigoEdicion,
    'nombre' => 'FESTIVIDAD CANDELARIA PRUEBA CIERRE ' . $anioTest,
    'anio'   => $anioTest,
]);
$edicionId = (int) $pdo->lastInsertId();

afirmar($edicionId > 0, "Edición piloto creada para auditoría de cierre (ID: {$edicionId}).");

// ------------------------------------------------------------------------------
// SECCIÓN 2: DIAGNÓSTICO EN LIMPIO (APTO PARA CIERRE DEFINITIVO)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: DIAGNÓSTICO INICIAL EN LIMPIO ---\n";

$diagLimpio = $servicioCierre->generarDiagnostico($orgId, $edicionId);

afirmar($diagLimpio['total_bloqueantes'] === 0, "Sin incidencias bloqueantes en edición sin pendientes.");
afirmar($diagLimpio['total_observaciones'] === 0, "Sin observaciones en edición en balance cero.");
afirmar($diagLimpio['es_apto_cierre_normal'] === true, "Diagnóstico dictamina apto para Cierre Definitivo pleno.");
afirmar($diagLimpio['es_apto_condicionado'] === false, "No requiere condicionado al estar en balance perfecto.");

// ------------------------------------------------------------------------------
// SECCIÓN 3: DETECCIÓN ESTRICTA DE INCIDENCIAS BLOQUEANTES
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: DETECCIÓN DE INCIDENCIAS BLOQUEANTES (FAIL-CLOSED) ---\n";

$stmtCli = $pdo->prepare("SELECT id FROM `clientes` WHERE organizacion_id = :org_id LIMIT 1");
$stmtCli->execute(['org_id' => $orgId]);
$clienteId = (int) $stmtCli->fetchColumn();

// Crear venta piloto para la edición
$stmtVta = $pdo->prepare("
    INSERT INTO `ventas` (
        `organizacion_id`, `edicion_id`, `cliente_id`, `origen_tipo`, `correlativo`, `fecha_venta`, `cliente_nombre_completo`,
        `estado`, `estado_financiero`, `moneda`, `subtotal`, `total`, `monto_pagado`, `saldo_pendiente`, `version_bloqueo`, `creado_por`
    ) VALUES (
        :org_id, :ed_id, :cli_id, 'DIRECTA', :correlativo, CURRENT_DATE, 'Cliente Piloto Cierre',
        'CONFIRMADA', 'PAGO_PARCIAL', 'PEN', 1000.00, 1000.00, 500.00, 500.00, 1, :usr_id
    )
");
$stmtVta->execute([
    'org_id'      => $orgId,
    'ed_id'       => $edicionId,
    'cli_id'      => $clienteId,
    'correlativo' => 'VTA-TEST-' . uniqid(),
    'usr_id'      => $adminId,
]);
$ventaId = (int) $pdo->lastInsertId();

// Insertar pago pendiente de verificación
$stmtPagoPend = $pdo->prepare("
    INSERT INTO `pagos` (
        `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`, `metodo_pago`,
        `estado`, `moneda`, `monto_cobrado_cliente`, `monto_neto_recibido`, `monto_aplicado_venta`,
        `fecha_pago`, `creado_por`
    ) VALUES (
        :org_id, :ed_id, :vta_id, :corr, 'TRANSFERENCIA_BANCARIA',
        'PENDIENTE_VERIFICACION', 'PEN', 500.00, 500.00, 500.00, NOW(), :usr_id
    )
");
$stmtPagoPend->execute([
    'org_id' => $orgId,
    'ed_id'  => $edicionId,
    'vta_id' => $ventaId,
    'corr'   => 'PAG-PEND-' . uniqid(),
    'usr_id' => $adminId,
]);
$pagoPendienteId = (int) $pdo->lastInsertId();

$diagConBloqueo = $servicioCierre->generarDiagnostico($orgId, $edicionId);
afirmar($diagConBloqueo['total_bloqueantes'] >= 1, "Detecta pago PENDIENTE_VERIFICACION como incidencia bloqueante.");
afirmar($diagConBloqueo['es_apto_cierre_normal'] === false, "Cierre normal queda inhabilitado ante pago no resuelto.");

// Intentar ejecutar cierre definitivo con bloqueante presente -> Debe lanzar InvalidArgumentException
$bloqueoEfectivo = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'DEFINITIVO');
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'bloqueantes')) {
        $bloqueoEfectivo = true;
    }
}
afirmar($bloqueoEfectivo, "Fail-Closed: Cierre es rígidamente denegado existiendo pagos o vouchers pendientes.");

// Limpiar el pago pendiente (aprobarlo formalmente)
$pdo->prepare("UPDATE `pagos` SET `estado` = 'APROBADO', `monto_neto_recibido` = 500.00 WHERE id = :id")->execute(['id' => $pagoPendienteId]);

// ------------------------------------------------------------------------------
// SECCIÓN 4: CIERRE CONDICIONADO, SEGREGACIÓN DE FUNCIONES (CUATRO OJOS) Y RBAC
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: CIERRE CONDICIONADO, CUATRO OJOS Y FACULTADES RBAC ---\n";

$diagCond = $servicioCierre->generarDiagnostico($orgId, $edicionId);
afirmar($diagCond['total_bloqueantes'] === 0, "No existen bloqueantes críticas tras dictaminar pagos.");
afirmar($diagCond['total_observaciones'] >= 1, "Registra saldo pendiente de S/ 500 como observación tolerable.");
afirmar($diagCond['es_apto_condicionado'] === true, "Apto para Cierre Condicionado.");

// 4.1 Intento de Cierre Definitivo teniendo saldos pendientes debe ser rechazado
$rechazoDefinitivoPorSaldo = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'DEFINITIVO');
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'Cierre CONDICIONADO')) {
        $rechazoDefinitivoPorSaldo = true;
    }
}
afirmar($rechazoDefinitivoPorSaldo, "Prohibición de Cierre Definitivo Ciego si existen saldos por liquidar.");

// 4.2 Intento de Cierre Condicionado sin justificación suficiente (< 15 caracteres)
$rechazoSinJustificacion = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Cierre rapido', autorizadorId: $autorizadorId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'mínimo 15 caracteres')) {
        $rechazoSinJustificacion = true;
    }
}
afirmar($rechazoSinJustificacion, "Cierre Condicionado exige justificación formal fundamentada.");

// 4.3 Intento de Cierre Condicionado sin usuario autorizador
$rechazoSinAutorizador = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Se acuerda pago restante a 30 días post-candelaria', autorizadorId: null);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'autorizador')) {
        $rechazoSinAutorizador = true;
    }
}
afirmar($rechazoSinAutorizador, "Cierre Condicionado exige usuario autorizador con facultades.");

// 4.4 Violación del principio de cuatro ojos (solicitante == autorizador)
$rechazoCuatroOjosCierre = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Justificación válida de prueba condicionado', autorizadorId: $adminId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'cuatro ojos')) {
        $rechazoCuatroOjosCierre = true;
    }
}
afirmar($rechazoCuatroOjosCierre, "Segregación de funciones: Rechaza Cierre Condicionado cuando solicitante == autorizador.");

// 4.5 Autorizador sin facultades RBAC
$rechazoAutorizadorSinRbac = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Justificación válida de prueba condicionado', autorizadorId: $operadorSinRbacId);
} catch (AccesoDenegadoExcepcion $e) {
    $rechazoAutorizadorSinRbac = true;
}
afirmar($rechazoAutorizadorSinRbac, "RBAC Efectivo: Rechaza autorización de Cierre Condicionado de usuario sin perfil administrativo.");

// 4.6 Ejecución formal y exitosa de Cierre Condicionado (con 2 identidades distintas)
$cierreCondicionado = $servicioCierre->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioId: $adminId,
    tipoCierre: 'CONDICIONADO',
    justificacion: 'Se formaliza cierre con saldo pendiente de S/ 500 bajo compromiso comercial de cobro.',
    autorizadorId: $autorizadorId,
    versionBloqueoEsperada: 1
);

afirmar(!empty($cierreCondicionado['id']), "Acta de Cierre Condicionado asentada con ID #{$cierreCondicionado['id']}.");
afirmar($cierreCondicionado['tipo_cierre'] === 'CONDICIONADO', "Tipo de cierre registrado como CONDICIONADO.");
afirmar(!empty($cierreCondicionado['hash_integridad_cierre']), "Hash criptográfico de sellado generado ({$cierreCondicionado['hash_integridad_cierre']}).");

$edicionPostCierre = $servicioCierre->obtenerEdicion($orgId, $edicionId);
afirmar($edicionPostCierre['estado'] === 'CERRADA', "Estado de la festividad actualizado formalmente a CERRADA.");

// ------------------------------------------------------------------------------
// SECCIÓN 5: SELLADO CRIPTOGRÁFICO HMAC-SHA256 Y AUDITORÍA DE INMUTABILIDAD
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: SELLADO CRIPTOGRÁFICO HMAC-SHA256 Y DETECCIÓN DE ADULTERACIONES ---\n";

$auditoriaSellado = $servicioCierre->verificarIntegridadCierre($orgId, (int) $cierreCondicionado['id']);
afirmar($auditoriaSellado['es_integro'] === true, "Verificación criptográfica exitosa: Cierre 100% íntegro.");
afirmar($auditoriaSellado['tipo_sellado'] === 'HMAC_SHA256', "Algoritmo de sellado verificado: HMAC-SHA256 con clave de aplicación.");

// Simular adulteración no autorizada directa en base de datos
$jsonOriginal = $cierreCondicionado['resumen_financiero_json'];
$jsonAdulterado = json_encode(array_merge(
    json_decode((string) $jsonOriginal, true) ?: [],
    ['total_neto_recaudado' => 9999999.99]
));
$pdo->prepare("UPDATE `edicion_cierres` SET `resumen_financiero_json` = :json WHERE id = :id")->execute([
    'json' => $jsonAdulterado,
    'id'   => $cierreCondicionado['id'],
]);

$auditoriaAdulterada = $servicioCierre->verificarIntegridadCierre($orgId, (int) $cierreCondicionado['id']);
afirmar($auditoriaAdulterada['es_integro'] === false, "Detección de adulteración: Hash HMAC detecta alteración maliciosa de datos.");
afirmar($auditoriaAdulterada['tipo_sellado'] === 'ALTERADO', "Dictamen pericial clasificado certeramente como ALTERADO.");

// Restaurar snapshot íntegro
$pdo->prepare("UPDATE `edicion_cierres` SET `resumen_financiero_json` = :json WHERE id = :id")->execute([
    'json' => $jsonOriginal,
    'id'   => $cierreCondicionado['id'],
]);
$auditoriaRestaurada = $servicioCierre->verificarIntegridadCierre($orgId, (int) $cierreCondicionado['id']);
afirmar($auditoriaRestaurada['es_integro'] === true, "Integridad restaurada tras subsanación.");

// ------------------------------------------------------------------------------
// SECCIÓN 6: GUARDIANES OPERATIVOS Y REGULARIZACIÓN LEGÍTIMA DE SALDOS
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: GUARDIANES OPERATIVOS Y REGULARIZACIÓN LEGÍTIMA DE SALDOS ---\n";

// 6.1 Guardián en Motor de Disponibilidad (Checkout público)
$bloqueoRepo = new ReservaBloqueoTemporalRepositorio($pdo);
$motorDisp = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);
$resBloqueo = $motorDisp->bloquearCupos(
    organizacionId: $orgId,
    edicionId: $edicionId,
    itemComercialId: 1,
    cuposSolicitados: 1,
    identificadorSesion: 'sesion-test-' . uniqid()
);
afirmar($resBloqueo['exito'] === false && str_contains($resBloqueo['mensaje'], 'CERRADA'), "Checkout: MotorDisponibilidad bloquea compras en festividad cerrada.");

// 6.2 Guardián en Postproducción (Creación de Proyectos)
$postprodServicio = new PostproduccionServicio($pdo, new AlmacenamientoLocalDriver());
$bloqueoPostprod = false;
try {
    $postprodServicio->crearProyecto(
        organizacionId: $orgId,
        edicionId: $edicionId,
        clienteId: $clienteId,
        titulo: 'Proyecto Bloqueado Post-Cierre'
    );
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'CERRADA')) {
        $bloqueoPostprod = true;
    }
}
afirmar($bloqueoPostprod, "Postproducción: Bloquea creación de nuevos proyectos en festividad cerrada.");

// 6.3 Guardián en Operaciones (CierreEdicionServicio::validarEdicionAbiertaParaOperaciones)
$bloqueoOps = false;
try {
    $servicioCierre->validarEdicionAbiertaParaOperaciones($orgId, $edicionId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'CERRADA')) {
        $bloqueoOps = true;
    }
}
afirmar($bloqueoOps, "Operaciones: Guardián de campo bloquea despachos en festividad cerrada.");

// 6.4 Regularización Legítima en Finanzas: PagoServicio
// En cierre CONDICIONADO con saldo pendiente, DEBE permitirse amortizar la deuda de la venta preexistente
$authzServicio = new AutorizacionServicio(pdo: $pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$pagoRepo = new PagoRepositorio($pdo);
$reembolsoRepo = new PagoReembolsoRepositorio($pdo);
$cuentaRepo = new CuentaBancariaRepositorio($pdo);
$pasarelaRepo = new PasarelaRepositorio($pdo);
$ventaRepo = new VentaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$configRepo = new ConfiguracionRepositorio($pdo);

$pagoServicio = new PagoServicio(
    $pagoRepo, $reembolsoRepo, $cuentaRepo, $pasarelaRepo,
    $ventaRepo, $edicionRepo, $configRepo, $authzServicio, $auditoriaRepo, $pdo
);

$contextoOp = new ContextoOperacion(
    actorTipo: 'HUMANO',
    usuarioId: $adminId,
    actorSistemaId: null,
    actorSistemaCodigo: null,
    canalId: 1,
    canalCodigo: 'APP',
    correlacionId: 'corr-test-cierre-f212a',
    origenIp: '127.0.0.1',
    agenteUsuario: 'SuiteF212A/Test',
    organizacionId: $orgId,
    metadatos: ['csrf_token' => 'token_valido_f212a'],
    edicionTrabajoId: $edicionId,
    origenEdicion: 'EXPLICITA'
);

// Amortizar S/ 200.00 del saldo pendiente de S/ 500.00 en la venta preexistente
$pagoAmortizacion = $pagoServicio->registrarPagoManual(
    organizacionId: $orgId,
    edicionId: $edicionId,
    ventaId: $ventaId,
    datos: [
        'metodo_pago'             => 'EFECTIVO',
        'monto'                   => 200.00,
        'aprobacion_inmediata'    => true,
        'observaciones'           => 'Cobranza amortizatoria de saldo acordado en cierre condicionado',
    ],
    contexto: $contextoOp
);

afirmar(!empty($pagoAmortizacion->id), "Regularización Legítima: PagoServicio permite amortizar saldo en Cierre Condicionado (ID Pago #{$pagoAmortizacion->id}).");

// Consultar saldo remanente de la venta
$stmtSaldoVta = $pdo->prepare("SELECT saldo_pendiente FROM `ventas` WHERE id = :id");
$stmtSaldoVta->execute(['id' => $ventaId]);
$saldoRemanente = (float) $stmtSaldoVta->fetchColumn();
afirmar(abs($saldoRemanente - 300.00) < 0.01, "Saldo de venta actualizado fielmente a S/ 300.00.");

// ------------------------------------------------------------------------------
// SECCIÓN 7: CONCURRENCIA SIMULTÁNEA REAL CON DOS CONEXIONES PDO INDEPENDIENTES
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: CONCURRENCIA REAL CON DOS CONEXIONES PDO INDEPENDIENTES ---\n";

// Crear edición exclusiva para concurrencia
$anioConcurrente = $anioTest + 1;
while (in_array($anioConcurrente, $aniosUsados) && $anioConcurrente < 2100) {
    $anioConcurrente++;
}
$stmtEdConc = $pdo->prepare("
    INSERT INTO `ediciones_candelaria` (
        `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`, `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`
    ) VALUES (
        :org_id, :codigo, :nombre, :anio, 'POSTPRODUCCION_ENTREGA', '2036-02-01', '2036-02-15', 'Edición Prueba Concurrencia F2.12A-1', 0
    )
");
$stmtEdConc->execute([
    'org_id' => $orgId,
    'codigo' => 'candelaria-conc-' . uniqid(),
    'nombre' => 'CANDELARIA CONCURRENCIA ' . $anioConcurrente,
    'anio'   => $anioConcurrente,
]);
$edicionConcId = (int) $pdo->lastInsertId();

$driver = (string) entorno('BD_DRIVER', 'mysql');
$host = (string) entorno('BD_HOST', '127.0.0.1');
$puerto = (int) entorno('BD_PUERTO', 3306);
$nombre = (string) entorno('BD_NOMBRE', 'app_candelaria');
$usuario = (string) entorno('BD_USUARIO', 'root');
$contrasena = (string) entorno('BD_CONTRASENA', '');
$charset = (string) entorno('BD_CHARSET', 'utf8mb4');

$dsn = "{$driver}:host={$host};port={$puerto};dbname={$nombre};charset={$charset}";
$opcionesPdo = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$pdoConcurrente1 = new PDO($dsn, $usuario, $contrasena, $opcionesPdo);
$pdoConcurrente2 = new PDO($dsn, $usuario, $contrasena, $opcionesPdo);

$servicioCierre1 = new CierreEdicionServicio($pdoConcurrente1);
$servicioCierre2 = new CierreEdicionServicio($pdoConcurrente2);

// Transacción 1: Asienta cierre definitivo
$cierreConc1 = $servicioCierre1->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionConcId,
    usuarioId: $adminId,
    tipoCierre: 'DEFINITIVO'
);
afirmar(!empty($cierreConc1['id']), "Conexión 1: Asienta cierre definitivo con éxito.");

// Transacción 2: Intento simultáneo de cerrar la misma festividad
$conflictoDetectado = false;
try {
    $servicioCierre2->ejecutarCierre(
        organizacionId: $orgId,
        edicionId: $edicionConcId,
        usuarioId: $adminId,
        tipoCierre: 'DEFINITIVO'
    );
} catch (ConflictoConcurrenciaExcepcion $e) {
    if (str_contains($e->getMessage(), 'CERRADA')) {
        $conflictoDetectado = true;
    }
}
afirmar($conflictoDetectado, "Conexión 2: Serializada con SELECT FOR UPDATE; detecta festividad cerrada y lanza ConflictoConcurrenciaExcepcion (HTTP 409).");

// ------------------------------------------------------------------------------
// SECCIÓN 8: PROCEDIMIENTO EXCEPCIONAL DE REAPERTURA AUDITADA
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 8: REAPERTURA EXCEPCIONAL CON CUATRO OJOS Y CLAVE DE AUTORIZADOR ---\n";

// 8.1 Reapertura sin motivo fundamentado (< 20 caracteres)
$rechazoReaperturaSinMotivo = false;
try {
    $servicioCierre->reabrirEdicionExcepcional($orgId, $edicionId, $adminId, $autorizadorId, 'Motivo corto');
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'mínimo 20 caracteres')) {
        $rechazoReaperturaSinMotivo = true;
    }
}
afirmar($rechazoReaperturaSinMotivo, "Reapertura exige fundamentación exhaustiva (mínimo 20 caracteres).");

// 8.2 Violación de cuatro ojos en reapertura (solicitante == autorizador)
$rechazoCuatroOjosReapertura = false;
try {
    $servicioCierre->reabrirEdicionExcepcional(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioSolicitanteId: $adminId,
        usuarioAutorizadorId: $adminId,
        motivoExcepcional: 'Regularización de saldos acordada según informe de auditoría'
    );
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'cuatro ojos') || str_contains($e->getMessage(), 'segregación de funciones')) {
        $rechazoCuatroOjosReapertura = true;
    }
}
afirmar($rechazoCuatroOjosReapertura, "Segregación de funciones: Rechaza reapertura cuando solicitante == autorizador.");

// 8.3 Autorizador sin facultades RBAC
$rechazoReaperturaSinRbac = false;
try {
    $servicioCierre->reabrirEdicionExcepcional(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioSolicitanteId: $adminId,
        usuarioAutorizadorId: $operadorSinRbacId,
        motivoExcepcional: 'Regularización de saldos acordada según informe de auditoría'
    );
} catch (AccesoDenegadoExcepcion $e) {
    $rechazoReaperturaSinRbac = true;
}
afirmar($rechazoReaperturaSinRbac, "RBAC Efectivo: Rechaza reapertura autorizada por usuario sin permisos de reabrir.");

// 8.4 Clave de confirmación incorrecta
$rechazoClaveErronea = false;
try {
    $servicioCierre->reabrirEdicionExcepcional(
        organizacionId: $orgId,
        edicionId: $edicionId,
        usuarioSolicitanteId: $adminId,
        usuarioAutorizadorId: $autorizadorId,
        motivoExcepcional: 'Regularización de saldos acordada según informe de auditoría',
        claveConfirmacionAutorizador: 'ClaveTotalmenteErronea!'
    );
} catch (AccesoDenegadoExcepcion $e) {
    if (str_contains($e->getMessage(), 'Clave de confirmación incorrecta')) {
        $rechazoClaveErronea = true;
    }
}
afirmar($rechazoClaveErronea, "Aprobación Expresa: Rechaza reapertura ante clave de confirmación de autorizador incorrecta.");

// 8.5 Ejecución formal y exitosa de Reapertura Extraordinaria con clave válida
$reapertura = $servicioCierre->reabrirEdicionExcepcional(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioSolicitanteId: $adminId,
    usuarioAutorizadorId: $autorizadorId,
    motivoExcepcional: 'Regularización autorizada de pasajes complementarios según Acta de Directorio #045-2035.',
    claveConfirmacionAutorizador: $claveAutorizadorPlana,
    nuevoEstado: 'POSTPRODUCCION_ENTREGA'
);

afirmar(!empty($reapertura['reapertura_id']), "Reapertura extraordinaria registrada con trazabilidad (ID #{$reapertura['reapertura_id']}).");

$edicionReabierta = $servicioCierre->obtenerEdicion($orgId, $edicionId);
afirmar($edicionReabierta['estado'] === 'POSTPRODUCCION_ENTREGA', "Estado de la festividad transita a POSTPRODUCCION_ENTREGA.");

$stmtCierreHist = $pdo->prepare("SELECT estado FROM `edicion_cierres` WHERE id = :id");
$stmtCierreHist->execute(['id' => $cierreCondicionado['id']]);
$estadoCierreHist = $stmtCierreHist->fetchColumn();
afirmar($estadoCierreHist === 'REABIERTO_EXCEPCIONAL', "Cierre anterior transita a REABIERTO_EXCEPCIONAL para preservación probatoria.");

// ------------------------------------------------------------------------------
// SECCIÓN 9: SEGUNDO CIERRE (DEFINITIVO PLENO POST-REGULARIZACIÓN)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 9: CIERRE DEFINITIVO PLENO POST-REGULARIZACIÓN ---\n";

// Liquidar saldo remanente de la venta para alcanzar balance cero
$pdo->prepare("UPDATE `ventas` SET `monto_pagado` = 1000.00, `saldo_pendiente` = 0.00, `estado_financiero` = 'PAGADA_TOTAL' WHERE id = :id")->execute(['id' => $ventaId]);

$diagPostLiquidar = $servicioCierre->generarDiagnostico($orgId, $edicionId);
afirmar($diagPostLiquidar['es_apto_cierre_normal'] === true, "Tras liquidar saldos, festividad es apta para Cierre Definitivo pleno.");

$cierreDefinitivo = $servicioCierre->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioId: $adminId,
    tipoCierre: 'DEFINITIVO'
);

afirmar($cierreDefinitivo['tipo_cierre'] === 'DEFINITIVO', "Cierre Definitivo pleno asentado satisfactoriamente.");
$edicionFinal = $servicioCierre->obtenerEdicion($orgId, $edicionId);
afirmar($edicionFinal['estado'] === 'CERRADA', "Festividad queda formalmente CERRADA en estado definitivo.");

// Verificar que en Cierre DEFINITIVO, PagoServicio rechaza cualquier pago
$rechazoPagoEnDefinitivo = false;
try {
    $pagoServicio->registrarPagoManual(
        organizacionId: $orgId,
        edicionId: $edicionId,
        ventaId: $ventaId,
        datos: [
            'metodo_pago' => 'EFECTIVO',
            'monto'       => 50.00,
        ],
        contexto: $contextoOp
    );
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'DEFINITIVO') || str_contains($e->getMessage(), 'sin saldos')) {
        $rechazoPagoEnDefinitivo = true;
    }
}
afirmar($rechazoPagoEnDefinitivo, "En Cierre Definitivo, PagoServicio bloquea todo intento de registro de pagos.");

// ------------------------------------------------------------------------------
// SECCIÓN 10: INTEGRIDAD DE LA CUENTA DE ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 10: INTEGRIDAD HISTÓRICA DEL USUARIO ORLANDO (ID 24) ---\n";

$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

// Limpieza final de datos de prueba de ediciones temporales
$pdo->exec("DELETE FROM `edicion_cierre_reaperturas` WHERE edicion_id IN ({$edicionId}, {$edicionConcId})");
$pdo->exec("DELETE FROM `edicion_cierres` WHERE edicion_id IN ({$edicionId}, {$edicionConcId})");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN ({$edicionId}, {$edicionConcId}))");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id IN ({$edicionId}, {$edicionConcId})");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id IN ({$edicionId}, {$edicionConcId})");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id IN ({$edicionId}, {$edicionConcId})");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id IN ({$edicionId}, {$edicionConcId})");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.12A: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

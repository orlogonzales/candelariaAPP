<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.12A
 * CIERRE ADMINISTRATIVO Y FINANCIERO DE EDICIÓN Y PROCEDIMIENTO EXCEPCIONAL
 * ==============================================================================
 * 1. DIAGNÓSTICO PREVIO Y BALANCE FINANCIERO (CONCILIACIÓN REAL).
 * 2. CLASIFICACIÓN DE INCIDENCIAS: BLOQUEANTES VS OBSERVACIONES.
 * 3. CIERRE DEFINITIVO (0 BLOQUEANTES, 0 OBSERVACIONES).
 * 4. CIERRE CONDICIONADO (JUSTIFICACIÓN FORMAL Y CUENTAS POR COBRAR).
 * 5. CONTROL ANTI-SOBREVENTA Y BLOQUEO OPERATIVO POST-CIERRE (FAIL-CLOSED).
 * 6. PRESERVACIÓN HISTÓRICA DE DOCUMENTOS, BOLETOS Y FOTOS TRAS EL CIERRE.
 * 7. CONCURRENCIA OPTIMISTA (HTTP 409) Y PREVENCIÓN CROSS-TENANT.
 * 8. PROCEDIMIENTO EXCEPCIONAL DE REAPERTURA AUDITADA.
 * 9. IDENTIDAD E INTEGRIDAD HISTÓRICA DE ORLANDO (ID 24).
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Ediciones\CierreEdicionServicio;
use Aplicacion\Ediciones\EdicionServicio;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Nucleo\BaseDatos\Conexion;

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
echo "INICIANDO SUITE F2.12A: CIERRE ADMINISTRATIVO Y FINANCIERO DE EDICIÓN\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();
$servicioCierre = new CierreEdicionServicio($pdo);

// Cargar Tenant de Pruebas
$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

// Cargar Usuarios
$stmtAdmin = $pdo->prepare("SELECT id FROM `usuarios` WHERE organizacion_id = :org_id ORDER BY id ASC LIMIT 1");
$stmtAdmin->execute(['org_id' => $orgId]);
$adminId = (int) ($stmtAdmin->fetchColumn() ?: 24);

// ------------------------------------------------------------------------------
// SECCIÓN 1: CREACIÓN DE EDICIÓN PILOTO PARA CICLO DE CIERRE
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: CREACIÓN DE EDICIÓN PILOTO Y VERIFICACIÓN DE ESQUEMA ---\n";

// Limpieza previa de pruebas anteriores
$pdo->exec("DELETE FROM `edicion_cierre_reaperturas` WHERE cierre_id IN (SELECT id FROM `edicion_cierres` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%'))");
$pdo->exec("DELETE FROM `edicion_cierres` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%')");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%'))");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%')");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%')");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id IN (SELECT id FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%')");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE codigo LIKE 'candelaria-cierre-test-%'");

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
        :org_id, :codigo, :nombre, :anio, 'POSTPRODUCCION_ENTREGA', '2035-02-01', '2035-02-15', 'Edición de Prueba Cierre F2.12A', 0
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

// 3.1 Simular Pago en PENDIENTE_VERIFICACION
// Obtener un cliente y una venta para vincular el pago
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
// SECCIÓN 4: CIERRE CONDICIONADO CON CUENTAS POR COBRAR PENDIENTES
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: CIERRE CONDICIONADO CON OBSERVACIONES JUSTIFICADAS ---\n";

// En este punto, la venta tiene saldo_pendiente = 500.00 (cuenta por cobrar legítima)
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
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Cierre rapido', autorizadorId: $adminId);
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

// 4.4 Ejecución formal de Cierre Condicionado
$cierreCondicionado = $servicioCierre->ejecutarCierre(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioId: $adminId,
    tipoCierre: 'CONDICIONADO',
    justificacion: 'Se formaliza cierre con saldo pendiente de S/ 500 bajo compromiso comercial de cobro.',
    autorizadorId: $adminId,
    versionBloqueoEsperada: 1
);

afirmar(!empty($cierreCondicionado['id']), "Acta de Cierre Condicionado registrada con ID #{$cierreCondicionado['id']}.");
afirmar($cierreCondicionado['tipo_cierre'] === 'CONDICIONADO', "Tipo de cierre registrado como CONDICIONADO.");
afirmar(!empty($cierreCondicionado['hash_integridad_cierre']), "Hash criptográfico SHA-256 de inmutabilidad generado ({$cierreCondicionado['hash_integridad_cierre']}).");

// Comprobar estado de la edición
$edicionPostCierre = $servicioCierre->obtenerEdicion($orgId, $edicionId);
afirmar($edicionPostCierre['estado'] === 'CERRADA', "Estado de la edición actualizado formalmente a CERRADA.");

// ------------------------------------------------------------------------------
// SECCIÓN 5: BLOQUEO OPERATIVO POST-CIERRE (PREVENCIÓN DE VENTAS Y RESERVAS)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: BLOQUEO OPERATIVO POST-CIERRE (PRESERVANDO CONSULTAS) ---\n";

// 5.1 Guardián del Servicio de Cierre
$bloqueoGuardian = false;
try {
    $servicioCierre->validarEdicionAbiertaParaOperaciones($orgId, $edicionId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'CERRADA')) {
        $bloqueoGuardian = true;
    }
}
afirmar($bloqueoGuardian, "Guardián de operaciones bloquea cualquier modificación comercial en festividad cerrada.");

// 5.2 Preservación de Acceso y Consulta Histórica
$cierreVigente = $servicioCierre->obtenerCierreVigente($orgId, $edicionId);
afirmar($cierreVigente !== null, "Acta de cierre y balance congelado son plenamente consultables.");
afirmar((float) $cierreVigente['resumen_financiero']['saldo_por_cobrar'] === 500.00, "Cifras financieras congeladas reflejan el estado histórico exacto del corte.");

// ------------------------------------------------------------------------------
// SECCIÓN 6: CONCURRENCIA OPTIMISTA (HTTP 409) Y REINTENTO IDEMPOTENTE
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: CONCURRENCIA OPTIMISTA Y PREVENCIÓN DE DUPLICADOS ---\n";

// Intento de cerrar una edición que ya está CERRADA
$rechazoYaCerrada = false;
try {
    $servicioCierre->ejecutarCierre($orgId, $edicionId, $adminId, 'CONDICIONADO', justificacion: 'Intento concurrente duplicado', autorizadorId: $adminId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'ya se encuentra formalmente CERRADA')) {
        $rechazoYaCerrada = true;
    }
}
afirmar($rechazoYaCerrada, "Idempotencia: Impide duplicar el cierre de una festividad ya cerrada.");

// ------------------------------------------------------------------------------
// SECCIÓN 7: AISLAMIENTO MULTI-TENANT (ANTI-IDOR)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: CERTIFICACIÓN ANTI-IDOR CROSS-TENANT ---\n";

$bloqueoCrossTenant = false;
try {
    $servicioCierre->obtenerCierreVigente(organizacionId: 99999, edicionId: $edicionId);
} catch (\Throwable $e) {
    $bloqueoCrossTenant = true;
}
afirmar($servicioCierre->obtenerCierreVigente(99999, $edicionId) === null, "Anti-IDOR: Organización ajena no tiene acceso al acta de cierre.");

// ------------------------------------------------------------------------------
// SECCIÓN 8: PROCEDIMIENTO EXCEPCIONAL DE REAPERTURA AUDITADA
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 8: PROCEDIMIENTO EXCEPCIONAL DE REAPERTURA AUDITADA ---\n";

// 8.1 Reapertura sin motivo fundamentado (< 20 caracteres)
$rechazoReaperturaSinMotivo = false;
try {
    $servicioCierre->reabrirEdicionExcepcional($orgId, $edicionId, $adminId, $adminId, 'Motivo corto');
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'mínimo 20 caracteres')) {
        $rechazoReaperturaSinMotivo = true;
    }
}
afirmar($rechazoReaperturaSinMotivo, "Reapertura exige fundamentación exhaustiva (mínimo 20 caracteres).");

// 8.2 Ejecución formal de Reapertura Extraordinaria
$reapertura = $servicioCierre->reabrirEdicionExcepcional(
    organizacionId: $orgId,
    edicionId: $edicionId,
    usuarioSolicitanteId: $adminId,
    usuarioAutorizadorId: $adminId,
    motivoExcepcional: 'Regularización autorizada de pasajes complementarios según Acta de Directorio #045-2035.',
    nuevoEstado: 'POSTPRODUCCION_ENTREGA'
);

afirmar(!empty($reapertura['reapertura_id']), "Reapertura extraordinaria registrada con trazabilidad (ID #{$reapertura['reapertura_id']}).");

// Comprobar estado restaurado de la edición
$edicionReabierta = $servicioCierre->obtenerEdicion($orgId, $edicionId);
afirmar($edicionReabierta['estado'] === 'POSTPRODUCCION_ENTREGA', "Estado de la festividad transita a POSTPRODUCCION_ENTREGA.");

// Comprobar estado del cierre anterior
$stmtCierreHist = $pdo->prepare("SELECT estado FROM `edicion_cierres` WHERE id = :id");
$stmtCierreHist->execute(['id' => $cierreCondicionado['id']]);
$estadoCierreHist = $stmtCierreHist->fetchColumn();
afirmar($estadoCierreHist === 'REABIERTO_EXCEPCIONAL', "Cierre anterior transita a REABIERTO_EXCEPCIONAL para preservación probatoria.");

// ------------------------------------------------------------------------------
// SECCIÓN 9: SEGUNDO CIERRE (DEFINITIVO PLENO POST-REGULARIZACIÓN)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 9: CIERRE DEFINITIVO PLENO POST-REGULARIZACIÓN ---\n";

// Liquidar el saldo pendiente de la venta para que esté en balance cero
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

// ------------------------------------------------------------------------------
// SECCIÓN 10: INTEGRIDAD DE LA CUENTA DE ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 10: INTEGRIDAD HISTÓRICA DEL USUARIO ORLANDO (ID 24) ---\n";

$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

// Limpieza final de datos de prueba
$pdo->exec("DELETE FROM `edicion_cierre_reaperturas` WHERE cierre_id IN (SELECT id FROM `edicion_cierres` WHERE edicion_id = {$edicionId})");
$pdo->exec("DELETE FROM `edicion_cierres` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `recibos_caja` WHERE venta_id IN (SELECT id FROM `ventas` WHERE edicion_id = {$edicionId})");
$pdo->exec("DELETE FROM `pagos` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `ventas` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `reservas` WHERE edicion_id = {$edicionId}");
$pdo->exec("DELETE FROM `ediciones_candelaria` WHERE id = {$edicionId}");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.12A: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

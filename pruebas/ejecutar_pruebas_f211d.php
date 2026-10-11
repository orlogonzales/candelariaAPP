<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.11D (HARDENING DE POSTPRODUCCIÓN)
 * NÚCLEO:
 * 1. AUDITORÍA Y CONCILIACIÓN DE ESTADOS CANÓNICOS (VENTA, FINANZAS, PAGO, RESERVA, CONTRATO).
 * 2. LÍMITES DE ALMACENAMIENTO, CUOTAS POR ORGANIZACIÓN Y LIMPIEZA DE INCOMPLETOS.
 * 3. VALIDACIÓN DE CONTENIDO REAL (MAGIC BYTES) Y PREVENCIÓN DE EJECUCIÓN.
 * 4. CERTIFICACIÓN ANTI-IDOR (CROSS-TENANT, CROSS-CLIENT, HMAC VENCIDO/MANIPULADO).
 * 5. POLÍTICA DE RETENCIÓN, ARCHIVO, REVOCACIÓN Y SUPRESIÓN AUTORIZADA (LEY 29733).
 * 6. CONCURRENCIA OPTIMISTA (HTTP 409) EN RESPUESTAS Y APROBACIONES DE ENTREGAS.
 * 7. INTEGRIDAD HISTÓRICA DE LA CUENTA DE ORLANDO (ID 24).
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver;
use Aplicacion\Postproduccion\PostproduccionServicio;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Ventas\EstadoVenta;
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
echo "INICIANDO SUITE F2.11D: HARDENING DE POSTPRODUCCIÓN Y CONCILIACIÓN CANÓNICA\n";
echo "==============================================================================\n";

$pdo = Conexion::obtenerInstancia();
$driver = new AlmacenamientoLocalDriver();
$servicio = new PostproduccionServicio($pdo, $driver);

// ------------------------------------------------------------------------------
// SECCIÓN 1: AUDITORÍA Y CONCILIACIÓN DE ESTADOS CANÓNICOS
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 1: AUDITORÍA Y CONCILIACIÓN DE ESTADOS CANÓNICOS ---\n";

// 1.1 Estado Comercial de Venta (EstadoVenta)
afirmar(EstadoVenta::tryFrom('CONFIRMADA') !== null, "EstadoVenta contiene CONFIRMADA.");
afirmar(EstadoVenta::tryFrom('LIQUIDADA') !== null, "EstadoVenta contiene LIQUIDADA.");
afirmar(EstadoVenta::tryFrom('CANCELADA') !== null, "EstadoVenta contiene CANCELADA.");
afirmar(EstadoVenta::tryFrom('ANULADA') !== null, "EstadoVenta contiene ANULADA.");
afirmar(EstadoVenta::tryFrom('PENDIENTE') === null, "Regla Canónica: EstadoVenta NO contiene PENDIENTE (prohibición de regresión).");

// 1.2 Estado Financiero de Venta (EstadoFinancieroVenta)
afirmar(EstadoFinancieroVenta::tryFrom('NO_PAGADA') !== null, "EstadoFinancieroVenta contiene NO_PAGADA.");
afirmar(EstadoFinancieroVenta::tryFrom('PAGO_PARCIAL') !== null, "EstadoFinancieroVenta contiene PAGO_PARCIAL.");
afirmar(EstadoFinancieroVenta::tryFrom('PAGADA_TOTAL') !== null, "EstadoFinancieroVenta contiene PAGADA_TOTAL.");
afirmar(EstadoFinancieroVenta::tryFrom('SOBREPAGADA') !== null, "EstadoFinancieroVenta contiene SOBREPAGADA.");
afirmar(EstadoFinancieroVenta::tryFrom('PENDIENTE') === null, "Regla Canónica: EstadoFinancieroVenta NO contiene PENDIENTE.");

// 1.3 Estado de Pago (EstadoPago)
afirmar(EstadoPago::tryFrom('PENDIENTE_VERIFICACION') !== null, "EstadoPago contiene PENDIENTE_VERIFICACION.");
afirmar(EstadoPago::tryFrom('APROBADO') !== null, "EstadoPago contiene APROBADO.");
afirmar(EstadoPago::tryFrom('RECHAZADO') !== null, "EstadoPago contiene RECHAZADO.");
afirmar(EstadoPago::tryFrom('ANULADO') !== null, "EstadoPago contiene ANULADO.");

// 1.4 Estado de Reserva (EstadoReserva)
afirmar(EstadoReserva::tryFrom('REGISTRADA') !== null, "EstadoReserva contiene REGISTRADA.");
afirmar(EstadoReserva::tryFrom('PENDIENTE_DATOS') !== null, "EstadoReserva contiene PENDIENTE_DATOS.");
afirmar(EstadoReserva::tryFrom('CONFIRMADA') !== null, "EstadoReserva contiene CONFIRMADA.");
afirmar(EstadoReserva::tryFrom('CANCELADA') !== null, "EstadoReserva contiene CANCELADA.");

// 1.5 Estados de Contratos Turísticos en base de datos
$stmtContratoEstados = $pdo->query("SHOW COLUMNS FROM `contratos_venta` LIKE 'estado_contrato'");
$colContrato = $stmtContratoEstados->fetch(PDO::FETCH_ASSOC);
afirmar(str_contains((string)$colContrato['Type'], "'BORRADOR','EMITIDO','ACEPTADO','ANULADO'"), "contratos_venta modela exactamente el catálogo canónico (BORRADOR, EMITIDO, ACEPTADO, ANULADO).");

// ------------------------------------------------------------------------------
// SECCIÓN 2: LÍMITES DE ALMACENAMIENTO, CUOTAS Y ARCHIVOS INCOMPLETOS
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: CUOTAS, LÍMITES Y LIMPIEZA DE ARCHIVOS INCOMPLETOS ---\n";

// Cargar tenant de prueba
$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

$stmtEd = $pdo->prepare("SELECT id FROM `ediciones_candelaria` WHERE organizacion_id = :org_id ORDER BY id DESC LIMIT 1");
$stmtEd->execute(['org_id' => $orgId]);
$edId = (int) $stmtEd->fetchColumn();

$stmtCli = $pdo->prepare("SELECT id FROM `clientes` WHERE organizacion_id = :org_id LIMIT 1");
$stmtCli->execute(['org_id' => $orgId]);
$clienteId = (int) $stmtCli->fetchColumn();

$proyecto = $servicio->crearProyecto(
    $orgId,
    $edId,
    $clienteId,
    titulo: 'Proyecto Hardening Postproducción F2.11D',
    descripcion: 'Pruebas de cuotas, magic bytes y concurrencia'
);
$proyectoId = (int) $proyecto['id'];

$galeria = $servicio->crearGaleria($orgId, $proyectoId, titulo: 'Álbum Seguridad');
$galeriaId = (int) $galeria['id'];

// 2.1 Verificación de límite de cuota configurada
// Configurar temporalmente cuota mínima de 1 MB para verificar corte
$pdo->prepare("
    INSERT INTO `parametros_configuracion` (`organizacion_id`, `ambito`, `codigo`, `tipo_dato`, `valor`, `etiqueta`, `descripcion`, `es_sistema`, `es_publico`, `es_editable`, `estado`)
    VALUES (:org_id, 'ORGANIZACION', 'postproduccion.cuota_almacenamiento_mb', 'INTEGER', '1', 'Cuota Postproducción', 'Cuota de prueba', 1, 0, 1, 'ACTIVO')
    ON DUPLICATE KEY UPDATE `valor` = '1', `estado` = 'ACTIVO'
")->execute(['org_id' => $orgId]);

// Simular que el uso actual ya supera la cuota de 1 MB
$pdo->prepare("UPDATE `postproduccion_proyectos` SET `peso_total_bytes` = 2000000 WHERE id = :id")->execute(['id' => $proyectoId]);

$bloqueoCuota = false;
try {
    $streamTemp = fopen('php://memory', 'r+');
    fwrite($streamTemp, str_repeat("A", 1024));
    rewind($streamTemp);
    $servicio->subirArchivoStream(
        $orgId,
        $proyectoId,
        $galeriaId,
        nombreOriginal: 'prueba_cuota.jpg',
        streamOrigen: $streamTemp,
        mimeType: 'image/jpeg'
    );
    fclose($streamTemp);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'Cuota de almacenamiento excedida')) {
        $bloqueoCuota = true;
    }
}
afirmar($bloqueoCuota, "Fail-Closed: Bloquea subida si la organización supera la cuota máxima permitida.");

// Restaurar cuota amplia
$pdo->prepare("UPDATE `parametros_configuracion` SET `valor` = '10240' WHERE `organizacion_id` = :org_id AND `codigo` = 'postproduccion.cuota_almacenamiento_mb'")->execute(['org_id' => $orgId]);
$pdo->prepare("UPDATE `postproduccion_proyectos` SET `peso_total_bytes` = 0 WHERE id = :id")->execute(['id' => $proyectoId]);

// 2.2 Limpieza estricta de archivos incompletos / rotos
$rutaArchivoRoto = "{$orgId}/proyectos/{$proyectoId}/galerias/{$galeriaId}/archivo_fallido_test.bin";
$streamSimuladoError = fopen('php://memory', 'r+');
// Un payload que dispara excepción por validación de seguridad a nivel stream
fwrite($streamSimuladoError, "<?php echo 'fallo de prueba'; ?>");
rewind($streamSimuladoError);

$falloCapturado = false;
try {
    $driver->guardarStream($streamSimuladoError, $rutaArchivoRoto);
} catch (\Throwable $e) {
    $falloCapturado = true;
}
fclose($streamSimuladoError);
afirmar($falloCapturado && !$driver->existe($rutaArchivoRoto), "Limpieza Segura: Si ocurre interrupción o error en el streaming, el archivo incompleto es eliminado de inmediato del disco.");

// ------------------------------------------------------------------------------
// SECCIÓN 3: VALIDACIÓN DE CONTENIDO REAL (MAGIC BYTES) Y AISLAMIENTO
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: VALIDACIÓN DE CONTENIDO REAL (MAGIC BYTES) ---\n";

// 3.1 Archivo con extensión .jpg o MIME simulado pero con código PHP real
$streamCamuflado = fopen('php://memory', 'r+');
fwrite($streamCamuflado, "<?php echo 'malicious payload'; system(\$_GET['cmd']); ?>");
rewind($streamCamuflado);

$bloqueoMagic = false;
try {
    $servicio->subirArchivoStream(
        $orgId,
        $proyectoId,
        $galeriaId,
        nombreOriginal: 'foto_paisaje.jpg',
        streamOrigen: $streamCamuflado,
        mimeType: 'image/jpeg'
    );
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'código ejecutable no permitido') || str_contains($e->getMessage(), 'Tipo MIME no permitido')) {
        $bloqueoMagic = true;
    }
}
fclose($streamCamuflado);
afirmar($bloqueoMagic, "Magic Bytes Inspection: Detecta payload ejecutable encubierto y rechaza la subida independientemente de la extensión.");

// 3.2 Almacenamiento fuera de la ruta pública
$streamLegitimo = fopen('php://memory', 'r+');
// Cabecera JPEG válida (\xFF\xD8\xFF\xE0)
fwrite($streamLegitimo, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . str_repeat("\xAA", 1024));
rewind($streamLegitimo);

$archivoSubido = $servicio->subirArchivoStream(
    $orgId,
    $proyectoId,
    $galeriaId,
    nombreOriginal: 'danzante_real.jpg',
    streamOrigen: $streamLegitimo,
    mimeType: 'image/jpeg'
);
fclose($streamLegitimo);

afirmar(!str_starts_with($archivoSubido['ruta_privada'], 'publico/'), "Almacenamiento seguro: El archivo se guarda en ruta privada inaccesible vía HTTP directo.");

// ------------------------------------------------------------------------------
// SECCIÓN 4: CERTIFICACIÓN ANTI-IDOR Y AISLAMIENTO MULTITENANT
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: CERTIFICACIÓN ANTI-IDOR (CROSS-TENANT Y CROSS-CLIENT) ---\n";

$archivoId = (int) $archivoSubido['id'];

// 4.1 Cross-Tenant IDOR: Tenant ajeno intenta descargar el archivo
$bloqueoCrossTenant = false;
try {
    $servicio->obtenerStreamDescarga(
        organizacionId: 99999, // Organización ajena
        archivoId: $archivoId,
        clienteId: $clienteId
    );
} catch (\InvalidArgumentException $e) {
    $bloqueoCrossTenant = true;
}
afirmar($bloqueoCrossTenant, "Anti-IDOR Cross-Tenant: Bloquea acceso a recursos de otra organización.");

// 4.2 Cross-Client IDOR: Cliente ajeno de la misma organización
$bloqueoCrossClient = false;
try {
    $servicio->obtenerStreamDescarga(
        organizacionId: $orgId,
        archivoId: $archivoId,
        clienteId: 88888 // Cliente no titular
    );
} catch (\InvalidArgumentException $e) {
    $bloqueoCrossClient = true;
}
afirmar($bloqueoCrossClient, "Anti-IDOR Cross-Client: Bloquea acceso a clientes no autorizados de la misma organización.");

// 4.3 Validación de Token HMAC temporal
$tokenTemporal = $servicio->generarTokenTemporalCliente($orgId, $archivoId, $clienteId, segundos: 60);
afirmar(!empty($tokenTemporal), "Token temporal HMAC generado exitosamente.");

$tokenAlterado = substr($tokenTemporal, 0, -4) . 'ZZZZ';
$bloqueoTokenFalso = false;
try {
    $servicio->procesarDescargaPorToken($orgId, $tokenAlterado, '127.0.0.1', 'Audit-Agent');
} catch (\InvalidArgumentException $e) {
    $bloqueoTokenFalso = true;
}
afirmar($bloqueoTokenFalso, "Fail-Closed: Token temporal con firma criptográfica manipulada es rechazado.");

// ------------------------------------------------------------------------------
// SECCIÓN 5: POLÍTICAS DE RETENCIÓN, ARCHIVO, REVOCACIÓN Y LEY 29733
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: RETENCIÓN, REVOCACIÓN Y SUPRESIÓN AUTORIZADA (LEY 29733) ---\n";

// 5.1 Verificar fecha de retención configurada
afirmar(!empty($archivoSubido['fecha_retencion_hasta']), "Fecha de retención legal registrada automáticamente ({$archivoSubido['fecha_retencion_hasta']}).");

// 5.2 Archivar archivo (conservación histórica pasiva)
$archivoArchivado = $servicio->archivarArchivo($orgId, $archivoId, 1);
afirmar($archivoArchivado['estado'] === 'ARCHIVADO', "Archivo transita satisfactoriamente a estado ARCHIVADO.");

// 5.3 Revocar archivo por seguridad/disputa
$archivoRevocado = $servicio->revocarArchivo($orgId, $archivoId, 1, 'Revocado por orden de auditoría');
afirmar($archivoRevocado['estado'] === 'REVOCADO', "Archivo transita satisfactoriamente a estado REVOCADO.");

// Comprobar que la descarga se bloquea al estar revocado
$bloqueoRevocado = false;
try {
    $servicio->obtenerStreamDescarga($orgId, $archivoId, $clienteId);
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'ha sido revocado')) {
        $bloqueoRevocado = true;
    }
}
afirmar($bloqueoRevocado, "Seguridad: Archivo revocado no permite descarga por ningún titular.");

// Restaurar temporalmente a ACTIVO para probar supresión
$pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'ACTIVO' WHERE id = :id")->execute(['id' => $archivoId]);

// 5.4 Solicitud de supresión de datos personales (Ley 29733)
$solicitudSupresion = $servicio->solicitarSupresionArchivo(
    $orgId,
    $archivoId,
    solicitanteTipo: 'CLIENTE',
    solicitanteId: $clienteId,
    motivo: 'Ejercicio del derecho de supresión / cancelación conforme a la Ley 29733',
    ip: '190.237.55.99'
);
afirmar(!empty($solicitudSupresion['id']), "Solicitud de supresión de datos registrada con trazabilidad en postproduccion_solicitudes_supresion.");
afirmar($solicitudSupresion['estado'] === 'PENDIENTE', "Solicitud de supresión se encuentra en estado PENDIENTE de autorización.");

// 5.5 Ejecución autorizada de supresión física
$rutaFisicaAntes = $archivoSubido['ruta_privada'];
$stmtAdmin = $pdo->prepare("SELECT id FROM `usuarios` WHERE `organizacion_id` = :org_id ORDER BY id ASC LIMIT 1");
$stmtAdmin->execute(['org_id' => $orgId]);
$adminId = (int) ($stmtAdmin->fetchColumn() ?: 24);

$supresionEjecutada = $servicio->ejecutarSupresionAutorizada(
    $orgId,
    (int) $solicitudSupresion['id'],
    usuarioAutorizadorId: $adminId
);
afirmar($supresionEjecutada['estado'] === 'APROBADA_EJECUTADA', "Solicitud de supresión transita a APROBADA_EJECUTADA.");
afirmar(!empty($supresionEjecutada['checksum_sha256_purgado']), "Hash criptográfico preservado en bitácora para evidencia probatoria.");
afirmar(!$driver->existe($rutaFisicaAntes), "Destrucción física comprobada: El archivo binario fue borrado completamente del disco.");

$archivoDespues = $servicio->obtenerArchivo($orgId, $archivoId);
afirmar($archivoDespues['estado'] === 'ELIMINADO_SOLICITUD', "Estado del archivo actualizado a ELIMINADO_SOLICITUD.");
afirmar(empty($archivoDespues['ruta_privada']), "Ruta privada del archivo blanqueada en la base de datos.");

// ------------------------------------------------------------------------------
// SECCIÓN 6: CONCURRENCIA OPTIMISTA EN ENTREGAS Y OBSERVACIONES
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: CONCURRENCIA OPTIMISTA EN ENTREGAS (HTTP 409) ---\n";

// Crear un archivo activo para la entrega
$streamNuevo = fopen('php://memory', 'r+');
fwrite($streamNuevo, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . str_repeat("\xBB", 2048));
rewind($streamNuevo);
$archivoNuevo = $servicio->subirArchivoStream(
    $orgId,
    $proyectoId,
    $galeriaId,
    nombreOriginal: 'foto_entrega_concurrente.jpg',
    streamOrigen: $streamNuevo,
    mimeType: 'image/jpeg'
);
fclose($streamNuevo);

$entrega = $servicio->crearEntrega(
    $orgId,
    $proyectoId,
    titulo: 'Entrega para Prueba de Concurrencia Optimista',
    tipoEntrega: 'PARCIAL',
    archivoIds: [(int) $archivoNuevo['id']]
);
$entregaId = (int) $entrega['id'];

afirmar((int) $entrega['version_bloqueo'] === 1, "Entrega creada con version_bloqueo inicial = 1.");

// Proceso A responde la entrega esperando version_bloqueo = 1
$entregaRespA = $servicio->responderEntregaCliente(
    $orgId,
    $entregaId,
    $clienteId,
    aprobada: false,
    observaciones: 'Observación del Proceso A',
    versionBloqueoEsperada: 1
);
afirmar($entregaRespA['estado_entrega'] === 'OBSERVADA_CLIENTE', "Proceso A registra observación y actualiza versión.");
afirmar((int) $entregaRespA['version_bloqueo'] === 2, "version_bloqueo incrementada a 2 tras actualización exitosa.");

// Proceso B intenta responder simultáneamente sobre la versión 1 (ya desfasada)
$colisionDetectada = false;
try {
    $servicio->responderEntregaCliente(
        $orgId,
        $entregaId,
        $clienteId,
        aprobada: true,
        versionBloqueoEsperada: 1 // Versión desfasada
    );
} catch (ConflictoConcurrenciaExcepcion $e) {
    $colisionDetectada = true;
}
afirmar($colisionDetectada, "Concurrencia Optimista: Proceso B es rechazado con ConflictoConcurrenciaExcepcion (HTTP 409).");

// ------------------------------------------------------------------------------
// SECCIÓN 7: HUELLA DIGITAL DE ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: VERIFICACIÓN DE IDENTIDAD E INTEGRIDAD HISTÓRICA ---\n";

$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.11D: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

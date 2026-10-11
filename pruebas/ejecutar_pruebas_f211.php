<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 2.11 (POSTPRODUCCIÓN Y GALERÍA MULTIMEDIA)
 * NÚCLEO: ARQUITECTURA DE ALMACENAMIENTO POR STREAMS, VALIDACIÓN MIME, CHECKSUMS SHA-256,
 *         PROYECTOS AUDIOVISUALES, GALERÍAS DE FOTO Y VIDEO, ENTREGAS PARCIALES,
 *         DESCARGAS SEGURAS ANTI-IDOR, TOKENS TEMPORALES, ACCESO HISTÓRICO EN EDICIONES CERRADAS,
 *         CONTROL RBAC (MÓDULO 24) E INTEGRIDAD DE CUENTA ORLANDO (ID 24).
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver;
use Aplicacion\Postproduccion\PostproduccionServicio;
use Nucleo\BaseDatos\Conexion;

$pruebasEjecutadas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function afirmar(bool $condicion, string $descripcion, string $detalleError = ''): void {
    global $pruebasEjecutadas, $pruebasExitosas, $pruebasFallidas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "INICIANDO SUITE F2.11: POSTPRODUCCIÓN Y GALERÍA MULTIMEDIA DE CLIENTES\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$driver = new AlmacenamientoLocalDriver();
$servicio = new PostproduccionServicio($pdo, $driver);

// ------------------------------------------------------------------------------
// SECCIÓN 1: ALMACENAMIENTO DESACOPLADO POR STREAMS Y CHECKSUM SHA-256 (F2.11A)
// ------------------------------------------------------------------------------
echo "--- SECCIÓN 1: ALMACENAMIENTO POR STREAMS Y SEGURIDAD TEMPORAL ---\n";

// 1.1 Prueba de escritura por stream
$streamSimulado = fopen('php://memory', 'r+');
$contenidoPrueba = str_repeat("CANDELARIA_2026_MULTIMEDIA_STREAM_BUFFER_DATA_", 1000); // ~46 KB
fwrite($streamSimulado, $contenidoPrueba);
rewind($streamSimulado);

$hashEsperado = hash('sha256', $contenidoPrueba);
$guardadoStream = $driver->guardarStream($streamSimulado, 'test_tenant/proyectos/1/test_chunk.bin');
fclose($streamSimulado);

afirmar(file_exists($guardadoStream['ruta_absoluta']), "Driver almacena archivo físicamente mediante streams.");
afirmar($guardadoStream['checksum_sha256'] === $hashEsperado, "Checksum SHA-256 calculado al vuelo coincide exactamente con los datos transmitidos.");
afirmar($guardadoStream['peso_bytes'] === strlen($contenidoPrueba), "Tamaño en bytes registrado con precisión ({$guardadoStream['peso_bytes']} bytes).");

// 1.2 Prueba de lectura por stream
$streamLectura = $driver->obtenerStream($guardadoStream['ruta_relativa']);
afirmar(is_resource($streamLectura), "Driver retorna recurso stream válido para lectura segura.");
$leido = stream_get_contents($streamLectura);
fclose($streamLectura);
afirmar($leido === $contenidoPrueba, "Contenido leído por stream coincide byte a byte con el original.");

// 1.3 Tokens de descarga temporales con firma HMAC
$tokenTemp = $driver->generarTokenDescargaTemporal(archivoId: 999, clienteId: 777, expiracionSegundos: 3600);
afirmar(!empty($tokenTemp) && str_contains($tokenTemp, '.'), "Token temporal generado con estructura de payload firmado.");

$tokenValido = $driver->validarTokenDescargaTemporal($tokenTemp);
afirmar($tokenValido !== null, "Token temporal válido es verificado exitosamente por el driver.");
afirmar($tokenValido['archivo_id'] === 999 && $tokenValido['cliente_id'] === 777, "Metadatos de archivo y cliente extraídos íntegros del token.");

// Token expirado
$tokenExpirado = $driver->generarTokenDescargaTemporal(archivoId: 999, clienteId: 777, expiracionSegundos: -10);
$tokenInvalido = $driver->validarTokenDescargaTemporal($tokenExpirado);
afirmar($tokenInvalido === null, "Token temporal vencido es rechazado de inmediato (fail-closed).");

// Token manipulado
$tokenManipulado = substr($tokenTemp, 0, -5) . 'AAAAA';
afirmar($driver->validarTokenDescargaTemporal($tokenManipulado) === null, "Token con firma manipulada es rechazado.");

// Limpieza de archivo de prueba
$driver->eliminar($guardadoStream['ruta_relativa']);

// ------------------------------------------------------------------------------
// SECCIÓN 2: DOMINIO DE PROYECTOS Y EDICIONES (F2.11A - PARTE 2)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 2: REGISTRO DE PROYECTOS Y VÍNCULO CON RESERVAS/CLIENTES ---\n";

// Cargar tenant, edición y cliente de prueba
$stmtEd = $pdo->query("SELECT id, organizacion_id FROM `ediciones_candelaria` WHERE estado != 'CERRADA' ORDER BY id DESC LIMIT 1");
$edicion = $stmtEd->fetch(PDO::FETCH_ASSOC);

$orgId = (int) ($edicion['organizacion_id'] ?? 10000);
$edId = (int) ($edicion['id'] ?? 1);

$stmtCli = $pdo->prepare("SELECT id FROM `clientes` WHERE organizacion_id = :org_id LIMIT 1");
$stmtCli->execute(['org_id' => $orgId]);
$clienteId = (int) $stmtCli->fetchColumn();

// 2.1 Crear proyecto audiovisual
$proyecto = $servicio->crearProyecto(
    $orgId,
    $edId,
    $clienteId,
    titulo: 'Cobertura Audiovisual Danza Diablada Puneña',
    descripcion: 'Sesión fotográfica y video documental 4K para cliente titular',
    fechaLimiteEntrega: date('Y-m-d', strtotime('+15 days'))
);

afirmar(!empty($proyecto['id']), "Proyecto de postproducción creado exitosamente (ID: {$proyecto['id']}).");
afirmar(str_starts_with($proyecto['codigo_proyecto'], 'PRY-POST-'), "Correlativo de proyecto cumple formato PRY-POST-YYYY-NNNN ({$proyecto['codigo_proyecto']}).");
afirmar($proyecto['estado'] === 'EN_EDICION', "Estado inicial del proyecto es EN_EDICION.");
afirmar((int) $proyecto['cliente_id'] === $clienteId, "Proyecto vinculado formalmente al cliente titular.");

$proyectoId = (int) $proyecto['id'];

// 2.2 Crear Galerías temáticas dentro del proyecto
$galeriaFoto = $servicio->crearGaleria(
    $orgId,
    $proyectoId,
    titulo: 'Sesión Lago Titicaca y Templo Santiago',
    tipoGaleria: 'FOTOGRAFIA',
    descripcion: 'Fotos de alta resolución en exteriores'
);
afirmar(!empty($galeriaFoto['id']), "Galería de fotos creada exitosamente.");
afirmar($galeriaFoto['tipo_galeria'] === 'FOTOGRAFIA', "Tipo de galería registrado como FOTOGRAFIA.");

$galeriaVideo = $servicio->crearGaleria(
    $orgId,
    $proyectoId,
    titulo: 'Clips y Reels 4K Procesión Virgen de la Candelaria',
    tipoGaleria: 'VIDEO',
    descripcion: 'Tomas cinematográficas de pasacalle'
);
afirmar(!empty($galeriaVideo['id']), "Galería de video creada exitosamente.");
afirmar($galeriaVideo['tipo_galeria'] === 'VIDEO', "Tipo de galería registrado como VIDEO.");

// ------------------------------------------------------------------------------
// SECCIÓN 3: SUBIDA DE ARCHIVOS CON WHITELIST MIME Y STREAMS (F2.11A - PARTE 3)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 3: SUBIDA DE ARCHIVOS MULTIMEDIA Y WHITELIST MIME ---\n";

// 3.1 Subir fotografía válida (JPEG)
$streamFoto = fopen('php://memory', 'r+');
$bytesFoto = "SIMULATED_JPEG_IMAGE_RAW_BINARY_DATA_RGB_" . uniqid();
fwrite($streamFoto, $bytesFoto);
rewind($streamFoto);

$archivoFoto = $servicio->subirArchivoStream(
    $orgId,
    $proyectoId,
    (int) $galeriaFoto['id'],
    nombreOriginal: 'sesion_lago_01.jpg',
    streamOrigen: $streamFoto,
    mimeType: 'image/jpeg',
    anchoPx: 6000,
    altoPx: 4000
);
fclose($streamFoto);

afirmar(!empty($archivoFoto['id']), "Fotografía de alta resolución registrada en la base de datos.");
afirmar($archivoFoto['tipo_archivo'] === 'FOTO', "Tipo clasificado como FOTO según MIME.");
afirmar($archivoFoto['mime_type'] === 'image/jpeg', "MIME type verificado.");
afirmar((int) $archivoFoto['ancho_px'] === 6000 && (int) $archivoFoto['alto_px'] === 4000, "Resolución de imagen registrada.");

// 3.2 Subir video válido (MP4)
$streamVideo = fopen('php://memory', 'r+');
$bytesVideo = "SIMULATED_MP4_VIDEO_H265_BINARY_STREAM_" . uniqid();
fwrite($streamVideo, $bytesVideo);
rewind($streamVideo);

$archivoVideo = $servicio->subirArchivoStream(
    $orgId,
    $proyectoId,
    (int) $galeriaVideo['id'],
    nombreOriginal: 'reel_candelaria_4k.mp4',
    streamOrigen: $streamVideo,
    mimeType: 'video/mp4',
    duracionSegundos: 120
);
fclose($streamVideo);

afirmar(!empty($archivoVideo['id']), "Video clip registrado en la base de datos.");
afirmar($archivoVideo['tipo_archivo'] === 'VIDEO', "Tipo clasificado como VIDEO.");
afirmar((int) $archivoVideo['duracion_segundos'] === 120, "Duración en segundos registrada.");

// 3.3 Rechazo de archivo no permitido (Fail-Closed en Whitelist)
$bloqueoMime = false;
try {
    $streamMalicioso = fopen('php://memory', 'r+');
    fwrite($streamMalicioso, "<?php phpinfo(); ?>");
    rewind($streamMalicioso);
    $servicio->subirArchivoStream(
        $orgId,
        $proyectoId,
        (int) $galeriaFoto['id'],
        nombreOriginal: 'exploit.php',
        streamOrigen: $streamMalicioso,
        mimeType: 'application/x-php'
    );
    fclose($streamMalicioso);
} catch (\InvalidArgumentException $e) {
    $bloqueoMime = true;
}
afirmar($bloqueoMime, "Fail-Closed: Bloquea subida de archivos ejecutables o no permitidos.");

// 3.4 Verificar totales del proyecto actualizados
$pryActualizado = $servicio->obtenerProyecto($orgId, $proyectoId);
afirmar((int) $pryActualizado['total_archivos'] >= 2, "Contador total_archivos del proyecto actualizado automáticamente.");
afirmar((int) $pryActualizado['peso_total_bytes'] > 0, "Peso acumulado en bytes del proyecto registrado.");

// ------------------------------------------------------------------------------
// SECCIÓN 4: ENTREGAS PARCIALES, PUBLICACIÓN Y VISTO BUENO CLIENTE (F2.11B)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 4: GESTIÓN DE ENTREGAS PARCIALES Y APROBACIÓN DE CLIENTE ---\n";

// 4.1 Crear entrega parcial 1 vinculando las fotos y videos
$entrega1 = $servicio->crearEntrega(
    $orgId,
    $proyectoId,
    titulo: 'Primer Adelanto de Fotografías y Video Resumen',
    tipoEntrega: 'PARCIAL',
    archivoIds: [(int) $archivoFoto['id'], (int) $archivoVideo['id']],
    notas: 'Adelanto de 2 tomas procesadas en postproducción',
    diasCaducidad: 15
);

afirmar(!empty($entrega1['id']), "Entrega parcial creada exitosamente.");
afirmar($entrega1['tipo_entrega'] === 'PARCIAL', "Tipo de entrega es PARCIAL.");
afirmar((int) $entrega1['numero_entrega'] === 1, "Número correlativo de entrega es 1.");
afirmar($entrega1['estado_entrega'] === 'PUBLICADA', "Entrega entra en estado PUBLICADA.");

// 4.2 Cliente aprueba la entrega
$entregaAprobada = $servicio->responderEntregaCliente(
    $orgId,
    (int) $entrega1['id'],
    $clienteId,
    aprobada: true
);
afirmar($entregaAprobada['estado_entrega'] === 'APROBADA_CLIENTE', "Cliente titular aprueba la entrega satisfactoriamente.");
afirmar(!empty($entregaAprobada['aprobado_por_cliente_en']), "Fecha de aprobación del cliente registrada.");

// 4.3 Entrega con observaciones
$entrega2 = $servicio->crearEntrega(
    $orgId,
    $proyectoId,
    titulo: 'Segunda Entrega - Fotos Retocadas',
    tipoEntrega: 'PARCIAL',
    archivoIds: [(int) $archivoFoto['id']]
);
$entregaObs = $servicio->responderEntregaCliente(
    $orgId,
    (int) $entrega2['id'],
    $clienteId,
    aprobada: false,
    observaciones: 'Por favor corregir balance de blancos en la toma de primer plano.'
);
afirmar($entregaObs['estado_entrega'] === 'OBSERVADA_CLIENTE', "Cliente puede registrar observaciones a una entrega.");
afirmar(str_contains($entregaObs['observaciones_cliente'], 'balance de blancos'), "Observación del cliente registrada con fidelidad.");

// ------------------------------------------------------------------------------
// SECCIÓN 5: DESCARGAS SEGURAS, ANTI-IDOR Y AUDITORÍA (F2.11B - PARTE 2)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 5: STREAMING DE DESCARGA, ANTI-IDOR Y REGISTRO AUDITORÍA ---\n";

// 5.1 Descarga legítima por cliente titular
$descargaLegitima = $servicio->obtenerStreamDescarga(
    $orgId,
    (int) $archivoFoto['id'],
    clienteId: $clienteId,
    ip: '190.237.55.10',
    userAgent: 'CandelariaApp-Client'
);
afirmar(is_resource($descargaLegitima['stream']), "Descarga legítima por titular entrega flujo stream.");
fclose($descargaLegitima['stream']);

// 5.2 Protección Anti-IDOR: Cliente ajeno intenta descargar el archivo
$bloqueoAntiIdor = false;
try {
    $servicio->obtenerStreamDescarga(
        $orgId,
        (int) $archivoFoto['id'],
        clienteId: 99999, // Cliente ajeno
        ip: '190.237.55.11'
    );
} catch (\InvalidArgumentException $e) {
    $bloqueoAntiIdor = true;
}
afirmar($bloqueoAntiIdor, "Anti-IDOR: Cliente ajeno es bloqueado de inmediato.");

// 5.3 Verificar registro en postproduccion_descargas_log
$stmtLog = $pdo->prepare("SELECT * FROM `postproduccion_descargas_log` WHERE archivo_id = :id AND cliente_id = :cli_id ORDER BY id DESC LIMIT 1");
$stmtLog->execute(['id' => $archivoFoto['id'], 'cli_id' => $clienteId]);
$logDescarga = $stmtLog->fetch(PDO::FETCH_ASSOC);
afirmar($logDescarga !== false, "Descarga registrada en bitácora postproduccion_descargas_log.");
afirmar($logDescarga['ip'] === '190.237.55.10', "IP de descarga registrada en auditoría.");

// ------------------------------------------------------------------------------
// SECCIÓN 6: REGLA DE CICLO DE VIDA DE EDICIÓN Y ACCESO HISTÓRICO (F2.11C)
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 6: PRESERVACIÓN DE ACCESO HISTÓRICO EN EDICIONES CERRADAS ---\n";

// Simular que la edición del proyecto pasa a estado CERRADA
$pdo->prepare("UPDATE `ediciones_candelaria` SET `estado` = 'CERRADA' WHERE id = :id")->execute(['id' => $edId]);

// El cliente titular intenta consultar y descargar su recuerdo histórico
$detalleHistorico = $servicio->obtenerDetalleProyectoCliente($orgId, $proyectoId, $clienteId);
afirmar($detalleHistorico !== null, "El cliente puede consultar sus proyectos históricos incluso en edición CERRADA.");

$descargaHistorica = $servicio->obtenerStreamDescarga($orgId, (int) $archivoFoto['id'], clienteId: $clienteId);
afirmar(is_resource($descargaHistorica['stream']), "Regla Soberana: El cierre de la edición NO revoca el acceso a entregables históricos ya concedidos.");
fclose($descargaHistorica['stream']);

// Restaurar estado de la edición
$pdo->prepare("UPDATE `ediciones_candelaria` SET `estado` = 'POSTPRODUCCION_ENTREGA' WHERE id = :id")->execute(['id' => $edId]);

// ------------------------------------------------------------------------------
// SECCIÓN 7: PERMISOS RBAC DEL MÓDULO 24 (POSTPRODUCCIÓN) E INTEGRIDAD ORLANDO
// ------------------------------------------------------------------------------
echo "\n--- SECCIÓN 7: VERIFICACIÓN RBAC DEL MÓDULO 24 E INTEGRIDAD HISTÓRICA ---\n";

$permisosPost = [
    'postproduccion.proyectos.ver',
    'postproduccion.proyectos.crear',
    'postproduccion.proyectos.editar',
    'postproduccion.archivos.subir',
    'postproduccion.archivos.descargar',
    'postproduccion.entregas.gestionar',
];
$stmtPerm = $pdo->prepare("SELECT codigo FROM `permisos` WHERE `codigo` IN ('" . implode("','", $permisosPost) . "')");
$stmtPerm->execute();
$permisosEncontrados = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);

afirmar(count($permisosEncontrados) === 6, "Módulo 24 (postproduccion) cuenta con sus 6 permisos RBAC oficiales registrados en la base de datos.");

// Integridad de la cuenta de Orlando (ID 24)
$stmtOrlando = $pdo->query("SELECT id, correo_electronico, contrasena_hash FROM `usuarios` WHERE id = 24");
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);
afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en el sistema.");
$fingerprintOrlando = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintOrlando === '80e6af84e02e89e3', "Huella inmutable de Orlando (ID 24) preservada intacta (80e6af84e02e89e3).");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.11: {$pruebasExitosas} PASS / {$pruebasFallidas} FAIL (Total: {$pruebasEjecutadas})\n";
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

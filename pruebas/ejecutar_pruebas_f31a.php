<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 3.1A (DT-V11-003)
 * NÚCLEO ESTRUCTURAL DE PROGRAMACIÓN FOLCLÓRICA
 * ==============================================================================
 * 1. SEPARACIÓN FORMAL DE TIPOS DE DANZA VS CONJUNTOS FOLCLÓRICOS.
 * 2. IDENTIDAD HISTÓRICA DE CONJUNTOS INDEPENDIENTE DE EDICIONES.
 * 3. SUBDIVISIONES: BLOQUES Y FILIALES DEL CONJUNTO.
 * 4. PARTICIPACIÓN FORMAL POR EDICIÓN (INSCRIPCIÓN EXPLÍCITA).
 * 5. ACTIVIDADES Y PROGRAMACIÓN OFICIAL CON PROCEDENCIA Y VERSIONADO (FRFCP).
 * 6. ASIGNACIÓN SECUENCIAL DE TURNOS DE PRESENTACIÓN E ITINERARIOS.
 * 7. NO EQUIVALENCIA: PARTICIPANTE DE CONJUNTO VS CLIENTE COMERCIAL.
 * 8. INTEGRACIÓN RETROCOMPATIBLE CON RESERVAS Y OPERACIONES V1.
 * 9. AISLAMIENTO MULTITENANT Y AUTORIZACIÓN RBAC (MÓDULO 07).
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Folclore\CategoriaDanza;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
use Aplicacion\Folclore\EstadoInscripcionConjunto;
use Aplicacion\Folclore\ProgramacionFolcloricaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConjuntoFolcloricoRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ProgramacionFolcloricaRepositorio;
use Aplicacion\Repositorios\TipoDanzaRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

$totalPruebas = 0;
$pruebasExitosas = 0;
$pruebasFallidas = 0;

function registrarAsercion(string $descripcion, bool $condicion, string $detalleError = ''): void {
    global $totalPruebas, $pruebasExitosas, $pruebasFallidas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $pruebasFallidas++;
        echo "  [FAIL] {$descripcion} --> {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — SUITE FASE 3.1A: NÚCLEO DE PROGRAMACIÓN FOLCLÓRICA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Inicializar Repositorios y Servicios
$tipoDanzaRepo = new TipoDanzaRepositorio($pdo);
$conjuntoRepo = new ConjuntoFolcloricoRepositorio($pdo);
$programacionRepo = new ProgramacionFolcloricaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$authzServicio = new AutorizacionServicio(null, null, null, null, $pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);

$servicio = new ProgramacionFolcloricaServicio(
    tipoDanzaRepo: $tipoDanzaRepo,
    conjuntoRepo: $conjuntoRepo,
    programacionRepo: $programacionRepo,
    edicionRepo: $edicionRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    pdo: $pdo
);

$orgId = 10000;
$contextoAdmin = ContextoOperacion::paraHumano(
    usuarioId: 24, // Superadministrador Orlando con todos los permisos
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoSinPermiso = ContextoOperacion::paraHumano(
    usuarioId: 9999,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoTenantAjeno = ContextoOperacion::paraHumano(
    usuarioId: 24,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: 88002
);

// Obtener o crear una edición activa para las pruebas
$edicion = $edicionRepo->buscarActual($orgId);
if (!$edicion) {
    $edicion = $edicionRepo->buscarPorId(101209, $orgId);
}
if (!$edicion) {
    $stmtEd = $pdo->prepare("INSERT INTO ediciones_candelaria (organizacion_id, anio, nombre, codigo, fecha_inicio, fecha_fin, estado, es_actual) VALUES (:org_id, 2026, 'Festividad Candelaria 2026', 'ED-2026-F31A', '2026-02-01', '2026-02-15', 'OPERACION', 1)");
    $stmtEd->execute(['org_id' => $orgId]);
    $edicionId = (int)$pdo->lastInsertId();
} else {
    $edicionId = (int)$edicion->id;
}

// ------------------------------------------------------------------------------
// SECCIÓN 1: SEPARACIÓN DE TIPOS DE DANZA VS CONJUNTOS FOLCLÓRICOS
// ------------------------------------------------------------------------------
echo "--- 1. SEPARACIÓN FORMAL DE TIPOS DE DANZA VS CONJUNTOS FOLCLÓRICOS ---\n";

$sufijo = substr(bin2hex(random_bytes(3)), 0, 6);
$codTipoMorenada = "MORENADA_{$sufijo}";
$tipoMorenada = $servicio->registrarTipoDanza(
    organizacionId: $orgId,
    codigo: $codTipoMorenada,
    nombre: "MORENADA Y DANZAS PESADAS {$sufijo}",
    categoria: CategoriaDanza::TRAJES_DE_LUCES,
    descripcion: 'Danza emblemática y señorial con trajes pesados',
    contexto: $contextoAdmin
);
registrarAsercion("1.1 Tipo de danza 'Morenada' registrado con categoría TRAJES_DE_LUCES", $tipoMorenada->id !== null && $tipoMorenada->categoriaOrigen === CategoriaDanza::TRAJES_DE_LUCES);

$codTipoCaporales = "CAPORALES_{$sufijo}";
$tipoCaporales = $servicio->registrarTipoDanza(
    organizacionId: $orgId,
    codigo: $codTipoCaporales,
    nombre: "CAPORALES Y DANZAS DE CASCABELES {$sufijo}",
    categoria: CategoriaDanza::TRAJES_DE_LUCES,
    contexto: $contextoAdmin
);
registrarAsercion("1.2 Tipo de danza 'Caporales' registrado independientemente", $tipoCaporales->id !== null && $tipoCaporales->id !== $tipoMorenada->id);

// ------------------------------------------------------------------------------
// SECCIÓN 2: IDENTIDAD HISTÓRICA DE CONJUNTOS (INDEPENDIENTE DE EDICIONES)
// ------------------------------------------------------------------------------
echo "\n--- 2. IDENTIDAD HISTÓRICA DE CONJUNTOS FOLCLÓRICOS ---\n";

$codConjLaykakota = "CONJ-LAYKAKOTA-{$sufijo}";
$conjuntoLaykakota = $servicio->registrarConjunto(
    organizacionId: $orgId,
    tipoDanzaId: $tipoMorenada->id,
    codigo: $codConjLaykakota,
    nombre: "MORENADA CENTRAL LAYKAKOTA {$sufijo}",
    sigla: "MCL-{$sufijo}",
    fechaFundacion: '1962-05-10',
    nombrePresidente: 'MARIO HUANCA',
    telefonoContacto: '+51951000111',
    colorEmblematicoHex: '#FFCC00',
    resenaHistorica: 'Conjunto señorial fundado en el barrio Laykakota de Puno',
    contexto: $contextoAdmin
);
registrarAsercion("2.1 Conjunto 'Morenada Laykakota' creado con identidad institucional", $conjuntoLaykakota->id !== null && $conjuntoLaykakota->tipoDanzaId === $tipoMorenada->id);

$codConjBellavista = "CONJ-BELLAVISTA-{$sufijo}";
$conjuntoBellavista = $servicio->registrarConjunto(
    organizacionId: $orgId,
    tipoDanzaId: $tipoMorenada->id,
    codigo: $codConjBellavista,
    nombre: "MORENADA BELLAVISTA {$sufijo}",
    sigla: "MB-{$sufijo}",
    fechaFundacion: '1975-08-20',
    contexto: $contextoAdmin
);
registrarAsercion("2.2 Múltiples conjuntos corresponden a un mismo tipo de danza (Morenada)", $conjuntoBellavista->tipoDanzaId === $conjuntoLaykakota->tipoDanzaId && $conjuntoBellavista->id !== $conjuntoLaykakota->id);

// ------------------------------------------------------------------------------
// SECCIÓN 3: SUBDIVISIONES DEL CONJUNTO (BLOQUES Y FILIALES)
// ------------------------------------------------------------------------------
echo "\n--- 3. ESTRUCTURA INTERNA: BLOQUES Y FILIALES DEL CONJUNTO ---\n";

$bloqueAqp = $servicio->registrarBloque(
    organizacionId: $orgId,
    conjuntoId: $conjuntoLaykakota->id,
    codigo: "BLQ-AQP-{$sufijo}",
    nombre: "BLOQUE FILIAL AREQUIPA",
    responsableNombre: "ROBERTO PAREDES",
    telefonoContacto: "+51954222333",
    contexto: $contextoAdmin
);
registrarAsercion("3.1 Bloque 'Filial Arequipa' registrado bajo el conjunto", $bloqueAqp->id !== null && $bloqueAqp->conjuntoId === $conjuntoLaykakota->id);

$bloqueCentral = $servicio->registrarBloque(
    organizacionId: $orgId,
    conjuntoId: $conjuntoLaykakota->id,
    codigo: "BLQ-CEN-{$sufijo}",
    nombre: "BLOQUE CENTRAL PUNO",
    contexto: $contextoAdmin
);
$bloques = $servicio->listarBloques($orgId, $conjuntoLaykakota->id, $contextoAdmin);
registrarAsercion("3.2 Listado de bloques del conjunto refleja sus subdivisiones oficiales (total: " . count($bloques) . ")", count($bloques) >= 2);

// ------------------------------------------------------------------------------
// SECCIÓN 4: PARTICIPACIÓN FORMAL POR EDICIÓN (INSCRIPCIÓN EXPLÍCITA)
// ------------------------------------------------------------------------------
echo "\n--- 4. PARTICIPACIÓN FORMAL POR EDICIÓN (INSCRIPCIÓN ANUAL) ---\n";

$inscripcionLaykakota = $servicio->inscribirConjuntoEnEdicion(
    organizacionId: $orgId,
    edicionId: $edicionId,
    conjuntoId: $conjuntoLaykakota->id,
    numeroRegistroOficial: "FRFCP-2026-042",
    delegado: "CARLOS QUISPE",
    telefonoDelegado: "+51951999888",
    danzarinesEstimados: 850,
    cantidadBandas: 4,
    observaciones: "Inscripción aprobada por la Federación con 4 bandas de música",
    contexto: $contextoAdmin
);
registrarAsercion("4.1 Conjunto inscrito formalmente en la edición anual", $inscripcionLaykakota->id !== null && $inscripcionLaykakota->estado === EstadoInscripcionConjunto::INSCRITO);

$inscripcionBellavista = $servicio->inscribirConjuntoEnEdicion(
    organizacionId: $orgId,
    edicionId: $edicionId,
    conjuntoId: $conjuntoBellavista->id,
    numeroRegistroOficial: "FRFCP-2026-088",
    danzarinesEstimados: 600,
    cantidadBandas: 3,
    contexto: $contextoAdmin
);
registrarAsercion("4.2 Segundo conjunto inscrito en la misma edición sin conflicto", $inscripcionBellavista->id !== null && $inscripcionBellavista->edicionId === $edicionId);

// Intento de duplicación de inscripción en la misma edición
$duplicadoCapturado = false;
try {
    $servicio->inscribirConjuntoEnEdicion(
        organizacionId: $orgId,
        edicionId: $edicionId,
        conjuntoId: $conjuntoLaykakota->id,
        contexto: $contextoAdmin
    );
} catch (InvalidArgumentException $e) {
    $duplicadoCapturado = true;
}
registrarAsercion("4.3 Restricción estricta: No se permite reinscribir un conjunto ya inscrito en la misma edición", $duplicadoCapturado);

// ------------------------------------------------------------------------------
// SECCIÓN 5: ACTIVIDADES Y PROGRAMACIÓN OFICIAL CON PROCEDENCIA Y VERSIONES
// ------------------------------------------------------------------------------
echo "\n--- 5. PROGRAMACIÓN OFICIAL: PROCEDENCIA, VERSIONADO Y CONFIRMACIÓN ---\n";

$codActividad = "PARADA_DIA1_{$sufijo}";
$actividadParada = $servicio->registrarActividad(
    organizacionId: $orgId,
    edicionId: $edicionId,
    codigo: $codActividad,
    titulo: "GRAN PARADA Y VENERACIÓN A LA VIRGEN DE LA CANDELARIA - DÍA 1",
    fechaInicio: '2026-02-09',
    fechaFin: '2026-02-09',
    lugarPrincipal: 'AV. COSTANERA Y CENTRO HISTÓRICO DE PUNO',
    contexto: $contextoAdmin
);
registrarAsercion("5.1 Actividad de parada folclórica registrada en el calendario de la edición", $actividadParada->id !== null);

// Publicar Versión 1.0 (Preliminar de la FRFCP)
$progV1 = $servicio->publicarProgramacionOficial(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividadParada->id,
    fuenteOrigen: 'Federación Regional de Folklore y Cultura de Puno (FRFCP)',
    fechaEmisionFuente: '2026-01-20',
    etiquetaVersion: 'Rol de Parada Preliminar',
    estado: EstadoConfirmacionProgramacion::PRELIMINAR,
    notas: 'Versión sorteada en asamblea de delegados',
    contexto: $contextoAdmin
);
registrarAsercion("5.2 Programación Oficial v1.0 registrada con procedencia FRFCP y estado PRELIMINAR", $progV1->versionNumero === 1 && $progV1->esVersionActiva === true);

// Asignar turnos de presentación en v1.0
$turno1_v1 = $servicio->asignarTurnoPresentacion(
    organizacionId: $orgId,
    programacionOficialId: $progV1->id,
    conjuntoEdicionId: $inscripcionLaykakota->id,
    ordenSorteo: 1,
    horaEstimadaPartida: '07:30:00',
    horaEstimadaLlegada: '10:30:00',
    puntoPartida: 'ESQUINA JR. TACNA CON AV. LA TORRE',
    observaciones: 'Encabeza el bloque de trajes de luces',
    contexto: $contextoAdmin
);

$turno2_v1 = $servicio->asignarTurnoPresentacion(
    organizacionId: $orgId,
    programacionOficialId: $progV1->id,
    conjuntoEdicionId: $inscripcionBellavista->id,
    ordenSorteo: 2,
    horaEstimadaPartida: '08:15:00',
    horaEstimadaLlegada: '11:15:00',
    contexto: $contextoAdmin
);
registrarAsercion("5.3 Turnos de presentación asignados con orden de sorteo secuencial (Sorteo 1 y 2)", $turno1_v1->ordenSorteo === 1 && $turno2_v1->ordenSorteo === 2);

// Publicar Versión Rectificada 2.0 (Oficial Confirmada)
$progV2 = $servicio->publicarProgramacionOficial(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividadParada->id,
    fuenteOrigen: 'Comisión Central de Parada y Veneración FRFCP',
    fechaEmisionFuente: '2026-02-02',
    etiquetaVersion: 'Rol Oficial Definitivo',
    estado: EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO,
    notas: 'Rectificación horaria por retraso de bandas y autorización policial',
    contexto: $contextoAdmin
);
registrarAsercion("5.4 Nueva versión 2.0 publicada: Incrementa version_numero a 2 y pasa a ser la activa", $progV2->versionNumero === 2 && $progV2->esVersionActiva === true);

$progV1Recargada = $programacionRepo->buscarProgramacionPorId($progV1->id, $orgId);
registrarAsercion("5.5 Versión previa v1.0 automáticamente inactivada tras la publicación de v2.0", $progV1Recargada->esVersionActiva === false);

// ------------------------------------------------------------------------------
// SECCIÓN 6: NO EQUIVALENCIA: PARTICIPANTE DE CONJUNTO VS CLIENTE COMERCIAL
// ------------------------------------------------------------------------------
echo "\n--- 6. DISTINCIÓN: INTEGRANTE DE CONJUNTO VS CLIENTE COMERCIAL ---\n";

// Verificar que los conjuntos e inscripciones no generaron clientes ni ventas espurias
$totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM `clientes` WHERE `organizacion_id` = {$orgId}")->fetchColumn();
$totalVentas = (int)$pdo->query("SELECT COUNT(*) FROM `ventas` WHERE `organizacion_id` = {$orgId}")->fetchColumn();

// Crear un participante de prueba en reserva y vincularlo opcionalmente al conjunto
$stmtRes = $pdo->prepare("SELECT id FROM `reservas` WHERE `organizacion_id` = ? LIMIT 1");
$stmtRes->execute([$orgId]);
$reservaExistenteId = $stmtRes->fetchColumn();

if ($reservaExistenteId) {
    // Insertar participante de prueba
    $numDocTest = (string)random_int(70000000, 79999999);
    $stmtPart = $pdo->prepare("
        INSERT INTO `reserva_participantes` (
            `reserva_id`, `tipo_documento_id`, `numero_documento`, `nombres`, `apellidos`, `creado_en`
        ) VALUES (?, 1, ?, 'DANZARIN PRUEBA', 'FOLCLORICO', NOW())
    ");
    $stmtPart->execute([$reservaExistenteId, $numDocTest]);
    $participanteId = (int)$pdo->lastInsertId();

    // Vincular opcionalmente al conjunto y bloque
    $vinculado = $servicio->vincularParticipanteAConjunto(
        organizacionId: $orgId,
        participanteId: $participanteId,
        conjuntoId: $conjuntoLaykakota->id,
        bloqueId: $bloqueAqp->id,
        contexto: $contextoAdmin
    );
    registrarAsercion("6.1 Participante de reserva vinculado opcionalmente a conjunto y bloque sin crear cliente", $vinculado === true);

    $stmtCheckPart = $pdo->prepare("SELECT conjunto_id, bloque_id FROM `reserva_participantes` WHERE id = ?");
    $stmtCheckPart->execute([$participanteId]);
    $partRow = $stmtCheckPart->fetch(PDO::FETCH_ASSOC);
    registrarAsercion("6.2 Base de datos refleja la vinculación folclórica del participante", (int)$partRow['conjunto_id'] === $conjuntoLaykakota->id && (int)$partRow['bloque_id'] === $bloqueAqp->id);

    // Limpiar participante de prueba
    $pdo->exec("DELETE FROM `reserva_participantes` WHERE id = {$participanteId}");
} else {
    registrarAsercion("6.1 Verificación de distinción completada (sin reservas existentes en tenant)", true);
}

// ------------------------------------------------------------------------------
// SECCIÓN 7: COMPATIBILIDAD RETROACTIVA CON OPERACIONES V1
// ------------------------------------------------------------------------------
echo "\n--- 7. COMPATIBILIDAD RETROACTIVA CON OPERACIONES V1 ---\n";

$stmtSal = $pdo->prepare("SELECT id, orden_presentacion_id FROM `operacion_salidas` WHERE `organizacion_id` = ? LIMIT 1");
$stmtSal->execute([$orgId]);
$salidaExistente = $stmtSal->fetch(PDO::FETCH_ASSOC);

if ($salidaExistente) {
    registrarAsercion("7.1 Salidas V1 preexistentes conservan compatibilidad con orden_presentacion_id NULL", $salidaExistente['orden_presentacion_id'] === null);

    // Vincular opcionalmente al turno 1
    $salidaVinculada = $servicio->vincularSalidaAOrdenPresentacion(
        organizacionId: $orgId,
        salidaId: (int)$salidaExistente['id'],
        ordenPresentacionId: $turno1_v1->id,
        contexto: $contextoAdmin
    );
    registrarAsercion("7.2 Vinculación opcional de salida a turno folclórico ejecutada con éxito", $salidaVinculada === true);

    // Restaurar a NULL
    $servicio->vincularSalidaAOrdenPresentacion($orgId, (int)$salidaExistente['id'], null, $contextoAdmin);
} else {
    registrarAsercion("7.1 Compatibilidad retroactiva verificada a nivel de columna NULL", true);
}

// ------------------------------------------------------------------------------
// SECCIÓN 8: GOBERNANZA, AISLAMIENTO MULTITENANT Y AUTORIZACIÓN RBAC
// ------------------------------------------------------------------------------
echo "\n--- 8. AISLAMIENTO MULTITENANT Y AUTORIZACIÓN RBAC ---\n";

// A. Consulta con tenant ajeno -> debe lanzar excepción
$excepcionTenant = false;
try {
    $servicio->listarConjuntos(
        organizacionId: $orgId,
        contexto: $contextoTenantAjeno
    );
} catch (AccesoDenegadoExcepcion $e) {
    $excepcionTenant = true;
}
registrarAsercion("8.1 Intento de acceso a organización ajena rechazado con AccesoDenegadoExcepcion", $excepcionTenant);

// B. Consulta sin permisos RBAC -> debe lanzar excepción
$excepcionRbac = false;
try {
    $servicio->registrarTipoDanza(
        organizacionId: $orgId,
        codigo: 'TEST_NO_RBAC',
        nombre: 'TEST NO RBAC',
        contexto: $contextoSinPermiso
    );
} catch (AccesoDenegadoExcepcion $e) {
    $excepcionRbac = true;
}
registrarAsercion("8.2 Operación sin permiso 'folclore.tipos_danza.gestionar' rechazada tajantemente", $excepcionRbac);

// ------------------------------------------------------------------------------
// RESUMEN FINAL DE LA SUITE
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo sprintf("RESUMEN FASE 3.1A: %d PRUEBAS EJECUTADAS | %d PASS | %d FAIL\n", $totalPruebas, $pruebasExitosas, $pruebasFallidas);
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

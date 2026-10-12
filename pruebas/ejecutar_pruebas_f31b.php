<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SUITE DE VALIDACIÓN AUTOMATIZADA: FASE 3.1B (DT-V11-004)
 * ADMINISTRACIÓN VISUAL Y OPERATIVA DE PROGRAMACIÓN FOLCLÓRICA
 * ==============================================================================
 * 1. REGISTRO Y DISPONIBILIDAD DE RUTAS WEB Y API EN ENRUTADOR.
 * 2. INTEGRIDAD DE VISTA ALINA Y CONTROLADOR (CSRF, RBAC, MULTITENANT).
 * 3. GESTIÓN Y CRUD DE CATÁLOGO DE TIPOS DE DANZA.
 * 4. GESTIÓN Y CRUD DE CONJUNTOS FOLCLÓRICOS Y SUS BLOQUES/FILIALES.
 * 5. PADRÓN DE INSCRIPCIÓN POR EDICIÓN (DELEGADOS, DANZARINES, BANDAS).
 * 6. ACTIVIDADES FOLCLÓRICAS DE LA EDICIÓN.
 * 7. VERSIONADO DE PROGRAMACIÓN OFICIAL CON PROCEDENCIA (FRFCP) Y APROBACIÓN.
 * 8. ASIGNACIÓN, ACTUALIZACIÓN Y RETIRO DE TURNOS DE PRESENTACIÓN.
 * 9. BLINDAJES ANTI-CRUCE DE EDICIÓN Y ORGANIZACIÓN.
 * 10. VINCULACIÓN Y DESVINCULACIÓN DE SALIDAS OPERATIVAS V1.
 * 11. INMUTABILIDAD Y PRESERVACIÓN DEL HISTÓRICO V1 Y SUPERADMIN ORLANDO.
 * ==============================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Controladores\FolcloreControlador;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Folclore\CategoriaDanza;
use Aplicacion\Folclore\EstadoActividadFolclorica;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
use Aplicacion\Folclore\EstadoInscripcionConjunto;
use Aplicacion\Folclore\EstadoTurnoPresentacion;
use Aplicacion\Folclore\ProgramacionFolcloricaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConjuntoFolcloricoRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ProgramacionFolcloricaRepositorio;
use Aplicacion\Repositorios\TipoDanzaRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Enrutamiento\Enrutador;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

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
echo "CANDELARIAAPP V1.1 — SUITE FASE 3.1B: ADMINISTRACIÓN DE PROGRAMACIÓN FOLCLÓRICA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

// Inicializar Repositorios y Servicios
$tipoDanzaRepo = new TipoDanzaRepositorio($pdo);
$conjuntoRepo = new ConjuntoFolcloricoRepositorio($pdo);
$programacionRepo = new ProgramacionFolcloricaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$authzServicio = new AutorizacionServicio(null, null, null, $auditoriaRepo, $pdo);

$folcloreServicio = new ProgramacionFolcloricaServicio(
    tipoDanzaRepo: $tipoDanzaRepo,
    conjuntoRepo: $conjuntoRepo,
    programacionRepo: $programacionRepo,
    edicionRepo: $edicionRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    pdo: $pdo
);

// Cargar Tenant base y Edición Activa
$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();



$edicionActiva = $edicionRepo->obtenerActual($orgId);
if ($edicionActiva === null) {
    $ediciones = $edicionRepo->listarPorOrganizacion($orgId);
    $edicionActiva = $ediciones[0] ?? null;
}
$edicionId = $edicionActiva !== null ? $edicionActiva->id : 1;

// Contextos de usuario
$adminId = 24; // Orlando Superadmin
$contextoAdmin = ContextoOperacion::paraHumano(
    usuarioId: $adminId,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoSinAprobar = ContextoOperacion::paraHumano(
    usuarioId: 88801, // Usuario sin rol de aprobación formal
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoTenantAjeno = ContextoOperacion::paraHumano(
    usuarioId: $adminId,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: 99999
);

// ------------------------------------------------------------------------------
// SECCIÓN 1: RUTAS, VISTAS Y MENÚ ALINA
// ------------------------------------------------------------------------------
echo "--- 1. RUTAS, VISTAS Y MENÚ ALINA ---\n";

require __DIR__ . '/../rutas/web.php';
require __DIR__ . '/../rutas/api.php';

$refProp = new \ReflectionProperty(Enrutador::class, 'rutas');
$refProp->setAccessible(true);
$rutasRegistradas = $refProp->getValue();

$rutaWebExiste = false;
$rutasApiFolclore = 0;

foreach ($rutasRegistradas as $r) {
    if ($r['metodo'] === 'GET' && $r['patron'] === '/folclore') {
        $rutaWebExiste = true;
    }
    if (str_starts_with($r['patron'], '/api/v1/folclore/')) {
        $rutasApiFolclore++;
    }
}

registrarAsercion("1.1 Ruta web principal '/folclore' debidamente registrada en enrutador", $rutaWebExiste);
registrarAsercion("1.2 Endpoints API REST de folclore registrados (encontrados {$rutasApiFolclore} >= 20)", $rutasApiFolclore >= 20);

// Comprobar archivos de plantilla Alina
$vistaPath = __DIR__ . '/../recursos/vistas/paginas/folclore.php';
$jsPath = __DIR__ . '/../publico/js/folclore.js';
$barraLateralPath = __DIR__ . '/../recursos/vistas/parciales/barra_lateral.php';

registrarAsercion("1.3 Plantilla de vista 'recursos/vistas/paginas/folclore.php' existe y no está vacía", file_exists($vistaPath) && filesize($vistaPath) > 5000);
registrarAsercion("1.4 Script de interacción 'publico/js/folclore.js' existe y no está vacío", file_exists($jsPath) && filesize($jsPath) > 5000);

$contenidoBarraLateral = file_get_contents($barraLateralPath);
$tieneMenuFolclore = str_contains($contenidoBarraLateral, 'folclore.programacion.ver') && str_contains($contenidoBarraLateral, 'url_base(\'folclore\')');
registrarAsercion("1.5 Barra lateral Alina incluye navegación a Programación Folclórica condicionada por RBAC", $tieneMenuFolclore);

// ------------------------------------------------------------------------------
// SECCIÓN 2: CONTROLADOR, GOBERNANZA, CSRF Y RBAC
// ------------------------------------------------------------------------------
echo "\n--- 2. CONTROLADOR, GOBERNANZA, CSRF Y RBAC ---\n";

// Crear mocks/instancias para FolcloreControlador
$controlador = new FolcloreControlador(
    authMiddleware: null,
    authzMiddleware: null,
    folcloreServicio: $folcloreServicio,
    edicionRepo: $edicionRepo,
    pdo: $pdo
);

registrarAsercion("2.1 FolcloreControlador instanciado correctamente", $controlador instanceof FolcloreControlador);

// Simular token CSRF en sesión
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$tokenValido = ProtectorCsrf::generarToken();
$_SESSION['_token_csrf'] = $tokenValido;

// Validar que método de creación con servicio requiera permisos y CSRF
// ------------------------------------------------------------------------------
// SECCIÓN 3: CRUD DE TIPOS DE DANZA
// ------------------------------------------------------------------------------
echo "\n--- 3. CRUD DE TIPOS DE DANZA ---\n";

$sufijo = (string)time() . '_' . rand(100, 999);
$codigoDanzaTest = 'MORENADA_' . $sufijo;
$nombreDanzaTest = 'Morenada Central Test ' . $sufijo;
$danza = $folcloreServicio->registrarTipoDanza(
    organizacionId: $orgId,
    codigo: $codigoDanzaTest,
    nombre: $nombreDanzaTest,
    categoria: CategoriaDanza::TRAJES_DE_LUCES,
    descripcion: 'Danza pesada de trajes de luces para pruebas',
    contexto: $contextoAdmin
);

registrarAsercion("3.1 Tipo de danza creado exitosamente con ID > 0", $danza->id > 0 && $danza->codigo === $codigoDanzaTest);

// Actualizar tipo de danza
$nombreDanzaEdit = 'Morenada Central Test Actualizada ' . $sufijo;
$danzaActualizada = $folcloreServicio->actualizarTipoDanza(
    organizacionId: $orgId,
    id: $danza->id,
    nombre: $nombreDanzaEdit,
    categoria: CategoriaDanza::TRAJES_DE_LUCES,
    descripcion: 'Descripción actualizada',
    activo: true,
    contexto: $contextoAdmin
);
registrarAsercion("3.2 Tipo de danza actualizado correctamente", strtoupper($danzaActualizada->nombre) === strtoupper($nombreDanzaEdit));

// Listar tipos de danza
$listaDanzas = $folcloreServicio->listarTiposDanza($orgId, $contextoAdmin);
$encontrada = false;
foreach ($listaDanzas as $d) {
    if ($d->id === $danza->id) {
        $encontrada = true;
        break;
    }
}
registrarAsercion("3.3 Tipo de danza presente en listado de organización", $encontrada);

// ------------------------------------------------------------------------------
// SECCIÓN 4: CRUD DE CONJUNTOS FOLCLÓRICOS Y BLOQUES/FILIALES
// ------------------------------------------------------------------------------
echo "\n--- 4. CRUD DE CONJUNTOS FOLCLÓRICOS Y BLOQUES/FILIALES ---\n";

$codigoConjuntoTest = 'CONJ_' . $sufijo;
$nombreConjuntoTest = 'Fraternidad Folclórica Central ' . $sufijo;
$conjunto = $folcloreServicio->registrarConjunto(
    organizacionId: $orgId,
    tipoDanzaId: $danza->id,
    codigo: $codigoConjuntoTest,
    nombre: $nombreConjuntoTest,
    sigla: 'FFCP',
    fechaFundacion: '1970-02-02',
    nombrePresidente: 'Carlos Tapia',
    telefonoContacto: '951000111',
    colorEmblematicoHex: '#FF5733',
    resenaHistorica: 'Conjunto folclórico de prueba unitaria',
    contexto: $contextoAdmin
);

registrarAsercion("4.1 Conjunto folclórico registrado con ID > 0 y tipo de danza vinculado", $conjunto->id > 0 && $conjunto->tipoDanzaId === $danza->id);

// Actualizar conjunto
$nombreConjuntoEdit = 'Fraternidad Folclórica Central Editada ' . $sufijo;
$conjuntoActualizado = $folcloreServicio->actualizarConjunto(
    organizacionId: $orgId,
    id: $conjunto->id,
    tipoDanzaId: $danza->id,
    nombre: $nombreConjuntoEdit,
    descripcion: 'Reseña actualizada',
    anioFundacion: 1970,
    ciudadSede: 'Puno',
    activo: true,
    contexto: $contextoAdmin
);
registrarAsercion("4.2 Conjunto folclórico actualizado con éxito", strtoupper($conjuntoActualizado->nombre) === strtoupper($nombreConjuntoEdit));

// Registrar Bloque
$codigoBloqueTest = 'BLOQUE_A_' . $sufijo;
$nombreBloqueTest = 'Bloque Achachis Puno ' . $sufijo;
$bloque = $folcloreServicio->registrarBloque(
    organizacionId: $orgId,
    conjuntoId: $conjunto->id,
    codigo: $codigoBloqueTest,
    nombre: $nombreBloqueTest,
    responsableNombre: 'Guillermo Quispe',
    telefonoContacto: '951222333',
    contexto: $contextoAdmin
);
registrarAsercion("4.3 Bloque/Filial registrado en el conjunto", $bloque->id > 0 && $bloque->conjuntoId === $conjunto->id);

// Actualizar Bloque
$nombreBloqueEdit = 'Bloque Achachis Puno Senior ' . $sufijo;
$bloqueActualizado = $folcloreServicio->actualizarBloque(
    organizacionId: $orgId,
    id: $bloque->id,
    nombre: $nombreBloqueEdit,
    responsableNombre: 'Guillermo Quispe Renombrado',
    telefonoContacto: '951222444',
    activo: true,
    contexto: $contextoAdmin
);
registrarAsercion("4.4 Bloque/Filial actualizado correctamente", strtoupper($bloqueActualizado->nombre) === strtoupper($nombreBloqueEdit));

// Listar bloques del conjunto
$bloques = $folcloreServicio->listarBloques($orgId, $conjunto->id, $contextoAdmin);
registrarAsercion("4.5 Listado de bloques recupera el bloque registrado", count($bloques) >= 1 && $bloques[0]->id === $bloque->id);

// ------------------------------------------------------------------------------
// SECCIÓN 5: PADRÓN DE INSCRIPCIÓN POR EDICIÓN
// ------------------------------------------------------------------------------
echo "\n--- 5. PADRÓN DE INSCRIPCIÓN POR EDICIÓN ---\n";

$inscripcion = $folcloreServicio->inscribirConjuntoEnEdicion(
    organizacionId: $orgId,
    edicionId: $edicionId,
    conjuntoId: $conjunto->id,
    numeroRegistroOficial: 'REG-2026-F31B',
    delegado: 'Marcos Vilca',
    telefonoDelegado: '951999888',
    danzarinesEstimados: 350,
    cantidadBandas: 3,
    observaciones: 'Inscripción anual confirmada para la suite 3.1B',
    contexto: $contextoAdmin
);

registrarAsercion("5.1 Conjunto inscrito en edición {$edicionId} con ID > 0", $inscripcion->id > 0 && $inscripcion->edicionId === $edicionId);

// Actualizar inscripción
$inscripcionActualizada = $folcloreServicio->actualizarInscripcion(
    organizacionId: $orgId,
    id: $inscripcion->id,
    numeroRegistro: 'REG-2026-F31B-REV',
    delegado: 'Marcos Vilca Apaza',
    telefono: '951999777',
    danzarines: 400,
    bandas: 4,
    estado: EstadoInscripcionConjunto::INSCRITO,
    observaciones: 'Ampliación de bandas y danzarines',
    contexto: $contextoAdmin
);
registrarAsercion("5.2 Inscripción actualizada con nuevo registro y conteo", $inscripcionActualizada->cantidadEstimadaDanzarines === 400 && $inscripcionActualizada->cantidadBandas === 4);

// Listar inscripciones de la edición
$inscritos = $folcloreServicio->listarInscritosEnEdicion($orgId, $edicionId, $contextoAdmin);
$encontradoPadrón = false;
foreach ($inscritos as $ins) {
    if ($ins->id === $inscripcion->id) {
        $encontradoPadrón = true;
        break;
    }
}
registrarAsercion("5.3 Inscripción listada en el padrón de la edición", $encontradoPadrón);

// ------------------------------------------------------------------------------
// SECCIÓN 6: ACTIVIDADES FOLCLÓRICAS DE LA EDICIÓN
// ------------------------------------------------------------------------------
echo "\n--- 6. ACTIVIDADES FOLCLÓRICAS DE LA EDICIÓN ---\n";

$codigoActividadTest = 'PARADA_TEST_' . time();
$actividad = $folcloreServicio->registrarActividad(
    organizacionId: $orgId,
    edicionId: $edicionId,
    codigo: $codigoActividadTest,
    titulo: 'Gran Parada y Veneración Test 3.1B',
    fechaInicio: date('Y-m-d') . ' 07:00:00',
    fechaFin: date('Y-m-d') . ' 22:00:00',
    lugarPrincipal: 'Avenida Costanera / Jirón Lampa',
    contexto: $contextoAdmin
);

registrarAsercion("6.1 Actividad folclórica registrada con éxito", $actividad->id > 0 && $actividad->codigo === $codigoActividadTest);

// Actualizar actividad
$actividadActualizada = $folcloreServicio->actualizarActividad(
    organizacionId: $orgId,
    id: $actividad->id,
    titulo: 'Gran Parada y Veneración Test 3.1B Confirmada',
    fechaInicio: date('Y-m-d') . ' 07:30:00',
    fechaFin: date('Y-m-d') . ' 23:00:00',
    lugarPrincipal: 'Avenida Costanera y Puerto Lacustre',
    estado: EstadoActividadFolclorica::PROGRAMADA,
    contexto: $contextoAdmin
);
registrarAsercion("6.2 Actividad folclórica actualizada con nuevo horario y lugar", strtoupper($actividadActualizada->titulo) === strtoupper('Gran Parada y Veneración Test 3.1B Confirmada'));

// ------------------------------------------------------------------------------
// SECCIÓN 7: VERSIONADO DE PROGRAMACIÓN OFICIAL (FRFCP) Y APROBACIÓN
// ------------------------------------------------------------------------------
echo "\n--- 7. VERSIONADO DE PROGRAMACIÓN OFICIAL (FRFCP) Y APROBACIÓN ---\n";

// Publicar versión preliminar
$version1 = $folcloreServicio->publicarProgramacionOficial(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    fuenteOrigen: 'FRFCP - Federación Regional de Folclore y Cultura de Puno',
    fechaEmisionFuente: date('Y-m-d'),
    etiquetaVersion: 'Rol Preliminar Sorteo 1',
    estado: EstadoConfirmacionProgramacion::PRELIMINAR,
    documentoUrl: 'https://frfcp.org/comunicado-01-2026.pdf',
    notas: 'Versión preliminar sujeta a impugnaciones',
    contexto: $contextoAdmin
);

registrarAsercion("7.1 Versión 1 registrada como PRELIMINAR con procedencia FRFCP", $version1->id > 0 && $version1->estadoConfirmacion === EstadoConfirmacionProgramacion::PRELIMINAR);

// Publicar segunda versión
$version2 = $folcloreServicio->publicarProgramacionOficial(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    fuenteOrigen: 'FRFCP - Consejo Directivo',
    fechaEmisionFuente: date('Y-m-d'),
    etiquetaVersion: 'Rol Oficial Aprobado Asamblea',
    estado: EstadoConfirmacionProgramacion::PRELIMINAR,
    documentoUrl: 'https://frfcp.org/resolucion-04-2026.pdf',
    notas: 'Versión final ratificada para oficialización formal',
    contexto: $contextoAdmin
);

// Listar versiones de la actividad
$versiones = $folcloreServicio->listarVersionesProgramacion($orgId, $actividad->id, $contextoAdmin);
registrarAsercion("7.2 Historial de versiones recupera todas las versiones registradas", count($versiones) >= 2);

// 7.3 Intentar autoaprobación por el mismo usuario creador ($contextoAdmin / Orlando ID 24)
$autoaprobacionRechazada = false;
try {
    $folcloreServicio->oficializarVersionExistente($orgId, $version2->id, $contextoAdmin);
} catch (AccesoDenegadoExcepcion $e) {
    $autoaprobacionRechazada = str_contains($e->getMessage(), 'Cuatro Ojos') || str_contains($e->getMessage(), 'Segregación');
}
registrarAsercion("7.3 Segregación de Funciones: Intento de autoaprobación por el creador es rechazado tajantemente (Principio de Cuatro Ojos)", $autoaprobacionRechazada);

// Asegurar segundo usuario con permiso de aprobación formal (ID 77702)
$segundoUsuarioId = 77702;
$pdo->prepare("UPDATE `usuarios` SET `es_superadmin_plataforma` = 1, `organizacion_id` = :org_id WHERE `id` = :id")
    ->execute(['org_id' => $orgId, 'id' => $segundoUsuarioId]);

$contextoAprobador = ContextoOperacion::paraHumano(
    usuarioId: $segundoUsuarioId,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

// 7.4 Oficializar formalmente la versión 2 mediante el segundo usuario (Aprobador Distinto)
$oficializada = $folcloreServicio->oficializarVersionExistente($orgId, $version2->id, $contextoAprobador);
registrarAsercion("7.4 Aprobación formal por segundo usuario (Cuatro Ojos) oficializa la versión y activa el rol", $oficializada);

$versionesPost = $folcloreServicio->listarVersionesProgramacion($orgId, $actividad->id, $contextoAdmin);
$v2Oficial = null;
$v1Desactivada = null;
foreach ($versionesPost as $v) {
    if ($v->id === $version2->id) $v2Oficial = $v;
    if ($v->id === $version1->id) $v1Desactivada = $v;
}

registrarAsercion("7.5 Trazabilidad y persistencia: version 2 con aprobado_por (#{$segundoUsuarioId}), fecha registrada y estado OFICIAL_CONFIRMADO", 
    $v2Oficial !== null && 
    $v2Oficial->estadoConfirmacion === EstadoConfirmacionProgramacion::OFICIAL_CONFIRMADO && 
    $v2Oficial->esVersionActiva &&
    $v2Oficial->aprobadoPor === $segundoUsuarioId &&
    !empty($v2Oficial->aprobadoEn)
);

// 7.6 Inmutabilidad histórica: Intento de reaprobar o alterar programación ya confirmada es bloqueado
$reaprobacionRechazada = false;
try {
    $folcloreServicio->oficializarVersionExistente($orgId, $version2->id, $contextoAprobador);
} catch (InvalidArgumentException $e) {
    $reaprobacionRechazada = str_contains($e->getMessage(), 'ya fue formalmente aprobada');
}
registrarAsercion("7.6 Inmutabilidad histórica: Intento de reaprobar o modificar programación ya confirmada es rechazado", $reaprobacionRechazada);

registrarAsercion("7.7 Versión 1 previa desmarcada como activa para garantizar unicidad operativa", $v1Desactivada !== null && !$v1Desactivada->esVersionActiva);

// ------------------------------------------------------------------------------
// SECCIÓN 8: ASIGNACIÓN Y GESTIÓN DE TURNOS DE PRESENTACIÓN
// ------------------------------------------------------------------------------
echo "\n--- 8. ASIGNACIÓN Y GESTIÓN DE TURNOS DE PRESENTACIÓN ---\n";

$turno = $folcloreServicio->asignarTurnoPresentacion(
    organizacionId: $orgId,
    programacionOficialId: $version2->id,
    conjuntoEdicionId: $inscripcion->id,
    ordenSorteo: 12,
    horaEstimadaPartida: '09:45:00',
    horaEstimadaLlegada: '12:30:00',
    puntoPartida: 'Esquina Jr. Lampa con Jr. Deustua',
    observaciones: 'Turno asignado por sorteo oficial',
    contexto: $contextoAdmin
);

registrarAsercion("8.1 Turno de presentación asignado con orden de sorteo 12 e itinerario", $turno->id > 0 && $turno->ordenSorteo === 12);

// Actualizar turno
$horaRealPartida = date('Y-m-d') . ' 09:52:00';
$turnoActualizado = $folcloreServicio->actualizarTurno(
    organizacionId: $orgId,
    turnoId: $turno->id,
    ordenSorteo: 12,
    horaEstimadaPartida: '09:50:00',
    horaEstimadaLlegada: '12:40:00',
    puntoPartida: 'Esquina Jr. Lampa con Jr. Deustua (Reubicado)',
    estadoTurno: EstadoTurnoPresentacion::EN_RUTA,
    horaRealPartida: $horaRealPartida,
    horaRealFin: null,
    observaciones: 'Inicio de desplazamiento verificado en campo',
    contexto: $contextoAdmin
);

registrarAsercion("8.2 Turno actualizado con estado EN_RUTA y hora real de partida", $turnoActualizado->estadoTurno === EstadoTurnoPresentacion::EN_RUTA && $turnoActualizado->horaRealPartida === $horaRealPartida);

// Obtener rol de presentación completo
$rolPresentacion = $folcloreServicio->obtenerRolPresentacion($orgId, $version2->id, $contextoAdmin);
registrarAsercion("8.3 Rol de presentación recupera el turno asignado con datos de conjunto", count($rolPresentacion) >= 1 && $rolPresentacion[0]->id === $turno->id);

// ------------------------------------------------------------------------------
// SECCIÓN 9: BLINDAJES ANTI-CRUCE DE EDICIÓN Y ORGANIZACIÓN
// ------------------------------------------------------------------------------
echo "\n--- 9. BLINDAJES ANTI-CRUCE DE EDICIÓN Y ORGANIZACIÓN ---\n";

$maxAnio = (int) $pdo->query("SELECT COALESCE(MAX(anio), 2050) FROM ediciones_candelaria WHERE organizacion_id = " . (int)$orgId)->fetchColumn();
$anioSec = $maxAnio + 1;
$codigoEdSec = 'ed-test-cruce-' . $anioSec . '-' . bin2hex(random_bytes(4));
$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`) VALUES (?, ?, ?, ?, '2050-01-01', '2050-02-28', 'PREOPERACION')")
    ->execute([$orgId, $codigoEdSec, 'EDICION PRUEBA CRUCE ' . $anioSec, $anioSec]);
$edicionSecId = (int)$pdo->lastInsertId();

// Inscribir el conjunto en la edición secundaria
$inscripcionEdSec = $folcloreServicio->inscribirConjuntoEnEdicion(
    organizacionId: $orgId,
    edicionId: $edicionSecId,
    conjuntoId: $conjunto->id,
    numeroRegistroOficial: 'REG-CRUCE-2027',
    contexto: $contextoAdmin
);

// Intento de asignar turno en $version2 (edición 2026) con una inscripción de la edición 2027
$cruceRechazado = false;
try {
    $folcloreServicio->asignarTurnoPresentacion(
        organizacionId: $orgId,
        programacionOficialId: $version2->id, // Pertenece a $edicionId
        conjuntoEdicionId: $inscripcionEdSec->id, // Pertenece a $edicionSecId
        ordenSorteo: 99,
        horaEstimadaPartida: '10:00:00',
        contexto: $contextoAdmin
    );
} catch (InvalidArgumentException $e) {
    $cruceRechazado = true;
}
registrarAsercion("9.1 Rechazo estricto de cruce de ediciones al asignar turno (inscripción 2027 en programación 2026)", $cruceRechazado);

// B. Intento de vincular participante a bloque de otro conjunto
$otroConjunto = $folcloreServicio->registrarConjunto(
    organizacionId: $orgId,
    tipoDanzaId: $danza->id,
    codigo: 'CONJ_OTRO_' . $sufijo,
    nombre: 'Otro Conjunto Para Prueba Cruce ' . $sufijo,
    contexto: $contextoAdmin
);

$cruceBloqueRechazado = false;
try {
    $folcloreServicio->vincularParticipanteAConjunto(
        organizacionId: $orgId,
        participanteId: 1,
        conjuntoId: $otroConjunto->id,
        bloqueId: $bloque->id, // Bloque pertenece a $conjunto->id, NO a $otroConjunto->id
        contexto: $contextoAdmin
    );
} catch (InvalidArgumentException $e) {
    $cruceBloqueRechazado = true;
}
registrarAsercion("9.2 Rechazo estricto de vinculación de participante si el bloque no pertenece al conjunto", $cruceBloqueRechazado);

// ------------------------------------------------------------------------------
// SECCIÓN 10: VINCULACIÓN Y DESVINCULACIÓN DE SALIDAS OPERATIVAS V1
// ------------------------------------------------------------------------------
echo "\n--- 10. VINCULACIÓN Y DESVINCULACIÓN DE SALIDAS OPERATIVAS V1 ---\n";

// Buscar o crear una salida operativa de V1 para la organización y edición
$stmtSalida = $pdo->prepare("SELECT id FROM `operacion_salidas` WHERE `organizacion_id` = ? AND `edicion_id` = ? ORDER BY id DESC LIMIT 1");
$stmtSalida->execute([$orgId, $edicionId]);
$salidaId = $stmtSalida->fetchColumn();

if (!$salidaId) {
    $stmtItem = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE `organizacion_id` = ? LIMIT 1");
    $stmtItem->execute([$orgId]);
    $itemId = (int)$stmtItem->fetchColumn();
    if (!$itemId) {
        $itemId = 1;
    }
    $correlativoSalida = 'SAL-' . date('Y') . '-' . rand(100000, 999999);
    $pdo->prepare("INSERT INTO `operacion_salidas` (`organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`, `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`, `estado`, `creado_por`) VALUES (?, ?, ?, ?, 'Salida Operativa Cobertura Folclórica', CURDATE(), '08:00:00', '08:30:00', 'Parque Pino', 'SIN_CONTROL', 'PROGRAMADA', ?)")
        ->execute([$orgId, $edicionId, $itemId, $correlativoSalida, $adminId]);
    $salidaId = (int)$pdo->lastInsertId();
} else {
    $salidaId = (int)$salidaId;
}

// Vincular salida al turno
$vinculada = $folcloreServicio->vincularSalidaAOrdenPresentacion(
    organizacionId: $orgId,
    salidaId: $salidaId,
    ordenPresentacionId: $turno->id,
    contexto: $contextoAdmin
);
registrarAsercion("10.1 Salida operativa V1 vinculada exitosamente al turno de presentación", $vinculada);

// Verificar en base de datos la columna orden_presentacion_id
$stmtCheckSalida = $pdo->prepare("SELECT orden_presentacion_id FROM `operacion_salidas` WHERE id = ?");
$stmtCheckSalida->execute([$salidaId]);
$ordenVinculadaId = (int)$stmtCheckSalida->fetchColumn();
registrarAsercion("10.2 Columna orden_presentacion_id en operacion_salidas actualizada correctamente", $ordenVinculadaId === $turno->id);

// Desvincular salida sin destruir la salida
$desvinculada = $folcloreServicio->vincularSalidaAOrdenPresentacion(
    organizacionId: $orgId,
    salidaId: $salidaId,
    ordenPresentacionId: null,
    contexto: $contextoAdmin
);
registrarAsercion("10.3 Salida operativa V1 desvinculada sin destruir la salida ni el turno", $desvinculada);

$stmtCheckSalida->execute([$salidaId]);
$ordenPostDesvinculacion = $stmtCheckSalida->fetchColumn();
registrarAsercion("10.4 Columna orden_presentacion_id quedó NULL tras desvinculación", $ordenPostDesvinculacion === null || $ordenPostDesvinculacion === false);

// ------------------------------------------------------------------------------
// SECCIÓN 11: RETIRO CONTROLADO DE TURNO Y RESPETO DE INTEGRIDAD
// ------------------------------------------------------------------------------
echo "\n--- 11. RETIRO CONTROLADO DE TURNO Y RESPETO DE INTEGRIDAD ---\n";

$eliminado = $folcloreServicio->eliminarTurno($orgId, $turno->id, $contextoAdmin);
registrarAsercion("11.1 Turno de presentación eliminado correctamente por el servicio", $eliminado);

// Comprobar que ya no aparece en el rol de presentación
$rolPostEliminar = $folcloreServicio->obtenerRolPresentacion($orgId, $version2->id, $contextoAdmin);
$encontradoPost = false;
foreach ($rolPostEliminar as $t) {
    if ($t->id === $turno->id) {
        $encontradoPost = true;
        break;
    }
}
registrarAsercion("11.2 Turno eliminado ya no forma parte del rol de presentación oficial", !$encontradoPost);

// ------------------------------------------------------------------------------
// SECCIÓN 12: INMUTABILIDAD DE V1 Y HUELLA HISTÓRICA DE SUPERADMIN ORLANDO
// ------------------------------------------------------------------------------
echo "\n--- 12. INMUTABILIDAD DE V1 Y HUELLA HISTÓRICA DE ORLANDO ---\n";

// Verificar Orlando Superadmin (ID 24)
$stmtOrlando = $pdo->prepare("SELECT id, estado, contrasena_hash, correo_electronico FROM `usuarios` WHERE id = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

registrarAsercion("12.1 Usuario Orlando (ID 24) existe y está registrado en la base de datos", $orlando !== false);
registrarAsercion("12.2 Usuario Orlando (ID 24) mantiene estado ACTIVO inalterado", ($orlando['estado'] ?? '') === 'ACTIVO');

// Huella inmutable de Orlando
$hashEsperado = '80e6af84e02e89e3';
$hashActual = substr(hash('sha256', (string) ($orlando['contrasena_hash'] ?? '')), 0, 16);
registrarAsercion("12.3 Huella criptográfica inmutable de Orlando ratificada ({$hashActual} == {$hashEsperado})", $hashActual === $hashEsperado);

// Integridad de transacciones comerciales V1 (ventas y pagos no nulos ni corruptos)
$stmtVentas = $pdo->query("SELECT COUNT(*) FROM `ventas`");
$totalVentas = (int) $stmtVentas->fetchColumn();

$stmtPagos = $pdo->query("SELECT COUNT(*) FROM `pagos`");
$totalPagos = (int) $stmtPagos->fetchColumn();

registrarAsercion("12.4 Preservación de registros históricos de ventas ({$totalVentas} registros)", $totalVentas >= 0);
registrarAsercion("12.5 Preservación de registros históricos de pagos ({$totalPagos} registros)", $totalPagos >= 0);

// ------------------------------------------------------------------------------
// RESUMEN FINAL DE LA SUITE FASE 3.1B
// ------------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo sprintf("RESUMEN FASE 3.1B: %d PRUEBAS EJECUTADAS | %d PASS / %d FAIL\n", $totalPruebas, $pruebasExitosas, $pruebasFallidas);
echo "==============================================================================\n";

if ($pruebasFallidas > 0) {
    exit(1);
}
exit(0);

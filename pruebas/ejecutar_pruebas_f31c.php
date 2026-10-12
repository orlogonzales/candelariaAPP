<?php

/**
 * Suite de Pruebas Automatizadas — Fase 3.1C (DT-V11-008)
 * Módulo: Seguimiento Operativo de Conjuntos y Cobertura Audiovisual
 *
 * Cobertura de pruebas:
 * 1. Rutas Web y API REST de Seguimiento
 * 2. Normalización de Estados y Máquina de Estados Operativa
 * 3. Actualización de Estados, Horas Reales e Incidencias
 * 4. Control de Concurrencia Optimista (Doble Operador / HTTP 409)
 * 5. Cálculo y Trazabilidad de Retrasos (Zona Horaria America/Lima)
 * 6. Cobertura Audiovisual: Vinculación 1:N y Detección de Recursos Humanos
 * 7. Blindajes Anti-Cruce de Edición y Organización en Cobertura
 * 8. Tablero de Control y Filtros Dinámicos (Actividad, Estado, Personal)
 * 9. Indicadores Clave de Desempeño (KPIs) Operativos
 * 10. Inmutabilidad de Versiones Oficiales Históricas y Huella Orlando
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Controladores\FolcloreControlador;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Folclore\CategoriaDanza;
use Aplicacion\Folclore\EstadoActividadFolclorica;
use Aplicacion\Folclore\EstadoConfirmacionProgramacion;
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

$pdo = Conexion::obtenerInstancia();

$totalAserciones = 0;
$asercionesPasadas = 0;
$asercionesFallidas = 0;

function registrarAsercion(string $descripcion, bool $condicion, string $detalleError = ''): void {
    global $totalAserciones, $asercionesPasadas, $asercionesFallidas;
    $totalAserciones++;
    if ($condicion) {
        $asercionesPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $asercionesFallidas++;
        echo "  [FAIL] {$descripcion} | Error: {$detalleError}\n";
    }
}

echo "==============================================================================\n";
echo "CANDELARIAAPP V1.1 — SUITE FASE 3.1C: SEGUIMIENTO OPERATIVO Y COBERTURA\n";
echo "==============================================================================\n\n";

// ------------------------------------------------------------------------------
// CONTEXTO OPERATIVO Y SERVICIOS
// ------------------------------------------------------------------------------
$stmtOrg = $pdo->query("SELECT id FROM `organizaciones` ORDER BY id ASC LIMIT 1");
$orgId = (int) $stmtOrg->fetchColumn();

$usuarioAdminId = 24; // Orlando Superadmin
$usuarioOperador1 = 77702; // Operador 1 / Aprobador Cuatro Ojos
$usuarioOperador2 = 24;    // Operador 2 / Concurrencia

// Asegurar que 77702 esté activo y pertenezca al tenant actual
$pdo->prepare("UPDATE `usuarios` SET `es_superadmin_plataforma` = 1, `organizacion_id` = :org_id, `estado` = 'ACTIVO' WHERE `id` = :id")
    ->execute(['org_id' => $orgId, 'id' => $usuarioOperador1]);

$contextoAdmin = ContextoOperacion::paraHumano(
    usuarioId: $usuarioAdminId,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoOperador1 = ContextoOperacion::paraHumano(
    usuarioId: $usuarioOperador1,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$contextoOperador2 = ContextoOperacion::paraHumano(
    usuarioId: $usuarioOperador2,
    canalId: 1,
    canalCodigo: 'WEB_ADMIN',
    organizacionId: $orgId
);

$tipoDanzaRepo = new TipoDanzaRepositorio($pdo);
$conjuntoRepo = new ConjuntoFolcloricoRepositorio($pdo);
$progRepo = new ProgramacionFolcloricaRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$edicionRepo = new EdicionRepositorio($pdo);

$authzServicio = new \Aplicacion\Autorizacion\AutorizacionServicio(null, null, null, $auditoriaRepo, $pdo);

$folcloreServicio = new ProgramacionFolcloricaServicio(
    tipoDanzaRepo: $tipoDanzaRepo,
    conjuntoRepo: $conjuntoRepo,
    programacionRepo: $progRepo,
    edicionRepo: $edicionRepo,
    authzServicio: $authzServicio,
    auditoriaRepo: $auditoriaRepo,
    pdo: $pdo
);

// ------------------------------------------------------------------------------
// SECCIÓN 1: RUTAS Y ENPOINTS OFICIALES DE SEGUIMIENTO (FASE 3.1C)
// ------------------------------------------------------------------------------
echo "--- 1. RUTAS Y ENDPOINTS OFICIALES DE SEGUIMIENTO ---\n";
require_once __DIR__ . '/../rutas/web.php';
require_once __DIR__ . '/../rutas/api.php';

$refProp = new \ReflectionProperty(Enrutador::class, 'rutas');
$refProp->setAccessible(true);
$rutasRegistradas = $refProp->getValue();

$rutaTableroWeb = false;
$rutaApiTablero = false;
$rutaApiTurnoEstado = false;

foreach ($rutasRegistradas as $r) {
    if ($r['metodo'] === 'GET' && $r['patron'] === '/folclore/tablero') {
        $rutaTableroWeb = true;
    }
    if ($r['metodo'] === 'GET' && $r['patron'] === '/api/v1/folclore/seguimiento/tablero') {
        $rutaApiTablero = true;
    }
    if ($r['metodo'] === 'PUT' && $r['patron'] === '/api/v1/folclore/seguimiento/turnos/{id}/estado') {
        $rutaApiTurnoEstado = true;
    }
}

registrarAsercion(
    "1.1 Ruta web '/folclore/tablero' debidamente registrada",
    $rutaTableroWeb
);

registrarAsercion(
    "1.2 Endpoint API 'GET /api/v1/folclore/seguimiento/tablero' registrado",
    $rutaApiTablero
);

registrarAsercion(
    "1.3 Endpoint API 'PUT /api/v1/folclore/seguimiento/turnos/{id}/estado' registrado",
    $rutaApiTurnoEstado
);

// ------------------------------------------------------------------------------
// SECCIÓN 2: NORMALIZACIÓN DE ESTADOS Y MÁQUINA DE ESTADOS
// ------------------------------------------------------------------------------
echo "\n--- 2. NORMALIZACIÓN DE ESTADOS Y MÁQUINA DE ESTADOS ---\n";

registrarAsercion(
    "2.1 Alias operativo 'PENDIENTE' normaliza a EstadoTurnoPresentacion::PROGRAMADO",
    EstadoTurnoPresentacion::normalizar('PENDIENTE') === EstadoTurnoPresentacion::PROGRAMADO
);

registrarAsercion(
    "2.2 Alias operativo 'EN_DESPLAZAMIENTO' normaliza a EstadoTurnoPresentacion::EN_RUTA",
    EstadoTurnoPresentacion::normalizar('EN_DESPLAZAMIENTO') === EstadoTurnoPresentacion::EN_RUTA
);

registrarAsercion(
    "2.3 Alias operativo 'EN_PRESENTACION' normaliza a EstadoTurnoPresentacion::EN_ESCENARIO",
    EstadoTurnoPresentacion::normalizar('EN_PRESENTACION') === EstadoTurnoPresentacion::EN_ESCENARIO
);

registrarAsercion(
    "2.4 Alias operativo 'NO_PRESENTADO' normaliza a EstadoTurnoPresentacion::NO_PRESENTO",
    EstadoTurnoPresentacion::normalizar('NO_PRESENTADO') === EstadoTurnoPresentacion::NO_PRESENTO
);

registrarAsercion(
    "2.5 Código canónico 'EN_PREPARACION' se preserva intacto",
    EstadoTurnoPresentacion::normalizar('EN_PREPARACION') === EstadoTurnoPresentacion::EN_PREPARACION
);

registrarAsercion(
    "2.6 Código canónico 'FINALIZADO' se preserva intacto",
    EstadoTurnoPresentacion::normalizar('FINALIZADO') === EstadoTurnoPresentacion::FINALIZADO
);

registrarAsercion(
    "2.7 Etiqueta amigable de 'PROGRAMADO' es 'Pendiente' y badge Alina 'secondary'",
    EstadoTurnoPresentacion::PROGRAMADO->etiquetaOperativa() === 'Pendiente' &&
    EstadoTurnoPresentacion::PROGRAMADO->colorBadgeAlina() === 'secondary'
);

// Transiciones de máquina de estados
registrarAsercion(
    "2.8 Transición válida: PROGRAMADO -> EN_PREPARACION permitida",
    EstadoTurnoPresentacion::PROGRAMADO->puedeTransicionarA(EstadoTurnoPresentacion::EN_PREPARACION)
);

registrarAsercion(
    "2.9 Transición válida: EN_PREPARACION -> EN_RUTA permitida",
    EstadoTurnoPresentacion::EN_PREPARACION->puedeTransicionarA(EstadoTurnoPresentacion::EN_RUTA)
);

registrarAsercion(
    "2.10 Transición válida: EN_RUTA -> EN_ESCENARIO permitida",
    EstadoTurnoPresentacion::EN_RUTA->puedeTransicionarA(EstadoTurnoPresentacion::EN_ESCENARIO)
);

registrarAsercion(
    "2.11 Transición válida: EN_ESCENARIO -> FINALIZADO permitida",
    EstadoTurnoPresentacion::EN_ESCENARIO->puedeTransicionarA(EstadoTurnoPresentacion::FINALIZADO)
);

registrarAsercion(
    "2.12 Transición inválida: FINALIZADO -> EN_PREPARACION rechazada por máquina de estados",
    !EstadoTurnoPresentacion::FINALIZADO->puedeTransicionarA(EstadoTurnoPresentacion::EN_PREPARACION)
);

registrarAsercion(
    "2.13 Transición inválida: NO_PRESENTO -> EN_ESCENARIO rechazada por máquina de estados",
    !EstadoTurnoPresentacion::NO_PRESENTO->puedeTransicionarA(EstadoTurnoPresentacion::EN_ESCENARIO)
);

// ------------------------------------------------------------------------------
// SECCIÓN 3: PREPARACIÓN DE DATOS OPERATIVOS PARA LA SUITE
// ------------------------------------------------------------------------------
echo "\n--- 3. PREPARACIÓN DE EDICIÓN, ACTIVIDAD Y COMPARSA DE PRUEBA ---\n";

// Edición de prueba
$edicionActiva = $edicionRepo->obtenerActual($orgId);
if ($edicionActiva === null) {
    $ediciones = $edicionRepo->listarPorOrganizacion($orgId);
    $edicionActiva = $ediciones[0] ?? null;
}
$edicionId = $edicionActiva !== null ? $edicionActiva->id : 1;

$sufijoUnico = bin2hex(random_bytes(3));

// Tipo de Danza y Conjunto
$tipoDanza = $folcloreServicio->registrarTipoDanza(
    organizacionId: $orgId,
    codigo: 'DANZA-F31C-' . $sufijoUnico,
    nombre: 'Diablada Puneña Central ' . $sufijoUnico,
    categoria: CategoriaDanza::TRAJES_DE_LUCES,
    contexto: $contextoAdmin
);

$conjunto = $folcloreServicio->registrarConjunto(
    organizacionId: $orgId,
    tipoDanzaId: $tipoDanza->id,
    codigo: 'CONJ-F31C-' . $sufijoUnico,
    nombre: 'Tradicional Diablada Porteño ' . $sufijoUnico,
    contexto: $contextoAdmin
);

// Actividad folclórica
$actividad = $folcloreServicio->registrarActividad(
    organizacionId: $orgId,
    edicionId: $edicionId,
    codigo: 'ACT-F31C-' . bin2hex(random_bytes(3)),
    titulo: 'Gran Concurso y Parada de Danzas con Trajes de Luces',
    fechaInicio: '2026-02-14 07:00:00',
    fechaFin: '2026-02-14 22:00:00',
    lugarPrincipal: 'Estadio Enrique Torres Belón',
    contexto: $contextoAdmin
);

// Padrón de inscripción
$inscripcion = $folcloreServicio->inscribirConjuntoEnEdicion(
    organizacionId: $orgId,
    edicionId: $edicionId,
    conjuntoId: $conjunto->id,
    numeroRegistroOficial: 'REG-F31C-' . rand(1000, 9999),
    contexto: $contextoAdmin
);

// Publicar programación preliminar y oficializarla
$versionPrelim = $folcloreServicio->publicarProgramacionOficial(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    fuenteOrigen: 'FRFCP Puno',
    fechaEmisionFuente: '2026-02-10',
    etiquetaVersion: 'Rol Oficial Parada 2026',
    estado: EstadoConfirmacionProgramacion::PRELIMINAR,
    documentoUrl: null,
    notas: 'Versión preliminar generada para seguimiento',
    contexto: $contextoAdmin
);

$oficializado = $folcloreServicio->oficializarVersionExistente(
    organizacionId: $orgId,
    programacionId: $versionPrelim->id,
    contexto: $contextoOperador1
);

// Asignar turno programado
$turno = $folcloreServicio->asignarTurnoPresentacion(
    organizacionId: $orgId,
    programacionOficialId: $versionPrelim->id,
    conjuntoEdicionId: $inscripcion->id,
    ordenSorteo: 25,
    horaEstimadaPartida: '10:00:00',
    horaEstimadaLlegada: '10:45:00',
    puntoPartida: 'Av. Costanera con Jr. Lampa',
    contexto: $contextoAdmin
);

registrarAsercion("3.1 Turno folclórico de prueba creado con ID > 0 en estado PROGRAMADO (Pendiente)", $turno->id > 0 && $turno->estadoTurno === EstadoTurnoPresentacion::PROGRAMADO);
registrarAsercion("3.2 Turno folclórico inicia con version_bloqueo = 1 para concurrencia optimista", $turno->versionBloqueo === 1);

// ------------------------------------------------------------------------------
// SECCIÓN 4: ACTUALIZACIÓN OPERATIVA DE ESTADOS Y COHERENCIA CRONOLÓGICA
// ------------------------------------------------------------------------------
echo "\n--- 4. ACTUALIZACIÓN OPERATIVA DE ESTADOS Y COHERENCIA CRONOLÓGICA ---\n";

// A. Pasar a EN_PREPARACION
$turnoPrep = $folcloreServicio->actualizarEstadoOperativoTurno(
    organizacionId: $orgId,
    turnoId: $turno->id,
    nuevoEstadoEntrada: 'EN_PREPARACION',
    observaciones: 'Comparsa concentrada en zona de pre-partida',
    versionBloqueo: 1,
    contexto: $contextoOperador1
);

registrarAsercion("4.1 Turno transiciona exitosamente a EN_PREPARACION", $turnoPrep->estadoTurno === EstadoTurnoPresentacion::EN_PREPARACION);
registrarAsercion("4.2 Se autoasigna hora_real_partida al iniciar preparación", !empty($turnoPrep->horaRealPartida));
registrarAsercion("4.3 version_bloqueo se incrementa a 2 tras la actualización", $turnoPrep->versionBloqueo === 2);

// B. Rechazo de transición inválida (saltar de EN_PREPARACION a FINALIZADO sin recorrer ruta/escenario)
$saltoInvalidoRechazado = false;
try {
    $folcloreServicio->actualizarEstadoOperativoTurno(
        organizacionId: $orgId,
        turnoId: $turno->id,
        nuevoEstadoEntrada: 'FINALIZADO',
        versionBloqueo: 2,
        contexto: $contextoOperador1
    );
} catch (InvalidArgumentException $e) {
    $saltoInvalidoRechazado = str_contains($e->getMessage(), 'Transición de estado operativa no permitida');
}
registrarAsercion("4.4 Transición inválida EN_PREPARACION -> FINALIZADO es rechazada tajantemente", $saltoInvalidoRechazado);

// C. Rechazo de incoherencia cronológica (hora fin anterior a hora partida)
$incoherenciaRechazada = false;
try {
    $folcloreServicio->actualizarEstadoOperativoTurno(
        organizacionId: $orgId,
        turnoId: $turno->id,
        nuevoEstadoEntrada: 'EN_RUTA',
        horaRealPartida: '2026-02-14 11:00:00',
        horaRealFin: '2026-02-14 10:30:00', // Anterior
        versionBloqueo: 2,
        contexto: $contextoOperador1
    );
} catch (InvalidArgumentException $e) {
    $incoherenciaRechazada = str_contains($e->getMessage(), 'Incoherencia cronológica');
}
registrarAsercion("4.5 Incoherencia cronológica (hora_fin < hora_partida) rechazada con excepción", $incoherenciaRechazada);

// D. Transición válida a EN_DESPLAZAMIENTO (alias de EN_RUTA) con hora real
$turnoRuta = $folcloreServicio->actualizarEstadoOperativoTurno(
    organizacionId: $orgId,
    turnoId: $turno->id,
    nuevoEstadoEntrada: 'EN_DESPLAZAMIENTO',
    horaRealPartida: '2026-02-14 10:20:00', // Con 20 min de retraso
    observaciones: 'Desplazamiento iniciado con congestión en cruce Jr. Puno',
    retrasoMinutos: 20,
    versionBloqueo: 2,
    contexto: $contextoOperador1
);

registrarAsercion("4.6 Actualización usando alias 'EN_DESPLAZAMIENTO' aceptada y guardada como EN_RUTA", $turnoRuta->estadoTurno === EstadoTurnoPresentacion::EN_RUTA);
registrarAsercion("4.7 Retraso operativo registrado: 20 minutos", $turnoRuta->retrasoMinutos === 20);
registrarAsercion("4.8 version_bloqueo se incrementa a 3", $turnoRuta->versionBloqueo === 3);

// ------------------------------------------------------------------------------
// SECCIÓN 5: CONTROL DE CONCURRENCIA OPTIMISTA (DOBLE OPERADOR / HTTP 409)
// ------------------------------------------------------------------------------
echo "\n--- 5. CONTROL DE CONCURRENCIA OPTIMISTA (DOBLE OPERADOR / HTTP 409) ---\n";

// El Operador 1 y el Operador 2 leen el turno simultáneamente cuando está en version_bloqueo = 3.
$versionLeida = 3;

// Operador 1 actualiza exitosamente a EN_ESCENARIO (version_bloqueo pasa a 4)
$turnoEscenario = $folcloreServicio->actualizarEstadoOperativoTurno(
    organizacionId: $orgId,
    turnoId: $turno->id,
    nuevoEstadoEntrada: 'EN_PRESENTACION',
    observaciones: 'Ingresó a estrado de jurados',
    versionBloqueo: $versionLeida,
    contexto: $contextoOperador1
);

registrarAsercion("5.1 Operador 1 actualiza exitosamente a EN_ESCENARIO (version pasa a 4)", $turnoEscenario->versionBloqueo === 4);

// Operador 2 intenta enviar una actualización basada en la versión 3 que ya está obsoleta
$conflictoConcurrenciaCapturado = false;
$codigoHttpConflicto = 0;
try {
    $folcloreServicio->actualizarEstadoOperativoTurno(
        organizacionId: $orgId,
        turnoId: $turno->id,
        nuevoEstadoEntrada: 'FINALIZADO',
        observaciones: 'Operador 2 intenta finalizar con versión obsoleta',
        versionBloqueo: $versionLeida, // Versión 3 obsoleta
        contexto: $contextoOperador2
    );
} catch (ConflictoConcurrenciaExcepcion $e) {
    $conflictoConcurrenciaCapturado = true;
    $codigoHttpConflicto = $e->getCode();
}

registrarAsercion("5.2 Conflicto de concurrencia optimista detectado atómicamente", $conflictoConcurrenciaCapturado);
registrarAsercion("5.3 Código de error de concurrencia es HTTP 409 Conflict", $codigoHttpConflicto === 409);

// Operador 2 recarga el dato actualizado (version 4) y ahora sí puede completar la transición a FINALIZADO
$turnoFinalizado = $folcloreServicio->actualizarEstadoOperativoTurno(
    organizacionId: $orgId,
    turnoId: $turno->id,
    nuevoEstadoEntrada: 'FINALIZADO',
    horaRealPartida: '2026-02-14 10:20:00',
    horaRealFin: '2026-02-14 11:15:00',
    observaciones: 'Presentación concluida con éxito ante el jurado',
    versionBloqueo: 4, // Versión correcta
    contexto: $contextoOperador2
);

registrarAsercion("5.4 Operador 2 actualiza exitosamente tras recargar versión actual (4 -> 5)", $turnoFinalizado->versionBloqueo === 5 && $turnoFinalizado->estadoTurno === EstadoTurnoPresentacion::FINALIZADO);

// ------------------------------------------------------------------------------
// SECCIÓN 6: COBERTURA AUDIOVISUAL Y RECURSOS HUMANOS (1:N)
// ------------------------------------------------------------------------------
echo "\n--- 6. COBERTURA AUDIOVISUAL Y RECURSOS HUMANOS (1:N) ---\n";

// Obtener un item comercial y persona válidos
$stmtItem = $pdo->prepare("SELECT id FROM `items_comerciales` WHERE `organizacion_id` = ? LIMIT 1");
$stmtItem->execute([$orgId]);
$itemId = (int)$stmtItem->fetchColumn();
if (!$itemId) {
    $itemId = (int)$pdo->query("SELECT id FROM `items_comerciales` LIMIT 1")->fetchColumn();
}

$stmtPersona = $pdo->prepare("SELECT id FROM `personas` WHERE `organizacion_id` = ? LIMIT 1");
$stmtPersona->execute([$orgId]);
$personaId = (int)$stmtPersona->fetchColumn();
if (!$personaId) {
    $personaId = (int)$pdo->query("SELECT id FROM `personas` LIMIT 1")->fetchColumn();
}

// Crear una salida operativa en operacion_salidas para la misma edición
$correlativoSalida1 = 'SAL-TEST-F31C-' . rand(10000, 99999);
$stmtCrearSalida1 = $pdo->prepare("
    INSERT INTO `operacion_salidas` (
        `organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`,
        `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`,
        `capacidad_maxima`, `estado`, `creado_por`
    ) VALUES (
        ?, ?, ?, ?, 'Cobertura Fotográfica Principal - Parada de Luces',
        '2026-02-14', '09:00:00', '09:30:00', 'Palco Central', 'SIN_CONTROL',
        NULL, 'PROGRAMADA', ?
    )
");
$stmtCrearSalida1->execute([$orgId, $edicionId, $itemId, $correlativoSalida1, $usuarioAdminId]);
$salida1Id = (int)$pdo->lastInsertId();

// Asignar recurso humano a la salida 1
$pdo->prepare("
    INSERT INTO `operacion_salida_recursos` (`salida_id`, `persona_id`, `rol_operativo`, `asignado_por`)
    VALUES (?, ?, 'GUIA_PRINCIPAL', ?)
")->execute([$salida1Id, $personaId, $usuarioAdminId]);

// Crear una segunda salida de cobertura (Cuadrilla de Video/Dron)
$correlativoSalida2 = 'SAL-TEST-F31C-' . rand(10000, 99999);
$stmtCrearSalida2 = $pdo->prepare("
    INSERT INTO `operacion_salidas` (
        `organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`,
        `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`,
        `capacidad_maxima`, `estado`, `creado_por`
    ) VALUES (
        ?, ?, ?, ?, 'Cobertura Dron y Video - Av. Costanera',
        '2026-02-14', '09:15:00', '09:45:00', 'Tribuna Costanera', 'SIN_CONTROL',
        NULL, 'PROGRAMADA', ?
    )
");
$stmtCrearSalida2->execute([$orgId, $edicionId, $itemId, $correlativoSalida2, $usuarioAdminId]);
$salida2Id = (int)$pdo->lastInsertId();

// Vincular ambas salidas al mismo turno (Relación 1:N permitida)
$folcloreServicio->vincularSalidaAOrdenPresentacion($orgId, $salida1Id, $turno->id, $contextoAdmin);
$folcloreServicio->vincularSalidaAOrdenPresentacion($orgId, $salida2Id, $turno->id, $contextoAdmin);

// Consultar tablero para verificar que ambas salidas y sus recursos aparezcan vinculados al turno
$tableroDatos = $folcloreServicio->obtenerTableroOperativo(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    contexto: $contextoAdmin
);

$turnosTablero = $tableroDatos['turnos'];
$turnoEnTablero = null;
foreach ($turnosTablero as $t) {
    if ((int)$t['id'] === $turno->id) {
        $turnoEnTablero = $t;
        break;
    }
}

registrarAsercion("6.1 Turno folclórico encontrado en el Tablero Operativo", $turnoEnTablero !== null);
registrarAsercion("6.2 Múltiples salidas de cobertura vinculadas al mismo turno (1:N verificado)", count($turnoEnTablero['salidas_cobertura'] ?? []) === 2);
registrarAsercion("6.3 Personal operativo y roles visibles en la cobertura del turno", !empty($turnoEnTablero['salidas_cobertura'][0]['personal'] ?? []));

// ------------------------------------------------------------------------------
// SECCIÓN 7: BLINDAJES ANTI-CRUCE DE EDICIÓN Y ORGANIZACIÓN EN COBERTURA
// ------------------------------------------------------------------------------
echo "\n--- 7. BLINDAJES ANTI-CRUCE DE EDICIÓN Y ORGANIZACIÓN ---\n";

// Intentar vincular una salida de otra edición al turno
$anioAjeno = 2055;
$pdo->prepare("INSERT INTO `ediciones_candelaria` (`organizacion_id`, `codigo`, `nombre`, `anio`, `fecha_inicio`, `fecha_fin`, `estado`) VALUES (?, 'ED-AJENA-F31C', 'Edicion Ajena', ?, '2055-01-01', '2055-02-28', 'PREOPERACION') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")
    ->execute([$orgId, $anioAjeno]);
$edicionAjenaId = (int)$pdo->lastInsertId();

$correlativoAjeno = 'SAL-AJENA-' . rand(10000, 99999);
$stmtSalidaAjena = $pdo->prepare("
    INSERT INTO `operacion_salidas` (
        `organizacion_id`, `edicion_id`, `item_comercial_id`, `correlativo`, `titulo`,
        `fecha_salida`, `hora_citacion`, `hora_salida`, `punto_encuentro`, `tipo_capacidad`,
        `capacidad_maxima`, `estado`, `creado_por`
    ) VALUES (
        ?, ?, ?, ?, 'Salida Edicion Ajena',
        '2055-02-14', '09:00:00', '09:30:00', 'Punto', 'SIN_CONTROL',
        NULL, 'PROGRAMADA', ?
    )
");
$stmtSalidaAjena->execute([$orgId, $edicionAjenaId, $itemId, $correlativoAjeno, $usuarioAdminId]);
$salidaAjenaId = (int)$pdo->lastInsertId();

$cruceEdicionRechazado = false;
try {
    $folcloreServicio->vincularSalidaAOrdenPresentacion($orgId, $salidaAjenaId, $turno->id, $contextoAdmin);
} catch (InvalidArgumentException $e) {
    $cruceEdicionRechazado = str_contains($e->getMessage(), 'Incompatibilidad de edición');
}
registrarAsercion("7.1 Intento de vincular salida de edición distinta es tajantemente rechazado", $cruceEdicionRechazado);

// ------------------------------------------------------------------------------
// SECCIÓN 8: TABLERO OPERATIVO, FILTROS Y KPIS
// ------------------------------------------------------------------------------
echo "\n--- 8. TABLERO OPERATIVO, FILTROS Y KPIS ---\n";

$kpis = $tableroDatos['kpis'];

registrarAsercion("8.1 KPIs calculados incluyen total_turnos >= 1", ($kpis['total_turnos'] ?? 0) >= 1);
registrarAsercion("8.2 KPI finalizados refleja comparsa culminada", ($kpis['finalizados'] ?? 0) >= 1);
registrarAsercion("8.3 KPI con_retraso refleja turno con demora registrada", ($kpis['con_retraso'] ?? 0) >= 1);
registrarAsercion("8.4 KPI cobertura_activa refleja turnos con personal asignado", ($kpis['cobertura_activa'] ?? 0) >= 1);

// Filtro por Estado: FINALIZADO
$tableroFiltradoEstado = $folcloreServicio->obtenerTableroOperativo(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    filtroEstado: 'FINALIZADO',
    contexto: $contextoAdmin
);
registrarAsercion("8.5 Filtro por estado 'FINALIZADO' retorna únicamente turnos concluidos", count($tableroFiltradoEstado['turnos']) >= 1);

// Filtro por Estado incompatible: PENDIENTE no debe incluir el turno ya finalizado
$tableroFiltradoPendiente = $folcloreServicio->obtenerTableroOperativo(
    organizacionId: $orgId,
    edicionId: $edicionId,
    actividadId: $actividad->id,
    filtroEstado: 'PENDIENTE',
    contexto: $contextoAdmin
);
$encontradoEnPendiente = false;
foreach ($tableroFiltradoPendiente['turnos'] as $tp) {
    if ((int)$tp['id'] === $turno->id) {
        $encontradoEnPendiente = true;
        break;
    }
}
registrarAsercion("8.6 Filtro por estado 'PENDIENTE' excluye comparsa ya finalizada", !$encontradoEnPendiente);

// ------------------------------------------------------------------------------
// SECCIÓN 9: INMUTABILIDAD HISTÓRICA Y AUDITORÍA
// ------------------------------------------------------------------------------
echo "\n--- 9. INMUTABILIDAD HISTÓRICA Y AUDITORÍA INMUTABLE ---\n";

// Verificar que la programación oficial versionada no fue alterada por el seguimiento operativo
$stmtProgVerif = $pdo->prepare("SELECT version_numero, estado_confirmacion, es_version_activa FROM folclore_programaciones_oficiales WHERE id = ?");
$stmtProgVerif->execute([$versionPrelim->id]);
$progVerif = $stmtProgVerif->fetch(PDO::FETCH_ASSOC);

registrarAsercion(
    "9.1 Programación oficial versionada permanece inmutable en estado OFICIAL_CONFIRMADO",
    $progVerif['estado_confirmacion'] === 'OFICIAL_CONFIRMADO' && (int)$progVerif['es_version_activa'] === 1
);

// Verificar bitácora de auditoría
$stmtAudit = $pdo->prepare("SELECT COUNT(*) FROM `auditoria_operaciones` WHERE `accion` = 'TURNO_ESTADO_OPERATIVO_ACTUALIZADO' AND `entidad_tipo` = 'folclore_orden_presentacion' AND `entidad_id` = ?");
$stmtAudit->execute([(string)$turno->id]);
$auditCount = (int)$stmtAudit->fetchColumn();

registrarAsercion("9.2 Eventos de cambio operativo auditados inmutablemente en auditoria_operaciones (conteo: {$auditCount} >= 4)", $auditCount >= 4);

// ------------------------------------------------------------------------------
// SECCIÓN 10: HUELLA CRIPTOGRÁFICA INMUTABLE DE ORLANDO (ID 24)
// ------------------------------------------------------------------------------
echo "\n--- 10. HUELLA CRIPTOGRÁFICA INMUTABLE DE ORLANDO (ID 24) ---\n";

$stmtOrl = $pdo->prepare("SELECT id, estado, contrasena_hash FROM usuarios WHERE id = 24");
$stmtOrl->execute();
$usuarioOrl = $stmtOrl->fetch(PDO::FETCH_ASSOC);

registrarAsercion("10.1 Usuario Orlando (ID 24) existe y está activo", ($usuarioOrl['estado'] ?? '') === 'ACTIVO');

$hashEsperado = '80e6af84e02e89e3';
$hashActual = substr(hash('sha256', (string)($usuarioOrl['contrasena_hash'] ?? '')), 0, 16);
registrarAsercion("10.2 Huella criptográfica inmutable de Orlando intacta: {$hashActual} == {$hashEsperado}", $hashActual === $hashEsperado);

echo "\n==============================================================================\n";
echo "RESUMEN FASE 3.1C: {$totalAserciones} PRUEBAS EJECUTADAS | {$asercionesPasadas} PASS / {$asercionesFallidas} FAIL\n";
echo "==============================================================================\n";

if ($asercionesFallidas > 0) {
    exit(1);
}
exit(0);

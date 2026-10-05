<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Controladores\EdicionControlador;
use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Ediciones\EdicionServicio;
use Aplicacion\Ediciones\EstadoEdicion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.1C: HARDENING Y GATE FINAL DE EDICIONES\n";
echo "CERTIFICACIÓN INTEGRAL DE CONTEXTO, FAIL-CLOSED, MULTI-TENANT Y MÁQUINA DE ESTADOS\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] " . $descripcion . "\n";
    } else {
        $fallos++;
        echo " [FAIL] " . $descripcion . ($detalles ? " -> Detalle: " . $detalles : "") . "\n";
    }
}

// ==============================================================================
// BLOQUE 1: VERIFICACIÓN FÍSICA DE BASE DE DATOS Y CLEAN INSTALL
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA LIMPIO Y VERIFICACIÓN DE INSTALACIÓN LIMPIA ---\n";

// 1.1: Columna eliminada en BD activa
$stmtCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ediciones_candelaria' AND column_name = 'configuracion_json'");
$stmtCol->execute();
$existeConfigJson = ((int) $stmtCol->fetchColumn()) > 0;
afirmar(!$existeConfigJson, "Columna 'configuracion_json' ausente en 'ediciones_candelaria' de la BD activa");

// 1.2: Columnas oficiales en BD activa
$stmtCols = $pdo->query("SHOW COLUMNS FROM `ediciones_candelaria`");
$colsActivas = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
$colsEsperadas = [
    'id', 'organizacion_id', 'codigo', 'nombre', 'anio', 'estado',
    'fecha_inicio', 'fecha_fin', 'descripcion', 'es_actual',
    'flyer_oficial_url', 'creado_en', 'actualizado_en'
];
$diferencias = array_diff($colsEsperadas, $colsActivas);
afirmar(empty($diferencias) && count($colsActivas) === 13, "Tabla 'ediciones_candelaria' tiene exactamente las 13 columnas oficiales de gobierno");

// 1.3: Clean Install Test en base de datos temporal
$dbTemp = 'candelaria_test_clean_gate_' . time();
$pdo->exec("CREATE DATABASE `{$dbTemp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $driver = (string) entorno('BD_DRIVER', 'mysql');
    $host = (string) entorno('BD_HOST', '127.0.0.1');
    $puerto = (int) entorno('BD_PUERTO', 3306);
    $usuario = (string) entorno('BD_USUARIO', 'root');
    $contrasena = (string) entorno('BD_CONTRASENA', '');
    $charset = (string) entorno('BD_CHARSET', 'utf8mb4');
    $colate = (string) entorno('BD_COLATE', 'utf8mb4_unicode_ci');

    $pdoTemp = new PDO(
        "{$driver}:host={$host};port={$puerto};dbname={$dbTemp};charset={$charset}",
        $usuario,
        $contrasena,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE '{$colate}'",
        ]
    );

    $sqlEsquema = file_get_contents(__DIR__ . '/../base_datos/esquema/esquema_base.sql');
    $pdoTemp->exec($sqlEsquema);
    $tablasTemp = $pdoTemp->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $colsTempEdiciones = $pdoTemp->query("SHOW COLUMNS FROM `ediciones_candelaria`")->fetchAll(PDO::FETCH_COLUMN);

    afirmar(count($tablasTemp) === 19, "Instalación limpia de 'esquema_base.sql' crea satisfactoriamente las 19 tablas oficiales");
    afirmar(!in_array('configuracion_json', $colsTempEdiciones, true), "Instalación limpia confirma ausencia de 'configuracion_json' en 'ediciones_candelaria'");
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbTemp}`");
}

// Iniciar transacción de aislamiento determinista para el resto de la suite
$pdo->beginTransaction();

try {
    // Instanciar repositorios y servicios
    $edicionRepo = new EdicionRepositorio($pdo);
    $orgRepo = new OrganizacionRepositorio($pdo);
    $rolRepo = new RolRepositorio($pdo);
    $permRepo = new PermisoRepositorio($pdo);
    $usrRepo = new UsuarioRepositorio($pdo);
    $auditoriaRepo = new AuditoriaRepositorio($pdo);
    $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $pdo);
    $edicionServicio = new EdicionServicio($edicionRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);
    $contextoResolver = new ContextoEdicionResolver($edicionRepo);

    // ==========================================================================
    // BLOQUE 2: MATRIZ EXHAUSTIVA DE RESOLUCIÓN DE CONTEXTO (FAIL-CLOSED)
    // ==========================================================================
    echo "\n--- BLOQUE 2: MATRIZ EXHAUSTIVA DE RESOLUCIÓN Y COMPORTAMIENTO FAIL-CLOSED ---\n";

    // Sembrar dos ediciones en Org 10000 (Edición A actual=1, Edición B actual=0)
    $edAId = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 10000, codigo: 'candelaria-2026-hard', nombre: 'CANDELARIA 2026 HARDENING',
        anio: 2026, fechaInicio: '2026-02-01', fechaFin: '2026-02-15', descripcion: null,
        estado: EstadoEdicion::PREOPERACION, esActual: true
    ));

    $edBId = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 10000, codigo: 'candelaria-2027-hard', nombre: 'CANDELARIA 2027 HARDENING',
        anio: 2027, fechaInicio: '2027-02-01', fechaFin: '2027-02-15', descripcion: null,
        estado: EstadoEdicion::PREOPERACION, esActual: false
    ));

    // Crear Org 20000 temporal dentro de la transacción para pruebas Anti-IDOR
    $stmtOrg2 = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (20000, 'org_f21c_segunda', 'SEGUNDA PRODUCCION F21C', 'SEGUNDA PRODUCCION F21C S.A.C.', 2, '20999888773', 'PE', 'contacto@segundaf21c.com', 'ACTIVO')");
    $stmtOrg2->execute();

    // Sembrar edición en Org 20000 para pruebas Anti-IDOR
    $edOrg2Id = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 20000, codigo: 'candelaria-2026-org20k', nombre: 'CANDELARIA ORG 20K',
        anio: 2026, fechaInicio: '2026-02-01', fechaFin: '2026-02-15', descripcion: null,
        estado: EstadoEdicion::PREOPERACION, esActual: true
    ));

    $ctxBase = ContextoOperacion::paraHumano(
        usuarioId: 24,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/Gate',
        organizacionId: 10000
    );

    // 2.1: Header AUSENTE con organización que tiene actual -> Fallback INSTITUCIONAL
    $ctxAusente = $contextoResolver->resolverDesdeServidor($ctxBase, []);
    afirmar($ctxAusente->edicionTrabajoId === $edAId && $ctxAusente->origenEdicion === 'INSTITUCIONAL',
        "Matriz 2.1: Header ausente resuelve institucionalmente a la edición actual ({$edAId}) con origen INSTITUCIONAL");

    // 2.2: Header PRESENTE VÁLIDO -> EXPLICITA
    $ctxExplicito = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $edBId]);
    afirmar($ctxExplicito->edicionTrabajoId === $edBId && $ctxExplicito->origenEdicion === 'EXPLICITA',
        "Matriz 2.2: Header explícito válido resuelve exactamente a la edición solicitada ({$edBId}) con origen EXPLICITA");

    // 2.3: Header PRESENTE como ENTERO ESCALAR en array servidor -> Resuelve correctamente
    $ctxEscalarInt = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => $edBId]);
    afirmar($ctxEscalarInt->edicionTrabajoId === $edBId && $ctxEscalarInt->origenEdicion === 'EXPLICITA',
        "Matriz 2.3: Header provisto como entero escalar en array resuelve deterministamente");

    // 2.4: Header PRESENTE VACÍO "" -> Fail-Closed estricto sin fallback silencioso
    $falloVacio = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '']);
    } catch (\InvalidArgumentException $e) {
        $falloVacio = true;
    }
    afirmar($falloVacio, "Matriz 2.4: Header presente pero vacío ('') es rechazado fail-closed (0 fallback silencioso)");

    // 2.5: Header PRESENTE ESPACIOS EN BLANCO "   " -> Fail-Closed estricto
    $falloEspacios = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '   ']);
    } catch (\InvalidArgumentException $e) {
        $falloEspacios = true;
    }
    afirmar($falloEspacios, "Matriz 2.5: Header con espacios en blanco ('   ') es rechazado fail-closed");

    // 2.6: Header PRESENTE CERO "0" -> Fail-Closed estricto
    $falloCero = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '0']);
    } catch (\InvalidArgumentException $e) {
        $falloCero = true;
    }
    afirmar($falloCero, "Matriz 2.6: Header '0' es rechazado fail-closed");

    // 2.7: Header PRESENTE NEGATIVO "-1" -> Fail-Closed estricto
    $falloNegativo = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '-1']);
    } catch (\InvalidArgumentException $e) {
        $falloNegativo = true;
    }
    afirmar($falloNegativo, "Matriz 2.7: Header negativo '-1' es rechazado fail-closed");

    // 2.8: Header PRESENTE DECIMAL "1.5" -> Fail-Closed estricto
    $falloDecimal = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '1.5']);
    } catch (\InvalidArgumentException $e) {
        $falloDecimal = true;
    }
    afirmar($falloDecimal, "Matriz 2.8: Header decimal '1.5' es rechazado fail-closed");

    // 2.9: Header PRESENTE INYECCIÓN SQL "1 OR 1=1" -> Fail-Closed estricto
    $falloInyeccion = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => "1 OR 1=1"]);
    } catch (\InvalidArgumentException $e) {
        $falloInyeccion = true;
    }
    afirmar($falloInyeccion, "Matriz 2.9: Header con intento de SQL Injection es rechazado fail-closed");

    // 2.10: Header PRESENTE XSS "<script>alert(1)</script>" -> Fail-Closed estricto
    $falloXss = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '<script>alert(1)</script>']);
    } catch (\InvalidArgumentException $e) {
        $falloXss = true;
    }
    afirmar($falloXss, "Matriz 2.10: Header con payload XSS es rechazado fail-closed");

    // 2.11: Header PRESENTE NO ESCALAR (array) -> Fail-Closed estricto
    $falloArray = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => ['array_payload']]);
    } catch (\InvalidArgumentException $e) {
        $falloArray = true;
    }
    afirmar($falloArray, "Matriz 2.11: Header no escalar (array) es rechazado fail-closed");

    // 2.12: Header PRESENTE INEXISTENTE -> Fail-Closed estricto sin caer a actual
    $falloInexistente = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '888888']);
    } catch (\InvalidArgumentException $e) {
        $falloInexistente = true;
    }
    afirmar($falloInexistente, "Matriz 2.12: Header con ID inexistente es rechazado fail-closed sin caer a actual");

    // 2.13: Header PRESENTE de OTRO TENANT (Anti-IDOR) -> AccesoDenegadoExcepcion
    $falloAntiIdor = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $edOrg2Id]);
    } catch (AccesoDenegadoExcepcion $e) {
        $falloAntiIdor = true;
    }
    afirmar($falloAntiIdor, "Matriz 2.13: Header con ID de otro tenant lanza AccesoDenegadoExcepcion (Anti-IDOR estricto)");

    // 2.14: Header AUSENTE en organización SIN edición actual configurada
    $ctxOrgSinActual = ContextoOperacion::paraHumano(
        usuarioId: 24,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit/Gate',
        organizacionId: 30000
    );
    $resSinActual = $contextoResolver->resolverDesdeServidor($ctxOrgSinActual, []);
    afirmar($resSinActual->edicionTrabajoId === null && $resSinActual->origenEdicion === 'AUSENTE',
        "Matriz 2.14: Header ausente sin edición actual configurada en tenant resuelve null con origen AUSENTE");

    // ==========================================================================
    // BLOQUE 3: CONCURRENCIA DE PESTAÑAS Y DEMOSTRACIÓN DE AISLAMIENTO
    // ==========================================================================
    echo "\n--- BLOQUE 3: AISLAMIENTO MULTI-PESTAÑA Y CERO MUTACIÓN GLOBAL ---\n";

    // Capturar estado global antes
    $sessionGlobalPrevia = $_SESSION ?? [];
    $cookieGlobalPrevia = $_COOKIE ?? [];

    $pestaña1 = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $edAId]);
    $pestaña2 = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $edBId]);
    $pestaña3 = $contextoResolver->resolverDesdeServidor($ctxBase, []);

    afirmar($pestaña1->edicionTrabajoId === $edAId, "Pestaña 1 opera aisladamente en Edición 2026 ({$edAId})");
    afirmar($pestaña2->edicionTrabajoId === $edBId, "Pestaña 2 opera aisladamente en Edición 2027 ({$edBId})");
    afirmar($pestaña3->edicionTrabajoId === $edAId, "Pestaña 3 hereda institucionalmente Edición 2026 ({$edAId})");
    afirmar($pestaña1->edicionTrabajoId !== $pestaña2->edicionTrabajoId, "Pestaña 1 y Pestaña 2 no colisionan concurrentemente");

    // Verificar que $_SESSION y $_COOKIE no fueron mutadas
    $sessionGlobalPosterior = $_SESSION ?? [];
    $cookieGlobalPosterior = $_COOKIE ?? [];
    afirmar($sessionGlobalPrevia === $sessionGlobalPosterior, "Aislamiento multi-pestaña no contamina la variable global \$_SESSION");
    afirmar($cookieGlobalPrevia === $cookieGlobalPosterior, "Aislamiento multi-pestaña no contamina la variable global \$_COOKIE");

    // ==========================================================================
    // BLOQUE 4: GOBERNANZA DE MÁQUINA DE ESTADOS E INMUTABILIDAD TERMINAL
    // ==========================================================================
    echo "\n--- BLOQUE 4: MÁQUINA DE ESTADOS, RETROCESOS GOBERNADOS Y ESTADO TERMINAL ---\n";

    // 4.1: Avance secuencial ordinario: PREOPERACION -> OPERACION
    $edicionGov = $edicionServicio->crear(10000, [
        'codigo'       => 'candelaria-2028-gov',
        'nombre'       => 'CANDELARIA 2028 GOBERNANZA',
        'anio'         => 2028,
        'fecha_inicio' => '2028-02-01',
        'fecha_fin'    => '2028-02-15',
        'descripcion'  => 'Ciclo de vida gobernado',
        'es_actual'    => false,
    ], $ctxBase);

    afirmar($edicionGov->estado === EstadoEdicion::PREOPERACION, "4.1: Edición recién creada nace estrictamente en PREOPERACION");

    // Prohibir salto ilegal PREOPERACION -> POSTPRODUCCION_ENTREGA
    $falloSalto1 = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxBase);
    } catch (\InvalidArgumentException $e) {
        $falloSalto1 = true;
    }
    afirmar($falloSalto1, "4.2: Salto hacia adelante prohibido (PREOPERACION -> POSTPRODUCCION_ENTREGA) es rechazado");

    // Prohibir salto ilegal PREOPERACION -> CERRADA
    $falloSalto2 = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'CERRADA', $ctxBase);
    } catch (\InvalidArgumentException $e) {
        $falloSalto2 = true;
    }
    afirmar($falloSalto2, "4.3: Salto directo hacia estado terminal (PREOPERACION -> CERRADA) es rechazado");

    // Avance legítimo a OPERACION
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'OPERACION', $ctxBase);
    afirmar($edicionGov->estado === EstadoEdicion::OPERACION, "4.4: Avance secuencial PREOPERACION -> OPERACION exitoso");

    // Prohibir salto ilegal OPERACION -> CERRADA
    $falloSalto3 = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'CERRADA', $ctxBase);
    } catch (\InvalidArgumentException $e) {
        $falloSalto3 = true;
    }
    afirmar($falloSalto3, "4.5: Salto ilegal OPERACION -> CERRADA es rechazado");

    // Retroceso excepcional OPERACION -> PREOPERACION sin motivo es rechazado
    $falloRetrocesoSinMotivo = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'PREOPERACION', $ctxBase, '   ');
    } catch (\InvalidArgumentException $e) {
        $falloRetrocesoSinMotivo = true;
    }
    afirmar($falloRetrocesoSinMotivo, "4.6: Retroceso OPERACION -> PREOPERACION sin motivo justificado es rechazado");

    // Retroceso excepcional con motivo justificado
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'PREOPERACION', $ctxBase, 'Ajuste de cronograma y permisos municipales');
    afirmar($edicionGov->estado === EstadoEdicion::PREOPERACION, "4.7: Retroceso OPERACION -> PREOPERACION con motivo formal es exitoso");

    // Re-avanzar a OPERACION y luego a POSTPRODUCCION_ENTREGA
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'OPERACION', $ctxBase);
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxBase);
    afirmar($edicionGov->estado === EstadoEdicion::POSTPRODUCCION_ENTREGA, "4.8: Avance secuencial OPERACION -> POSTPRODUCCION_ENTREGA exitoso");

    // Prohibir retroceso doble no autorizado (POSTPRODUCCION_ENTREGA -> PREOPERACION)
    $falloRetrocesoDoble = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'PREOPERACION', $ctxBase, 'Intento de salto atrás doble');
    } catch (\InvalidArgumentException $e) {
        $falloRetrocesoDoble = true;
    }
    afirmar($falloRetrocesoDoble, "4.9: Retroceso no autorizado (POSTPRODUCCION_ENTREGA -> PREOPERACION) es prohibido");

    // Retroceso excepcional POSTPRODUCCION_ENTREGA -> OPERACION con motivo
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'OPERACION', $ctxBase, 'Reapertura para cobertura de concurso regional de trajes de luces');
    afirmar($edicionGov->estado === EstadoEdicion::OPERACION, "4.10: Retroceso POSTPRODUCCION_ENTREGA -> OPERACION con motivo formal exitoso");

    // Avanzar nuevamente a POSTPRODUCCION_ENTREGA y luego a CERRADA
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxBase);
    $edicionGov = $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'CERRADA', $ctxBase);
    afirmar($edicionGov->estado === EstadoEdicion::CERRADA, "4.11: Transición formal a estado terminal CERRADA exitosa");

    // Prohibición absoluta de reapertura o transición desde CERRADA
    $falloReapertura = false;
    try {
        $edicionServicio->cambiarEstado($edicionGov->id, 10000, 'OPERACION', $ctxBase, 'Intento ilegal de reapertura');
    } catch (\InvalidArgumentException $e) {
        $falloReapertura = true;
    }
    afirmar($falloReapertura, "4.12: Inmutabilidad de CERRADA: intento de transición/reapertura es categóricamente rechazado");

    // Prohibición de modificación de datos en edición CERRADA
    $falloEditarCerrada = false;
    try {
        $edicionServicio->actualizar($edicionGov->id, 10000, ['nombre' => 'CANDELARIA 2028 MUTADA ILEGALMENTE'], $ctxBase);
    } catch (\InvalidArgumentException $e) {
        $falloEditarCerrada = true;
    }
    afirmar($falloEditarCerrada, "4.13: Inmutabilidad de CERRADA: modificaciones ordinarias de datos son bloqueadas");

    // ==========================================================================
    // BLOQUE 5: CONCURRENCIA, EXCLUSIVIDAD DE es_actual Y LOCK FOR UPDATE
    // ==========================================================================
    echo "\n--- BLOQUE 5: EXCLUSIVIDAD DE es_actual, SERIALIZACIÓN FOR UPDATE Y ATOMICIDAD ---\n";

    // Verificar exclusividad de es_actual: designar edBId como actual
    $edBActualizada = $edicionServicio->establecerActual($edBId, 10000, $ctxBase);
    afirmar($edBActualizada->esActual === true, "5.1: Edición B (ID: {$edBId}) designada exitosamente como es_actual = 1");

    // Verificar que edAId fue desmarcada automáticamente
    $edAConsulta = $edicionRepo->buscarPorId($edAId);
    afirmar($edAConsulta->esActual === false, "5.2: Edición A (ID: {$edAId}) fue desmarcada automáticamente (es_actual = 0)");

    // Verificar que existe exactamente UNA sola edición actual en la organización
    $stmtCountActual = $pdo->prepare("SELECT COUNT(*) FROM `ediciones_candelaria` WHERE `organizacion_id` = 10000 AND `es_actual` = 1");
    $stmtCountActual->execute();
    $conteoActual = (int) $stmtCountActual->fetchColumn();
    afirmar($conteoActual === 1, "5.3: Exclusividad garantizada: existe exactamente 1 edición con es_actual = 1 en la organización");

    // Intentar designar como actual un ID inexistente en el repositorio directamente para probar atomicidad
    $falloActualInexistente = false;
    try {
        $edicionRepo->establecerComoActual(999999, 10000);
    } catch (\InvalidArgumentException $e) {
        $falloActualInexistente = true;
    }
    afirmar($falloActualInexistente, "5.4: Repositorio rechaza designar ID inexistente y hace rollback atómico");

    // Comprobar que tras el intento fallido la edición previa sigue siendo la actual
    $edBTrasFallo = $edicionRepo->buscarPorId($edBId);
    afirmar($edBTrasFallo->esActual === true, "5.5: Rollback atómico preserva íntegra la edición actual previa ({$edBId})");

    // ==========================================================================
    // BLOQUE 6: ANTI-IDOR, CONCURRENCIA OPTIMISTA Y PROHIBICIÓN DE BORRADO FÍSICO
    // ==========================================================================
    echo "\n--- BLOQUE 6: ANTI-IDOR, CONCURRENCIA OPTIMISTA (409) Y PROHIBICIÓN DE BORRADO ---\n";

    // 6.1: Operador de Org 10000 no puede acceder a edición de Org 20000
    $edicionAjena = $edicionServicio->obtener($edOrg2Id, 10000, $ctxBase);
    afirmar($edicionAjena === null, "6.1: Anti-IDOR: consulta de edición ajena retorna null (sin fuga de información)");

    // 6.2: Intento de mutar edición de otro tenant
    $falloMutarAjena = false;
    try {
        $edicionServicio->actualizar($edOrg2Id, 10000, ['nombre' => 'HACK TENANT'], $ctxBase);
    } catch (AccesoDenegadoExcepcion $e) {
        $falloMutarAjena = true;
    }
    afirmar($falloMutarAjena, "6.2: Anti-IDOR: intento de modificar edición de otra organización lanza AccesoDenegadoExcepcion");

    // 6.3: Concurrencia optimista: timestamp desfasado
    $edicionA = $edicionRepo->buscarPorId($edAId);
    $falloConcurrencia = false;
    try {
        $edicionServicio->actualizar(
            $edAId,
            10000,
            ['nombre' => 'CANDELARIA 2026 ACTUALIZADA'],
            $ctxBase,
            '2020-01-01 00:00:00' // timestamp deliberadamente desfasado
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $falloConcurrencia = true;
    }
    afirmar($falloConcurrencia, "6.3: Concurrencia optimista: detecta versión desfasada y lanza ConflictoConcurrenciaExcepcion (409)");

    // 6.4: Prohibición de eliminación física
    $metodosRepo = get_class_methods($edicionRepo);
    $tieneMetodoEliminar = in_array('eliminar', $metodosRepo, true) || in_array('borrar', $metodosRepo, true) || in_array('delete', $metodosRepo, true);
    afirmar(!$tieneMetodoEliminar, "6.4: Conservación histórica: EdicionRepositorio no expone ningún método de eliminación física");

    // ==========================================================================
    // BLOQUE 7: AUDITORÍA INMUTABLE DE TODAS LAS ACCIONES DE EDICIÓN
    // ==========================================================================
    echo "\n--- BLOQUE 7: PISTA DE AUDITORÍA INMUTABLE EN auditoria_operaciones ---\n";

    $stmtAuditoria = $pdo->prepare("SELECT accion, entidad_tipo, datos_nuevos_json FROM `auditoria_operaciones` WHERE `modulo` = 'ediciones' ORDER BY id DESC LIMIT 10");
    $stmtAuditoria->execute();
    $registrosAuditoria = $stmtAuditoria->fetchAll(PDO::FETCH_ASSOC);

    $accionesRegistradas = array_column($registrosAuditoria, 'accion');

    afirmar(in_array('CREAR_EDICION', $accionesRegistradas, true), "7.1: Auditoría registra evento 'CREAR_EDICION'");
    afirmar(in_array('AVANZAR_ESTADO_EDICION', $accionesRegistradas, true), "7.2: Auditoría registra evento 'AVANZAR_ESTADO_EDICION'");
    afirmar(in_array('RETROCEDER_ESTADO_EDICION', $accionesRegistradas, true), "7.3: Auditoría registra evento 'RETROCEDER_ESTADO_EDICION' con motivo explícito");
    afirmar(in_array('SELECCIONAR_EDICION_ACTUAL', $accionesRegistradas, true), "7.4: Auditoría registra evento 'SELECCIONAR_EDICION_ACTUAL'");

    // Verificar que el motivo de retroceso está en los metadatos auditados
    $eventoRetroceso = null;
    foreach ($registrosAuditoria as $reg) {
        if ($reg['accion'] === 'RETROCEDER_ESTADO_EDICION') {
            $eventoRetroceso = json_decode((string) $reg['datos_nuevos_json'], true);
            break;
        }
    }
    afirmar(!empty($eventoRetroceso['motivo']), "7.5: Auditoría de retroceso contiene motivo explícito preservado inmutable");

} finally {
    // Revertir transacción determinista para dejar la base de datos limpia de datos de prueba
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[INFO] Transacción de pruebas F2.1C revertida con ROLLBACK determinista.\n";
    }
}

// ==============================================================================
// BLOQUE 8: PRESERVACIÓN ABSOLUTA DE CREDENCIALES DE ORLANDO (ID 24)
// ==============================================================================
echo "\n--- BLOQUE 8: CERTIFICACIÓN DE PRESERVACIÓN DE CREDENCIALES (ORLANDO ID 24) ---\n";

$stmtOrlando = $pdo->prepare("SELECT id, nombre_usuario, correo_electronico, estado, intentos_fallidos, bloqueado_hasta, contrasena_hash FROM `usuarios` WHERE `id` = 24");
$stmtOrlando->execute();
$orlando = $stmtOrlando->fetch(PDO::FETCH_ASSOC);

afirmar($orlando !== false, "Usuario Orlando (ID 24) existe en la base de datos");
afirmar($orlando['estado'] === 'ACTIVO', "Estado de Orlando es ACTIVO");
afirmar((int) $orlando['intentos_fallidos'] === 0, "Intentos fallidos de login de Orlando es exactamente 0");
afirmar($orlando['bloqueado_hasta'] === null, "Bloqueo temporal de cuenta de Orlando es NULL");

// REGLA DE SEGURIDAD: Nunca imprimir el hash, solo el fingerprint truncado a 16 caracteres
$fingerprintHash = substr(hash('sha256', (string) $orlando['contrasena_hash']), 0, 16);
afirmar($fingerprintHash === '80e6af84e02e89e3', "Huella criptográfica SHA-256 (truncada 16) coincide exactamente con '80e6af84e02e89e3'");

echo "\n==============================================================================\n";
echo "RESUMEN DE SUITE F2.1C: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}

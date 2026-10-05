<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Ediciones\EdicionServicio;
use Aplicacion\Ediciones\EstadoEdicion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE DOMINIO EDICIÓN CANDELARIA F2.1A\n";
echo "ENTIDAD, PERSISTENCIA, CICLO DE VIDA, RBAC, ANTI-IDOR, CONCURRENCIA Y AUDITORÍA\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$descripcion}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$descripcion}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

// 0. Instanciar Repositorios y Servicios
$orgRepo       = new OrganizacionRepositorio($pdo);
$usuarioRepo   = new UsuarioRepositorio($pdo);
$personaRepo   = new PersonaRepositorio($pdo);
$rolRepo       = new RolRepositorio($pdo);
$permisoRepo   = new PermisoRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$actorSysRepo  = new ActorSistemaRepositorio($pdo);
$configRepo    = new ConfiguracionRepositorio($pdo);
$edicionRepo   = new EdicionRepositorio($pdo);

$authzServicio = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$edicionServicio = new EdicionServicio($edicionRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);

// Huella digital previa de Orlando para certificar preservación
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

$pdo->beginTransaction();

try {
    // --------------------------------------------------------------------------
    // Preparar Fixtures Efímeros dentro de la Transacción
    // --------------------------------------------------------------------------
    $rolSuper = $rolRepo->buscarPorCodigo('superadmin_plataforma');
    $rolAdmin = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolOper  = $rolRepo->buscarPorCodigo('operador_produccion');

    // 1. Segunda organización efímera para pruebas de aislamiento Anti-IDOR
    $stmtOrg2 = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (20000, 'org_test_segunda', 'SEGUNDA PRODUCCION S.A.C.', 'SEGUNDA PRODUCCION SOCIEDAD ANONIMA CERRADA', 2, '20999888771', 'PE', 'contacto@segunda.com', 'ACTIVO')");
    $stmtOrg2->execute();
    $org2Id = 20000;

    // 1b. Superadmin efímero para operaciones globales
    $perSuper = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '70000020', nombres: 'Super', apellidos: 'Tester',
        correoElectronico: 'super_f21a@test.com', estado: 'ACTIVO'
    );
    $perSuperId = $personaRepo->crear($perSuper);
    $usrSuper = new Usuario(
        id: null, organizacionId: 10000, personaId: $perSuperId,
        nombreUsuario: 'super_ediciones_f21a', nombreCompleto: 'Super Ediciones',
        correoElectronico: 'super_f21a@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: true, estado: 'ACTIVO'
    );
    $usrSuperId = $usuarioRepo->crear($usrSuper);
    $rolRepo->asignarRolAUsuario($usrSuperId, $rolSuper->id);

    // 2. Administrador de Org 10000 efímero
    $perAdmin1 = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71110021', nombres: 'Admin', apellidos: 'Ediciones',
        correoElectronico: 'admin_f21a@test.com', estado: 'ACTIVO'
    );
    $perAdmin1Id = $personaRepo->crear($perAdmin1);
    $usrAdmin1 = new Usuario(
        id: null, organizacionId: 10000, personaId: $perAdmin1Id,
        nombreUsuario: 'admin_ediciones_f21a', nombreCompleto: 'Admin Ediciones',
        correoElectronico: 'admin_f21a@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrAdmin1Id = $usuarioRepo->crear($usrAdmin1);
    $rolRepo->asignarRolAUsuario($usrAdmin1Id, $rolAdmin->id);

    // 3. Administrador de Org 2 efímero
    $perAdmin2 = new Persona(
        id: null, organizacionId: $org2Id, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '72220022', nombres: 'Admin Dos', apellidos: 'Tenant Dos',
        correoElectronico: 'admin2_f21a@test.com', estado: 'ACTIVO'
    );
    $perAdmin2Id = $personaRepo->crear($perAdmin2);
    $usrAdmin2 = new Usuario(
        id: null, organizacionId: $org2Id, personaId: $perAdmin2Id,
        nombreUsuario: 'admin2_ediciones_f21a', nombreCompleto: 'Admin Tenant Dos',
        correoElectronico: 'admin2_f21a@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrAdmin2Id = $usuarioRepo->crear($usrAdmin2);
    $rolRepo->asignarRolAUsuario($usrAdmin2Id, $rolAdmin->id);

    // 4. Operador sin permisos de edición
    $perOper = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '73330023', nombres: 'Operador', apellidos: 'Produccion',
        correoElectronico: 'oper_f21a@test.com', estado: 'ACTIVO'
    );
    $perOperId = $personaRepo->crear($perOper);
    $usrOper = new Usuario(
        id: null, organizacionId: 10000, personaId: $perOperId,
        nombreUsuario: 'oper_ediciones_f21a', nombreCompleto: 'Operador Produccion',
        correoElectronico: 'oper_f21a@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrOperId = $usuarioRepo->crear($usrOper);
    $rolRepo->asignarRolAUsuario($usrOperId, $rolOper->id);

    // Contextos de operación
    $ctxSuper  = ContextoOperacion::paraHumano($usrSuperId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000);
    $ctxAdmin1 = ContextoOperacion::paraHumano($usrAdmin1Id, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000);
    $ctxAdmin2 = ContextoOperacion::paraHumano($usrAdmin2Id, 1, 'WEB', '127.0.0.1', 'CLI-Tester', $org2Id);
    $ctxOper   = ContextoOperacion::paraHumano($usrOperId, 1, 'WEB', '127.0.0.1', 'CLI-Tester', 10000);

    // ==============================================================================
    // 01. CREACIÓN VÁLIDA CON NORMALIZACIÓN Y SLUG
    // ==============================================================================
    $edicion2027 = $edicionServicio->crear(10000, [
        'codigo'       => 'candelaria-2027',
        'nombre'       => 'candelaria 2027: fiesta patronal',
        'anio'         => 2027,
        'fecha_inicio' => '2027-01-20',
        'fecha_fin'    => '2027-02-28',
        'descripcion'  => 'Edición oficial 2027',
        'es_actual'    => true,
    ], $ctxAdmin1);

    afirmar(
        $edicion2027->id !== null
        && $edicion2027->nombre === 'CANDELARIA 2027: FIESTA PATRONAL'
        && $edicion2027->codigo === 'candelaria-2027'
        && $edicion2027->anio === 2027
        && $edicion2027->estado === EstadoEdicion::PREOPERACION
        && $edicion2027->esActual === true,
        '01. Creación válida de edición con normalización de nombre a MAYÚSCULAS y código slug'
    );

    // ==============================================================================
    // 02. ORGANIZACIÓN OBLIGATORIA (RECHAZA <= 0)
    // ==============================================================================
    $rechazaOrgCero = false;
    try {
        new Edicion(
            id: null, organizacionId: 0, codigo: 'candelaria-2028',
            nombre: 'CANDELARIA 2028', anio: 2028,
            fechaInicio: '2028-01-15', fechaFin: '2028-02-25'
        );
    } catch (\InvalidArgumentException $e) {
        $rechazaOrgCero = str_contains($e->getMessage(), 'mayor a 0');
    }

    afirmar($rechazaOrgCero === true, '02. Invariante de Entidad: Organización obligatoria y rechazo de ID <= 0');

    // ==============================================================================
    // 03. TENANT INEXISTENTE EN BD ES RECHAZADO
    // ==============================================================================
    $rechazaTenantInexistente = false;
    try {
        $edicionServicio->crear(99999, [
            'codigo' => 'candelaria-2099', 'nombre' => 'CANDELARIA 2099', 'anio' => 2099,
            'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-02-01'
        ], $ctxSuper);
    } catch (\InvalidArgumentException $e) {
        $rechazaTenantInexistente = str_contains($e->getMessage(), 'no existe');
    }

    afirmar($rechazaTenantInexistente === true, '03. Integridad Relacional: Rechazo de creación para tenant inexistente');

    // ==============================================================================
    // 04. AÑO VÁLIDO EN RANGO [2000, 2100]
    // ==============================================================================
    $edicion2028 = $edicionServicio->crear(10000, [
        'codigo'       => 'candelaria-2028',
        'nombre'       => 'CANDELARIA 2028',
        'anio'         => 2028,
        'fecha_inicio' => '2028-01-15',
        'fecha_fin'    => '2028-02-20',
        'es_actual'    => false,
    ], $ctxAdmin1);

    afirmar($edicion2028->anio === 2028, '04. Rango de Años: Aceptación legítima de año en rango [2000, 2100]');

    // ==============================================================================
    // 05. AÑO INVÁLIDO ES RECHAZADO
    // ==============================================================================
    $rechazaAnioInvalido = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo' => 'candelaria-1990', 'nombre' => 'CANDELARIA 1990', 'anio' => 1990,
            'fecha_inicio' => '1990-01-01', 'fecha_fin' => '1990-02-01'
        ], $ctxAdmin1);
    } catch (\InvalidArgumentException $e) {
        $rechazaAnioInvalido = str_contains($e->getMessage(), '2000 y 2100');
    }

    afirmar($rechazaAnioInvalido === true, '05. Rango de Años: Rechazo riguroso de años fuera de rango (<2000 o >2100)');

    // ==============================================================================
    // 06. UNICIDAD DE AÑO EN LA MISMA ORGANIZACIÓN
    // ==============================================================================
    $rechazaAnioDuplicado = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo' => 'candelaria-2027-bis', 'nombre' => 'CANDELARIA 2027 BIS', 'anio' => 2027,
            'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-02-01'
        ], $ctxAdmin1);
    } catch (\InvalidArgumentException $e) {
        $rechazaAnioDuplicado = str_contains($e->getMessage(), 'ya cuenta con una edición registrada para el año');
    }

    afirmar($rechazaAnioDuplicado === true, '06. Unicidad de Negocio: Rechazo de año duplicado en la misma organización');

    // ==============================================================================
    // 07. UNICIDAD DE CÓDIGO SLUG EN LA MISMA ORGANIZACIÓN
    // ==============================================================================
    $rechazaCodigoDuplicado = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo' => 'candelaria-2027', 'nombre' => 'OTRO NOMBRE', 'anio' => 2029,
            'fecha_inicio' => '2029-01-01', 'fecha_fin' => '2029-02-01'
        ], $ctxAdmin1);
    } catch (\InvalidArgumentException $e) {
        $rechazaCodigoDuplicado = str_contains($e->getMessage(), 'ya está asignado');
    }

    afirmar($rechazaCodigoDuplicado === true, '07. Unicidad de Código: Rechazo de código slug duplicado en la misma organización');

    // ==============================================================================
    // 08. MISMO AÑO EN DISTINTAS ORGANIZACIONES (MULTI-TENANT VÁLIDO)
    // ==============================================================================
    $edicionOrg2_2027 = $edicionServicio->crear($org2Id, [
        'codigo'       => 'candelaria-2027',
        'nombre'       => 'CANDELARIA 2027 TENANT DOS',
        'anio'         => 2027,
        'fecha_inicio' => '2027-01-10',
        'fecha_fin'    => '2027-02-15',
    ], $ctxAdmin2);

    afirmar(
        $edicionOrg2_2027->organizacionId === $org2Id && $edicionOrg2_2027->anio === 2027,
        '08. Multi-Tenant: Mismo año y código admitidos simultáneamente en distintas organizaciones'
    );

    // ==============================================================================
    // 09. VALIDACIÓN DE FECHAS: fecha_inicio <= fecha_fin ACEPTADO
    // ==============================================================================
    afirmar(
        $edicion2027->fechaInicio <= $edicion2027->fechaFin,
        '09. Coherencia Temporal: Intervalo de fechas válido (fecha_inicio <= fecha_fin)'
    );

    // ==============================================================================
    // 10. RECHAZO DE FECHAS INVERTIDAS: fecha_inicio > fecha_fin
    // ==============================================================================
    $rechazaFechasInvertidas = false;
    try {
        new Edicion(
            id: null, organizacionId: 10000, codigo: 'candelaria-2030',
            nombre: 'CANDELARIA 2030', anio: 2030,
            fechaInicio: '2030-03-01', fechaFin: '2030-02-01'
        );
    } catch (\InvalidArgumentException $e) {
        $rechazaFechasInvertidas = str_contains($e->getMessage(), 'Inconsistencia temporal');
    }

    afirmar($rechazaFechasInvertidas === true, '10. Coherencia Temporal: Rechazo estricto si fecha_inicio es posterior a fecha_fin');

    // ==============================================================================
    // 11. ESTADO INICIAL PREOPERACION
    // ==============================================================================
    afirmar(
        $edicion2027->estado === EstadoEdicion::PREOPERACION
        && $edicion2027->estado->etiqueta() === 'PREOPERACIÓN',
        '11. Ciclo de Vida: Toda nueva edición nace en estado PREOPERACION con etiqueta formal'
    );

    // ==============================================================================
    // 12. ESTADO ARBITRARIO RECHAZADO POR VALIDACIÓN Y CHECK
    // ==============================================================================
    $rechazaEstadoInvalido = false;
    try {
        new Edicion(
            id: null, organizacionId: 10000, codigo: 'candelaria-2031',
            nombre: 'CANDELARIA 2031', anio: 2031,
            fechaInicio: '2031-01-01', fechaFin: '2031-02-01',
            estado: 'ESTADO_INVENTADO'
        );
    } catch (\InvalidArgumentException $e) {
        $rechazaEstadoInvalido = str_contains($e->getMessage(), 'no es un estado válido');
    }

    afirmar($rechazaEstadoInvalido === true, '12. Máquina de Estados: Rechazo de estados arbitrarios o no gobernados');

    // ==============================================================================
    // 13. TRANSICIÓN DE AVANCE: PREOPERACION -> OPERACION
    // ==============================================================================
    $edicion2027EnOperacion = $edicionServicio->cambiarEstado(
        $edicion2027->id, 10000, 'OPERACION', $ctxAdmin1
    );

    afirmar(
        $edicion2027EnOperacion->estado === EstadoEdicion::OPERACION,
        '13. Máquina de Estados: Avance legítimo de PREOPERACION a OPERACION'
    );

    // ==============================================================================
    // 14. AVANCE SECUENCIAL COMPLETO: OPERACION -> POSTPRODUCCION_ENTREGA -> CERRADA
    // ==============================================================================
    $edicion2027EnPost = $edicionServicio->cambiarEstado(
        $edicion2027->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxAdmin1
    );
    $edicion2027Cerrada = $edicionServicio->cambiarEstado(
        $edicion2027->id, 10000, 'CERRADA', $ctxAdmin1
    );

    afirmar(
        $edicion2027EnPost->estado === EstadoEdicion::POSTPRODUCCION_ENTREGA
        && $edicion2027Cerrada->estado === EstadoEdicion::CERRADA
        && $edicion2027Cerrada->estado->esTerminal() === true,
        '14. Máquina de Estados: Avance secuencial a POSTPRODUCCION_ENTREGA y estado terminal CERRADA'
    );

    // ==============================================================================
    // 15. TRANSICIÓN INVÁLIDA CON SALTO (PREOPERACION -> CERRADA BLOQUEADA)
    // ==============================================================================
    $rechazaSaltoFase = false;
    try {
        $edicionServicio->cambiarEstado($edicion2028->id, 10000, 'CERRADA', $ctxAdmin1);
    } catch (\InvalidArgumentException $e) {
        $rechazaSaltoFase = str_contains($e->getMessage(), 'Transición no permitida');
    }

    afirmar($rechazaSaltoFase === true, '15. Máquina de Estados: Rechazo de saltos de fase ilegales (PREOPERACION -> CERRADA)');

    // ==============================================================================
    // 16. RETROCESO CONTROLADO CON MOTIVO REGISTRADO
    // ==============================================================================
    $edicion2028EnOp = $edicionServicio->cambiarEstado($edicion2028->id, 10000, 'OPERACION', $ctxAdmin1);
    $edicion2028Retrocedida = $edicionServicio->cambiarEstado(
        $edicion2028->id, 10000, 'PREOPERACION', $ctxAdmin1, motivo: 'Ajuste de cronograma oficial'
    );

    afirmar(
        $edicion2028Retrocedida->estado === EstadoEdicion::PREOPERACION,
        '16. Máquina de Estados: Retroceso controlado entre fases operativas con motivo formal'
    );

    // ==============================================================================
    // 17. RETROCESO SIN MOTIVO ES RECHAZADO
    // ==============================================================================
    $edicion2028EnOp2 = $edicionServicio->cambiarEstado($edicion2028->id, 10000, 'OPERACION', $ctxAdmin1);
    $rechazaRetrocesoSinMotivo = false;
    try {
        $edicionServicio->cambiarEstado($edicion2028->id, 10000, 'PREOPERACION', $ctxAdmin1, motivo: '');
    } catch (\InvalidArgumentException $e) {
        $rechazaRetrocesoSinMotivo = str_contains($e->getMessage(), 'requiere obligatoriamente registrar un motivo');
    }

    afirmar($rechazaRetrocesoSinMotivo === true, '17. Máquina de Estados: Rechazo de retroceso si no se provee motivo justificativo');

    // ==============================================================================
    // 18. EDICIÓN CERRADA RECHAZA MODIFICACIONES ORDINARIAS
    // ==============================================================================
    $rechazaEdicionCerrada = false;
    try {
        $edicionServicio->actualizar($edicion2027->id, 10000, [
            'nombre' => 'MODIFICACIÓN NO PERMITIDA'
        ], $ctxAdmin1);
    } catch (\InvalidArgumentException $e) {
        $rechazaEdicionCerrada = str_contains($e->getMessage(), 'se encuentra en estado CERRADA');
    }

    afirmar($rechazaEdicionCerrada === true, '18. Invariante de Cierre: Edición CERRADA bloquea modificaciones operativas ordinarias');

    // ==============================================================================
    // 19. REAPERTURA EXCEPCIONAL CON AUDITORÍA REFORZADA
    // ==============================================================================
    $edicion2027Reabierta = $edicionServicio->cambiarEstado(
        $edicion2027->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxAdmin1, motivo: 'Reapertura para entrega de remanente fotográfico'
    );

    afirmar(
        $edicion2027Reabierta->estado === EstadoEdicion::POSTPRODUCCION_ENTREGA,
        '19. Máquina de Estados: Reapertura excepcional de edición CERRADA hacia POSTPRODUCCION_ENTREGA'
    );

    // ==============================================================================
    // 20. EDICIÓN ACTUAL: EXCLUSIVIDAD TRANSACCIONAL DENTRO DEL TENANT
    // ==============================================================================
    // Inicialmente edicion2027 era actual. Ahora marcamos edicion2028 como actual.
    $edicion2028Actual = $edicionServicio->establecerActual($edicion2028->id, 10000, $ctxAdmin1);
    $edicion2027NoActual = $edicionServicio->obtener($edicion2027->id, 10000, $ctxAdmin1);

    afirmar(
        $edicion2028Actual->esActual === true
        && $edicion2027NoActual->esActual === false,
        '20. Edición Actual: Exclusividad transaccional garantizada (solo una edición actual por tenant)'
    );

    // ==============================================================================
    // 21. ANTI-IDOR: OPERADOR DE ORG 10000 NO PUEDE ACCEDER A EDICIONES DE ORG 2
    // ==============================================================================
    $bloqueoIdor = false;
    try {
        $edicionServicio->actualizar($edicionOrg2_2027->id, $org2Id, [
            'nombre' => 'ATAQUE IDOR'
        ], $ctxAdmin1);
    } catch (AccesoDenegadoExcepcion $e) {
        $bloqueoIdor = true;
    }

    $consultaIdorNull = $edicionServicio->obtener($edicionOrg2_2027->id, 10000, $ctxAdmin1);

    afirmar(
        $bloqueoIdor === true && $consultaIdorNull === null,
        '21. Aislamiento Anti-IDOR: Operador no puede consultar ni mutar ediciones pertenecientes a otra organización'
    );

    // ==============================================================================
    // 22. CONCURRENCIA OPTIMISTA: DETECCIÓN DE CONFLICTO POR ACTUALIZADO_EN
    // ==============================================================================
    $detectaConflicto = false;
    try {
        $edicionServicio->actualizar(
            $edicion2028->id, 10000,
            ['nombre' => 'EDICIÓN CON CONFLICTO'],
            $ctxAdmin1,
            actualizadoEnEsperado: '2000-01-01 00:00:00'
        );
    } catch (ConflictoConcurrenciaExcepcion) {
        $detectaConflicto = true;
    }

    afirmar($detectaConflicto === true, '22. Concurrencia Optimista: ConflictoConcurrenciaExcepcion ante versión desfasada');

    // ==============================================================================
    // 23. POLÍTICA DE ELIMINACIÓN: CERO MÉTODOS DE DELETE FÍSICO EN REPOSITORIO
    // ==============================================================================
    $refRepo = new ReflectionClass(EdicionRepositorio::class);
    $metodosRepo = array_map(fn($m) => strtolower($m->getName()), $refRepo->getMethods());

    $tieneDelete = false;
    foreach ($metodosRepo as $m) {
        if (str_contains($m, 'delete') || str_contains($m, 'eliminar') || str_contains($m, 'truncate') || str_contains($m, 'destroy')) {
            $tieneDelete = true;
            break;
        }
    }

    afirmar($tieneDelete === false, '23. Conservación Histórica: EdicionRepositorio prohíbe eliminación física por diseño');

    // ==============================================================================
    // 24. PISTA DE AUDITORÍA INMUTABLE REGISTRA MUTACIONES Y TRANSICIONES
    // ==============================================================================
    $stmtAudit = $pdo->prepare("SELECT COUNT(*) FROM `auditoria_operaciones` WHERE `modulo` = 'ediciones' AND `entidad_tipo` = 'EDICION'");
    $stmtAudit->execute();
    $totalAuditEdiciones = (int) $stmtAudit->fetchColumn();

    afirmar(
        $totalAuditEdiciones >= 5,
        '24. Auditoría Inmutable: Eventos de creación, actualización, transición y selección actual registrados'
    );

    // ==============================================================================
    // 25. CONTROL DE ACCESO RBAC: OPERADOR SIN PERMISO ES BLOQUEADO
    // ==============================================================================
    $bloqueoRbacCrear = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo' => 'candelaria-2035', 'nombre' => 'CANDELARIA 2035', 'anio' => 2035,
            'fecha_inicio' => '2035-01-01', 'fecha_fin' => '2035-02-01'
        ], $ctxOper);
    } catch (AccesoDenegadoExcepcion $e) {
        $bloqueoRbacCrear = str_contains($e->getMessage(), 'ediciones.crear');
    }

    afirmar($bloqueoRbacCrear === true, '25. RBAC: Operador de producción sin permiso ediciones.crear es denegado');

} finally {
    // ==============================================================================
    // ROLLBACK OBLIGATORIO: BASE DE DATOS INTACTA
    // ==============================================================================
    $pdo->rollBack();
}

// ==============================================================================
// 26. CERTIFICACIÓN POST-ROLLBACK: CUENTA ORLANDO 100% INALTERADA
// ==============================================================================
$stmtOrlandoPost = $pdo->prepare("SELECT id, contrasena_hash, estado, intentos_fallidos, bloqueado_hasta, organizacion_id FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

afirmar(
    $fingerprintOrlandoPost === '80e6af84e02e89e3'
    && $fingerprintOrlandoPost === $fingerprintOrlandoPre
    && (int) ($orlandoPost['id'] ?? 0) === 24
    && ($orlandoPost['estado'] ?? '') === 'ACTIVO'
    && (int) ($orlandoPost['intentos_fallidos'] ?? -1) === 0
    && $orlandoPost['bloqueado_hasta'] === null
    && (int) ($orlandoPost['organizacion_id'] ?? 0) === 10000,
    '26. Preservación Post-Rollback: Cuenta orlando (ID 24) 100% inalterada con huella criptográfica idéntica'
);

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F2.1A: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}

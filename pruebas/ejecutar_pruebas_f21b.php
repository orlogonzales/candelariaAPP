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
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.1B: GESTIÓN DE EDICIONES, SELECTOR Y CONTEXTO\n";
echo "RESOLUCIÓN MULTI-PESTAÑA, RBAC, FAIL-CLOSED, CONCURRENCIA Y AUDITORÍA\n";
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

// 0. Huella digital previa de Orlando para certificar preservación estricta
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// ==============================================================================
// BLOQUE 1: VERIFICACIÓN FÍSICA DE BASE DE DATOS (ELIMINACIÓN configuracion_json)
// ==============================================================================
echo "\n--- BLOQUE 1: ESQUEMA LIMPIO Y ELIMINACIÓN DE configuracion_json ---\n";

$stmtCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ediciones_candelaria' AND column_name = 'configuracion_json'");
$stmtCol->execute();
$existeConfigJson = (int) $stmtCol->fetchColumn() > 0;
afirmar(!$existeConfigJson, "Columna 'configuracion_json' eliminada físicamente de 'ediciones_candelaria' en la base de datos");

// Instanciación de servicios
$orgRepo       = new OrganizacionRepositorio($pdo);
$usuarioRepo   = new UsuarioRepositorio($pdo);
$personaRepo   = new PersonaRepositorio($pdo);
$rolRepo       = new RolRepositorio($pdo);
$permisoRepo   = new PermisoRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$edicionRepo   = new EdicionRepositorio($pdo);

$authzServicio = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$edicionServicio = new EdicionServicio($edicionRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);
$contextoResolver = new ContextoEdicionResolver($edicionRepo);

// Iniciar transacción de pruebas
$pdo->beginTransaction();

try {
    // ==========================================================================
    // BLOQUE 2: CONTEXTOOPERNACION CON CONTEXTO DE EDICIÓN Y ORIGEN
    // ==========================================================================
    echo "\n--- BLOQUE 2: CONTEXTOOPERACION EXTENDIDO (EDICIÓN Y ORIGEN) ---\n";

    $ctxBase = ContextoOperacion::paraHumano(
        usuarioId: 999,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: 10000,
        metadatos: ['csrf_token' => 'token_test_123']
    );

    afirmar($ctxBase->edicionTrabajoId === null, "Contexto base humano inicia con edicionTrabajoId null");
    afirmar($ctxBase->origenEdicion === 'AUSENTE', "Contexto base humano inicia con origenEdicion AUSENTE");

    $ctxConEdicion = $ctxBase->conEdicionTrabajo(101, 'EXPLICITA');
    afirmar($ctxConEdicion->edicionTrabajoId === 101, "conEdicionTrabajo(101, 'EXPLICITA') asigna ID correctamente");
    afirmar($ctxConEdicion->origenEdicion === 'EXPLICITA', "conEdicionTrabajo asigna origen EXPLICITA");
    afirmar($ctxConEdicion->usuarioId === $ctxBase->usuarioId, "conEdicionTrabajo preserva inmutabilidad de usuarioId");
    afirmar($ctxBase->edicionTrabajoId === null, "El contexto original permanece inalterado (inmutabilidad estricta)");

    $ctxInstitucional = $ctxBase->conEdicionTrabajo(102, 'INSTITUCIONAL');
    afirmar($ctxInstitucional->origenEdicion === 'INSTITUCIONAL', "conEdicionTrabajo permite origen INSTITUCIONAL");

    $excepcionInvariante = false;
    try {
        $ctxBase->conEdicionTrabajo(-5, 'EXPLICITA');
    } catch (InvalidArgumentException) {
        $excepcionInvariante = true;
    }
    afirmar($excepcionInvariante, "ContextoOperacion rechaza edicionTrabajoId negativo");

    $excepcionOrigenInvalido = false;
    try {
        $ctxBase->conEdicionTrabajo(101, 'ORIGEN_INEXISTENTE');
    } catch (InvalidArgumentException) {
        $excepcionOrigenInvalido = true;
    }
    afirmar($excepcionOrigenInvalido, "ContextoOperacion rechaza origen de edición no permitido");

    // ==========================================================================
    // BLOQUE 3: CONTEXTOEDICIONRESOLVER Y AISLAMIENTO FAIL-CLOSED
    // ==========================================================================
    echo "\n--- BLOQUE 3: CONTEXTOEDICIONRESOLVER Y COMPORTAMIENTO FAIL-CLOSED ---\n";

    // Creamos dos organizaciones y ediciones de prueba
    $stmtOrg2 = $pdo->prepare("INSERT INTO `organizaciones` (`id`, `codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (20000, 'org_f21b_segunda', 'SEGUNDA PRODUCCION F21B', 'SEGUNDA PRODUCCION F21B S.A.C.', 2, '20999888772', 'PE', 'contacto@segunda.com', 'ACTIVO')");
    $stmtOrg2->execute();

    // Edición 1 para Org 10000 (es_actual = 1)
    $ed1Org1Id = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 10000, codigo: 'candelaria-2026-test', nombre: 'Candelaria 2026 Test',
        anio: 2026, fechaInicio: '2026-02-01', fechaFin: '2026-02-15', descripcion: null,
        estado: EstadoEdicion::OPERACION, esActual: true
    ));

    // Edición 2 para Org 10000 (es_actual = 0)
    $ed2Org1Id = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 10000, codigo: 'candelaria-2027-test', nombre: 'Candelaria 2027 Test',
        anio: 2027, fechaInicio: '2027-02-01', fechaFin: '2027-02-15', descripcion: null,
        estado: EstadoEdicion::PREOPERACION, esActual: false
    ));

    // Edición 3 para Org 20000 (es_actual = 1 de esa otra org)
    $ed1Org2Id = $edicionRepo->crear(new Edicion(
        id: null, organizacionId: 20000, codigo: 'candelaria-2026-org2', nombre: 'Candelaria Org 2 2026',
        anio: 2026, fechaInicio: '2026-02-01', fechaFin: '2026-02-15', descripcion: null,
        estado: EstadoEdicion::PREOPERACION, esActual: true
    ));

    // 3.1: Header AUSENTE con organización que tiene es_actual = 1 -> Fallback institucional
    $resAusente = $contextoResolver->resolverDesdeServidor($ctxBase, []);
    afirmar($resAusente->edicionTrabajoId === $ed1Org1Id, "Header ausente resuelve fallback institucional a es_actual (Edición {$ed1Org1Id})");
    afirmar($resAusente->origenEdicion === 'INSTITUCIONAL', "Origen resuelto en fallback institucional es 'INSTITUCIONAL'");

    // 3.2: Header PRESENTE VÁLIDO (ej: Edición 2027 en esta pestaña)
    $resExplicito = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $ed2Org1Id]);
    afirmar($resExplicito->edicionTrabajoId === $ed2Org1Id, "Header explícito X-Edicion-Id resuelve edición solicitada ({$ed2Org1Id})");
    afirmar($resExplicito->origenEdicion === 'EXPLICITA', "Origen resuelto con header válido es 'EXPLICITA'");

    // 3.3: Header PRESENTE INVÁLIDO (no numérico / formato incorrecto) -> Fail-Closed estricto SIN fallback
    $falloHeaderInvalido = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => 'no_es_entero']);
    } catch (InvalidArgumentException $e) {
        $falloHeaderInvalido = true;
    }
    afirmar($falloHeaderInvalido, "Header X-Edicion-Id no numérico es rechazado con InvalidArgumentException (Fail-Closed sin fallback)");

    // 3.4: Header PRESENTE NEGATIVO o CERO -> Fail-Closed estricto
    $falloHeaderCero = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '0']);
    } catch (InvalidArgumentException $e) {
        $falloHeaderCero = true;
    }
    afirmar($falloHeaderCero, "Header X-Edicion-Id = 0 es rechazado fail-closed");

    // 3.5: Header PRESENTE con ID INEXISTENTE -> Fail-Closed estricto
    $falloHeaderInexistente = false;
    try {
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => '999999']);
    } catch (InvalidArgumentException $e) {
        $falloHeaderInexistente = true;
    }
    afirmar($falloHeaderInexistente, "Header X-Edicion-Id inexistente es rechazado fail-closed sin caer a es_actual");

    // 3.6: Header PRESENTE con ID de OTRO TENANT (Anti-IDOR) -> Acceso Denegado Fail-Closed
    $falloHeaderAntiIdor = false;
    try {
        // Solicitamos desde el contexto de Org 10000 la edición de Org 20000
        $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $ed1Org2Id]);
    } catch (AccesoDenegadoExcepcion $e) {
        $falloHeaderAntiIdor = true;
    }
    afirmar($falloHeaderAntiIdor, "Header X-Edicion-Id de otro tenant es rechazado con AccesoDenegadoExcepcion (Anti-IDOR estricto)");

    // 3.7: Header AUSENTE en organización SIN ninguna edición actual
    $ctxOrgSinEdicion = ContextoOperacion::paraHumano(
        usuarioId: 998,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: 30000
    );
    $resSinActual = $contextoResolver->resolverDesdeServidor($ctxOrgSinEdicion, []);
    afirmar($resSinActual->edicionTrabajoId === null, "Header ausente sin edición actual en tenant resuelve edicionTrabajoId = null");
    afirmar($resSinActual->origenEdicion === 'AUSENTE', "Header ausente sin edición actual en tenant resuelve origen AUSENTE");

    // ==========================================================================
    // BLOQUE 4: DEMOSTRACIÓN DE AISLAMIENTO MULTI-PESTAÑA (TAB CONCURRENCY)
    // ==========================================================================
    echo "\n--- BLOQUE 4: DEMOSTRACIÓN DE AISLAMIENTO MULTI-PESTAÑA ---\n";

    // Pestaña A consulta con Candelaria 2026
    $ctxPestanaA = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $ed1Org1Id]);
    // Pestaña B consulta con Candelaria 2027 simultáneamente
    $ctxPestanaB = $contextoResolver->resolverDesdeServidor($ctxBase, ['HTTP_X_EDICION_ID' => (string) $ed2Org1Id]);
    // Pestaña C es una nueva ventana sin selección previa
    $ctxPestanaC = $contextoResolver->resolverDesdeServidor($ctxBase, []);

    afirmar($ctxPestanaA->edicionTrabajoId === $ed1Org1Id, "Pestaña A opera deterministamente en Edición 2026");
    afirmar($ctxPestanaB->edicionTrabajoId === $ed2Org1Id, "Pestaña B opera deterministamente en Edición 2027");
    afirmar($ctxPestanaC->edicionTrabajoId === $ed1Org1Id, "Pestaña C sin selección hereda la actual 2026 institucional");
    afirmar($ctxPestanaA->edicionTrabajoId !== $ctxPestanaB->edicionTrabajoId, "Pestaña A y B mantienen contextos independientes sin colisión");

    // ==========================================================================
    // BLOQUE 5: CREACIÓN DE USUARIOS EFÍMEROS CON DISTINTOS ROLES Y PERMISOS
    // ==========================================================================
    echo "\n--- BLOQUE 5: CONFIGURACIÓN DE ACTORES EFÍMEROS PARA ENDPOINTS API ---\n";

    // 5.1 Persona y Usuario con rol admin (todos los permisos de ediciones)
    $perAdmin = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71112221', nombres: 'Admin', apellidos: 'Ediciones',
        correoElectronico: 'admin_f21b@test.com', estado: 'ACTIVO'
    );
    $perAdminId = $personaRepo->crear($perAdmin);
    $usrAdmin = new Usuario(
        id: null, organizacionId: 10000, personaId: $perAdminId,
        nombreUsuario: 'admin_ediciones_f21b', nombreCompleto: 'Admin Ediciones',
        correoElectronico: 'admin_f21b@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrAdminId = $usuarioRepo->crear($usrAdmin);
    $rolAdminOrg = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolRepo->sincronizarRolesUsuario($usrAdminId, [$rolAdminOrg->id]);

    // 5.2 Persona y Usuario sin permisos (usuario raso sin ediciones.*)
    $perLector = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71112222', nombres: 'Lector', apellidos: 'SinPermisos',
        correoElectronico: 'lector_f21b@test.com', estado: 'ACTIVO'
    );
    $perLectorId = $personaRepo->crear($perLector);
    $usrLector = new Usuario(
        id: null, organizacionId: 10000, personaId: $perLectorId,
        nombreUsuario: 'lector_f21b', nombreCompleto: 'Lector Sin Permisos',
        correoElectronico: 'lector_f21b@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        esSuperadminPlataforma: false, estado: 'ACTIVO'
    );
    $usrLectorId = $usuarioRepo->crear($usrLector);
    // Rol vacío o rol sin permisos de edición
    $stmtRolVacio = $pdo->prepare("INSERT INTO `roles` (`organizacion_id`, `codigo`, `nombre`, `descripcion`) VALUES (10000, 'rol_vacio_f21b', 'Rol Vacio Test', 'Sin permisos')");
    $stmtRolVacio->execute();
    $rolVacioId = (int) $pdo->lastInsertId();
    $rolRepo->sincronizarRolesUsuario($usrLectorId, [$rolVacioId]);

    // Contextos mockeados para controladores
    $tokenCsrfValido = ProtectorCsrf::generarToken('sesion_test_f21b');
    $ctxAdmin = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: 10000,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    $ctxLector = ContextoOperacion::paraHumano(
        usuarioId: $usrLectorId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'CLI-Tester',
        organizacionId: 10000,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    // ==========================================================================
    // BLOQUE 6: CONTROLADOR EDICIONES - PRUEBAS DE SEGURIDAD Y RBAC
    // ==========================================================================
    echo "\n--- BLOQUE 6: ENDPOINTS DE EDICIONCONTROLADOR Y CONTROL DE ACCESO ---\n";

    // Mock middleware para inyectar contextos
    $crearControladorConContexto = function (?ContextoOperacion $ctx) use ($edicionServicio, $edicionRepo, $contextoResolver, $pdo, $authzServicio) {
        $authMock = new class($ctx) extends AutenticacionMiddleware {
            public function __construct(private ?ContextoOperacion $mockCtx) {}
            public function manejar(): ContextoOperacion {
                if ($this->mockCtx === null) {
                    throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.');
                }
                return $this->mockCtx;
            }
            public function procesar(array $servidor = [], array $cookies = [], bool $fallarSiNoAutenticado = true): ?ContextoOperacion {
                if ($this->mockCtx === null && $fallarSiNoAutenticado) {
                    throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.');
                }
                return $this->mockCtx;
            }
        };

        $authzMock = new class($authzServicio) extends AutorizacionMiddleware {
            public function __construct(private AutorizacionServicio $authz) {}
            public function manejar(string $permiso): void {
                $ctx = ContextoOperacion::actual();
                if ($ctx === null || $ctx->usuarioId === null) {
                    throw new AccesoDenegadoExcepcion('No autenticado para verificar permisos.');
                }
                if (!$this->authz->tienePermiso($ctx->usuarioId, $permiso)) {
                    throw new AccesoDenegadoExcepcion("Acceso denegado: falta permiso {$permiso}.");
                }
            }
            public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $lanzarExcepcion = true): bool {
                $ctx = $contexto ?? ContextoOperacion::actual();
                if ($ctx === null || $ctx->usuarioId === null) return false;
                $ok = $this->authz->tienePermiso($ctx->usuarioId, $permiso);
                if (!$ok && $lanzarExcepcion) {
                    throw new AccesoDenegadoExcepcion("Acceso denegado: falta permiso {$permiso}.");
                }
                return $ok;
            }
        };

        return new EdicionControlador(
            authMiddleware: $authMock,
            authzMiddleware: $authzMock,
            edicionServicio: $edicionServicio,
            edicionRepo: $edicionRepo,
            contextoResolver: $contextoResolver,
            pdo: $pdo
        );
    };

    // 6.1: Acceso no autenticado a listar() -> 403 / 401
    $ctrlSinAuth = $crearControladorConContexto(null);
    $resListarSinAuth = json_decode($ctrlSinAuth->listar(), true);
    afirmar($resListarSinAuth['exito'] === false && $resListarSinAuth['codigo'] === 403, "GET /api/v1/ediciones sin autenticación es denegado");

    // 6.2: Acceso sin permiso 'ediciones.ver' -> 403
    $ctrlLector = $crearControladorConContexto($ctxLector);
    ContextoOperacion::establecerActual($ctxLector);
    $resListarLector = json_decode($ctrlLector->listar(), true);
    afirmar($resListarLector['exito'] === false && $resListarLector['codigo'] === 403, "GET /api/v1/ediciones sin permiso 'ediciones.ver' retorna 403");

    // 6.3: Acceso con permiso 'ediciones.ver' -> 200 con listado
    $ctrlAdmin = $crearControladorConContexto($ctxAdmin);
    ContextoOperacion::establecerActual($ctxAdmin);
    $resListarAdmin = json_decode($ctrlAdmin->listar(), true);
    afirmar($resListarAdmin['exito'] === true && is_array($resListarAdmin['datos']), "GET /api/v1/ediciones con 'ediciones.ver' retorna 200 y catálogo");
    afirmar(count($resListarAdmin['datos']) >= 2, "Listado de ediciones contiene al menos las ediciones del tenant");

    // 6.4: Detalle Anti-IDOR: intentar ver edición de otro tenant retorna 404
    $resDetalleAjeno = json_decode($ctrlAdmin->detalle($ed1Org2Id), true);
    afirmar($resDetalleAjeno['exito'] === false && $resDetalleAjeno['codigo'] === 404, "GET /api/v1/ediciones/{id_ajeno} retorna 404 (Anti-IDOR fail-closed)");

    // 6.5: Detalle propio retorna 200
    $resDetallePropio = json_decode($ctrlAdmin->detalle($ed1Org1Id), true);
    afirmar($resDetallePropio['exito'] === true && $resDetallePropio['datos']['id'] === $ed1Org1Id, "GET /api/v1/ediciones/{id_propio} retorna 200 con datos");

    // ==========================================================================
    // BLOQUE 7: CREAR EDICIÓN (VALIDACIONES Y UNICIDAD)
    // ==========================================================================
    echo "\n--- BLOQUE 7: CREACIÓN DE EDICIONES Y VALIDACIONES DE UNICIDAD ---\n";

    // Inyectar datos POST simulados
    $inyectarPayload = function (array $payload) use ($tokenCsrfValido) {
        $payload['csrf_token'] = $tokenCsrfValido;
        $json = json_encode($payload);
        // Simular php://input asignando un stream personalizado o sobreescribiendo en función mock
        $GLOBALS['__mock_json_input'] = $json;
    };

    // Subclase rápida para mockear lectura de php://input y $_SERVER en el controlador
    $crearControladorParaMutaciones = function (ContextoOperacion $ctx) use ($edicionServicio, $edicionRepo, $contextoResolver, $pdo, $authzServicio) {
        return new class($ctx, $edicionServicio, $edicionRepo, $contextoResolver, $pdo, $authzServicio) extends EdicionControlador {
            public function __construct(
                private ContextoOperacion $testCtx,
                $edServ, $edRep, $ctxRes, $p, $authz
            ) {
                $authMock = new class($testCtx) extends AutenticacionMiddleware {
                    public function __construct(private ContextoOperacion $mockCtx) {}
                    public function manejar(): ContextoOperacion { return $this->mockCtx; }
                    public function procesar(array $s = [], array $c = [], bool $f = true): ?ContextoOperacion { return $this->mockCtx; }
                };
                $authzMock = new class($authz, $testCtx) extends AutorizacionMiddleware {
                    public function __construct(private AutorizacionServicio $authz, private ContextoOperacion $testCtx) {}
                    public function manejar(string $permiso): void {
                        if (!$this->authz->tienePermiso($this->testCtx->usuarioId, $permiso)) {
                            throw new AccesoDenegadoExcepcion("Acceso denegado: falta permiso {$permiso}.");
                        }
                    }
                    public function verificarPermiso(string $permiso, ?ContextoOperacion $c = null, bool $l = true): bool {
                        $ok = $this->authz->tienePermiso($this->testCtx->usuarioId, $permiso);
                        if (!$ok && $l) throw new AccesoDenegadoExcepcion("Acceso denegado: falta permiso {$permiso}.");
                        return $ok;
                    }
                };
                parent::__construct($authMock, $authzMock, $edServ, $edRep, $ctxRes, $p);
            }

            public function testCrear(array $payload): array {
                ContextoOperacion::establecerActual($this->testCtx);
                // Inyectamos token CSRF
                $payload['csrf_token'] = $this->testCtx->metadatos['csrf_token'] ?? '';
                $json = json_encode($payload);
                // Usamos reflexión para invocar
                return json_decode($this->ejecutarConPayload('crear', $payload), true);
            }

            public function testActualizar(int $id, array $payload): array {
                ContextoOperacion::establecerActual($this->testCtx);
                return json_decode($this->ejecutarConPayload('actualizar', $payload, $id), true);
            }

            public function testCambiarEstado(int $id, array $payload): array {
                ContextoOperacion::establecerActual($this->testCtx);
                return json_decode($this->ejecutarConPayload('cambiarEstado', $payload, $id), true);
            }

            public function testSeleccionarActual(int $id): array {
                ContextoOperacion::establecerActual($this->testCtx);
                return json_decode($this->ejecutarConPayload('seleccionarActual', [], $id), true);
            }

            public function testContextoActual(array $servidor = []): array {
                ContextoOperacion::establecerActual($this->testCtx);
                // Mock $_SERVER
                $servidorOriginal = $_SERVER;
                $_SERVER = array_merge($_SERVER, $servidor);
                $resp = json_decode($this->contextoActual(), true);
                $_SERVER = $servidorOriginal;
                return $resp;
            }

            private function ejecutarConPayload(string $metodo, array $payload, ?int $paramId = null): string {
                // Guardamos payload en reflection
                $prop = new ReflectionProperty(EdicionControlador::class, 'pdo');
                // Simulamos la llamada inyectando php://input
                $refClass = new ReflectionClass(EdicionControlador::class);
                $met = $refClass->getMethod($metodo);
                
                // Sobrescribir temporalmente helper
                $prevServer = $_SERVER;
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $this->testCtx->metadatos['csrf_token'] ?? '';

                // Usamos un wrapper para pasar payload directamente a través de reflection si es necesario
                // Pero como obtenerPayloadJson lee php://input, usamos un flujo directo seguro:
                $sub = new class($this, $payload) {
                    public function __construct(private $ctrl, private $data) {}
                    public function call($metodo, $paramId) {
                        // En lugar de stream input, inyectamos método
                        return $paramId !== null ? $this->ctrl->$metodo($paramId) : $this->ctrl->$metodo();
                    }
                };

                // Asignamos a variable estática temporal
                $refProp = $refClass->hasProperty('payloadTest') ? $refClass->getProperty('payloadTest') : null;
                
                // Invocamos directamente pasando el payload vía stream wrappers o directamente
                // Dado que obtenerPayloadJson usa file_get_contents('php://input'), usamos data wrapper:
                // En PHP podemos crear un contexto para crear()
                return $paramId !== null ? $this->$metodo($paramId) : $this->$metodo();
            }
        };
    };

    // Probemos directamente con EdicionServicio para verificar toda la lógica de dominio antes del controlador
    // 7.1: Alta válida vía EdicionServicio
    $nuevaEdicion = $edicionServicio->crear(10000, [
        'codigo'       => 'candelaria-2028-test',
        'nombre'       => 'Candelaria 2028 Test',
        'anio'         => 2028,
        'fecha_inicio' => '2028-02-01',
        'fecha_fin'    => '2028-02-15',
        'descripcion'  => 'Prueba de creación 2028',
        'es_actual'    => false
    ], $ctxAdmin);

    afirmar($nuevaEdicion->id > 0, "Alta válida de edición 2028 retorna ID persistido");
    afirmar($nuevaEdicion->estado === EstadoEdicion::PREOPERACION, "Toda nueva edición nace en estado PREOPERACION");
    afirmar($nuevaEdicion->esActual === false, "Toda nueva edición nace con es_actual = false");

    // 7.2: Unicidad de año en el mismo tenant -> 422
    $falloAnioDuplicado = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo'       => 'candelaria-2028-dup',
            'nombre'       => 'Candelaria 2028 Duplicada',
            'anio'         => 2028,
            'fecha_inicio' => '2028-02-01',
            'fecha_fin'    => '2028-02-15'
        ], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $falloAnioDuplicado = true;
    }
    afirmar($falloAnioDuplicado, "Creación con año 2028 duplicado para la misma organización es rechazada");

    // 7.3: Unicidad de código en el mismo tenant -> 422
    $falloCodigoDuplicado = false;
    try {
        $edicionServicio->crear(10000, [
            'codigo'       => 'candelaria-2028-test',
            'nombre'       => 'Candelaria 2029 Codigo Duplicado',
            'anio'         => 2029,
            'fecha_inicio' => '2029-02-01',
            'fecha_fin'    => '2029-02-15'
        ], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $falloCodigoDuplicado = true;
    }
    afirmar($falloCodigoDuplicado, "Creación con código 'CAND_2028_TEST' duplicado es rechazada");

    // ==========================================================================
    // BLOQUE 8: ACTUALIZACIÓN Y CONCURRENCIA OPTIMISTA (409)
    // ==========================================================================
    echo "\n--- BLOQUE 8: ACTUALIZACIÓN Y CONCURRENCIA OPTIMISTA ---\n";

    // 8.1: Actualización exitosa con actualizado_en_esperado correcto
    $tsEsperado = $nuevaEdicion->actualizadoEn;
    $edicionModificada = $edicionServicio->actualizar(
        $nuevaEdicion->id,
        10000,
        ['nombre' => 'Candelaria 2028 Nombre Actualizado'],
        $ctxAdmin,
        $tsEsperado
    );
    afirmar($edicionModificada->nombre === 'CANDELARIA 2028 NOMBRE ACTUALIZADO', "Actualización con timestamp coincidente modifica el nombre correctamente (normalizado)");

    // 8.2: Concurrencia optimista (timestamp desfasado) -> ConflictoConcurrenciaExcepcion (409)
    $falloConcurrencia = false;
    try {
        $edicionServicio->actualizar(
            $nuevaEdicion->id,
            10000,
            ['nombre' => 'Intento con timestamp desfasado'],
            $ctxAdmin,
            '2020-01-01 00:00:00'
        );
    } catch (ConflictoConcurrenciaExcepcion $e) {
        $falloConcurrencia = true;
    }
    afirmar($falloConcurrencia, "Actualización con timestamp desfasado lanza ConflictoConcurrenciaExcepcion (409)");

    // ==========================================================================
    // BLOQUE 9: MÁQUINA DE ESTADOS Y GOBERNANZA DEL CICLO DE VIDA
    // ==========================================================================
    echo "\n--- BLOQUE 9: TRANSICIONES DE ESTADO Y RETROCESOS CON AUDITORÍA ---\n";

    // 9.1: Avance legal secuencial: PREOPERACION -> OPERACION
    $edAvanzada1 = $edicionServicio->cambiarEstado(
        $nuevaEdicion->id,
        10000,
        'OPERACION',
        $ctxAdmin
    );
    afirmar($edAvanzada1->estado === EstadoEdicion::OPERACION, "Transición legal PREOPERACION -> OPERACION exitosa");

    // 9.2: Retroceso ilegal directo: PREOPERACION -> (no existe retroceso desde preoperacion)
    // Pero ahora estamos en OPERACION, intentemos retroceder a PREOPERACION SIN motivo -> debe fallar
    $falloRetrocesoSinMotivo = false;
    try {
        $edicionServicio->cambiarEstado(
            $nuevaEdicion->id,
            10000,
            'PREOPERACION',
            $ctxAdmin,
            null // Sin motivo
        );
    } catch (InvalidArgumentException $e) {
        $falloRetrocesoSinMotivo = true;
    }
    afirmar($falloRetrocesoSinMotivo, "Retroceso excepcional OPERACION -> PREOPERACION sin motivo es rechazado");

    // 9.3: Retroceso excepcional CON motivo válido -> éxito
    $edRetrocedida = $edicionServicio->cambiarEstado(
        $nuevaEdicion->id,
        10000,
        'PREOPERACION',
        $ctxAdmin,
        'Postergación del cronograma oficial por contingencia climática en Puno'
    );
    afirmar($edRetrocedida->estado === EstadoEdicion::PREOPERACION, "Retroceso excepcional OPERACION -> PREOPERACION con motivo justificado es exitoso");

    // 9.4: Volver a avanzar hasta POSTPRODUCCION_ENTREGA y luego CERRADA
    $edicionServicio->cambiarEstado($nuevaEdicion->id, 10000, 'OPERACION', $ctxAdmin);
    $edPost = $edicionServicio->cambiarEstado($nuevaEdicion->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxAdmin);
    afirmar($edPost->estado === EstadoEdicion::POSTPRODUCCION_ENTREGA, "Avance a POSTPRODUCCION_ENTREGA exitoso");

    $edCerrada = $edicionServicio->cambiarEstado($nuevaEdicion->id, 10000, 'CERRADA', $ctxAdmin);
    afirmar($edCerrada->estado === EstadoEdicion::CERRADA, "Cierre formal de festividad a estado CERRADA exitoso");

    // 9.5: Invariante terminal: CERRADA bloquea cualquier transición posterior (sin reapertura ordinaria)
    $falloTransicionDesdeCerrada = false;
    try {
        $edicionServicio->cambiarEstado($nuevaEdicion->id, 10000, 'POSTPRODUCCION_ENTREGA', $ctxAdmin, 'Intento de reapertura');
    } catch (InvalidArgumentException $e) {
        $falloTransicionDesdeCerrada = true;
    }
    afirmar($falloTransicionDesdeCerrada, "Estado terminal CERRADA bloquea transiciones y reaperturas");

    // 9.6: Invariante terminal: CERRADA bloquea actualizaciones de datos
    $falloEdicionCerrada = false;
    try {
        $edicionServicio->actualizar($nuevaEdicion->id, 10000, ['nombre' => 'Intento en edición cerrada'], $ctxAdmin);
    } catch (InvalidArgumentException $e) {
        $falloEdicionCerrada = true;
    }
    afirmar($falloEdicionCerrada, "Estado terminal CERRADA bloquea modificaciones de datos");

    // ==========================================================================
    // BLOQUE 10: SELECCIÓN DE EDICIÓN ACTUAL INSTITUCIONAL Y EXCLUSIVIDAD CON LOCK
    // ==========================================================================
    echo "\n--- BLOQUE 10: SELECCIÓN DE EDICIÓN ACTUAL CON EXCLUSIVIDAD Y LOCK ---\n";

    // Creamos edición 2029 para designarla como actual
    $ed2029 = $edicionServicio->crear(10000, [
        'codigo'       => 'candelaria-2029-test',
        'nombre'       => 'Candelaria 2029 Test',
        'anio'         => 2029,
        'fecha_inicio' => '2029-02-01',
        'fecha_fin'    => '2029-02-15'
    ], $ctxAdmin);

    // Verificar que ed1Org1Id (2026) tiene es_actual = 1 inicialmente
    $ed1Previa = $edicionRepo->buscarPorId($ed1Org1Id);
    afirmar($ed1Previa->esActual === true, "Edición 2026 era inicialmente es_actual = 1");

    // Designar ed2029 como actual institucional
    $ed2029Actual = $edicionServicio->establecerActual($ed2029->id, 10000, $ctxAdmin);
    afirmar($ed2029Actual->esActual === true, "Edición 2029 designada exitosamente como es_actual = 1");

    // Verificar que la anterior (2026) fue desmarcada automáticamente en la base de datos
    $ed2026Recargada = $edicionRepo->buscarPorId($ed1Org1Id);
    afirmar($ed2026Recargada->esActual === false, "Edición 2026 fue desmarcada automáticamente (es_actual = 0)");

    // Verificar que la edición de la OTRA organización (Org 20000) NO fue afectada
    $edOrg2Recargada = $edicionRepo->buscarPorId($ed1Org2Id);
    afirmar($edOrg2Recargada->esActual === true, "Edición de otra organización no fue afectada por el cambio (aislamiento estricto)");

    // Verificar conteo de es_actual = 1 en la organización 10000 (debe ser exactamente 1)
    $stmtCountActual = $pdo->prepare("SELECT COUNT(*) FROM ediciones_candelaria WHERE organizacion_id = 10000 AND es_actual = 1");
    $stmtCountActual->execute();
    $conteoActualOrg = (int) $stmtCountActual->fetchColumn();
    afirmar($conteoActualOrg === 1, "Existe exactamente UNA sola edición con es_actual = 1 en la organización");

    // ==========================================================================
    // BLOQUE 11: ENDPOINT GET /api/v1/contexto/edicion
    // ==========================================================================
    echo "\n--- BLOQUE 11: ENDPOINT GET /api/v1/contexto/edicion ---\n";

    ContextoOperacion::establecerActual($ctxAdmin);
    // Simular llamada con servidor sin header
    $servidorSinHeader = [];
    $contextoResolver->resolverDesdeServidor($ctxAdmin, $servidorSinHeader);
    
    $ctrlEdicion = $crearControladorConContexto($ctxAdmin);
    $resContexto = json_decode($ctrlEdicion->contextoActual(), true);

    afirmar($resContexto['exito'] === true, "GET /api/v1/contexto/edicion retorna 200 exitoso");
    afirmar(isset($resContexto['datos']['edicion_trabajo']), "Respuesta incluye objeto 'edicion_trabajo'");
    afirmar(isset($resContexto['datos']['origen']), "Respuesta incluye campo 'origen'");
    afirmar(is_array($resContexto['datos']['ediciones']), "Respuesta incluye array 'ediciones' para poblar selector global");

} catch (Throwable $e) {
    afirmar(false, "Excepción inesperada en la suite F2.1B: " . $e->getMessage() . "\n" . $e->getTraceAsString());
} finally {
    // Revertir absolutamente todo cambio de la suite de pruebas
    $pdo->rollBack();
    echo "\n[INFO] Transacción de pruebas revertida con ROLLBACK determinista.\n";
}

// ==============================================================================
// BLOQUE 12: CERTIFICACIÓN DE INMUTABILIDAD DE ORLANDO (ID 24)
// ==============================================================================
echo "\n--- BLOQUE 12: PRESERVACIÓN ABSOLUTA DE CREDENCIALES DE ORLANDO ---\n";

$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

afirmar(
    $fingerprintOrlandoPre !== null && $fingerprintOrlandoPre === $fingerprintOrlandoPost,
    "Credenciales de Orlando (ID 24) preservadas idénticas (Huella: {$fingerprintOrlandoPre})",
    "Pre: {$fingerprintOrlandoPre} vs Post: {$fingerprintOrlandoPost}"
);

afirmar(
    (int) $orlandoPost['intentos_fallidos'] === 0 && $orlandoPost['estado'] === 'ACTIVO',
    "Estado de Orlando permanece ACTIVO con 0 intentos fallidos"
);

echo "\n==============================================================================\n";
echo "RESUMEN DE PRUEBAS F2.1B: ÉXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}
exit(0);

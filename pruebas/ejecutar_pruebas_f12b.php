<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require_once __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\BrandingServicio;
use Aplicacion\Controladores\OrganizacionControlador;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
use Aplicacion\Repositorios\ActorSistemaRepositorio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\SesionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Seguridad\AutenticacionServicio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS DE ORGANIZACIÓN Y BRANDING F1.2B\n";
echo "FICHA INSTITUCIONAL, EDICIÓN, BRANDING SEGURO, RECHAZO SVG, RBAC Y ANTI-IDOR\n";
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
$sesionRepo    = new SesionRepositorio($pdo);
$auditoriaRepo = new AuditoriaRepositorio($pdo);
$actorSysRepo  = new ActorSistemaRepositorio($pdo);

$authzServicio = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
$authServicio  = new AutenticacionServicio($usuarioRepo, $sesionRepo, $auditoriaRepo, $actorSysRepo, $pdo);

$raizProyecto = dirname(__DIR__);

// Helper para crear imagen PNG válida en memoria/archivo
function crearImagenPngTemporal(int $ancho = 50, int $alto = 50): string
{
    $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . bin2hex(random_bytes(6)) . '.png';
    $img = imagecreatetruecolor($ancho, $alto);
    $color = imagecolorallocate($img, 220, 53, 69);
    imagefilledrectangle($img, 0, 0, $ancho, $alto, $color);
    imagepng($img, $ruta);
    imagedestroy($img);
    return $ruta;
}

// Helper para crear imagen JPEG válida
function crearImagenJpgTemporal(int $ancho = 50, int $alto = 50): string
{
    $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . bin2hex(random_bytes(6)) . '.jpg';
    $img = imagecreatetruecolor($ancho, $alto);
    $color = imagecolorallocate($img, 13, 110, 253);
    imagefilledrectangle($img, 0, 0, $ancho, $alto, $color);
    imagejpeg($img, $ruta);
    imagedestroy($img);
    return $ruta;
}

$pdo->beginTransaction();

try {
    // ==============================================================================
    // CONFIGURACIÓN DE OPERADORES Y CONTEXTOS DE PRUEBA
    // ==============================================================================
    $org10000 = $orgRepo->buscarPorId(10000);
    afirmar($org10000 !== null, '00. Organización operativa 10000 existe en la base de datos');

    // 1. Operador con permisos completos (admin_organizacion con permisos 7-11)
    $perAdmin = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71110001', nombres: 'Admin', apellidos: 'Organizacion', estado: 'ACTIVO'
    );
    $perAdminId = $personaRepo->crear($perAdmin);

    $usrAdmin = new Usuario(
        id: null, organizacionId: 10000, personaId: $perAdminId,
        nombreUsuario: 'admin_f12b', nombreCompleto: 'Admin F12B',
        correoElectronico: 'admin_f12b@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    );
    $usrAdminId = $usuarioRepo->crear($usrAdmin);
    $rolAdmin = $rolRepo->buscarPorCodigo('admin_organizacion');
    $rolRepo->asignarRolAUsuario($usrAdminId, $rolAdmin->id);

    $tokenValido = ProtectorCsrf::obtenerOCrearToken();
    $ctxAdmin = ContextoOperacion::paraHumano($usrAdminId, 1, 'APP', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => $tokenValido]);

    // 2. Operador sin permiso organizacion.ver ni organizacion.editar ni branding.editar (rol operador o usuario básico)
    $perLector = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71110002', nombres: 'Lector', apellidos: 'SinPermisos', estado: 'ACTIVO'
    );
    $perLectorId = $personaRepo->crear($perLector);

    $usrLector = new Usuario(
        id: null, organizacionId: 10000, personaId: $perLectorId,
        nombreUsuario: 'lector_f12b', nombreCompleto: 'Lector Sin Permisos',
        correoElectronico: 'lector_f12b@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    );
    $usrLectorId = $usuarioRepo->crear($usrLector);
    $rolOperador = $rolRepo->buscarPorCodigo('operador_produccion');
    $rolRepo->asignarRolAUsuario($usrLectorId, $rolOperador->id);

    $ctxLector = ContextoOperacion::paraHumano($usrLectorId, 1, 'APP', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => $tokenValido]);

    // 3. Operador solo con permiso organizacion.ver (para probar edición sin permiso)
    $perSoloVer = new Persona(
        id: null, organizacionId: 10000, tipoPersona: 'NATURAL', tipoDocumentoId: 1,
        numeroDocumento: '71110003', nombres: 'Solo', apellidos: 'Ver', estado: 'ACTIVO'
    );
    $perSoloVerId = $personaRepo->crear($perSoloVer);

    $usrSoloVer = new Usuario(
        id: null, organizacionId: 10000, personaId: $perSoloVerId,
        nombreUsuario: 'solover_f12b', nombreCompleto: 'Solo Ver',
        correoElectronico: 'solover_f12b@test.com', contrasenaHash: password_hash('Pass123!', PASSWORD_DEFAULT),
        estado: 'ACTIVO'
    );
    $usrSoloVerId = $usuarioRepo->crear($usrSoloVer);
    // Rol temporal solo con organizacion.ver
    $permisoVer = $permisoRepo->buscarPorCodigo('organizacion.ver');
    $stmtRolCustom = $pdo->prepare("INSERT INTO roles (organizacion_id, codigo, nombre, descripcion, es_sistema) VALUES (10000, 'rol_solo_ver', 'Solo Ver Org', 'Solo Ver Org', 0)");
    $stmtRolCustom->execute();
    $rolSoloVerId = (int) $pdo->lastInsertId();
    $permisoRepo->asignarPermisoARol($rolSoloVerId, $permisoVer->id);
    $rolRepo->asignarRolAUsuario($usrSoloVerId, $rolSoloVerId);

    $ctxSoloVer = ContextoOperacion::paraHumano($usrSoloVerId, 1, 'APP', '127.0.0.1', 'CLI-Tester', 10000, metadatos: ['csrf_token' => $tokenValido]);

    // Mock helpers para simular middlewares en controlador
    $crearControlador = function (?ContextoOperacion $ctx) use ($orgRepo, $auditoriaRepo, $authzServicio, $pdo, $raizProyecto) {
        $mockAuth = new class($ctx) extends AutenticacionMiddleware {
            public function __construct(private ?ContextoOperacion $c) {}
            public function procesar(array $servidor = [], array $cookies = [], bool $bloquearPeticion = true): ?ContextoOperacion {
                ContextoOperacion::establecerActual($this->c);
                return $this->c;
            }
        };

        $mockAuthz = new class($authzServicio) extends AutorizacionMiddleware {
            public function __construct(private AutorizacionServicio $as) { parent::__construct($as); }
            public function verificarPermiso(string $permiso, ?ContextoOperacion $contexto = null, bool $bloquear = true): bool {
                if ($contexto === null || $contexto->usuarioId === null) return false;
                return $this->as->tienePermiso($contexto->usuarioId, $permiso);
            }
        };

        return new OrganizacionControlador($mockAuth, $mockAuthz, $orgRepo, $auditoriaRepo, $pdo, $raizProyecto);
    };

    // ==============================================================================
    // 01. CONSULTA SIN SESIÓN RESPONDE HTTP 401
    // ==============================================================================
    $ctrlSinSesion = $crearControlador(null);
    $resp401 = json_decode($ctrlSinSesion->detalle(), true);

    afirmar(
        $resp401['exito'] === false && $resp401['codigo'] === 401,
        '01. GET /api/v1/organizacion sin sesión activa responde HTTP 401 Unauthorized'
    );

    // ==============================================================================
    // 02. CONSULTA SIN PERMISO organizacion.ver RESPONDE HTTP 403
    // ==============================================================================
    $ctrlSinPermiso = $crearControlador($ctxLector);
    $resp403 = json_decode($ctrlSinPermiso->detalle(), true);

    afirmar(
        $resp403['exito'] === false && $resp403['codigo'] === 403,
        '02. GET /api/v1/organizacion sin permiso organizacion.ver responde HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 03. CONSULTA CON SESIÓN Y PERMISO RETORNA DATOS DE ORGANIZACIÓN ACTUAL
    // ==============================================================================
    $ctrlAutorizado = $crearControlador($ctxAdmin);
    $resp200 = json_decode($ctrlAutorizado->detalle(), true);

    afirmar(
        $resp200['exito'] === true
        && $resp200['codigo'] === 200
        && isset($resp200['datos']['organizacion'])
        && (int) $resp200['datos']['organizacion']['id'] === 10000
        && $resp200['datos']['organizacion']['codigo'] === 'og_estudio'
        && $resp200['datos']['organizacion']['nombre_comercial'] === 'O.G. ESTUDIO CREATIVO',
        '03. GET /api/v1/organizacion con organizacion.ver retorna HTTP 200 y datos de la organización operativa actual'
    );

    // ==============================================================================
    // 04. ANTI-IDOR: MANIPULACIÓN DE organizacion_id ES IGNORADA
    // ==============================================================================
    // Simulamos que el frontend malicioso envía organizacion_id=20000 en query o body
    $_GET['organizacion_id'] = '20000';
    $respIdor = json_decode($ctrlAutorizado->detalle(), true);
    unset($_GET['organizacion_id']);

    afirmar(
        $respIdor['exito'] === true && (int) $respIdor['datos']['organizacion']['id'] === 10000,
        '04. Aislamiento Anti-IDOR: El backend resuelve la organización desde el contexto autenticado, ignorando parámetros externos'
    );

    // ==============================================================================
    // 05. EDICIÓN SIN TOKEN CSRF ES RECHAZADA CON HTTP 403
    // ==============================================================================
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_POST['_csrf_token']);
    $_POST = ['nombre_comercial' => 'NUEVO NOMBRE'];

    $respCsrfAusente = json_decode($ctrlAutorizado->actualizar(), true);

    afirmar(
        $respCsrfAusente['exito'] === false && $respCsrfAusente['codigo'] === 403,
        '05. PUT /api/v1/organizacion sin token CSRF es rechazado con HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 06. EDICIÓN CON TOKEN CSRF INVÁLIDO ES RECHAZADA CON HTTP 403
    // ==============================================================================
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_falso_invalido_123456';
    $respCsrfInvalido = json_decode($ctrlAutorizado->actualizar(), true);
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);

    afirmar(
        $respCsrfInvalido['exito'] === false && $respCsrfInvalido['codigo'] === 403,
        '06. PUT /api/v1/organizacion con token CSRF inválido o alterado es rechazado con HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 07. EDICIÓN SIN PERMISO organizacion.editar ES RECHAZADA CON HTTP 403
    // ==============================================================================
    $ctrlSoloVer = $crearControlador($ctxSoloVer);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenValido;
    $_POST = [
        '_csrf_token'      => $tokenValido,
        'nombre_comercial' => 'INTENTO ILEGAL',
    ];

    $respSinPermisoEditar = json_decode($ctrlSoloVer->actualizar(), true);

    afirmar(
        $respSinPermisoEditar['exito'] === false && $respSinPermisoEditar['codigo'] === 403,
        '07. PUT /api/v1/organizacion sin permiso organizacion.editar es rechazado con HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 08. EDICIÓN AUTORIZADA ACTUALIZA EXITOSAMENTE LA INFORMACIÓN INSTITUCIONAL
    // ==============================================================================
    $_POST = [
        '_csrf_token'        => $tokenValido,
        'nombre_comercial'   => 'o.g. producciones creativas',
        'razon_social'       => 'o.g. producciones creativas s.a.c.',
        'tipo_documento_id'  => 2,
        'numero_documento'   => '20609998881',
        'direccion'          => 'jr. deustua 250',
        'codigo_pais'        => 'pe',
        'departamento'       => 'puno',
        'provincia'          => 'puno',
        'distrito'           => 'puno',
        'telefono_whatsapp'  => '+51 951 888 777',
        'correo_contacto'    => 'INFO@OGPRODUCCIONES.COM',
        'sitio_web'          => 'HTTPS://WWW.OGPRODUCCIONES.COM',
        'contacto_nombre'    => 'orlando gonzales m.',
        'contacto_cargo'     => 'productor general'
    ];

    $respEdicionOk = json_decode($ctrlAutorizado->actualizar(), true);

    afirmar(
        $respEdicionOk['exito'] === true
        && $respEdicionOk['codigo'] === 200
        && isset($respEdicionOk['datos']['organizacion']),
        '08. PUT /api/v1/organizacion con credenciales y CSRF válidos actualiza exitosamente (HTTP 200)'
    );

    // ==============================================================================
    // 09. NORMALIZACIÓN DE TEXTOS DE NEGOCIO A MAYÚSCULAS
    // ==============================================================================
    $orgPostEdicion = $orgRepo->buscarPorId(10000);

    afirmar(
        $orgPostEdicion->nombreComercial === 'O.G. PRODUCCIONES CREATIVAS'
        && $orgPostEdicion->razonSocial === 'O.G. PRODUCCIONES CREATIVAS S.A.C.'
        && $orgPostEdicion->direccion === 'JR. DEUSTUA 250'
        && $orgPostEdicion->departamento === 'PUNO'
        && $orgPostEdicion->contactoNombre === 'ORLANDO GONZALES M.'
        && $orgPostEdicion->contactoCargo === 'PRODUCTOR GENERAL',
        '09. Normalización de textos de negocio: Nombres, razones sociales, cargos y direcciones se persisten en MAYÚSCULAS'
    );

    // ==============================================================================
    // 10. NORMALIZACIÓN DE CANALES DIGITALES (EMAIL Y URL)
    // ==============================================================================
    afirmar(
        $orgPostEdicion->correoContacto === 'info@ogproducciones.com'
        && $orgPostEdicion->sitioWeb === 'https://www.ogproducciones.com'
        && $orgPostEdicion->codigoPais === 'PE',
        '10. Normalización de canales digitales: Correo electrónico y sitio web se persisten en minúsculas limpias'
    );

    // ==============================================================================
    // 11. VALIDACIÓN: FORMATO DE CORREO INVÁLIDO RECHAZADO CON HTTP 422
    // ==============================================================================
    $_POST['correo_contacto'] = 'correo_invalido_sin_arroba';
    $respEmailInvalido = json_decode($ctrlAutorizado->actualizar(), true);

    afirmar(
        $respEmailInvalido['exito'] === false && $respEmailInvalido['codigo'] === 422,
        '11. Validación: PUT con formato de correo electrónico inválido es rechazado con HTTP 422'
    );

    // ==============================================================================
    // 12. VALIDACIÓN: URL SIN ESQUEMA http/https RECHAZADA CON HTTP 422
    // ==============================================================================
    $_POST['correo_contacto'] = 'contacto@valido.com';
    $_POST['sitio_web'] = 'ftp://sitio-invalido.com';
    $respUrlInvalida = json_decode($ctrlAutorizado->actualizar(), true);

    afirmar(
        $respUrlInvalida['exito'] === false && $respUrlInvalida['codigo'] === 422,
        '12. Validación: PUT con URL que no inicia con http:// o https:// es rechazada con HTTP 422'
    );

    // ==============================================================================
    // 13. AUDITORÍA INMUTABLE REGISTRA MODIFICACIONES INSTITUCIONALES
    // ==============================================================================
    $stmtAuditPerfil = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'ACTUALIZAR_PERFIL_ORGANIZACION' ORDER BY id DESC LIMIT 1");
    $auditPerfil = $stmtAuditPerfil->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $auditPerfil !== false
        && $auditPerfil['modulo'] === 'organizacion'
        && strtoupper($auditPerfil['entidad_tipo']) === 'ORGANIZACION'
        && (int) $auditPerfil['organizacion_id'] === 10000
        && !empty($auditPerfil['datos_previos_json'])
        && !empty($auditPerfil['datos_nuevos_json'])
        && !str_contains((string) $auditPerfil['datos_nuevos_json'], 'password'),
        '13. Auditoría inmutable: Modificación de organización registra evento ACTUALIZAR_PERFIL_ORGANIZACION con datos previos y nuevos'
    );

    // ==============================================================================
    // 14. BRANDING: USUARIO SIN PERMISO branding.editar ES RECHAZADO CON HTTP 403
    // ==============================================================================
    $imgTestPng = crearImagenPngTemporal(60, 60);
    $_FILES['logo'] = [
        'name'     => 'logo_test.png',
        'type'     => 'image/png',
        'tmp_name' => $imgTestPng,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($imgTestPng),
    ];
    $_POST = ['_csrf_token' => $tokenValido];

    $respBrandingSinPermiso = json_decode($ctrlSoloVer->actualizarLogo(), true);

    afirmar(
        $respBrandingSinPermiso['exito'] === false && $respBrandingSinPermiso['codigo'] === 403,
        '14. Branding: Carga de logotipo por usuario sin permiso branding.editar es rechazada con HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 15. BRANDING: CARGA SIN TOKEN CSRF ES RECHAZADA CON HTTP 403
    // ==============================================================================
    unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_POST['_csrf_token']);
    $respBrandingSinCsrf = json_decode($ctrlAutorizado->actualizarLogo(), true);

    afirmar(
        $respBrandingSinCsrf['exito'] === false && $respBrandingSinCsrf['codigo'] === 403,
        '15. Branding: Carga de logotipo sin token CSRF válido es rechazada con HTTP 403 Forbidden'
    );

    // ==============================================================================
    // 16. BRANDING: CARGA DE ARCHIVO PNG VÁLIDO SE PROCESA EXITOSAMENTE (HTTP 200)
    // ==============================================================================
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenValido;
    $_POST['_csrf_token'] = $tokenValido;

    $respUploadPng = json_decode($ctrlAutorizado->actualizarLogo(), true);

    afirmar(
        $respUploadPng['exito'] === true
        && $respUploadPng['codigo'] === 200
        && str_starts_with($respUploadPng['datos']['ruta_relativa'], '/recursos/subidas/organizaciones/10000/branding/logo_')
        && str_ends_with($respUploadPng['datos']['ruta_relativa'], '.png'),
        '16. Branding: Carga de archivo PNG válido es procesada exitosamente con nombre seguro y ruta relativa'
    );

    $rutaFisicaLogo1 = $raizProyecto . '/publico' . $respUploadPng['datos']['ruta_relativa'];
    afirmar(file_exists($rutaFisicaLogo1), '16b. Archivo físico de logotipo persistido exitosamente en el filesystem');

    // ==============================================================================
    // 17. BRANDING: CARGA DE ARCHIVO JPG VÁLIDO SE PROCESA EXITOSAMENTE (HTTP 200)
    // ==============================================================================
    $imgTestJpg = crearImagenJpgTemporal(80, 80);
    $_FILES['logo'] = [
        'name'     => 'marca_secundaria.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => $imgTestJpg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($imgTestJpg),
    ];

    $respUploadJpg = json_decode($ctrlAutorizado->actualizarLogo(), true);

    afirmar(
        $respUploadJpg['exito'] === true
        && $respUploadJpg['codigo'] === 200
        && str_ends_with($respUploadJpg['datos']['ruta_relativa'], '.jpg'),
        '17. Branding: Carga de archivo JPG válido es procesada exitosamente con normalización y dimensiones correctas'
    );

    $rutaFisicaLogo2 = $raizProyecto . '/publico' . $respUploadJpg['datos']['ruta_relativa'];
    afirmar(file_exists($rutaFisicaLogo2), '17b. Nuevo archivo JPG persistido en disco');

    // ==============================================================================
    // 18. SEGURIDAD DE ARCHIVOS: ARCHIVO > 2MB ES RECHAZADO CON HTTP 422
    // ==============================================================================
    $rutaArchivoPesado = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oversized_' . bin2hex(random_bytes(4)) . '.png';
    // Crear archivo mayor a 2 MB (2,200,000 bytes)
    $fp = fopen($rutaArchivoPesado, 'w');
    fseek($fp, 2200000);
    fwrite($fp, "\0");
    fclose($fp);

    $_FILES['logo'] = [
        'name'     => 'logo_gigante.png',
        'type'     => 'image/png',
        'tmp_name' => $rutaArchivoPesado,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($rutaArchivoPesado),
    ];

    $respOversized = json_decode($ctrlAutorizado->actualizarLogo(), true);
    @unlink($rutaArchivoPesado);

    afirmar(
        $respOversized['exito'] === false
        && $respOversized['codigo'] === 422
        && str_contains($respOversized['mensaje'], 'tamaño máximo'),
        '18. Seguridad de Archivos: Archivo con peso superior a 2 MB es rechazado tempranamente con HTTP 422'
    );

    // ==============================================================================
    // 19. SEGURIDAD DE ARCHIVOS: FALSO MIME (PHP CON EXTENSIÓN .PNG) ES RECHAZADO
    // ==============================================================================
    $rutaPhpDisfrazado = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fake_png_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($rutaPhpDisfrazado, "<?php echo 'malware payload'; ?>");

    $_FILES['logo'] = [
        'name'     => 'icono.png',
        'type'     => 'image/png', // Cliente miente
        'tmp_name' => $rutaPhpDisfrazado,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($rutaPhpDisfrazado),
    ];

    $respFakeMime = json_decode($ctrlAutorizado->actualizarLogo(), true);
    @unlink($rutaPhpDisfrazado);

    afirmar(
        $respFakeMime['exito'] === false
        && $respFakeMime['codigo'] === 422
        && (str_contains($respFakeMime['mensaje'], 'Tipo de contenido no permitido') || str_contains($respFakeMime['mensaje'], 'no es una imagen')),
        '19. Seguridad de Archivos: Falso MIME (script PHP camuflado como PNG) detectado por finfo/decodificador y rechazado con HTTP 422'
    );

    // ==============================================================================
    // 20. SEGURIDAD DE ARCHIVOS: DOBLE EXTENSIÓN PELIGROSA ES RECHAZADA
    // ==============================================================================
    $imgTestDouble = crearImagenPngTemporal(40, 40);
    $_FILES['logo'] = [
        'name'     => 'payload.php.png',
        'type'     => 'image/png',
        'tmp_name' => $imgTestDouble,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($imgTestDouble),
    ];

    $respDoubleExt = json_decode($ctrlAutorizado->actualizarLogo(), true);
    @unlink($imgTestDouble);

    afirmar(
        $respDoubleExt['exito'] === false
        && $respDoubleExt['codigo'] === 422
        && str_contains($respDoubleExt['mensaje'], 'peligroso'),
        '20. Seguridad de Archivos: Nombre peligroso con doble extensión ejecutable (ej. payload.php.png) es bloqueado con HTTP 422'
    );

    // ==============================================================================
    // 21. SEGURIDAD DE ARCHIVOS: PATH TRAVERSAL EN RUTA RELATIVA BLOQUEADO
    // ==============================================================================
    $bloqueoPathTraversal = BrandingServicio::eliminarArchivoAnterior(
        $raizProyecto,
        10000,
        '/recursos/subidas/organizaciones/10000/branding/../../../../etc/passwd'
    );

    afirmar(
        $bloqueoPathTraversal === false,
        '21. Seguridad de Archivos: Intentos de path traversal en rutas de branding son neutralizados estructuralmente'
    );

    // ==============================================================================
    // 22. POLÍTICA DE SVG: RECHAZO CONTROLADO ANTE CARGA DE ARCHIVO SVG
    // ==============================================================================
    $rutaSvgTest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vector_' . bin2hex(random_bytes(4)) . '.svg';
    file_put_contents($rutaSvgTest, '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="40"/></svg>');

    $_FILES['logo'] = [
        'name'     => 'logo_vectorial.svg',
        'type'     => 'image/svg+xml',
        'tmp_name' => $rutaSvgTest,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($rutaSvgTest),
    ];

    $respSvg = json_decode($ctrlAutorizado->actualizarLogo(), true);
    @unlink($rutaSvgTest);

    afirmar(
        $respSvg['exito'] === false
        && $respSvg['codigo'] === 422
        && str_contains($respSvg['mensaje'], 'SVG se encuentra temporalmente deshabilitado'),
        '22. Política de SVG: Archivo SVG es rechazado con mensaje controlado de seguridad por ausencia de sanitizer vectorial'
    );

    // ==============================================================================
    // 23. SUSTITUCIÓN DE BRANDING Y CERO ARCHIVOS HUÉRFANOS
    // ==============================================================================
    // En el paso 17, $rutaFisicaLogo2 reemplazó a $rutaFisicaLogo1.
    // Verificamos que el archivo físico anterior haya sido retirado del disco.
    afirmar(
        !file_exists($rutaFisicaLogo1),
        '23. Sustitución consistente: La actualización de branding elimina de forma segura el archivo anterior en disco, evitando huérfanos'
    );

    // ==============================================================================
    // 24. AUDITORÍA INMUTABLE DE BRANDING SIN BINARIOS EN BD
    // ==============================================================================
    $stmtAuditLogo = $pdo->query("SELECT * FROM auditoria_operaciones WHERE accion = 'ACTUALIZAR_BRANDING_LOGO' ORDER BY id DESC LIMIT 1");
    $auditLogo = $stmtAuditLogo->fetch(PDO::FETCH_ASSOC);

    afirmar(
        $auditLogo !== false
        && $auditLogo['modulo'] === 'organizacion'
        && strtoupper($auditLogo['entidad_tipo']) === 'ORGANIZACION'
        && str_contains((string) $auditLogo['datos_nuevos_json'], 'logo_url')
        && str_contains((string) $auditLogo['datos_nuevos_json'], 'dimensiones')
        && !str_contains((string) $auditLogo['datos_nuevos_json'], 'data:image') // Cero base64/binarios
        && !str_contains((string) $auditLogo['datos_nuevos_json'], 'password'),
        '24. Auditoría inmutable de branding: Registra metadatos y ruta relativa, sin almacenar contenido binario en BD'
    );

    // ==============================================================================
    // 25. CARGA DE ISOTIPO Y AISLAMIENTO DE ATRIBUTOS
    // ==============================================================================
    $imgTestIsotipo = crearImagenPngTemporal(32, 32);
    $_FILES['isotipo'] = [
        'name'     => 'favicon.png',
        'type'     => 'image/png',
        'tmp_name' => $imgTestIsotipo,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($imgTestIsotipo),
    ];

    $respIsotipo = json_decode($ctrlAutorizado->actualizarIsotipo(), true);
    $orgPostIsotipo = $orgRepo->buscarPorId(10000);

    afirmar(
        $respIsotipo['exito'] === true
        && $respIsotipo['codigo'] === 200
        && str_starts_with($orgPostIsotipo->isotipoUrl, '/recursos/subidas/organizaciones/10000/branding/isotipo_')
        && $orgPostIsotipo->logoUrl !== null
        && str_starts_with($orgPostIsotipo->logoUrl, '/recursos/subidas/organizaciones/10000/branding/logo_'),
        '25. Carga de Isotipo: Actualiza isotipo_url de forma atómica e independiente sin alterar ni degradar el logo_url'
    );

    // Limpieza de archivos creados durante pruebas
    if (file_exists($rutaFisicaLogo2)) {
        @unlink($rutaFisicaLogo2);
    }
    if (!empty($orgPostIsotipo->isotipoUrl)) {
        $rutaFisicaIsotipo = $raizProyecto . '/publico' . $orgPostIsotipo->isotipoUrl;
        if (file_exists($rutaFisicaIsotipo)) {
            @unlink($rutaFisicaIsotipo);
        }
    }

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n==============================================================================\n";
echo "RESULTADO FINAL F1.2B: {$exitos} PRUEBAS EXITOSAS / {$fallos} FALLOS\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}

<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Crm\OrigenComercialServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la administración del catálogo de Orígenes Comerciales (F2.2C).
 */
class OrigenComercialControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private OrigenComercialServicio $origenServicio;
    private OrigenComercialRepositorio $origenRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?OrigenComercialServicio $origenServicio = null,
        ?OrigenComercialRepositorio $origenRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->origenRepo = $origenRepo ?? new OrigenComercialRepositorio($this->pdo);

        if ($origenServicio !== null) {
            $this->origenServicio = $origenServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $this->pdo);

            $this->origenServicio = new OrigenComercialServicio(
                $this->origenRepo,
                $authzServicio,
                $auditoriaRepo,
                $this->pdo
            );
        }
    }

    /**
     * GET /crm/origenes
     * Renderiza la vista del catálogo de orígenes comerciales.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.origenes.administrar', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Orígenes Comerciales',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (crm.origenes.administrar) para acceder a los orígenes comerciales.'
            ], 'principal');
        }

        return Vista::renderizar('crm/origenes', [
            'titulo'          => 'Orígenes Comerciales | CandelariaAPP',
            'subtitulo'       => 'Catálogo Institucional de Canales de Captación',
            'tituloSeccion'   => 'Orígenes Comerciales',
            'seccionActiva'   => 'crm_origenes',
            'scriptAdicional' => url_base('publico/js/crm_origenes.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/crm/origenes
     * Retorna el listado de orígenes comerciales del tenant.
     */
    public function listar(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.origenes.administrar', $contexto, false)
            && !$this->authzMiddleware->verificarPermiso('crm.oportunidades.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con los permisos necesarios para consultar orígenes comerciales.');
        }

        try {
            $soloActivos = isset($_GET['solo_activos']) && $_GET['solo_activos'] === '1';
            $origenes = $this->origenServicio->listarPorOrganizacion(
                (int) $contexto->organizacionId,
                $soloActivos,
                $contexto
            );

            return $this->responderJson(true, 200, 'Orígenes comerciales consultados.', [
                'total'    => count($origenes),
                'origenes' => array_map(fn($o) => $o->aArreglo(), $origenes),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/crm/origenes
     * Alta de un nuevo origen comercial.
     */
    public function crear(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.origenes.administrar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.origenes.administrar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $codigo = trim((string) ($cuerpo['codigo'] ?? ''));
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;
            $orden = isset($cuerpo['orden']) ? (int) $cuerpo['orden'] : 0;

            if ($codigo === '' || $nombre === '') {
                return $this->responderJson(false, 400, 'El código y el nombre del origen comercial son obligatorios.');
            }

            $creado = $this->origenServicio->crear(
                (int) $contexto->organizacionId,
                $codigo,
                $nombre,
                $descripcion,
                $orden,
                $contexto
            );

            return $this->responderJson(true, 201, 'Origen comercial creado exitosamente.', [
                'origen' => $creado->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/crm/origenes/{id}
     * Modifica el nombre, descripción y orden de un origen comercial (código inmutable).
     */
    public function actualizar(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.origenes.administrar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.origenes.administrar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;
            $orden = isset($cuerpo['orden']) ? (int) $cuerpo['orden'] : 0;

            if ($nombre === '') {
                return $this->responderJson(false, 400, 'El nombre del origen comercial es obligatorio.');
            }

            $actualizado = $this->origenServicio->actualizar(
                (int) $contexto->organizacionId,
                $idInt,
                $nombre,
                $descripcion,
                $orden,
                $contexto
            );

            return $this->responderJson(true, 200, 'Origen comercial actualizado exitosamente.', [
                'origen' => $actualizado->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/crm/origenes/{id}/estado
     * Activa o desactiva un origen comercial.
     */
    public function cambiarEstado(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.origenes.administrar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.origenes.administrar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $activo = !empty($cuerpo['activo']);

            if ($activo) {
                $this->origenServicio->activar((int) $contexto->organizacionId, $idInt, $contexto);
            } else {
                $this->origenServicio->desactivar((int) $contexto->organizacionId, $idInt, $contexto);
            }

            $origen = $this->origenRepo->buscarPorId($idInt);

            return $this->responderJson(true, 200, 'Estado del origen comercial actualizado.', [
                'origen' => $origen?->aArreglo(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/crm/origenes/{id}
     * Prohibición de eliminación física.
     */
    public function eliminar(string|int|array $id = 0): string
    {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'exito'   => false,
            'codigo'  => 405,
            'mensaje' => 'Método no permitido. Para proteger la integridad referencial de oportunidades históricas, los orígenes comerciales no admiten borrado físico. Utilice la desactivación.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function verificarCsrf(ContextoOperacion $contexto, array $cuerpo = []): void
    {
        $tokenCookie = ManejadorCookie::extraerDePeticion();
        if ($tokenCookie !== null) {
            $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
            $tokenRecibido = $cuerpo['_csrf_token'] ?? $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'   => false,
                    'codigo'  => 403,
                    'mensaje' => 'Token CSRF inválido o ausente. Por favor recargue la página.',
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
        }
    }

    private function obtenerCuerpo(): array
    {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $dec = json_decode($input, true);
            if (is_array($dec)) {
                return $dec;
            }
        }
        return $_POST;
    }

    private function responderJson(bool $exito, int $codigo, string $mensaje, array $datos = [], array $errores = []): string
    {
        http_response_code($codigo);
        $payload = [
            'exito'   => $exito,
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
        ];

        if (!empty($datos)) {
            $payload['datos'] = $datos;
        }

        if (!empty($errores)) {
            $payload['errores'] = $errores;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function responderErrorSeguro(Throwable $e): string
    {
        http_response_code(500);
        return json_encode([
            'exito'   => false,
            'codigo'  => 500,
            'mensaje' => 'Ha ocurrido un error interno al procesar la operación.',
            'errores' => ['codigo_referencia' => 'ERR_' . substr(md5($e->getMessage() . time()), 0, 8)]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

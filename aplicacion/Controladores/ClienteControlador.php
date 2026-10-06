<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Clientes\ClienteServicio;
use Aplicacion\Clientes\ConsentimientoServicio;
use Aplicacion\Clientes\EstadoCliente;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la gestión comercial de Clientes y CRM (F2.2C).
 */
class ClienteControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private ClienteServicio $clienteServicio;
    private ConsentimientoServicio $consentimientoServicio;
    private ClienteRepositorio $clienteRepo;
    private PersonaRepositorio $personaRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?ClienteServicio $clienteServicio = null,
        ?ConsentimientoServicio $consentimientoServicio = null,
        ?ClienteRepositorio $clienteRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->clienteRepo = $clienteRepo ?? new ClienteRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);

        $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
        $authzServicio = new AutorizacionServicio(pdo: $this->pdo);

        if ($clienteServicio !== null) {
            $this->clienteServicio = $clienteServicio;
        } else {
            $this->clienteServicio = new ClienteServicio(
                $this->clienteRepo,
                $this->personaRepo,
                $authzServicio,
                $auditoriaRepo,
                $this->pdo
            );
        }

        $this->consentimientoServicio = $consentimientoServicio ?? new ConsentimientoServicio(
            $this->clienteRepo,
            $authzServicio,
            $auditoriaRepo,
            $this->pdo
        );
    }

    /**
     * GET /clientes
     * Renderiza la vista principal del Padrón de Clientes.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Clientes',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (clientes.ver) para acceder al padrón de clientes.'
            ], 'principal');
        }

        $stmtTipos = $this->pdo->query("SELECT id, codigo, nombre FROM tipos_documento WHERE activo = 1 ORDER BY id ASC");
        $tiposDocumento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        return Vista::renderizar('paginas/clientes/index', [
            'titulo'          => 'Padrón de Clientes | CandelariaAPP',
            'subtitulo'       => 'Gestión Comercial y Cartera de Clientes',
            'tituloSeccion'   => 'Clientes',
            'seccionActiva'   => 'clientes',
            'tiposDocumento'  => $tiposDocumento,
            'scriptAdicional' => url_base('publico/js/clientes.js')
        ], 'principal');
    }

    /**
     * GET /clientes/{id}
     * Renderiza la Ficha 360 del Cliente.
     */
    public function ficha(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Ficha de Cliente',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con el privilegio clientes.ver para ver la ficha del cliente.'
            ], 'principal');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cliente = $this->clienteRepo->buscarPorId($idInt);
        if ($cliente === null || $cliente->organizacionId !== $contexto->organizacionId) {
            http_response_code(404);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Cliente No Encontrado | CandelariaAPP',
                'subtitulo'     => 'Ficha 360',
                'tituloSeccion' => 'Error 404',
                'mensaje'       => 'El cliente solicitado no existe en su organización.'
            ], 'principal');
        }

        $persona = $this->personaRepo->buscarPorId($cliente->personaId);

        return Vista::renderizar('paginas/clientes/ficha', [
            'titulo'          => 'Ficha 360: ' . ($persona?->obtenerNombreCompleto() ?? 'Cliente #' . $id),
            'subtitulo'       => 'Historial Comercial, Oportunidades e Interacciones',
            'tituloSeccion'   => 'Ficha de Cliente',
            'seccionActiva'   => 'clientes',
            'cliente'         => $cliente,
            'persona'         => $persona,
            'scriptAdicional' => url_base('publico/js/cliente_ficha.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/clientes
     * Listado asíncrono para DataTables.
     */
    public function listar(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso clientes.ver.');
        }

        try {
            $estado = isset($_GET['estado']) ? (string) $_GET['estado'] : null;
            $busqueda = isset($_GET['busqueda']) ? (string) $_GET['busqueda'] : null;
            $limite = isset($_GET['limite']) ? max(1, min(200, (int) $_GET['limite'])) : 50;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

            $total = $this->clienteRepo->contarConDetalles($contexto->organizacionId, $estado, $busqueda);
            $clientes = $this->clienteRepo->listarConDetalles($contexto->organizacionId, $estado, $busqueda, $limite, $offset);

            $formateados = array_map(function (array $c) {
                $nombreCompleto = !empty($c['razon_social'])
                    ? $c['razon_social']
                    : trim(($c['apellidos'] ?? '') . ', ' . ($c['nombres'] ?? ''));

                return [
                    'id'                          => (int) $c['cliente_id'],
                    'persona_id'                  => (int) $c['persona_id'],
                    'tipo_persona'                => $c['tipo_persona'],
                    'nombre_completo'             => $nombreCompleto,
                    'numero_documento'            => $c['numero_documento'],
                    'tipo_documento_codigo'       => $c['tipo_documento_codigo'],
                    'tipo_documento_nombre'       => $c['tipo_documento_nombre'],
                    'correo_electronico'          => $c['correo_electronico'],
                    'telefono_whatsapp'           => $c['telefono_whatsapp'],
                    'codigo_pais'                 => $c['codigo_pais'],
                    'estado_comercial'            => $c['estado_comercial'],
                    'consentimiento_operativo'    => (bool) $c['consentimiento_operativo'],
                    'consentimiento_operativo_en' => $c['consentimiento_operativo_en'],
                    'consentimiento_promocional'  => (bool) $c['consentimiento_promocional'],
                    'consentimiento_promocional_en'=> $c['consentimiento_promocional_en'],
                    'ultima_interaccion_en'       => $c['ultima_interaccion_en'],
                    'total_oportunidades'         => (int) ($c['total_oportunidades'] ?? 0),
                    'actualizado_en'              => $c['cliente_actualizado_en'],
                    'creado_en'                   => $c['cliente_creado_en'],
                ];
            }, $clientes);

            return $this->responderJson(true, 200, 'Clientes consultados exitosamente.', [
                'total'    => $total,
                'clientes' => $formateados,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/clientes/{id}
     * Detalle completo del cliente, persona y consentimientos.
     */
    public function detalle(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso clientes.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $cliente = $this->clienteRepo->buscarPorId($idInt);
            if ($cliente === null || $cliente->organizacionId !== $contexto->organizacionId) {
                return $this->responderJson(false, 404, 'Cliente no encontrado en esta organización.');
            }

            $persona = $this->personaRepo->buscarPorId($cliente->personaId);
            $consentimientosHistorial = $this->consentimientoServicio->obtenerHistorial($contexto->organizacionId, $idInt, $contexto);

            return $this->responderJson(true, 200, 'Detalle del cliente.', [
                'cliente' => $cliente->aArreglo(),
                'persona' => $persona ? $persona->aArreglo() : null,
                'consentimientos_historial' => $consentimientosHistorial
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/personas/buscar
     * Búsqueda flexible de personas (para modal de nuevo cliente o Select2).
     */
    public function buscarPersonas(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)
            && !$this->authzMiddleware->verificarPermiso('clientes.crear', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con privilegios para buscar personas.');
        }

        try {
            $termino = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
            if (strlen($termino) < 2) {
                return $this->responderJson(true, 200, 'Término muy corto.', ['personas' => []]);
            }

            $limite = isset($_GET['limite']) ? max(1, min(50, (int) $_GET['limite'])) : 20;
            $personas = $this->personaRepo->buscarPorTermino($contexto->organizacionId, $termino, $limite);

            return $this->responderJson(true, 200, 'Búsqueda completada.', [
                'personas' => $personas,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/clientes
     * Alta de cliente (vinculando persona existente o registrando persona nueva).
     */
    public function crear(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.crear', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso clientes.crear.');
        }

        $cuerpo = json_decode(file_get_contents('php://input'), true);
        if (!is_array($cuerpo)) {
            $cuerpo = $_POST;
        }

        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $personaId = isset($cuerpo['persona_id']) && (int) $cuerpo['persona_id'] > 0
                ? (int) $cuerpo['persona_id']
                : null;

            if ($personaId !== null) {
                // Caso A: Incorporar persona existente como cliente
                $datosComerciales = [
                    'telefono_whatsapp' => $cuerpo['telefono_whatsapp'] ?? null,
                    'estado_comercial'  => $cuerpo['estado_comercial'] ?? 'PROSPECTO',
                    'notas_comerciales' => $cuerpo['notas_comerciales'] ?? null,
                ];
                $cliente = $this->clienteServicio->crearDesdePersona(
                    $contexto->organizacionId,
                    $personaId,
                    $datosComerciales,
                    $contexto
                );
            } else {
                // Caso B: Registrar nueva Persona y vincular como Cliente
                $datosPersona = [
                    'tipo_persona'       => $cuerpo['tipo_persona'] ?? 'NATURAL',
                    'tipo_documento_id'  => !empty($cuerpo['tipo_documento_id']) ? (int) $cuerpo['tipo_documento_id'] : null,
                    'numero_documento'   => $cuerpo['numero_documento'] ?? null,
                    'nombres'            => $cuerpo['nombres'] ?? null,
                    'apellidos'          => $cuerpo['apellidos'] ?? null,
                    'razon_social'       => $cuerpo['razon_social'] ?? null,
                    'nombre_comercial'   => $cuerpo['nombre_comercial'] ?? null,
                    'correo_electronico' => $cuerpo['correo_electronico'] ?? null,
                    'telefono_movil'     => $cuerpo['telefono_movil'] ?? null,
                    'telefono_whatsapp'  => $cuerpo['telefono_whatsapp'] ?? null,
                    'direccion'          => $cuerpo['direccion'] ?? null,
                    'ciudad'             => $cuerpo['ciudad'] ?? null,
                    'codigo_pais'        => $cuerpo['codigo_pais'] ?? 'PE',
                ];

                $datosComerciales = [
                    'estado_comercial'  => $cuerpo['estado_comercial'] ?? 'PROSPECTO',
                    'notas_comerciales' => $cuerpo['notas_comerciales'] ?? null,
                ];

                $cliente = $this->clienteServicio->crearPersonaYCliente(
                    $contexto->organizacionId,
                    $datosPersona,
                    $datosComerciales,
                    $contexto
                );
            }

            return $this->responderJson(true, 201, 'Cliente incorporado exitosamente al ciclo comercial.', [
                'cliente' => $cliente->aArreglo(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/clientes/{id}
     * Actualiza notas y datos de contacto del cliente.
     */
    public function actualizar(array $params = []): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso clientes.editar.');
        }

        $cuerpo = json_decode(file_get_contents('php://input'), true);
        if (!is_array($cuerpo)) {
            $cuerpo = $_POST;
        }

        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $cliente = $this->clienteRepo->buscarPorId($id);
            if ($cliente === null || $cliente->organizacionId !== $contexto->organizacionId) {
                return $this->responderJson(false, 404, 'Cliente no encontrado.');
            }

            $datosActualizacion = [
                'notas_comerciales'  => $cuerpo['notas_comerciales'] ?? $cliente->notasComerciales,
                'telefono_whatsapp'  => $cuerpo['telefono_whatsapp'] ?? null,
                'correo_electronico' => $cuerpo['correo_electronico'] ?? null,
                'telefono_movil'     => $cuerpo['telefono_movil'] ?? null,
                'direccion'          => $cuerpo['direccion'] ?? null,
                'ciudad'             => $cuerpo['ciudad'] ?? null,
            ];

            $clienteActualizado = $this->clienteServicio->actualizar(
                $contexto->organizacionId,
                $id,
                $datosActualizacion,
                $contexto
            );

            return $this->responderJson(true, 200, 'Datos comerciales del cliente actualizados.', [
                'cliente' => $clienteActualizado->aArreglo(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/clientes/{id}/estado
     * Transición gobernada de estado comercial.
     */
    public function cambiarEstado(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        $cuerpo = json_decode(file_get_contents('php://input'), true);
        if (!is_array($cuerpo)) {
            $cuerpo = $_POST;
        }

        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        $nuevoEstadoStr = (string) ($cuerpo['estado_comercial'] ?? '');
        $permisoRequerido = ($nuevoEstadoStr === 'INACTIVO') ? 'clientes.desactivar' : 'clientes.editar';

        if (!$this->authzMiddleware->verificarPermiso($permisoRequerido, $contexto, false)) {
            return $this->responderJson(false, 403, "No cuenta con el privilegio requerido ({$permisoRequerido}).");
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $cliente = $this->clienteRepo->buscarPorId($idInt);
            if ($cliente === null || $cliente->organizacionId !== $contexto->organizacionId) {
                return $this->responderJson(false, 404, 'Cliente no encontrado.');
            }

            $nuevoEstado = EstadoCliente::desdeCadena($nuevoEstadoStr);
            $motivo = isset($cuerpo['motivo']) ? (string) $cuerpo['motivo'] : null;
            $clienteActualizado = $this->clienteServicio->cambiarEstadoComercial(
                $contexto->organizacionId,
                $idInt,
                $nuevoEstadoStr,
                $motivo,
                $contexto
            );

            return $this->responderJson(true, 200, "Estado comercial actualizado a '{$nuevoEstado->value}'.", [
                'cliente' => $clienteActualizado->aArreglo(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/clientes/{id}/consentimientos
     * Registro con trazabilidad de consentimiento operativo o promocional.
     */
    public function actualizarConsentimientos(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('clientes.editar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso clientes.editar.');
        }

        $cuerpo = json_decode(file_get_contents('php://input'), true);
        if (!is_array($cuerpo)) {
            $cuerpo = $_POST;
        }

        $errorCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errorCsrf !== null) {
            return $errorCsrf;
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $tipoConsentimiento = (string) ($cuerpo['tipo'] ?? ''); // 'OPERATIVO' o 'PROMOCIONAL'
            $otorgado = (bool) ($cuerpo['otorgado'] ?? false);
            $medio = (string) ($cuerpo['medio'] ?? 'WEB_FORM');
            $evidencia = (string) ($cuerpo['evidencia'] ?? 'Panel administrativo Alina');

            if ($otorgado) {
                $this->consentimientoServicio->otorgar(
                    organizacionId: $contexto->organizacionId,
                    clienteId: $idInt,
                    tipo: $tipoConsentimiento,
                    canal: $medio,
                    motivo: $evidencia,
                    contexto: $contexto
                );
            } else {
                $this->consentimientoServicio->revocar(
                    organizacionId: $contexto->organizacionId,
                    clienteId: $idInt,
                    tipo: $tipoConsentimiento,
                    canal: $medio,
                    motivo: $evidencia,
                    contexto: $contexto
                );
            }

            $clienteActualizado = $this->clienteRepo->buscarPorId($idInt);

            return $this->responderJson(true, 200, 'Consentimiento registrado con pista de trazabilidad inmutable.', [
                'cliente' => $clienteActualizado ? $clienteActualizado->aArreglo() : null,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/clientes/{id}
     * Rechazo categórico de eliminación física (Gobernanza inmutable).
     */
    public function eliminar(): string
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);

        return json_encode([
            'exito'   => false,
            'codigo'  => 405,
            'mensaje' => 'Método no permitido. La eliminación física de clientes está prohibida por la gobernanza de datos. Utilice la desactivación comercial.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function verificarCsrf(ContextoOperacion $contexto, array $cuerpo = []): ?string
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        if ($tokenEsperado !== null) {
            $tokenRecibido = $cuerpo['_csrf_token'] ?? $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
                return $this->responderJson(false, 403, 'Token CSRF inválido o ausente.');
            }
        }
        return null;
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

<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Crm\InteraccionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ManejadorCookie;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador oficial para la gestión y registro de Interacciones Comerciales (F2.2C).
 */
class InteraccionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private InteraccionServicio $interaccionServicio;
    private InteraccionCrmRepositorio $interaccionRepo;
    private HistorialEtapaRepositorio $historialRepo;
    private ClienteRepositorio $clienteRepo;
    private OportunidadRepositorio $oportunidadRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?InteraccionServicio $interaccionServicio = null,
        ?InteraccionCrmRepositorio $interaccionRepo = null,
        ?HistorialEtapaRepositorio $historialRepo = null,
        ?ClienteRepositorio $clienteRepo = null,
        ?OportunidadRepositorio $oportunidadRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->interaccionRepo = $interaccionRepo ?? new InteraccionCrmRepositorio($this->pdo);
        $this->historialRepo = $historialRepo ?? new HistorialEtapaRepositorio($this->pdo);
        $this->clienteRepo = $clienteRepo ?? new ClienteRepositorio($this->pdo);
        $this->oportunidadRepo = $oportunidadRepo ?? new OportunidadRepositorio($this->pdo);

        if ($interaccionServicio !== null) {
            $this->interaccionServicio = $interaccionServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $this->pdo);

            $this->interaccionServicio = new InteraccionServicio(
                $this->interaccionRepo,
                $this->clienteRepo,
                $this->oportunidadRepo,
                $authzServicio,
                $auditoriaRepo,
                $this->pdo
            );
        }
    }

    /**
     * GET /api/v1/crm/interacciones
     * Listado con detalles de interacciones (por cliente y opcionalmente oportunidad).
     */
    public function listar(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.interacciones.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.interacciones.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $clienteId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id'])
                ? (int) $_GET['cliente_id']
                : null;

            if ($clienteId === null || $clienteId <= 0) {
                return $this->responderJson(false, 400, 'Debe especificar el parámetro cliente_id.');
            }

            // Validar Anti-IDOR
            $cliente = $this->clienteRepo->buscarPorId($clienteId);
            if ($cliente === null || $cliente->organizacionId !== $orgId) {
                return $this->responderJson(false, 404, 'Cliente no encontrado en su organización.');
            }

            $oportunidadId = isset($_GET['oportunidad_id']) && is_numeric($_GET['oportunidad_id'])
                ? (int) $_GET['oportunidad_id']
                : null;

            $limite = isset($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 50;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

            $interacciones = $this->interaccionRepo->listarConDetalles($orgId, $clienteId, $oportunidadId, $limite, $offset);

            return $this->responderJson(true, 200, 'Interacciones consultadas exitosamente.', [
                'total'         => count($interacciones),
                'interacciones' => $interacciones,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/crm/interacciones
     * Registra una nueva interacción comercial append-only.
     */
    public function crear(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.interacciones.crear', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso crm.interacciones.crear.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $this->verificarCsrf($contexto, $cuerpo);

        try {
            $clienteId = isset($cuerpo['cliente_id']) ? (int) $cuerpo['cliente_id'] : 0;
            if ($clienteId <= 0) {
                return $this->responderJson(false, 400, 'Debe especificar un cliente válido.');
            }

            $oportunidadId = isset($cuerpo['oportunidad_id']) && (int) $cuerpo['oportunidad_id'] > 0
                ? (int) $cuerpo['oportunidad_id']
                : null;

            $canalId = isset($cuerpo['canal_id']) ? (int) $cuerpo['canal_id'] : 0;
            if ($canalId <= 0) {
                return $this->responderJson(false, 400, 'Debe especificar el canal de comunicación.');
            }

            $tipo = trim((string) ($cuerpo['tipo'] ?? ''));
            if ($tipo === '') {
                return $this->responderJson(false, 400, 'Debe especificar el tipo de interacción.');
            }

            $direccion = trim((string) ($cuerpo['direccion'] ?? ''));
            if ($direccion === '') {
                return $this->responderJson(false, 400, 'Debe especificar la dirección de la interacción (ENTRANTE/SALIENTE/INTERNA).');
            }

            $resumen = trim((string) ($cuerpo['resumen'] ?? ''));
            if ($resumen === '') {
                return $this->responderJson(false, 400, 'El resumen de la interacción es obligatorio.');
            }

            $detalle = !empty($cuerpo['detalle']) ? trim((string) $cuerpo['detalle']) : null;

            $interaccion = $this->interaccionServicio->registrar(
                (int) $contexto->organizacionId,
                $clienteId,
                $oportunidadId,
                $canalId,
                $tipo,
                $direccion,
                $resumen,
                $detalle,
                $contexto
            );

            return $this->responderJson(true, 201, 'Interacción registrada exitosamente.', [
                'interaccion' => $interaccion->aArreglo(),
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
     * GET /api/v1/crm/clientes/{id}/timeline
     * Timeline unificado del cliente (interacciones + cambios de etapa de sus oportunidades).
     */
    public function timeline(string|int|array $id = 0): string
    {
        header('Content-Type: application/json; charset=utf-8');

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('crm.interacciones.ver', $contexto, false)
            && !$this->authzMiddleware->verificarPermiso('clientes.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con los permisos necesarios para consultar el timeline.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $clienteId = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;

            $cliente = $this->clienteRepo->buscarPorId($clienteId);
            if ($cliente === null || $cliente->organizacionId !== $orgId) {
                return $this->responderJson(false, 404, 'Cliente no encontrado en su organización.');
            }

            // 1. Obtener todas las interacciones del cliente
            $interacciones = $this->interaccionRepo->listarConDetalles($orgId, $clienteId, null, 100);

            // 2. Obtener oportunidades del cliente para extraer su historial de etapas
            $oportunidades = $this->oportunidadRepo->listarPorCliente($orgId, $clienteId);

            $eventos = [];

            foreach ($interacciones as $it) {
                $eventos[] = [
                    'tipo_evento'     => 'INTERACCION',
                    'id'              => (int) $it['id'],
                    'fecha'           => $it['creado_en'],
                    'titulo'          => $it['resumen'],
                    'subtitulo'       => ($it['canal_nombre'] ?? 'Canal') . ' · ' . $it['tipo'] . ' (' . $it['direccion'] . ')',
                    'descripcion'     => $it['detalle'],
                    'autor'           => $it['usuario_nombre_completo'] ?? $it['actor_tipo'],
                    'oportunidad_id'  => $it['oportunidad_id'] ? (int) $it['oportunidad_id'] : null,
                    'icono'           => match ($it['tipo']) {
                        'LLAMADA'   => 'fa-phone',
                        'WHATSAPP'  => 'fa-brands fa-whatsapp',
                        'CORREO'    => 'fa-envelope',
                        'REUNION'   => 'fa-handshake',
                        'NOTA'      => 'fa-sticky-note',
                        default     => 'fa-comment-dots',
                    },
                    'color_clase'     => match ($it['tipo']) {
                        'LLAMADA'   => 'primary',
                        'WHATSAPP'  => 'success',
                        'CORREO'    => 'info',
                        'REUNION'   => 'warning',
                        'NOTA'      => 'secondary',
                        default     => 'primary',
                    },
                ];
            }

            foreach ($oportunidades as $op) {
                $historial = $this->historialRepo->listarConDetalles($orgId, (int) $op->id);
                foreach ($historial as $h) {
                    $tituloEtapa = $h['etapa_anterior'] !== null
                        ? "Transición: {$h['etapa_anterior']} → {$h['etapa_nueva']}"
                        : "Apertura en {$h['etapa_nueva']}";

                    $eventos[] = [
                        'tipo_evento'     => 'CAMBIO_ETAPA',
                        'id'              => (int) $h['id'],
                        'fecha'           => $h['creado_en'],
                        'titulo'          => "Oportunidad: {$op->titulo}",
                        'subtitulo'       => $tituloEtapa,
                        'descripcion'     => $h['motivo'],
                        'autor'           => $h['usuario_nombre_completo'] ?? $h['actor_tipo'],
                        'oportunidad_id'  => (int) $op->id,
                        'icono'           => match ($h['etapa_nueva']) {
                            'GANADA'  => 'fa-trophy',
                            'PERDIDA' => 'fa-circle-xmark',
                            default   => 'fa-arrow-right-arrow-left',
                        },
                        'color_clase'     => match ($h['etapa_nueva']) {
                            'GANADA'  => 'success',
                            'PERDIDA' => 'danger',
                            default   => 'warning',
                        },
                    ];
                }
            }

            // Ordenar descendentemente por fecha
            usort($eventos, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

            return $this->responderJson(true, 200, 'Timeline comercial obtenido.', [
                'total'   => count($eventos),
                'eventos' => $eventos,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * DELETE /api/v1/crm/interacciones/{id}
     * Prohibición absoluta de borrado físico.
     */
    public function eliminar(string|int|array $id = 0): string
    {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'exito'   => false,
            'codigo'  => 405,
            'mensaje' => 'Método no permitido. Las interacciones comerciales son registros append-only inmutables y no admiten borrado físico.',
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

<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use Throwable;

/**
 * Controlador API para la gestión de Configuración General y Parámetros Operativos (F1.2C).
 * Gobernanza y Soberanía:
 * - Separación de ámbitos PLATAFORMA vs ORGANIZACION.
 * - Validación fail-closed de pertenencia y contexto organizacional.
 * - Concurrencia optimista ligera basada en timestamps (HTTP 409 Conflict).
 * - Protección contra inyección de secretos e inmutabilidad de parámetros de infraestructura.
 * - Pista de auditoría inmutable en auditoria_operaciones.
 */
class ConfiguracionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private ConfiguracionServicio $configServicio;
    private AutorizacionServicio $authzServicio;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?ConfiguracionServicio $configServicio = null,
        ?AutorizacionServicio $authzServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->authzServicio = $authzServicio ?? new AutorizacionServicio(pdo: $this->pdo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio(
            configRepo: new ConfiguracionRepositorio($this->pdo),
            orgRepo: new OrganizacionRepositorio($this->pdo),
            authzServicio: $this->authzServicio,
            auditoriaRepo: new AuditoriaRepositorio($this->pdo),
            pdo: $this->pdo
        );
    }

    /**
     * GET /api/v1/configuracion
     * Lista los parámetros de configuración accesibles según los permisos del operador.
     */
    public function listar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            return $this->responderJson(false, 401, 'No autenticado: requiere una sesión activa.');
        }

        $puedeOrg = $this->authzMiddleware->verificarPermiso('configuracion_organizacion.ver', $contexto, false);
        $puedePlat = $this->authzMiddleware->verificarPermiso('configuracion_plataforma.ver', $contexto, false);

        if (!$puedeOrg && !$puedePlat) {
            return $this->responderJson(false, 403, 'Acceso denegado: no cuenta con privilegios para consultar configuraciones.');
        }

        $filtroAmbito = isset($_GET['ambito']) ? strtoupper(trim((string) $_GET['ambito'])) : null;

        if ($filtroAmbito !== null && !in_array($filtroAmbito, ['PLATAFORMA', 'ORGANIZACION'], true)) {
            return $this->responderJson(false, 400, "Ámbito solicitado inválido: '{$filtroAmbito}'. Debe ser PLATAFORMA u ORGANIZACION.");
        }

        // Si solicitó explícitamente PLATAFORMA sin tener permiso:
        if ($filtroAmbito === 'PLATAFORMA' && !$puedePlat) {
            return $this->responderJson(false, 403, "Acceso denegado: requiere el permiso 'configuracion_plataforma.ver'.");
        }

        // Si solicitó explícitamente ORGANIZACION sin tener permiso:
        if ($filtroAmbito === 'ORGANIZACION' && !$puedeOrg) {
            return $this->responderJson(false, 403, "Acceso denegado: requiere el permiso 'configuracion_organizacion.ver'.");
        }

        $itemsOrg = [];
        if ($puedeOrg && ($filtroAmbito === null || $filtroAmbito === 'ORGANIZACION')) {
            try {
                $orgId = $this->resolverOrganizacionId($contexto);
                $paramsOrg = $this->configServicio->listarOrganizacion($orgId, $contexto->usuarioId);
                $itemsOrg = array_map(fn($p) => $p->aArreglo(), $paramsOrg);
            } catch (AccesoDenegadoExcepcion $e) {
                if ($filtroAmbito === 'ORGANIZACION') {
                    return $this->responderJson(false, 403, $e->getMessage());
                }
                // Si fue consulta global, omitir org si no hay tenant
                $itemsOrg = [];
            }
        }

        $itemsPlat = [];
        if ($puedePlat && ($filtroAmbito === null || $filtroAmbito === 'PLATAFORMA')) {
            try {
                $paramsPlat = $this->configServicio->listarPlataforma($contexto->usuarioId);
                $itemsPlat = array_map(fn($p) => $p->aArreglo(), $paramsPlat);
            } catch (AccesoDenegadoExcepcion $e) {
                if ($filtroAmbito === 'PLATAFORMA') {
                    return $this->responderJson(false, 403, $e->getMessage());
                }
                $itemsPlat = [];
            }
        }

        $puedeEditarOrg = $this->authzMiddleware->verificarPermiso('configuracion_organizacion.editar', $contexto, false);
        $puedeEditarPlat = $this->authzMiddleware->verificarPermiso('configuracion_plataforma.editar', $contexto, false)
            && $this->authzServicio->esSuperadmin($contexto->usuarioId);

        return $this->responderJson(true, 200, 'Configuración recuperada exitosamente.', [
            'organizacion' => $itemsOrg,
            'plataforma'   => $itemsPlat,
            'permisos'     => [
                'puede_ver_organizacion'    => $puedeOrg,
                'puede_editar_organizacion' => $puedeEditarOrg,
                'puede_ver_plataforma'      => $puedePlat,
                'puede_editar_plataforma'   => $puedeEditarPlat,
            ],
        ]);
    }

    /**
     * PUT /api/v1/configuracion
     * Actualiza un parámetro individual o un lote de parámetros dentro de un ámbito gobernado.
     */
    public function actualizar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            return $this->responderJson(false, 401, 'No autenticado: requiere una sesión activa.');
        }

        // Validación estricta CSRF
        try {
            $this->validarCsrf($contexto);
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        }

        $cuerpo = $this->obtenerCuerpoPeticion();
        $ambito = isset($cuerpo['ambito']) ? strtoupper(trim((string) $cuerpo['ambito'])) : '';

        if (!in_array($ambito, ['PLATAFORMA', 'ORGANIZACION'], true)) {
            return $this->responderJson(false, 400, "Debe especificar un ámbito válido ('PLATAFORMA' u 'ORGANIZACION').");
        }

        // Determinar lista de parámetros a actualizar (individual o lote)
        $items = [];
        if (isset($cuerpo['parametros']) && is_array($cuerpo['parametros'])) {
            $items = $cuerpo['parametros'];
        } elseif (isset($cuerpo['codigo'])) {
            $items[] = [
                'codigo'         => (string) $cuerpo['codigo'],
                'valor'          => $cuerpo['valor'] ?? null,
                'actualizado_en' => $cuerpo['actualizado_en'] ?? null,
            ];
        }

        if (empty($items)) {
            return $this->responderJson(false, 400, 'No se proporcionaron parámetros para actualizar.');
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            if ($ambito === 'ORGANIZACION') {
                if (!$this->authzMiddleware->verificarPermiso('configuracion_organizacion.editar', $contexto, false)) {
                    throw new AccesoDenegadoExcepcion("Acceso denegado: requiere el permiso 'configuracion_organizacion.editar'.", 'configuracion_organizacion.editar', 403);
                }

                $orgId = $this->resolverOrganizacionId($contexto);

                foreach ($items as $item) {
                    $codigo = trim((string) ($item['codigo'] ?? ''));
                    if ($codigo === '') {
                        throw new InvalidArgumentException('El código del parámetro no puede estar vacío.');
                    }
                    $valor = $item['valor'] ?? null;
                    $actualizadoEn = isset($item['actualizado_en']) ? (string) $item['actualizado_en'] : null;

                    $this->configServicio->actualizarOrganizacion(
                        organizacionId: $orgId,
                        codigo: $codigo,
                        nuevoValor: $valor,
                        operadorId: $contexto->usuarioId,
                        contexto: $contexto,
                        actualizadoEnEsperado: $actualizadoEn
                    );
                }
            } else {
                // Ámbito PLATAFORMA
                if (!$this->authzMiddleware->verificarPermiso('configuracion_plataforma.editar', $contexto, false)
                    || !$this->authzServicio->esSuperadmin($contexto->usuarioId)) {
                    throw new AccesoDenegadoExcepcion('Acceso denegado: solo el Superadministrador de Plataforma puede modificar parámetros soberanos.', null, 403);
                }

                foreach ($items as $item) {
                    $codigo = trim((string) ($item['codigo'] ?? ''));
                    if ($codigo === '') {
                        throw new InvalidArgumentException('El código del parámetro no puede estar vacío.');
                    }
                    $valor = $item['valor'] ?? null;
                    $actualizadoEn = isset($item['actualizado_en']) ? (string) $item['actualizado_en'] : null;

                    $this->configServicio->actualizarPlataforma(
                        codigo: $codigo,
                        nuevoValor: $valor,
                        operadorId: $contexto->usuarioId,
                        contexto: $contexto,
                        actualizadoEnEsperado: $actualizadoEn
                    );
                }
            }

            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $this->responderJson(true, 200, 'Configuración actualizada exitosamente.');
        } catch (AccesoDenegadoExcepcion $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 422, $e->getMessage());
        } catch (Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 500, 'Error interno del servidor al actualizar la configuración.');
        }
    }

    /**
     * Resuelve el ID de la organización desde el contexto autenticado.
     * Fail-closed: 403 estricto si no existe contexto organizacional.
     */
    private function resolverOrganizacionId(ContextoOperacion $contexto): int
    {
        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new AccesoDenegadoExcepcion('Contexto organizacional ausente o inválido para la sesión activa.');
        }

        return $contexto->organizacionId;
    }

    /**
     * Valida el token CSRF para operaciones mutables (PUT, POST).
     */
    private function validarCsrf(ContextoOperacion $contexto): void
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        $cuerpo = $this->obtenerCuerpoPeticion();
        $post = !empty($cuerpo) ? array_merge($_POST, $cuerpo) : $_POST;

        $tokenRecibido = ProtectorCsrf::extraerTokenDePeticion($post, $_SERVER);

        if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new InvalidArgumentException('Token CSRF inválido o ausente. Actualice la página e intente nuevamente.');
        }
    }

    /**
     * Obtiene y decodifica el cuerpo de la petición (JSON o form-data).
     */
    private function obtenerCuerpoPeticion(): array
    {
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $decodificado = json_decode($json, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return $_POST;
    }

    /**
     * Genera respuestas JSON consistentes con encabezados apropiados.
     */
    private function responderJson(bool $exito, int $codigoHttp, string $mensaje, mixed $datos = null): string
    {
        if (!headers_sent()) {
            http_response_code($codigoHttp);
            header('Content-Type: application/json; charset=utf-8');
        }

        return json_encode([
            'exito'   => $exito,
            'codigo'  => $codigoHttp,
            'mensaje' => $mensaje,
            'datos'   => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

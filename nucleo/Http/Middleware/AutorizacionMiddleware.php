<?php

declare(strict_types=1);

namespace Nucleo\Http\Middleware;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Nucleo\Http\ContextoOperacion;

/**
 * Middleware de Autorización RBAC de CandelariaAPP.
 * Intercepta peticiones para comprobar permisos granulares en backend.
 * Devuelve 401 si no hay autenticación, 403 si carece de permiso, o permite la ejecución.
 */
class AutorizacionMiddleware
{
    private AutorizacionServicio $autorizacionServicio;

    public function __construct(?AutorizacionServicio $autorizacionServicio = null)
    {
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
    }

    /**
     * Verifica que la solicitud actual cuente con un permiso específico.
     */
    public function verificarPermiso(
        string $codigoPermiso,
        ?ContextoOperacion $contexto = null,
        bool $bloquearPeticion = true
    ): bool {
        $contexto = $contexto ?? ContextoOperacion::actual();

        // 1. Sin autenticación -> HTTP 401
        if ($contexto === null || $contexto->actorTipo !== 'HUMANO' || $contexto->usuarioId === null) {
            if ($bloquearPeticion) {
                $this->denegarAcceso(401, 'No autenticado: debe iniciar sesión para acceder a este recurso.');
            }
            return false;
        }

        // 2. Comprobar privilegio en backend
        $tieneAcceso = $this->autorizacionServicio->tienePermiso($contexto->usuarioId, $codigoPermiso);

        if (!$tieneAcceso) {
            if ($bloquearPeticion) {
                // Registrar intento de acceso indebido
                try {
                    $this->autorizacionServicio->autorizar($contexto->usuarioId, $codigoPermiso, $contexto);
                } catch (AccesoDenegadoExcepcion) {
                    // Esperado
                }

                $this->denegarAcceso(403, "Acceso denegado: no cuenta con el permiso requerido '{$codigoPermiso}'.");
            }
            return false;
        }

        return true;
    }

    /**
     * Verifica que la solicitud cuente con al menos uno de los permisos provistos.
     */
    public function verificarCualquierPermiso(
        array $codigosPermisos,
        ?ContextoOperacion $contexto = null,
        bool $bloquearPeticion = true
    ): bool {
        $contexto = $contexto ?? ContextoOperacion::actual();

        if ($contexto === null || $contexto->actorTipo !== 'HUMANO' || $contexto->usuarioId === null) {
            if ($bloquearPeticion) {
                $this->denegarAcceso(401, 'No autenticado: debe iniciar sesión para acceder a este recurso.');
            }
            return false;
        }

        $tieneAcceso = $this->autorizacionServicio->tieneCualquierPermiso($contexto->usuarioId, $codigosPermisos);

        if (!$tieneAcceso) {
            if ($bloquearPeticion) {
                $permisosStr = implode(', ', $codigosPermisos);
                $this->denegarAcceso(403, "Acceso denegado: se requiere al menos uno de los siguientes permisos: [{$permisosStr}].");
            }
            return false;
        }

        return true;
    }

    /**
     * Verifica que la solicitud pertenezca a un usuario con un rol específico.
     */
    public function verificarRol(
        string $codigoRol,
        ?ContextoOperacion $contexto = null,
        bool $bloquearPeticion = true
    ): bool {
        $contexto = $contexto ?? ContextoOperacion::actual();

        if ($contexto === null || $contexto->actorTipo !== 'HUMANO' || $contexto->usuarioId === null) {
            if ($bloquearPeticion) {
                $this->denegarAcceso(401, 'No autenticado: debe iniciar sesión para acceder a este recurso.');
            }
            return false;
        }

        $tieneRol = $this->autorizacionServicio->tieneRol($contexto->usuarioId, $codigoRol);

        if (!$tieneRol) {
            if ($bloquearPeticion) {
                $this->denegarAcceso(403, "Acceso denegado: se requiere el rol '{$codigoRol}'.");
            }
            return false;
        }

        return true;
    }

    /**
     * Comprueba si el usuario es superadministrador.
     */
    public function esSuperadmin(int $usuarioId): bool
    {
        return $this->autorizacionServicio->esSuperadmin($usuarioId);
    }

    /**
     * Valida si un operador puede asignar la lista de roles especificada.
     * @param int[] $rolesIds
     */
    public function puedeAsignarRoles(int $operadorId, array $rolesIds): bool
    {
        return $this->autorizacionServicio->puedeAsignarRoles($operadorId, $rolesIds);
    }

    /**
     * Verifica si el operador tiene alcance sobre los recursos de una organización (anti-IDOR).
     */
    public function verificarAlcanceOrganizacion(int $operadorId, int $recursoOrganizacionId): bool
    {
        return $this->autorizacionServicio->verificarAlcanceOrganizacion($operadorId, $recursoOrganizacionId);
    }

    /**
     * Retorna los roles asignables por el operador en sesión.
     * @return \Aplicacion\Entidades\Rol[]
     */
    public function obtenerRolesAsignables(int $operadorId, ?int $organizacionId = null): array
    {
        return $this->autorizacionServicio->obtenerRolesAsignables($operadorId, $organizacionId);
    }

    public function obtenerServicio(): AutorizacionServicio
    {
        return $this->autorizacionServicio;
    }

    /**
     * Emite la respuesta de error HTTP uniforme y detiene la ejecución si aplica.
     */
    private function denegarAcceso(int $codigoHttp, string $mensaje): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $esApi = str_contains($uri, '/api') || str_contains($accept, 'application/json');

        if (!headers_sent()) {
            http_response_code($codigoHttp);
            if ($esApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'exito'   => false,
                    'codigo'  => $codigoHttp,
                    'mensaje' => $mensaje,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            exit;
        }
    }
}

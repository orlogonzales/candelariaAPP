<?php

declare(strict_types=1);

namespace Aplicacion\Autorizacion;

use Aplicacion\Entidades\Rol;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio Central de Autorización RBAC de CandelariaAPP.
 * Evalúa privilegios en backend garantizando que ni el frontend ni parámetros de cliente
 * puedan omitir las políticas de seguridad.
 */
class AutorizacionServicio
{
    private PDO $pdo;
    private RolRepositorio $rolRepo;
    private PermisoRepositorio $permisoRepo;
    private UsuarioRepositorio $usuarioRepo;
    private AuditoriaRepositorio $auditoriaRepo;

    public function __construct(
        ?RolRepositorio $rolRepo = null,
        ?PermisoRepositorio $permisoRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->pdo);
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
    }

    /**
     * Determina si un usuario tiene autorización para ejecutar una acción protegida por un permiso.
     */
    public function tienePermiso(int $usuarioId, string $codigoPermiso): bool
    {
        $codigoPermiso = trim($codigoPermiso);
        if ($codigoPermiso === '') {
            return false;
        }

        // 1. Validar existencia y vigencia de la cuenta de usuario en base de datos
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
        if ($usuario === null || $usuario->estado !== 'ACTIVO' || $usuario->estaBloqueado()) {
            return false;
        }

        // 2. Comprobar si ostenta rango de Superadministrador de Plataforma
        if ($this->esSuperadmin($usuarioId)) {
            return true;
        }

        // 3. Evaluar asignación a través de la matriz de roles y permisos
        return $this->permisoRepo->usuarioTienePermiso($usuarioId, $codigoPermiso);
    }

    /**
     * Comprueba si el usuario ostenta el rango supremo de Superadministrador de Plataforma.
     */
    public function esSuperadmin(int $usuarioId): bool
    {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
        if ($usuario === null || $usuario->estado !== 'ACTIVO' || $usuario->estaBloqueado()) {
            return false;
        }

        return (bool) $usuario->esSuperadminPlataforma || $this->rolRepo->usuarioTieneRol($usuarioId, 'superadmin_plataforma');
    }

    /**
     * Valida si un operador tiene autorización para asignar los roles indicados.
     * H-02: Ningún operador que no sea superadministrador puede asignar 'superadmin_plataforma'.
     *
     * @param int[] $rolesIds
     */
    public function puedeAsignarRoles(int $operadorId, array $rolesIds): bool
    {
        if (empty($rolesIds)) {
            return true;
        }

        $esSuperadmin = $this->esSuperadmin($operadorId);

        foreach ($rolesIds as $rId) {
            $rol = $this->rolRepo->buscarPorId((int) $rId);
            if ($rol !== null && $rol->codigo === 'superadmin_plataforma') {
                if (!$esSuperadmin) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Valida el aislamiento multi-tenant IDOR.
     * H-03: Solo un superadministrador puede operar de forma cross-tenant.
     * Los operadores estándar solo pueden operar sobre recursos de su misma organización.
     */
    public function verificarAlcanceOrganizacion(int $operadorId, int $recursoOrganizacionId): bool
    {
        if ($this->esSuperadmin($operadorId)) {
            return true;
        }

        $operador = $this->usuarioRepo->buscarPorId($operadorId);
        if ($operador === null || $operador->estado !== 'ACTIVO' || $operador->estaBloqueado()) {
            return false;
        }

        return $operador->organizacionId === $recursoOrganizacionId;
    }

    /**
     * Retorna los roles que pueden ser visualizados/asignados por el operador.
     * H-04: Si el operador no es superadmin, se oculta 'superadmin_plataforma'.
     *
     * @return Rol[]
     */
    public function obtenerRolesAsignables(int $operadorId, ?int $organizacionId = null): array
    {
        $roles = $this->rolRepo->obtenerTodos($organizacionId);
        if ($this->esSuperadmin($operadorId)) {
            return $roles;
        }

        return array_values(array_filter($roles, fn(Rol $r) => $r->codigo !== 'superadmin_plataforma'));
    }

    /**
     * Comprueba si el usuario cuenta con al menos uno de los permisos provistos.
     * @param string[] $codigosPermisos
     */
    public function tieneCualquierPermiso(int $usuarioId, array $codigosPermisos): bool
    {
        foreach ($codigosPermisos as $permiso) {
            if ($this->tienePermiso($usuarioId, (string) $permiso)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Comprueba si el usuario cuenta con la totalidad de los permisos provistos.
     * @param string[] $codigosPermisos
     */
    public function tieneTodosLosPermisos(int $usuarioId, array $codigosPermisos): bool
    {
        if (empty($codigosPermisos)) {
            return false;
        }

        foreach ($codigosPermisos as $permiso) {
            if (!$this->tienePermiso($usuarioId, (string) $permiso)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Comprueba si el usuario tiene asignado un rol específico por su código.
     */
    public function tieneRol(int $usuarioId, string $codigoRol): bool
    {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
        if ($usuario === null || $usuario->estado !== 'ACTIVO' || $usuario->estaBloqueado()) {
            return false;
        }

        return $this->rolRepo->usuarioTieneRol($usuarioId, $codigoRol);
    }

    /**
     * Retorna la lista de roles activos asignados al usuario.
     * @return Rol[]
     */
    public function obtenerRolesUsuario(int $usuarioId): array
    {
        return $this->rolRepo->obtenerRolesDeUsuario($usuarioId);
    }

    /**
     * Retorna todos los códigos de permisos accesibles para el usuario.
     * @return string[]
     */
    public function obtenerPermisosUsuario(int $usuarioId): array
    {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
        if ($usuario === null || $usuario->estado !== 'ACTIVO' || $usuario->estaBloqueado()) {
            return [];
        }

        if ($this->esSuperadmin($usuarioId)) {
            return array_map(fn($p) => $p->codigo, $this->permisoRepo->obtenerTodos());
        }

        return $this->permisoRepo->obtenerCodigosPermisosDeUsuario($usuarioId);
    }

    /**
     * Fuerza la autorización del usuario; lanza AccesoDenegadoExcepcion si el permiso no está concedido.
     * Registra auditoría de intento no autorizado cuando se provee el contexto de operación.
     *
     * @throws AccesoDenegadoExcepcion
     */
    public function autorizar(int $usuarioId, string $codigoPermiso, ?ContextoOperacion $contexto = null): void
    {
        if (!$this->tienePermiso($usuarioId, $codigoPermiso)) {
            if ($contexto !== null) {
                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'seguridad',
                    accion: 'ACCESO_DENEGADO',
                    entidadTipo: 'permiso',
                    entidadId: $codigoPermiso,
                    datosPrevios: null,
                    datosNuevos: [
                        'permiso_requerido' => $codigoPermiso,
                        'usuario_id'        => $usuarioId,
                        'resultado'         => '403_FORBIDDEN',
                    ]
                );
            }

            throw new AccesoDenegadoExcepcion(
                mensaje: "Acceso denegado: se requiere el permiso '{$codigoPermiso}'.",
                permisoRequerido: $codigoPermiso
            );
        }
    }
}

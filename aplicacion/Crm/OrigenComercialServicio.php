<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\OrigenComercial;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio para la gestión del catálogo relacional de Orígenes Comerciales por Tenant.
 */
class OrigenComercialServicio
{
    private PDO $pdo;

    public function __construct(
        private OrigenComercialRepositorio $origenRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function crear(
        int $organizacionId,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        int $orden = 0,
        ?ContextoOperacion $contexto = null
    ): OrigenComercial {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.origenes.administrar', $contexto);

        $codigoNormalizado = strtoupper(trim($codigo));
        $origenExistente = $this->origenRepo->buscarPorCodigo($organizacionId, $codigoNormalizado);
        if ($origenExistente !== null) {
            throw new InvalidArgumentException("Ya existe un origen comercial con el código '{$codigoNormalizado}' en esta organización.");
        }

        $nuevoOrigen = new OrigenComercial(
            id: null,
            organizacionId: $organizacionId,
            codigo: $codigoNormalizado,
            nombre: trim($nombre),
            descripcion: $descripcion,
            activo: true,
            orden: $orden
        );

        $id = $this->origenRepo->crear($nuevoOrigen);
        $creado = $this->origenRepo->buscarPorId($id);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'crm_prospectos',
            accion: 'CREAR_ORIGEN_COMERCIAL',
            entidadTipo: 'OrigenComercial',
            entidadId: (string) $id,
            datosPrevios: null,
            datosNuevos: $creado->aArreglo()
        );

        return $creado;
    }

    public function desactivar(int $organizacionId, int $origenId, ?ContextoOperacion $contexto = null): bool
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.origenes.administrar', $contexto);

        $origen = $this->origenRepo->buscarPorId($origenId);
        if ($origen === null || $origen->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El origen comercial no existe o pertenece a otra organización.");
        }

        $datosPrevios = $origen->aArreglo();
        $ok = $this->origenRepo->desactivar($origenId, $organizacionId);
        $actualizado = $this->origenRepo->buscarPorId($origenId);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'crm_prospectos',
            accion: 'DESACTIVAR_ORIGEN_COMERCIAL',
            entidadTipo: 'OrigenComercial',
            entidadId: (string) $origenId,
            datosPrevios: $datosPrevios,
            datosNuevos: $actualizado->aArreglo()
        );

        return $ok;
    }

    public function activar(int $organizacionId, int $origenId, ?ContextoOperacion $contexto = null): bool
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.origenes.administrar', $contexto);

        $origen = $this->origenRepo->buscarPorId($origenId);
        if ($origen === null || $origen->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El origen comercial no existe o pertenece a otra organización.");
        }

        $datosPrevios = $origen->aArreglo();
        $ok = $this->origenRepo->activar($origenId, $organizacionId);
        $actualizado = $this->origenRepo->buscarPorId($origenId);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'crm_prospectos',
            accion: 'ACTIVAR_ORIGEN_COMERCIAL',
            entidadTipo: 'OrigenComercial',
            entidadId: (string) $origenId,
            datosPrevios: $datosPrevios,
            datosNuevos: $actualizado->aArreglo()
        );

        return $ok;
    }

    public function actualizar(
        int $organizacionId,
        int $origenId,
        string $nombre,
        ?string $descripcion = null,
        int $orden = 0,
        ?ContextoOperacion $contexto = null
    ): OrigenComercial {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.origenes.administrar', $contexto);

        $origen = $this->origenRepo->buscarPorId($origenId);
        if ($origen === null || $origen->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El origen comercial no existe o pertenece a otra organización.");
        }

        $datosPrevios = $origen->aArreglo();
        $origenModificado = new OrigenComercial(
            id: $origenId,
            organizacionId: $organizacionId,
            codigo: $origen->codigo, // Código inmutable para proteger semántica histórica
            nombre: trim($nombre),
            descripcion: $descripcion !== null ? trim($descripcion) : null,
            activo: $origen->activo,
            orden: $orden
        );

        $this->origenRepo->actualizar($origenModificado);
        $actualizado = $this->origenRepo->buscarPorId($origenId);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'crm_prospectos',
            accion: 'ACTUALIZAR_ORIGEN_COMERCIAL',
            entidadTipo: 'OrigenComercial',
            entidadId: (string) $origenId,
            datosPrevios: $datosPrevios,
            datosNuevos: $actualizado->aArreglo()
        );

        return $actualizado;
    }

    /**
     * @return OrigenComercial[]
     */
    public function listarPorOrganizacion(int $organizacionId, bool $soloActivos = true, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.ver', $contexto);

        return $this->origenRepo->listarPorOrganizacion($organizacionId, $soloActivos);
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        $ctx = $contexto ?? ContextoOperacion::actual();
        if ($ctx === null) {
            throw new InvalidArgumentException("Se requiere un ContextoOperacion válido.");
        }
        return $ctx;
    }

    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->actorTipo === 'HUMANO') {
            if ($contexto->organizacionId !== null && $contexto->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("Violación de aislamiento multi-tenant: el usuario no pertenece a la organización solicitada.");
            }
        }
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->actorTipo === 'HUMANO') {
            if ($contexto->usuarioId === null) {
                throw new AccesoDenegadoExcepcion("Se requiere un usuario humano autenticado.");
            }
            if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
                throw new AccesoDenegadoExcepcion("Permiso denegado: se requiere el privilegio '{$permiso}'.");
            }
        }
    }
}

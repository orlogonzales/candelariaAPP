<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

use Aplicacion\Entidades\OperacionRecurso;
use Aplicacion\Entidades\Proveedor;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OperacionRecursoRepositorio;
use Aplicacion\Repositorios\ProveedorRepositorio;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Nucleo\Http\ContextoOperacion;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Servicio de Dominio para la Gestión de Proveedores y Recursos Físicos.
 */
class ProveedorRecursoServicio
{
    private PDO $pdo;

    public function __construct(
        private ProveedorRepositorio $proveedorRepo,
        private OperacionRecursoRepositorio $recursoRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function registrarProveedor(
        int $organizacionId,
        int $personaId,
        ?string $tipoServicioPrincipal = null,
        ?string $notasContacto = null,
        ?ContextoOperacion $contexto = null
    ): Proveedor {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('proveedores.gestionar', $contexto);

        // Validar que la persona exista en la base de datos
        $stmtPer = $this->pdo->prepare("SELECT COUNT(*) FROM `personas` WHERE `id` = :id");
        $stmtPer->execute(['id' => $personaId]);
        if ((int) $stmtPer->fetchColumn() === 0) {
            throw new InvalidArgumentException("La persona #{$personaId} no existe en la base de datos.");
        }

        // Validar que no esté ya registrado como proveedor en este tenant
        $existente = $this->proveedorRepo->buscarPorPersonaId($personaId, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("La persona #{$personaId} ya está registrada como proveedor en su organización.");
        }

        $proveedor = new Proveedor(
            id: null,
            organizacionId: $organizacionId,
            personaId: $personaId,
            tipoServicioPrincipal: $tipoServicioPrincipal,
            estado: EstadoProveedor::ACTIVO,
            notasContacto: $notasContacto,
            creadoPor: $contexto->usuarioId ?? 1
        );

        $guardado = $this->proveedorRepo->guardar($proveedor);

        $this->registrarAuditoria(
            $contexto,
            'PROVEEDOR_REGISTRADO',
            'proveedores',
            (int) $guardado->id,
            [
                'persona_id'              => $personaId,
                'tipo_servicio_principal' => $tipoServicioPrincipal,
            ]
        );

        return $guardado;
    }

    public function suspenderProveedor(
        int $organizacionId,
        int $proveedorId,
        ?ContextoOperacion $contexto = null
    ): void {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('proveedores.gestionar', $contexto);

        $proveedor = $this->proveedorRepo->buscarPorId($proveedorId, $organizacionId);
        if ($proveedor === null) {
            throw new InvalidArgumentException("El proveedor #{$proveedorId} no existe en su organización.");
        }

        $this->proveedorRepo->actualizarEstado($proveedorId, $organizacionId, EstadoProveedor::SUSPENDIDO);

        $this->registrarAuditoria(
            $contexto,
            'PROVEEDOR_SUSPENDIDO',
            'proveedores',
            $proveedorId,
            ['estado_anterior' => $proveedor->estado->value]
        );
    }

    public function registrarRecursoFisico(
        int $organizacionId,
        TipoRecursoFisico $tipoRecurso,
        string $codigoInterno,
        string $nombre,
        PropiedadRecurso $propiedadTipo,
        ?int $proveedorId = null,
        int $capacidadMaxima = 1,
        ?string $identificacionOficial = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): OperacionRecurso {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('recursos.gestionar', $contexto);

        $codigoTrim = strtoupper(trim($codigoInterno));
        $existente = $this->recursoRepo->buscarPorCodigo($codigoTrim, $organizacionId);
        if ($existente !== null) {
            throw new InvalidArgumentException("Ya existe un recurso con el código '{$codigoTrim}' en su organización.");
        }

        if ($propiedadTipo === PropiedadRecurso::EXTERNO) {
            if ($proveedorId === null || $proveedorId <= 0) {
                throw new InvalidArgumentException("Debe asociar un proveedor para un recurso de propiedad EXTERNO.");
            }
            $prov = $this->proveedorRepo->buscarPorId($proveedorId, $organizacionId);
            if ($prov === null) {
                throw new InvalidArgumentException("El proveedor #{$proveedorId} no existe en su organización.");
            }
            if ($prov->estado !== EstadoProveedor::ACTIVO) {
                throw new InvalidArgumentException("El proveedor asociado no se encuentra ACTIVO ({$prov->estado->value}).");
            }
        } else {
            $proveedorId = null;
        }

        $recurso = new OperacionRecurso(
            id: null,
            organizacionId: $organizacionId,
            tipoRecurso: $tipoRecurso,
            codigoInterno: $codigoTrim,
            nombre: trim($nombre),
            propiedadTipo: $propiedadTipo,
            proveedorId: $proveedorId,
            capacidadMaxima: $capacidadMaxima,
            identificacionOficial: $identificacionOficial !== null ? trim($identificacionOficial) : null,
            estado: EstadoRecursoFisico::DISPONIBLE,
            notas: $notas
        );

        $guardado = $this->recursoRepo->guardar($recurso);

        $this->registrarAuditoria(
            $contexto,
            'RECURSO_FISICO_REGISTRADO',
            'operacion_recursos',
            (int) $guardado->id,
            [
                'codigo_interno'   => $guardado->codigoInterno,
                'tipo_recurso'     => $tipoRecurso->value,
                'capacidad_maxima' => $capacidadMaxima,
            ]
        );

        return $guardado;
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        return $contexto ?? new ContextoOperacion(
            usuarioId: 1,
            roles: ['ADMINISTRADOR'],
            ip: '127.0.0.1',
            userAgent: 'CLI/Test'
        );
    }

    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId !== null && $contexto->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("Violación de aislamiento multi-tenant.");
        }
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId === null) {
            throw new AccesoDenegadoExcepcion("Operación no autenticada.");
        }
        if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
            throw new AccesoDenegadoExcepcion("No cuenta con el permiso requerido: '{$permiso}'.");
        }
    }

    private function registrarAuditoria(
        ContextoOperacion $contexto,
        string $accion,
        string $entidadTipo,
        int $entidadId,
        array $datos
    ): void {
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'operaciones',
            accion: $accion,
            entidadTipo: $entidadTipo,
            entidadId: (string) $entidadId,
            datosPrevios: null,
            datosNuevos: $datos
        );
    }
}

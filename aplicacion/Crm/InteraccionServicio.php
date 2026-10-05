<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\InteraccionCrm;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\InteraccionCrmRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio para registro y consulta de interacciones comerciales de CRM.
 * Garantiza:
 * 1. Trazabilidad append-only inmutable de llamadas, mensajes, notas y reuniones.
 * 2. Reutilización del actor soberano (HUMANO/SISTEMA) y correlación de ContextoOperacion.
 * 3. Validación estricta anti-cruce: la oportunidad debe pertenecer al mismo cliente y organización.
 * 4. Control de acceso RBAC ('crm.interacciones.crear', 'crm.interacciones.ver') y Anti-IDOR.
 */
class InteraccionServicio
{
    private PDO $pdo;

    public function __construct(
        private InteraccionCrmRepositorio $interaccionRepo,
        private ClienteRepositorio $clienteRepo,
        private OportunidadRepositorio $oportunidadRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra una interacción comercial para un cliente (y opcionalmente una oportunidad específica).
     */
    public function registrar(
        int $organizacionId,
        int $clienteId,
        ?int $oportunidadId,
        int $canalId,
        string $tipoStr,
        string $direccionStr,
        string $resumen,
        ?string $detalle = null,
        ?ContextoOperacion $contexto = null
    ): InteraccionCrm {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.interacciones.crear', $contexto);

        // 1. Validar Anti-IDOR del Cliente
        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El cliente no existe o pertenece a otra organización.");
        }

        // 2. Si se asocia a una Oportunidad, validar pertenencia estricta al cliente y tenant
        if ($oportunidadId !== null) {
            $oportunidad = $this->oportunidadRepo->buscarPorId($oportunidadId);
            if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("La oportunidad comercial especificada no existe o pertenece a otra organización.");
            }
            if ($oportunidad->clienteId !== $clienteId) {
                throw new InvalidArgumentException(
                    "Inconsistencia comercial: La oportunidad #{$oportunidadId} pertenece al cliente #{$oportunidad->clienteId}, no al cliente #{$clienteId}."
                );
            }
        }

        // 3. Validar canal existente
        $stmtCanal = $this->pdo->prepare("SELECT id FROM `canales` WHERE `id` = :id LIMIT 1");
        $stmtCanal->execute([':id' => $canalId]);
        if (!$stmtCanal->fetch()) {
            throw new InvalidArgumentException("El canal especificado (#{$canalId}) no existe en el catálogo de canales.");
        }

        // 4. Parsear y validar tipos de interacción
        $tipo = TipoInteraccion::desdeCadena($tipoStr);
        $direccion = DireccionInteraccion::desdeCadena($direccionStr);

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $interaccion = new InteraccionCrm(
                id: null,
                organizacionId: $organizacionId,
                clienteId: $clienteId,
                oportunidadId: $oportunidadId,
                canalId: $canalId,
                tipo: $tipo,
                direccion: $direccion,
                resumen: $resumen,
                detalle: $detalle,
                actorTipo: $contexto->actorTipo,
                usuarioId: $contexto->usuarioId,
                actorSistemaId: $contexto->actorSistemaId,
                correlacionId: $contexto->correlacionId
            );

            $interaccionId = $this->interaccionRepo->registrar($interaccion);
            $interaccionCreada = $this->interaccionRepo->buscarPorId($interaccionId);

            // Auditoría técnica inmutable
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'crm_prospectos',
                accion: 'REGISTRAR_INTERACCION_CRM',
                entidadTipo: 'InteraccionCrm',
                entidadId: (string) $interaccionId,
                datosPrevios: null,
                datosNuevos: $interaccionCreada->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $interaccionCreada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return InteraccionCrm[]
     */
    public function listarPorCliente(int $organizacionId, int $clienteId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.interacciones.ver', $contexto);

        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El cliente no existe o pertenece a otra organización.");
        }

        return $this->interaccionRepo->listarPorCliente($organizacionId, $clienteId);
    }

    /**
     * @return InteraccionCrm[]
     */
    public function listarPorOportunidad(int $organizacionId, int $oportunidadId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.interacciones.ver', $contexto);

        $oportunidad = $this->oportunidadRepo->buscarPorId($oportunidadId);
        if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("La oportunidad comercial no existe o pertenece a otra organización.");
        }

        return $this->interaccionRepo->listarPorOportunidad($organizacionId, $oportunidadId);
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        $ctx = $contexto ?? ContextoOperacion::actual();
        if ($ctx === null) {
            throw new InvalidArgumentException("Se requiere un ContextoOperacion válido para registrar interacciones.");
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

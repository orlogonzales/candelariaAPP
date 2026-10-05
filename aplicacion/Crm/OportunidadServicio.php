<?php

declare(strict_types=1);

namespace Aplicacion\Crm;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Entidades\HistorialEtapaOportunidad;
use Aplicacion\Entidades\Oportunidad;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialEtapaRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\OrigenComercialRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio oficial para la gestión comercial de Oportunidades en CRM.
 * Implementa:
 * 1. Aislamiento multi-tenant estricto y Anti-IDOR (Cliente, Edición, Asesor y Origen del mismo tenant).
 * 2. Máquina de estados flexible hacia adelante con terminales inmutables (GANADA/PERDIDA).
 * 3. Motivo obligatorio en PERDIDA y detalle obligatorio en OTRO.
 * 4. Snapshot inmutable de la moneda institucional vigente.
 * 5. Control de concurrencia optimista (version_bloqueo).
 * 6. Historial append-only de transiciones de etapa.
 * 7. Pista de auditoría técnica transversal.
 * 8. Prohibición de borrado físico.
 */
class OportunidadServicio
{
    private PDO $pdo;

    public function __construct(
        private OportunidadRepositorio $oportunidadRepo,
        private HistorialEtapaRepositorio $historialRepo,
        private ClienteRepositorio $clienteRepo,
        private EdicionRepositorio $edicionRepo,
        private OrigenComercialRepositorio $origenRepo,
        private UsuarioRepositorio $usuarioRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        private ConfiguracionServicio $configServicio,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra una nueva oportunidad comercial para un cliente dentro de una edición.
     */
    public function crear(
        int $organizacionId,
        int $edicionId,
        int $clienteId,
        string $titulo,
        ?int $usuarioAsignadoId = null,
        ?int $origenComercialId = null,
        ?float $valorEstimado = null,
        ?string $moneda = null,
        ?string $proximoSeguimientoEn = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): Oportunidad {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.crear', $contexto);

        // 1. Validar Anti-IDOR del Cliente
        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || $cliente->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("El cliente especificado no existe o pertenece a otra organización.");
        }

        // 2. Validar Anti-IDOR de la Edición
        $edicion = $this->edicionRepo->buscarPorId($edicionId);
        if ($edicion === null || $edicion->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("La edición especificada no existe o pertenece a otra organización.");
        }

        // 3. Validar Origen Comercial si se proporciona
        if ($origenComercialId !== null) {
            $origen = $this->origenRepo->buscarPorId($origenComercialId);
            if ($origen === null || $origen->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("El origen comercial especificado no existe o pertenece a otra organización.");
            }
            if (!$origen->activo) {
                throw new InvalidArgumentException("No se puede asignar un origen comercial inactivo a una nueva oportunidad.");
            }
        }

        // 4. Validar Asesor Responsable si se proporciona
        if ($usuarioAsignadoId !== null) {
            $usuario = $this->usuarioRepo->buscarPorId($usuarioAsignadoId);
            if ($usuario === null || $usuario->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("El usuario asesor asignado no pertenece a la misma organización.");
            }
            if ($usuario->estado !== 'ACTIVO') {
                throw new InvalidArgumentException("No se puede asignar como responsable a un usuario inactivo o bloqueado.");
            }
        }

        // 5. Resolver snapshot de moneda institucional
        $monedaResuelta = $moneda !== null ? strtoupper(trim($moneda)) : null;
        if ($monedaResuelta === null || $monedaResuelta === '') {
            $monedaResuelta = (string) $this->configServicio->obtenerPlataforma('plataforma.moneda_principal', 'PEN');
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $oportunidad = new Oportunidad(
                id: null,
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                clienteId: $clienteId,
                usuarioAsignadoId: $usuarioAsignadoId,
                origenComercialId: $origenComercialId,
                titulo: $titulo,
                etapa: EtapaOportunidad::NUEVA,
                valorEstimado: $valorEstimado,
                moneda: $monedaResuelta,
                proximoSeguimientoEn: $proximoSeguimientoEn,
                motivoPerdida: null,
                motivoPerdidaDetalle: null,
                notas: $notas,
                versionBloqueo: 1
            );

            $oportunidadId = $this->oportunidadRepo->crear($oportunidad);
            $oportunidadCreada = $this->oportunidadRepo->buscarPorId($oportunidadId);

            // Registro inicial append-only en historial de etapas
            $this->historialRepo->registrar(new HistorialEtapaOportunidad(
                id: null,
                organizacionId: $organizacionId,
                oportunidadId: $oportunidadId,
                etapaAnterior: null,
                etapaNueva: EtapaOportunidad::NUEVA->value,
                motivo: 'Apertura inicial de oportunidad comercial',
                actorTipo: $contexto->actorTipo,
                usuarioId: $contexto->usuarioId,
                actorSistemaId: $contexto->actorSistemaId,
                correlacionId: $contexto->correlacionId
            ));

            // Auditoría técnica inmutable
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'crm_prospectos',
                accion: 'CREAR_OPORTUNIDAD',
                entidadTipo: 'Oportunidad',
                entidadId: (string) $oportunidadId,
                datosPrevios: null,
                datosNuevos: $oportunidadCreada->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $oportunidadCreada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transiciona de etapa una oportunidad comercial con validación de reglas y concurrencia optimista.
     */
    public function cambiarEtapa(
        int $organizacionId,
        int $oportunidadId,
        string $nuevaEtapaStr,
        ?string $motivoPerdidaStr = null,
        ?string $motivoPerdidaDetalle = null,
        ?string $motivoCambio = null,
        ?int $versionBloqueoEsperada = null,
        ?ContextoOperacion $contexto = null
    ): Oportunidad {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.cambiar_etapa', $contexto);

        $nuevaEtapa = EtapaOportunidad::desdeCadena($nuevaEtapaStr);

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $oportunidad = $this->oportunidadRepo->buscarPorIdBloqueante($oportunidadId);
            if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("La oportunidad no existe o pertenece a otra organización.");
            }

            $version = $versionBloqueoEsperada ?? $oportunidad->versionBloqueo;

            // Validar legalidad de la transición según máquina de estados
            if (!$oportunidad->etapa->puedeTransicionarA($nuevaEtapa)) {
                throw new InvalidArgumentException(
                    "Transición comercial no permitida: No es legal pasar de '{$oportunidad->etapa->value}' a '{$nuevaEtapa->value}'."
                );
            }

            // Validar reglas de motivo si es PERDIDA
            $motivoPerdida = null;
            if ($nuevaEtapa === EtapaOportunidad::PERDIDA) {
                if ($motivoPerdidaStr === null || trim($motivoPerdidaStr) === '') {
                    throw new InvalidArgumentException("Una oportunidad en etapa PERDIDA exige obligatoriamente especificar el motivo de pérdida.");
                }
                $motivoPerdida = MotivoPerdida::desdeCadena($motivoPerdidaStr);
                if ($motivoPerdida === MotivoPerdida::OTRO) {
                    if ($motivoPerdidaDetalle === null || trim($motivoPerdidaDetalle) === '') {
                        throw new InvalidArgumentException("El motivo de pérdida 'OTRO' exige obligatoriamente ingresar un detalle explicativo.");
                    }
                }
            } else {
                $motivoPerdida = null;
                $motivoPerdidaDetalle = null;
            }

            $datosPrevios = $oportunidad->aArreglo();

            $this->oportunidadRepo->cambiarEtapaConcurrente(
                id: $oportunidadId,
                organizacionId: $organizacionId,
                nuevaEtapa: $nuevaEtapa->value,
                motivoPerdida: $motivoPerdida?->value,
                motivoPerdidaDetalle: $motivoPerdidaDetalle !== null ? trim($motivoPerdidaDetalle) : null,
                versionBloqueo: $version
            );

            // Registro append-only en historial de etapas
            $this->historialRepo->registrar(new HistorialEtapaOportunidad(
                id: null,
                organizacionId: $organizacionId,
                oportunidadId: $oportunidadId,
                etapaAnterior: $oportunidad->etapa->value,
                etapaNueva: $nuevaEtapa->value,
                motivo: $motivoCambio ?? ($motivoPerdida !== null ? "Pérdida: {$motivoPerdida->value}" : "Avance de pipeline"),
                actorTipo: $contexto->actorTipo,
                usuarioId: $contexto->usuarioId,
                actorSistemaId: $contexto->actorSistemaId,
                correlacionId: $contexto->correlacionId
            ));

            $oportunidadActualizada = $this->oportunidadRepo->buscarPorId($oportunidadId);

            // Auditoría técnica
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'crm_prospectos',
                accion: 'CAMBIAR_ETAPA_OPORTUNIDAD',
                entidadTipo: 'Oportunidad',
                entidadId: (string) $oportunidadId,
                datosPrevios: $datosPrevios,
                datosNuevos: $oportunidadActualizada->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $oportunidadActualizada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Asigna o reasigna el asesor responsable comercial con concurrencia optimista.
     */
    public function asignarResponsable(
        int $organizacionId,
        int $oportunidadId,
        ?int $nuevoUsuarioAsignadoId,
        ?int $versionBloqueoEsperada = null,
        ?ContextoOperacion $contexto = null
    ): Oportunidad {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.asignar', $contexto);

        if ($nuevoUsuarioAsignadoId !== null) {
            $usuario = $this->usuarioRepo->buscarPorId($nuevoUsuarioAsignadoId);
            if ($usuario === null || $usuario->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("El usuario asesor asignado no pertenece a la misma organización.");
            }
            if ($usuario->estado !== 'ACTIVO') {
                throw new InvalidArgumentException("No se puede asignar como responsable a un usuario inactivo o bloqueado.");
            }
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $oportunidad = $this->oportunidadRepo->buscarPorIdBloqueante($oportunidadId);
            if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("La oportunidad no existe o pertenece a otra organización.");
            }

            $version = $versionBloqueoEsperada ?? $oportunidad->versionBloqueo;
            $datosPrevios = $oportunidad->aArreglo();

            $this->oportunidadRepo->asignarResponsableConcurrente(
                id: $oportunidadId,
                organizacionId: $organizacionId,
                usuarioAsignadoId: $nuevoUsuarioAsignadoId,
                versionBloqueo: $version
            );

            $oportunidadActualizada = $this->oportunidadRepo->buscarPorId($oportunidadId);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'crm_prospectos',
                accion: 'ASIGNAR_OPORTUNIDAD',
                entidadTipo: 'Oportunidad',
                entidadId: (string) $oportunidadId,
                datosPrevios: $datosPrevios,
                datosNuevos: $oportunidadActualizada->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $oportunidadActualizada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Modifica los datos descriptivos de una oportunidad con concurrencia optimista.
     */
    public function editar(
        int $organizacionId,
        int $oportunidadId,
        string $titulo,
        ?int $origenComercialId,
        ?float $valorEstimado,
        ?string $proximoSeguimientoEn,
        ?string $notas,
        ?int $versionBloqueoEsperada = null,
        ?ContextoOperacion $contexto = null
    ): Oportunidad {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.editar', $contexto);

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $oportunidad = $this->oportunidadRepo->buscarPorIdBloqueante($oportunidadId);
            if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
                throw new AccesoDenegadoExcepcion("La oportunidad no existe o pertenece a otra organización.");
            }

            if ($origenComercialId !== null) {
                $origen = $this->origenRepo->buscarPorId($origenComercialId);
                if ($origen === null || $origen->organizacionId !== $organizacionId) {
                    throw new AccesoDenegadoExcepcion("El origen comercial especificado no pertenece a la misma organización.");
                }
                // Si el origen fue cambiado hacia uno nuevo, debe estar activo
                if ($oportunidad->origenComercialId !== $origenComercialId && !$origen->activo) {
                    throw new InvalidArgumentException("No se puede asignar un origen comercial inactivo.");
                }
            }

            $version = $versionBloqueoEsperada ?? $oportunidad->versionBloqueo;
            $datosPrevios = $oportunidad->aArreglo();

            $oportunidadModificada = new Oportunidad(
                id: $oportunidadId,
                organizacionId: $organizacionId,
                edicionId: $oportunidad->edicionId,
                clienteId: $oportunidad->clienteId,
                usuarioAsignadoId: $oportunidad->usuarioAsignadoId,
                origenComercialId: $origenComercialId,
                titulo: $titulo,
                etapa: $oportunidad->etapa,
                valorEstimado: $valorEstimado,
                moneda: $oportunidad->moneda,
                proximoSeguimientoEn: $proximoSeguimientoEn,
                motivoPerdida: $oportunidad->motivoPerdida,
                motivoPerdidaDetalle: $oportunidad->motivoPerdidaDetalle,
                notas: $notas,
                versionBloqueo: $version
            );

            $this->oportunidadRepo->actualizarConcurrente($oportunidadModificada);
            $oportunidadActualizada = $this->oportunidadRepo->buscarPorId($oportunidadId);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'crm_prospectos',
                accion: 'EDITAR_OPORTUNIDAD',
                entidadTipo: 'Oportunidad',
                entidadId: (string) $oportunidadId,
                datosPrevios: $datosPrevios,
                datosNuevos: $oportunidadActualizada->aArreglo()
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $oportunidadActualizada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consulta una oportunidad por ID con aislamiento multi-tenant.
     */
    public function buscarPorId(int $organizacionId, int $oportunidadId, ?ContextoOperacion $contexto = null): ?Oportunidad
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.ver', $contexto);

        $oportunidad = $this->oportunidadRepo->buscarPorId($oportunidadId);
        if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
            return null;
        }

        return $oportunidad;
    }

    /**
     * @return Oportunidad[]
     */
    public function listarPorCliente(int $organizacionId, int $clienteId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.ver', $contexto);

        return $this->oportunidadRepo->listarPorCliente($organizacionId, $clienteId);
    }

    /**
     * @return Oportunidad[]
     */
    public function listarPorEdicion(int $organizacionId, int $edicionId, ?string $etapa = null, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.ver', $contexto);

        return $this->oportunidadRepo->listarPorEdicion($organizacionId, $edicionId, $etapa);
    }

    /**
     * @return HistorialEtapaOportunidad[]
     */
    public function obtenerHistorialEtapas(int $organizacionId, int $oportunidadId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('crm.oportunidades.ver', $contexto);

        $oportunidad = $this->oportunidadRepo->buscarPorId($oportunidadId);
        if ($oportunidad === null || $oportunidad->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("La oportunidad no existe o pertenece a otra organización.");
        }

        return $this->historialRepo->listarPorOportunidad($organizacionId, $oportunidadId);
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        $ctx = $contexto ?? ContextoOperacion::actual();
        if ($ctx === null) {
            throw new InvalidArgumentException("Se requiere un ContextoOperacion válido para ejecutar operaciones de CRM.");
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

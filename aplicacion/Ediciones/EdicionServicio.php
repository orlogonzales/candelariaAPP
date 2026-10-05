<?php

declare(strict_types=1);

namespace Aplicacion\Ediciones;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

/**
 * Servicio de dominio oficial para el ciclo de vida y gestión de Ediciones Candelaria.
 *
 * Responsabilidades:
 * - Aislamiento estricto multi-tenant y prevención Anti-IDOR.
 * - Validación y cumplimiento riguroso de RBAC.
 * - Gobernanza de la máquina de estados y transiciones del ciclo de vida.
 * - Pista de auditoría inmutable en auditoria_operaciones.
 * - Soporte de concurrencia optimista.
 */
class EdicionServicio
{
    private PDO $pdo;

    public function __construct(
        private EdicionRepositorio $edicionRepo,
        private OrganizacionRepositorio $orgRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Da de alta una nueva edición Candelaria.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws InvalidArgumentException
     */
    public function crear(int $organizacionId, array $datos, ContextoOperacion $contexto): Edicion
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.crear', $contexto);

        // 1. Verificar existencia de la organización
        $org = $this->orgRepo->buscarPorId($organizacionId);
        if ($org === null) {
            throw new InvalidArgumentException("La organización solicitada (ID: {$organizacionId}) no existe en la base de datos.");
        }

        // 2. Extraer y validar campos obligatorios
        $codigo = (string) ($datos['codigo'] ?? '');
        $nombre = (string) ($datos['nombre'] ?? '');
        $anio = (int) ($datos['anio'] ?? 0);
        $fechaInicio = (string) ($datos['fecha_inicio'] ?? '');
        $fechaFin = (string) ($datos['fecha_fin'] ?? '');
        $descripcion = isset($datos['descripcion']) ? (string) $datos['descripcion'] : null;
        $esActual = !empty($datos['es_actual']);
        $configuracion = isset($datos['configuracion']) && is_array($datos['configuracion']) ? $datos['configuracion'] : null;

        // 3. Comprobar unicidad de año y código en el tenant
        if ($this->edicionRepo->existeAnio($organizacionId, $anio)) {
            throw new InvalidArgumentException("La organización ya cuenta con una edición registrada para el año {$anio}.");
        }

        if ($this->edicionRepo->existeCodigo($organizacionId, $codigo)) {
            throw new InvalidArgumentException("El código '{$codigo}' ya está asignado a otra edición de la organización.");
        }

        // 4. Instanciar entidad con validación de invariantes
        $edicion = new Edicion(
            id: null,
            organizacionId: $organizacionId,
            codigo: $codigo,
            nombre: $nombre,
            anio: $anio,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            estado: EstadoEdicion::PREOPERACION,
            descripcion: $descripcion,
            esActual: $esActual,
            flyerOficialUrl: null,
            configuracion: $configuracion
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $nuevoId = $this->edicionRepo->crear($edicion);

            if ($esActual) {
                $this->edicionRepo->establecerComoActual($nuevoId, $organizacionId);
            }

            // Registrar en pista de auditoría
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'ediciones',
                accion: 'CREAR_EDICION',
                entidadTipo: 'EDICION',
                entidadId: (string) $nuevoId,
                datosPrevios: null,
                datosNuevos: $edicion->aArreglo()
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->edicionRepo->buscarPorId($nuevoId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza información mutable de una edición existente.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws ConflictoConcurrenciaExcepcion
     * @throws InvalidArgumentException
     */
    public function actualizar(
        int $id,
        int $organizacionId,
        array $datos,
        ContextoOperacion $contexto,
        ?string $actualizadoEnEsperado = null
    ): Edicion {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.editar', $contexto);

        $edicionActual = $this->edicionRepo->buscarPorId($id);
        if ($edicionActual === null || $edicionActual->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion('La edición solicitada no existe o no pertenece a la organización autorizada.');
        }

        // Si la edición está CERRADA, se bloquea la modificación ordinaria
        if ($edicionActual->estado->esTerminal()) {
            throw new InvalidArgumentException(
                "La edición '{$edicionActual->nombre}' se encuentra en estado CERRADA y no admite modificaciones operativas ordinarias."
            );
        }

        $nuevoNombre = isset($datos['nombre']) ? (string) $datos['nombre'] : $edicionActual->nombre;
        $nuevaFechaInicio = isset($datos['fecha_inicio']) ? (string) $datos['fecha_inicio'] : $edicionActual->fechaInicio;
        $nuevaFechaFin = isset($datos['fecha_fin']) ? (string) $datos['fecha_fin'] : $edicionActual->fechaFin;
        $nuevaDescripcion = array_key_exists('descripcion', $datos) ? (string) $datos['descripcion'] : $edicionActual->descripcion;
        $nuevaConfig = array_key_exists('configuracion', $datos) && is_array($datos['configuracion'])
            ? $datos['configuracion']
            : $edicionActual->configuracion;

        $edicionModificada = new Edicion(
            id: $edicionActual->id,
            organizacionId: $edicionActual->organizacionId,
            codigo: $edicionActual->codigo,
            nombre: $nuevoNombre,
            anio: $edicionActual->anio,
            fechaInicio: $nuevaFechaInicio,
            fechaFin: $nuevaFechaFin,
            estado: $edicionActual->estado,
            descripcion: $nuevaDescripcion,
            esActual: $edicionActual->esActual,
            flyerOficialUrl: $edicionActual->flyerOficialUrl,
            configuracion: $nuevaConfig,
            creadoEn: $edicionActual->creadoEn,
            actualizadoEn: $edicionActual->actualizadoEn
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $this->edicionRepo->actualizar($edicionModificada, $actualizadoEnEsperado);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'ediciones',
                accion: 'ACTUALIZAR_EDICION',
                entidadTipo: 'EDICION',
                entidadId: (string) $id,
                datosPrevios: $edicionActual->aArreglo(),
                datosNuevos: $edicionModificada->aArreglo()
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->edicionRepo->buscarPorId($id);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transiciona el estado del ciclo de vida de una edición con validación de máquina de estados.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws InvalidArgumentException
     */
    public function cambiarEstado(
        int $id,
        int $organizacionId,
        string $nuevoEstadoValor,
        ContextoOperacion $contexto,
        ?string $motivo = null,
        ?string $actualizadoEnEsperado = null
    ): Edicion {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.cambiar_estado', $contexto);

        $edicion = $this->edicionRepo->buscarPorId($id);
        if ($edicion === null || $edicion->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion('La edición solicitada no existe o no pertenece a la organización.');
        }

        // Una edición CERRADA es terminal estricta y queda históricamente congelada
        if ($edicion->estado === EstadoEdicion::CERRADA) {
            throw new InvalidArgumentException(
                "La edición '{$edicion->nombre}' se encuentra en estado CERRADA y está históricamente congelada. No admite cambios de estado."
            );
        }

        $nuevoEstado = EstadoEdicion::intentarDesde($nuevoEstadoValor);
        if ($nuevoEstado === null) {
            throw new InvalidArgumentException("El estado '{$nuevoEstadoValor}' no es un estado válido del ciclo de vida.");
        }

        if ($edicion->estado === $nuevoEstado) {
            throw new InvalidArgumentException("La edición ya se encuentra en el estado '{$nuevoEstado->value}'.");
        }

        // Evaluar si es retroceso operativo extraordinario
        $esRetroceso = match (true) {
            $edicion->estado === EstadoEdicion::OPERACION && $nuevoEstado === EstadoEdicion::PREOPERACION => true,
            $edicion->estado === EstadoEdicion::POSTPRODUCCION_ENTREGA && $nuevoEstado === EstadoEdicion::OPERACION => true,
            default => false,
        };

        if ($esRetroceso && empty(trim((string) $motivo))) {
            throw new InvalidArgumentException('El retroceso de estado operativo requiere obligatoriamente registrar un motivo formal.');
        }

        // Validar transición en la máquina de estados del Enum
        if (!$edicion->estado->puedeTransicionarA($nuevoEstado, $esRetroceso)) {
            throw new InvalidArgumentException(
                "Transición no permitida: no es posible pasar de '{$edicion->estado->value}' a '{$nuevoEstado->value}'."
            );
        }

        $edicionActualizada = new Edicion(
            id: $edicion->id,
            organizacionId: $edicion->organizacionId,
            codigo: $edicion->codigo,
            nombre: $edicion->nombre,
            anio: $edicion->anio,
            fechaInicio: $edicion->fechaInicio,
            fechaFin: $edicion->fechaFin,
            estado: $nuevoEstado,
            descripcion: $edicion->descripcion,
            esActual: $edicion->esActual,
            flyerOficialUrl: $edicion->flyerOficialUrl,
            configuracion: $edicion->configuracion,
            creadoEn: $edicion->creadoEn,
            actualizadoEn: $edicion->actualizadoEn
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $this->edicionRepo->actualizar($edicionActualizada, $actualizadoEnEsperado);

            $accionAuditoria = $esRetroceso ? 'RETROCEDER_ESTADO_EDICION' : 'AVANZAR_ESTADO_EDICION';

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'ediciones',
                accion: $accionAuditoria,
                entidadTipo: 'EDICION',
                entidadId: (string) $id,
                datosPrevios: ['estado' => $edicion->estado->value],
                datosNuevos: [
                    'estado' => $nuevoEstado->value,
                    'motivo' => $motivo,
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->edicionRepo->buscarPorId($id);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Establece formalmente una edición como la activa por defecto de la organización.
     *
     * @throws AccesoDenegadoExcepcion
     * @throws InvalidArgumentException
     */
    public function establecerActual(int $id, int $organizacionId, ContextoOperacion $contexto): Edicion
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.seleccionar_actual', $contexto);

        $edicion = $this->edicionRepo->buscarPorId($id);
        if ($edicion === null || $edicion->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion('La edición solicitada no existe o no pertenece a la organización.');
        }

        $edicionActualPrevia = $this->edicionRepo->obtenerActual($organizacionId);

        $this->edicionRepo->establecerComoActual($id, $organizacionId);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'ediciones',
            accion: 'SELECCIONAR_EDICION_ACTUAL',
            entidadTipo: 'EDICION',
            entidadId: (string) $id,
            datosPrevios: $edicionActualPrevia ? ['id' => $edicionActualPrevia->id, 'nombre' => $edicionActualPrevia->nombre] : null,
            datosNuevos: ['id' => $id, 'nombre' => $edicion->nombre, 'anio' => $edicion->anio]
        );

        return $this->edicionRepo->buscarPorId($id);
    }

    /**
     * Retorna una edición por su ID asegurando aislamiento de tenant.
     *
     * @throws AccesoDenegadoExcepcion
     */
    public function obtener(int $id, int $organizacionId, ContextoOperacion $contexto): ?Edicion
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.ver', $contexto);

        $edicion = $this->edicionRepo->buscarPorId($id);
        if ($edicion === null || $edicion->organizacionId !== $organizacionId) {
            return null;
        }

        return $edicion;
    }

    /**
     * Retorna el listado completo de ediciones de una organización.
     *
     * @return Edicion[]
     * @throws AccesoDenegadoExcepcion
     */
    public function listar(int $organizacionId, ContextoOperacion $contexto): array
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.ver', $contexto);

        return $this->edicionRepo->listarPorOrganizacion($organizacionId);
    }

    /**
     * Obtiene la edición operativa actual por defecto de una organización.
     *
     * @throws AccesoDenegadoExcepcion
     */
    public function obtenerActual(int $organizacionId, ContextoOperacion $contexto): ?Edicion
    {
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ediciones.ver', $contexto);

        return $this->edicionRepo->obtenerActual($organizacionId);
    }

    /**
     * Valida el alcance del operador sobre la organización (Anti-IDOR).
     */
    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
            throw new AccesoDenegadoExcepcion('Contexto organizacional ausente o inválido para la sesión activa.');
        }

        if ($contexto->usuarioId !== null) {
            if (!$this->authzServicio->verificarAlcanceOrganizacion($contexto->usuarioId, $organizacionId)) {
                throw new AccesoDenegadoExcepcion('Acceso denegado: el operador no cuenta con alcance sobre la organización especificada.');
            }
        }
    }

    /**
     * Valida que el operador cuente con el permiso RBAC requerido.
     */
    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId !== null) {
            if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
                throw new AccesoDenegadoExcepcion("Acceso denegado: se requiere el permiso '{$permiso}'.");
            }
        }
    }
}

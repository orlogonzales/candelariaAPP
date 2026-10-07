<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

use Aplicacion\Entidades\EntregaProducto;
use Aplicacion\Entidades\OperacionAsistencia;
use Aplicacion\Entidades\OperacionIncidencia;
use Aplicacion\Entidades\OperacionRecurso;
use Aplicacion\Entidades\OperacionSalida;
use Aplicacion\Entidades\OperacionSalidaPrestacion;
use Aplicacion\Entidades\OperacionSalidaRecurso;
use Aplicacion\Entidades\Proveedor;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OperacionRecursoRepositorio;
use Aplicacion\Repositorios\OperacionSalidaRepositorio;
use Aplicacion\Repositorios\ProveedorRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\TipoCapacidad;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Nucleo\Http\ContextoOperacion;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;
use RuntimeException;

/**
 * Servicio Central de Dominio de Operaciones, Salidas, Recursos y Check-in (F2.6D).
 * Autoridad soberana sobre los hechos físicos y logísticos de campo.
 */
class OperacionServicio
{
    private PDO $pdo;

    public function __construct(
        private OperacionSalidaRepositorio $salidaRepo,
        private OperacionRecursoRepositorio $recursoRepo,
        private ProveedorRepositorio $proveedorRepo,
        private ReservaRepositorio $reservaRepo,
        private EntregaProductoRepositorio $entregaRepo,
        private EdicionRepositorio $edicionRepo,
        private ItemComercialRepositorio $itemRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Crea y programa una nueva Salida Operativa de campo.
     */
    public function crearSalida(
        int $organizacionId,
        int $edicionId,
        int $itemComercialId,
        string $titulo,
        string $fechaSalida,
        string $horaCitacion,
        string $horaSalida,
        string $puntoEncuentro,
        TipoCapacidad $tipoCapacidad,
        ?int $capacidadMaxima = null,
        ?ContextoOperacion $contexto = null
    ): OperacionSalida {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.gestionar_salidas', $contexto);

        $edicion = $this->edicionRepo->buscarPorId($edicionId, $organizacionId);
        if ($edicion === null) {
            throw new InvalidArgumentException("La edición #{$edicionId} no existe en su organización.");
        }

        $item = $this->itemRepo->buscarPorId($itemComercialId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("El ítem comercial #{$itemComercialId} no existe en su organización.");
        }

        $anio = (int) date('Y', strtotime($fechaSalida));
        $correlativo = $this->salidaRepo->generarSiguienteCorrelativo($organizacionId, $anio);

        $salida = new OperacionSalida(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            itemComercialId: $itemComercialId,
            correlativo: $correlativo,
            titulo: trim($titulo),
            fechaSalida: trim($fechaSalida),
            horaCitacion: trim($horaCitacion),
            horaSalida: trim($horaSalida),
            puntoEncuentro: trim($puntoEncuentro),
            tipoCapacidad: $tipoCapacidad,
            capacidadMaxima: $capacidadMaxima,
            estado: EstadoSalida::PROGRAMADA,
            versionBloqueo: 1,
            creadoPor: $contexto->usuarioId ?? 1
        );

        $guardada = $this->salidaRepo->guardar($salida);

        $this->registrarAuditoria(
            $contexto,
            'SALIDA_CREADA',
            'operacion_salidas',
            (int) $guardada->id,
            [
                'correlativo'    => $guardada->correlativo,
                'fecha_salida'   => $guardada->fechaSalida,
                'tipo_capacidad' => $guardada->tipoCapacidad->value,
            ]
        );

        return $guardada;
    }

    /**
     * Asigna una prestación agendada de reserva a una salida operativa compatible.
     */
    public function asignarPrestacionASalida(
        int $organizacionId,
        int $salidaId,
        int $prestacionId,
        ?ContextoOperacion $contexto = null
    ): OperacionSalidaPrestacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.asignar_prestaciones', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if (!$salida->estado->permiteAsignacion()) {
            throw new InvalidArgumentException(
                "La salida #{$salida->correlativo} se encuentra en estado '{$salida->estado->value}' y no admite nuevas asignaciones."
            );
        }

        $prestacion = $this->reservaRepo->buscarPrestacionPorId($prestacionId);
        if ($prestacion === null) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no existe.");
        }

        $reserva = $this->reservaRepo->buscarPorId($prestacion->reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no pertenece a su organización.");
        }

        // 1. Compatibilidad de Edición
        if ($reserva->edicionId !== $salida->edicionId) {
            throw new InvalidArgumentException("La prestación pertenece a una edición distinta a la de la salida operativa.");
        }

        // 2. Compatibilidad de Servicio
        if ($prestacion->itemComercialId !== $salida->itemComercialId) {
            throw new InvalidArgumentException(
                "Incompatibilidad de servicio: La prestación es para el ítem #{$prestacion->itemComercialId} y la salida opera el ítem #{$salida->itemComercialId}."
            );
        }

        // 3. Compatibilidad de Fecha
        if ($prestacion->fechaServicio !== $salida->fechaSalida) {
            throw new InvalidArgumentException(
                "Incompatibilidad de fecha: La prestación está agendada para {$prestacion->fechaServicio} y la salida opera en {$salida->fechaSalida}."
            );
        }

        // 4. Estado de agendamiento
        if ($prestacion->estadoAgendamiento !== EstadoAgendamientoPrestacion::PROGRAMADA) {
            throw new InvalidArgumentException(
                "La prestación debe encontrarse en estado PROGRAMADA para ser asignada a una salida de campo (estado actual: {$prestacion->estadoAgendamiento->value})."
            );
        }

        // 5. No doble asignación activa
        $salidaActivaId = $this->salidaRepo->buscarSalidaActivaPorPrestacion($prestacionId);
        if ($salidaActivaId !== null) {
            throw new InvalidArgumentException(
                "La prestación #{$prestacionId} ya se encuentra asignada a la salida activa #{$salidaActivaId}."
            );
        }

        // 6. Validación transaccional de Capacidad
        $ocupacionActual = $this->salidaRepo->calcularOcupacionActiva($salidaId);
        $nuevaOcupacion = $ocupacionActual + $prestacion->cantidad;

        if ($salida->tipoCapacidad === TipoCapacidad::COLECTIVA) {
            if ($salida->capacidadMaxima !== null && $nuevaOcupacion > $salida->capacidadMaxima) {
                throw new InvalidArgumentException(
                    "Capacidad excedida: La salida admite hasta {$salida->capacidadMaxima} pasajeros. Ocupación actual: {$ocupacionActual}, solicitada: {$prestacion->cantidad}."
                );
            }
        } elseif ($salida->tipoCapacidad === TipoCapacidad::EXCLUSIVA) {
            if ($ocupacionActual > 0) {
                throw new InvalidArgumentException("Una salida de capacidad EXCLUSIVA no admite más de un grupo/prestación contratada.");
            }
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // Asignar prestación a la salida
            $asignacion = $this->salidaRepo->asignarPrestacion(
                salidaId: $salidaId,
                prestacionId: $prestacionId,
                cantidadPasajeros: $prestacion->cantidad,
                asignadoPor: $contexto->usuarioId ?? 1
            );

            // Registrar a cada participante en operacion_asistencias (PII operacional minimizada)
            $participantes = $this->reservaRepo->obtenerParticipantesPorReserva($reserva->id);
            foreach ($participantes as $p) {
                // Verificar si el participante está asignado a esta prestación
                if (in_array((int) $p->id, $prestacion->participanteIds, true)) {
                    $asistencia = new OperacionAsistencia(
                        id: null,
                        salidaId: $salidaId,
                        prestacionId: $prestacionId,
                        participanteId: (int) $p->id,
                        ubicacionAsiento: null,
                        estadoAsistencia: EstadoAsistencia::PENDIENTE
                    );
                    $this->salidaRepo->registrarAsistencia($asistencia);
                }
            }

            $this->registrarAuditoria(
                $contexto,
                'PRESTACION_ASIGNADA_A_SALIDA',
                'operacion_salidas',
                $salidaId,
                [
                    'prestacion_id'      => $prestacionId,
                    'cantidad_pasajeros' => $prestacion->cantidad,
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $asignacion;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Desasigna una prestación de una salida operativa.
     */
    public function desasignarPrestacionDeSalida(
        int $organizacionId,
        int $salidaId,
        int $prestacionId,
        ?ContextoOperacion $contexto = null
    ): void {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.asignar_prestaciones', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if (!$salida->estado->permiteAsignacion()) {
            throw new InvalidArgumentException(
                "No se puede desasignar prestaciones de una salida en estado '{$salida->estado->value}'."
            );
        }

        $this->salidaRepo->desasignarPrestacion($salidaId, $prestacionId);

        $this->registrarAuditoria(
            $contexto,
            'PRESTACION_DESASIGNADA_DE_SALIDA',
            'operacion_salidas',
            $salidaId,
            ['prestacion_id' => $prestacionId]
        );
    }

    /**
     * Asigna un recurso físico (vehículo/lancha) o una persona (guía/chofer) a la salida.
     */
    public function asignarRecursoASalida(
        int $organizacionId,
        int $salidaId,
        ?int $recursoFisicoId,
        ?int $personaId,
        RolOperativoRecurso $rolOperativo,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): OperacionSalidaRecurso {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.asignar_recursos', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if ($salida->estado->esTerminal()) {
            throw new InvalidArgumentException("No se pueden asignar recursos a una salida en estado terminal.");
        }

        if ($recursoFisicoId !== null) {
            $rec = $this->recursoRepo->buscarPorId($recursoFisicoId, $organizacionId);
            if ($rec === null) {
                throw new InvalidArgumentException("El recurso físico #{$recursoFisicoId} no existe en su organización.");
            }
            if ($rec->estado !== EstadoRecursoFisico::DISPONIBLE) {
                throw new InvalidArgumentException("El recurso físico '{$rec->codigoInterno}' no está disponible ({$rec->estado->value}).");
            }
        }

        if ($personaId !== null) {
            $stmtPer = $this->pdo->prepare("SELECT COUNT(*) FROM `personas` WHERE `id` = :id");
            $stmtPer->execute(['id' => $personaId]);
            if ((int) $stmtPer->fetchColumn() === 0) {
                throw new InvalidArgumentException("La persona #{$personaId} no existe en la base de datos.");
            }
        }

        $asignacion = new OperacionSalidaRecurso(
            id: null,
            salidaId: $salidaId,
            recursoFisicoId: $recursoFisicoId,
            personaId: $personaId,
            rolOperativo: $rolOperativo,
            notas: $notas,
            asignadoPor: $contexto->usuarioId ?? 1
        );

        $guardado = $this->salidaRepo->asignarRecurso($asignacion);

        $this->registrarAuditoria(
            $contexto,
            'RECURSO_ASIGNADO_A_SALIDA',
            'operacion_salidas',
            $salidaId,
            [
                'recurso_fisico_id' => $recursoFisicoId,
                'persona_id'        => $personaId,
                'rol_operativo'     => $rolOperativo->value,
            ]
        );

        return $guardado;
    }

    /**
     * Desasigna un recurso físico o persona de la salida operativa.
     */
    public function desasignarRecursoDeSalida(
        int $organizacionId,
        int $salidaId,
        int $salidaRecursoId,
        ?ContextoOperacion $contexto = null
    ): void {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.asignar_recursos', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if ($salida->estado->esTerminal()) {
            throw new InvalidArgumentException("No se pueden modificar recursos de una salida en estado terminal.");
        }

        $this->salidaRepo->desasignarRecurso($salidaRecursoId);

        $this->registrarAuditoria(
            $contexto,
            'RECURSO_DESASIGNADO_DE_SALIDA',
            'operacion_salidas',
            $salidaId,
            ['salida_recurso_id' => $salidaRecursoId]
        );
    }

    /**
     * Consulta las prestaciones agendadas compatibles y disponibles para ser asignadas a esta salida.
     */
    public function obtenerPrestacionesCompatiblesParaSalida(
        int $organizacionId,
        int $salidaId,
        ?ContextoOperacion $contexto = null
    ): array {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.ver', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        $ocupacionActual = $this->salidaRepo->calcularOcupacionActiva($salidaId);
        $capacidadDisponible = ($salida->capacidadMaxima !== null)
            ? max(0, $salida->capacidadMaxima - (int) $ocupacionActual)
            : 999;

        $stmt = $this->pdo->prepare("
            SELECT
                rp.id,
                rp.reserva_id,
                rp.concepto_codigo,
                rp.concepto_nombre,
                rp.cantidad,
                rp.tipo_capacidad,
                rp.fecha_servicio,
                rp.hora_servicio,
                rp.punto_encuentro,
                r.correlativo AS reserva_correlativo,
                r.contacto_nombre AS titular_reserva,
                (SELECT COUNT(*) FROM `prestacion_participantes` pp WHERE pp.prestacion_id = rp.id) AS participantes_registrados
            FROM `reserva_prestaciones` rp
            INNER JOIN `reservas` r ON r.id = rp.reserva_id
            WHERE r.organizacion_id = :org_id
              AND r.edicion_id = :edicion_id
              AND rp.item_comercial_id = :item_id
              AND rp.fecha_servicio = :fecha_salida
              AND rp.estado_agendamiento = 'PROGRAMADA'
              AND r.estado <> 'CANCELADA'
              AND rp.id NOT IN (
                  SELECT osp.prestacion_id
                  FROM `operacion_salida_prestaciones` osp
                  INNER JOIN `operacion_salidas` os ON os.id = osp.salida_id
                  WHERE os.estado <> 'CANCELADA'
              )
            ORDER BY rp.hora_servicio ASC, rp.id ASC
        ");

        $stmt->execute([
            'org_id'        => $organizacionId,
            'edicion_id'    => $salida->edicionId,
            'item_id'       => $salida->itemComercialId,
            'fecha_salida'  => $salida->fechaSalida,
        ]);

        $candidatas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($c) use ($capacidadDisponible, $salida, $ocupacionActual) {
            $cant = (float) $c['cantidad'];
            $esCompatibleCapacidad = true;
            $motivoIncompatibilidad = null;

            if ($salida->tipoCapacidad === TipoCapacidad::COLECTIVA && $salida->capacidadMaxima !== null) {
                if ($cant > $capacidadDisponible) {
                    $esCompatibleCapacidad = false;
                    $motivoIncompatibilidad = "Excede capacidad disponible ({$cant} pax > {$capacidadDisponible} libres)";
                }
            } elseif ($salida->tipoCapacidad === TipoCapacidad::EXCLUSIVA && $ocupacionActual > 0) {
                $esCompatibleCapacidad = false;
                $motivoIncompatibilidad = "Salida exclusiva ya tiene grupo asignado";
            }

            $c['es_compatible'] = $esCompatibleCapacidad;
            $c['motivo_incompatibilidad'] = $motivoIncompatibilidad;
            return $c;
        }, $candidatas);
    }

    /**
     * Registra el check-in (presencia, no-show y asignación de asiento) de un participante individual.
     */
    public function marcarCheckin(
        int $organizacionId,
        int $salidaId,
        int $participanteId,
        EstadoAsistencia $estadoAsistencia,
        ?string $asiento = null,
        ?string $observacion = null,
        ?ContextoOperacion $contexto = null
    ): OperacionAsistencia {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.checkin', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if (!$salida->estado->permiteCheckin()) {
            throw new InvalidArgumentException(
                "La salida #{$salida->correlativo} se encuentra en estado '{$salida->estado->value}' y no admite registro de check-in."
            );
        }

        // Si la salida es de capacidad DISCRETA y se marca PRESENTE con asiento, verificar que no esté ocupado
        if ($salida->tipoCapacidad === TipoCapacidad::DISCRETA && $asiento !== null && $asiento !== '') {
            $asientoTrim = strtoupper(trim($asiento));
            $asistencias = $this->salidaRepo->obtenerAsistenciasPorSalida($salidaId);
            foreach ($asistencias as $asist) {
                if ($asist->participanteId !== $participanteId &&
                    $asist->ubicacionAsiento !== null &&
                    strtoupper($asist->ubicacionAsiento) === $asientoTrim) {
                    throw new InvalidArgumentException("El asiento '{$asientoTrim}' ya se encuentra ocupado por otro pasajero en esta salida.");
                }
            }
            $asiento = $asientoTrim;
        }

        $actualizado = $this->salidaRepo->actualizarCheckin(
            salidaId: $salidaId,
            participanteId: $participanteId,
            nuevoEstado: $estadoAsistencia,
            asiento: $asiento,
            marcadoPor: $contexto->usuarioId ?? 1,
            observacion: $observacion
        );

        $this->registrarAuditoria(
            $contexto,
            'CHECKIN_REGISTRADO',
            'operacion_salidas',
            $salidaId,
            [
                'participante_id'   => $participanteId,
                'estado_asistencia' => $estadoAsistencia->value,
                'ubicacion_asiento' => $asiento,
            ]
        );

        return $actualizado;
    }

    /**
     * Transiciona la salida al estado DESPACHADA iniciando la ejecución de campo.
     */
    public function despacharSalida(
        int $organizacionId,
        int $salidaId,
        int $versionEsperada,
        ?ContextoOperacion $contexto = null
    ): OperacionSalida {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.ejecutar', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if (!$salida->estado->permiteDespacho()) {
            throw new InvalidArgumentException("Únicamente salidas PROGRAMADA o EN_CHECKIN pueden ser despachadas.");
        }

        $ahora = date('Y-m-d H:i:s');
        $actualizada = $this->salidaRepo->actualizarEstado(
            salidaId: $salidaId,
            nuevoEstado: EstadoSalida::DESPACHADA,
            versionEsperada: $versionEsperada,
            horaInicioReal: $ahora
        );

        $this->registrarAuditoria(
            $contexto,
            'SALIDA_DESPACHADA',
            'operacion_salidas',
            $salidaId,
            ['hora_inicio_real' => $ahora]
        );

        return $actualizada;
    }

    /**
     * Finaliza la salida operativa tras la ejecución exitosa de los servicios de campo.
     */
    public function finalizarSalida(
        int $organizacionId,
        int $salidaId,
        int $versionEsperada,
        ?ContextoOperacion $contexto = null
    ): OperacionSalida {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.ejecutar', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if ($salida->estado !== EstadoSalida::DESPACHADA) {
            throw new InvalidArgumentException("Únicamente una salida en estado DESPACHADA puede ser finalizada.");
        }

        $ahora = date('Y-m-d H:i:s');
        $actualizada = $this->salidaRepo->actualizarEstado(
            salidaId: $salidaId,
            nuevoEstado: EstadoSalida::FINALIZADA,
            versionEsperada: $versionEsperada,
            horaFinReal: $ahora
        );

        $this->registrarAuditoria(
            $contexto,
            'SALIDA_FINALIZADA',
            'operacion_salidas',
            $salidaId,
            ['hora_fin_real' => $ahora]
        );

        return $actualizada;
    }

    /**
     * Interrumpe la salida operativa por condiciones de fuerza mayor o incidencias graves de campo.
     */
    public function interrumpirSalida(
        int $organizacionId,
        int $salidaId,
        string $motivo,
        int $versionEsperada,
        ?ContextoOperacion $contexto = null
    ): OperacionSalida {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.ejecutar', $contexto);

        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new InvalidArgumentException("El motivo de interrupción es obligatorio.");
        }

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if ($salida->estado !== EstadoSalida::DESPACHADA) {
            throw new InvalidArgumentException("Únicamente una salida en ejecución (DESPACHADA) puede ser interrumpida.");
        }

        $ahora = date('Y-m-d H:i:s');
        $actualizada = $this->salidaRepo->actualizarEstado(
            salidaId: $salidaId,
            nuevoEstado: EstadoSalida::INTERRUMPIDA,
            versionEsperada: $versionEsperada,
            motivo: $motivoTrim,
            horaFinReal: $ahora
        );

        $this->registrarAuditoria(
            $contexto,
            'SALIDA_INTERRUMPIDA',
            'operacion_salidas',
            $salidaId,
            ['motivo' => $motivoTrim]
        );

        return $actualizada;
    }

    /**
     * Cancela una salida operativa antes de su despacho de campo.
     */
    public function cancelarSalida(
        int $organizacionId,
        int $salidaId,
        string $motivo,
        int $versionEsperada,
        ?ContextoOperacion $contexto = null
    ): OperacionSalida {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.gestionar_salidas', $contexto);

        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new InvalidArgumentException("El motivo de cancelación es obligatorio.");
        }

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        if ($salida->estado === EstadoSalida::FINALIZADA || $salida->estado === EstadoSalida::CANCELADA) {
            throw new InvalidArgumentException("No se puede cancelar una salida que ya se encuentra en estado terminal.");
        }

        if ($salida->estado === EstadoSalida::DESPACHADA) {
            throw new InvalidArgumentException("Una salida despachada en ejecución de campo no puede ser cancelada; debe ser interrumpida si surge un impedimento.");
        }

        $actualizada = $this->salidaRepo->actualizarEstado(
            salidaId: $salidaId,
            nuevoEstado: EstadoSalida::CANCELADA,
            versionEsperada: $versionEsperada,
            motivo: $motivoTrim
        );

        $this->registrarAuditoria(
            $contexto,
            'SALIDA_CANCELADA',
            'operacion_salidas',
            $salidaId,
            ['motivo' => $motivoTrim]
        );

        return $actualizada;
    }

    /**
     * Registra una incidencia operativa ocurrida durante la salida.
     */
    public function registrarIncidencia(
        int $organizacionId,
        int $salidaId,
        TipoIncidenciaOperativa $tipoIncidencia,
        string $descripcion,
        ?string $accionesTomadas = null,
        bool $afectoContinuidad = false,
        ?ContextoOperacion $contexto = null
    ): OperacionIncidencia {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.registrar_incidencias', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        $incidencia = new OperacionIncidencia(
            id: null,
            salidaId: $salidaId,
            tipoIncidencia: $tipoIncidencia,
            descripcion: $descripcion,
            accionesTomadas: $accionesTomadas,
            afectoContinuidad: $afectoContinuidad,
            registradoPor: $contexto->usuarioId ?? 1
        );

        $guardada = $this->salidaRepo->registrarIncidencia($incidencia);

        $this->registrarAuditoria(
            $contexto,
            'INCIDENCIA_REGISTRADA',
            'operacion_salidas',
            $salidaId,
            [
                'incidencia_id'      => $guardada->id,
                'tipo_incidencia'    => $tipoIncidencia->value,
                'afecto_continuidad' => $afectoContinuidad,
            ]
        );

        return $guardada;
    }

    /**
     * Construye determinísticamente el Manifiesto de Pasajeros de la salida (PII operacional minimizada).
     */
    public function obtenerManifiesto(int $organizacionId, int $salidaId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('operacion.ver', $contexto);

        $salida = $this->salidaRepo->buscarPorId($salidaId, $organizacionId);
        if ($salida === null) {
            throw new InvalidArgumentException("La salida operativa #{$salidaId} no existe en su organización.");
        }

        $asistencias = $this->salidaRepo->obtenerAsistenciasPorSalida($salidaId);
        $prestaciones = $this->salidaRepo->obtenerPrestacionesPorSalida($salidaId);
        $recursos = $this->salidaRepo->obtenerRecursosPorSalida($salidaId);

        $stmtPasajeros = $this->pdo->prepare("
            SELECT 
                oa.id AS asistencia_id,
                oa.salida_id,
                oa.prestacion_id,
                oa.participante_id,
                oa.ubicacion_asiento,
                oa.estado_asistencia,
                oa.marcado_en,
                rp.nombres,
                rp.apellidos,
                rp.numero_documento,
                rp.nacionalidad,
                rp.rango_etario,
                rp.regimen_alimentario,
                rp.requiere_asistencia_movilidad,
                rp.telefono_contacto,
                r.correlativo AS reserva_correlativo,
                r.contacto_nombre AS contratante_reserva
            FROM `operacion_asistencias` oa
            INNER JOIN `reserva_participantes` rp ON rp.id = oa.participante_id
            INNER JOIN `reservas` r ON r.id = rp.reserva_id
            WHERE oa.salida_id = :salida_id
            ORDER BY oa.ubicacion_asiento ASC, rp.apellidos ASC
        ");
        $stmtPasajeros->execute(['salida_id' => $salidaId]);
        $pasajeros = $stmtPasajeros->fetchAll(PDO::FETCH_ASSOC);

        return [
            'salida'        => $salida,
            'total_pax'     => count($pasajeros),
            'presentes'     => count(array_filter($pasajeros, fn($p) => $p['estado_asistencia'] === 'PRESENTE')),
            'no_show'       => count(array_filter($pasajeros, fn($p) => $p['estado_asistencia'] === 'NO_SHOW')),
            'pendientes'    => count(array_filter($pasajeros, fn($p) => $p['estado_asistencia'] === 'PENDIENTE')),
            'pasajeros'     => $pasajeros,
            'recursos'      => $recursos,
            'prestaciones'  => $prestaciones,
        ];
    }

    /**
     * Proyección Determinista hacia la Reserva:
     * Operación produce hechos físicos; Reserva consume y proyecta el resultado administrativo.
     */
    public function proyectarResultadoReserva(int $organizacionId, int $reservaId): ResultadoProyeccionReserva
    {
        $reserva = $this->reservaRepo->buscarPorId($reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La reserva #{$reservaId} no existe en su organización.");
        }

        $prestaciones = $this->reservaRepo->obtenerPrestacionesPorReserva($reservaId);
        if (empty($prestaciones)) {
            return ResultadoProyeccionReserva::CANCELADA;
        }

        $prestacionesEvaluadas = 0;
        $prestacionesCumplidas = 0;
        $prestacionesNoShow = 0;
        $prestacionesInterrumpidas = 0;

        foreach ($prestaciones as $prest) {
            $stmtAsist = $this->pdo->prepare("
                SELECT oa.estado_asistencia, os.estado AS salida_estado
                FROM `operacion_asistencias` oa
                INNER JOIN `operacion_salidas` os ON os.id = oa.salida_id
                WHERE oa.prestacion_id = :prest_id
            ");
            $stmtAsist->execute(['prest_id' => $prest->id]);
            $filas = $stmtAsist->fetchAll(PDO::FETCH_ASSOC);

            if (empty($filas)) {
                // No ha sido operada aún
                continue;
            }

            $prestacionesEvaluadas++;

            // Verificar estado de la salida
            $salidaEstado = $filas[0]['salida_estado'];
            if ($salidaEstado === 'INTERRUMPIDA') {
                $prestacionesInterrumpidas++;
                continue;
            }

            $todosPresentes = true;
            $todosNoShow = true;

            foreach ($filas as $f) {
                if ($f['estado_asistencia'] === 'PRESENTE') {
                    $todosNoShow = false;
                } else {
                    $todosPresentes = false;
                }
            }

            if ($todosPresentes && $salidaEstado === 'FINALIZADA') {
                $prestacionesCumplidas++;
            } elseif ($todosNoShow) {
                $prestacionesNoShow++;
            }
        }

        if ($prestacionesEvaluadas === 0) {
            return ResultadoProyeccionReserva::CUMPLIDA_PARCIAL;
        }

        if ($prestacionesInterrumpidas > 0) {
            return ResultadoProyeccionReserva::INTERRUMPIDA;
        }

        if ($prestacionesCumplidas === count($prestaciones)) {
            return ResultadoProyeccionReserva::CUMPLIDA;
        }

        if ($prestacionesNoShow === count($prestaciones)) {
            return ResultadoProyeccionReserva::NO_SHOW_TOTAL;
        }

        return ResultadoProyeccionReserva::CUMPLIDA_PARCIAL;
    }

    /**
     * Despacho y entrega de bienes tangibles vendidos (entregas_productos).
     */
    public function despacharEntrega(
        int $organizacionId,
        int $entregaId,
        ?ContextoOperacion $contexto = null
    ): EntregaProducto {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('entregas.despachar', $contexto);

        $entrega = $this->entregaRepo->buscarPorId($entregaId);
        if ($entrega === null || $entrega->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La orden de entrega #{$entregaId} no existe en su organización.");
        }

        if ($entrega->estado !== \Aplicacion\Reservas\EstadoEntregaProducto::PENDIENTE_ENTREGA) {
            throw new InvalidArgumentException("Solo se pueden despachar entregas en estado PENDIENTE_ENTREGA (estado actual: {$entrega->estado->value}).");
        }

        $ahora = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            UPDATE `entregas_productos`
            SET `estado` = 'ENTREGADO',
                `fecha_entrega` = :fecha,
                `entregado_por` = :usuario_id,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'         => $entregaId,
            'org_id'     => $organizacionId,
            'fecha'      => $ahora,
            'usuario_id' => $contexto->usuarioId ?? 1,
        ]);

        $this->registrarAuditoria(
            $contexto,
            'ENTREGA_DESPACHADA',
            'entregas_productos',
            $entregaId,
            [
                'correlativo'   => $entrega->correlativo,
                'fecha_entrega' => $ahora,
            ]
        );

        return $this->entregaRepo->buscarPorId($entregaId)
            ?? throw new RuntimeException("No se pudo recargar la entrega #{$entregaId}.");
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

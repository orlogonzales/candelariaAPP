<?php

declare(strict_types=1);

namespace Aplicacion\Reservas;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\EntregaItem;
use Aplicacion\Entidades\EntregaProducto;
use Aplicacion\Entidades\ItemConfiguracionOperativa;
use Aplicacion\Entidades\Reserva;
use Aplicacion\Entidades\ReservaParticipante;
use Aplicacion\Entidades\ReservaPrestacion;
use Aplicacion\Entidades\ReservaReprogramacion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\EntregaProductoRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Servicio Central de Dominio de Reservas (F2.6C).
 * Implementa la formalización de compromisos de servicio desde ventas confirmadas,
 * bifurcación limpia de bienes tangibles hacia órdenes de entrega,
 * agendamiento diferido, reprogramación inmutable y gestión de participantes con PII operacional minimizada.
 */
class ReservaServicio
{
    private PDO $pdo;

    public function __construct(
        private ReservaRepositorio $reservaRepo,
        private EntregaProductoRepositorio $entregaRepo,
        private ItemConfiguracionOperativaRepositorio $itemCfgRepo,
        private VentaRepositorio $ventaRepo,
        private EdicionRepositorio $edicionRepo,
        private ItemComercialRepositorio $itemRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Formaliza una Venta CONFIRMADA generando concurrentemente:
     * 1. Una cabecera de Reserva con sus prestaciones (para componentes de tipo SERVICIO que requieren reserva).
     * 2. Una cabecera de Orden de Entrega con sus ítems (para componentes de tipo PRODUCTO).
     *
     * @return array{reserva: ?Reserva, entrega: ?EntregaProducto}
     */
    public function formalizarDesdeVenta(
        int $organizacionId,
        int $ventaId,
        ?ContextoOperacion $contexto = null
    ): array {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('reservas.crear_desde_venta', $contexto);

        $transaccionIniciada = false;
        $savepoint = null;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        } else {
            $savepoint = 'sp_rsv_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT {$savepoint}");
        }

        try {
            // 1. Bloqueo pesimista de fila FOR UPDATE sobre la venta
            $stmtVta = $this->pdo->prepare("
                SELECT * FROM `ventas`
                WHERE `id` = :id AND `organizacion_id` = :org_id
                FOR UPDATE
            ");
            $stmtVta->execute(['id' => $ventaId, 'org_id' => $organizacionId]);
            $filaVenta = $stmtVta->fetch(PDO::FETCH_ASSOC);

            if (!$filaVenta) {
                throw new InvalidArgumentException("La venta #{$ventaId} no existe en la organización especificada.");
            }

            // 2. Validación de estado: Únicamente ventas CONFIRMADA pueden generar reserva
            if ($filaVenta['estado'] !== EstadoVenta::CONFIRMADA->value) {
                throw new InvalidArgumentException(
                    "Únicamente una venta en estado CONFIRMADA puede formalizar reservas u órdenes de entrega (estado actual: {$filaVenta['estado']})."
                );
            }

            // 3. Regla 1:1 e Idempotencia: Verificar que no exista ya una reserva ni una entrega para esta venta
            $reservaExistente = $this->reservaRepo->buscarPorVentaId($ventaId);
            if ($reservaExistente !== null) {
                throw new RuntimeException("La venta #{$ventaId} ya cuenta con una reserva generada (#{$reservaExistente->correlativo}).");
            }

            $entregaExistente = $this->entregaRepo->buscarPorVentaId($ventaId);
            if ($entregaExistente !== null) {
                throw new RuntimeException("La venta #{$ventaId} ya cuenta con una orden de entrega generada (#{$entregaExistente->correlativo}).");
            }

            // 4. Cargar la venta completa con sus líneas y componentes
            $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
            if ($venta === null) {
                throw new InvalidArgumentException("No se pudieron cargar los datos de la venta #{$ventaId}.");
            }

            // 5. Recopilar todos los item_comercial_id involucrados para validar configuración operativa (Fail-Closed)
            $itemIds = [];
            foreach ($venta->lineas as $linea) {
                if ($linea->tipoLinea->value === 'ITEM' && $linea->itemComercialId !== null) {
                    $itemIds[] = $linea->itemComercialId;
                } elseif ($linea->tipoLinea->value === 'PAQUETE') {
                    foreach ($linea->componentes as $comp) {
                        if ($comp->itemComercialId !== null) {
                            $itemIds[] = $comp->itemComercialId;
                        }
                    }
                }
            }

            $configs = $this->itemCfgRepo->buscarPorItemIds($itemIds);

            // 6. Bifurcación en prestaciones reservables vs ítems de entrega
            $prestacionesParaCrear = [];
            $itemsEntregaParaCrear = [];

            foreach ($venta->lineas as $linea) {
                if ($linea->tipoLinea->value === 'ITEM') {
                    $itemId = $linea->itemComercialId;
                    if ($itemId === null || !isset($configs[$itemId])) {
                        throw new RuntimeException(
                            "El ítem comercial '{$linea->conceptoCodigo}' carece de configuración operativa explícita. Operación abortada (Fail-Closed)."
                        );
                    }
                    $cfg = $configs[$itemId];

                    if ($cfg->requiereReserva) {
                        $prestacionesParaCrear[] = new ReservaPrestacion(
                            id: null,
                            reservaId: 0,
                            ventaLineaId: (int) $linea->id,
                            ventaLineaComponenteId: null,
                            itemComercialId: $itemId,
                            conceptoCodigo: $linea->conceptoCodigo,
                            conceptoNombre: $linea->conceptoNombre,
                            unidadMedida: $linea->unidadMedida,
                            cantidad: (float) $linea->cantidad,
                            tipoCapacidad: $cfg->tipoCapacidad,
                            requiereAgendamiento: $cfg->requiereAgendamiento,
                            requiereParticipantes: $cfg->requiereParticipantes,
                            estadoAgendamiento: EstadoAgendamientoPrestacion::PENDIENTE_PROGRAMAR,
                            fechaServicio: null,
                            horaServicio: null,
                            puntoEncuentro: $cfg->puntoPartidaPredeterminado,
                            notasPrestacion: null
                        );
                    } else {
                        $itemsEntregaParaCrear[] = new EntregaItem(
                            id: null,
                            entregaId: 0,
                            ventaLineaId: (int) $linea->id,
                            ventaLineaComponenteId: null,
                            itemComercialId: $itemId,
                            conceptoCodigo: $linea->conceptoCodigo,
                            conceptoNombre: $linea->conceptoNombre,
                            unidadMedida: $linea->unidadMedida,
                            cantidad: (float) $linea->cantidad
                        );
                    }
                } elseif ($linea->tipoLinea->value === 'PAQUETE') {
                    foreach ($linea->componentes as $comp) {
                        $itemId = $comp->itemComercialId;
                        if ($itemId === null || !isset($configs[$itemId])) {
                            throw new RuntimeException(
                                "El componente '{$comp->itemCodigo}' del paquete '{$linea->conceptoCodigo}' carece de configuración operativa explícita. Operación abortada (Fail-Closed)."
                            );
                        }
                        $cfg = $configs[$itemId];

                        // Cantidad total = cantidad del paquete vendida * cantidad de componentes incluidos
                        $cantidadEfectiva = (float) $linea->cantidad * (float) $comp->cantidad;

                        if ($cfg->requiereReserva) {
                            $prestacionesParaCrear[] = new ReservaPrestacion(
                                id: null,
                                reservaId: 0,
                                ventaLineaId: (int) $linea->id,
                                ventaLineaComponenteId: (int) $comp->id,
                                itemComercialId: $itemId,
                                conceptoCodigo: $comp->itemCodigo,
                                conceptoNombre: $comp->itemNombre,
                                unidadMedida: $comp->unidadMedida,
                                cantidad: $cantidadEfectiva,
                                tipoCapacidad: $cfg->tipoCapacidad,
                                requiereAgendamiento: $cfg->requiereAgendamiento,
                                requiereParticipantes: $cfg->requiereParticipantes,
                                estadoAgendamiento: EstadoAgendamientoPrestacion::PENDIENTE_PROGRAMAR,
                                fechaServicio: null,
                                horaServicio: null,
                                puntoEncuentro: $cfg->puntoPartidaPredeterminado,
                                notasPrestacion: $comp->nota
                            );
                        } else {
                            $itemsEntregaParaCrear[] = new EntregaItem(
                                id: null,
                                entregaId: 0,
                                ventaLineaId: (int) $linea->id,
                                ventaLineaComponenteId: (int) $comp->id,
                                itemComercialId: $itemId,
                                conceptoCodigo: $comp->itemCodigo,
                                conceptoNombre: $comp->itemNombre,
                                unidadMedida: $comp->unidadMedida,
                                cantidad: $cantidadEfectiva
                            );
                        }
                    }
                }
            }

            // 7. Generación de Reserva si existen prestaciones de servicio
            $reservaCreada = null;
            if (!empty($prestacionesParaCrear)) {
                $anio = (int) date('Y');
                $correlativoReserva = $this->reservaRepo->generarSiguienteCorrelativo($organizacionId, $anio);

                // Determinar estado inicial de la reserva
                $requiereParticipantesAlguna = false;
                foreach ($prestacionesParaCrear as $p) {
                    if ($p->requiereParticipantes) {
                        $requiereParticipantesAlguna = true;
                        break;
                    }
                }
                $estadoInicial = $requiereParticipantesAlguna
                    ? EstadoReserva::PENDIENTE_DATOS
                    : EstadoReserva::REGISTRADA;

                $reserva = new Reserva(
                    id: null,
                    organizacionId: $organizacionId,
                    edicionId: $venta->edicionId,
                    ventaId: $ventaId,
                    clienteId: $venta->clienteId,
                    correlativo: $correlativoReserva,
                    estado: $estadoInicial,
                    contactoNombre: $venta->clienteNombreCompleto,
                    contactoTipoDocumento: $venta->clienteTipoDocumento,
                    contactoNumeroDocumento: $venta->clienteNumeroDocumento,
                    contactoTelefono: $venta->clienteTelefono,
                    contactoEmail: $venta->clienteEmail,
                    notasOperativas: null,
                    versionBloqueo: 1,
                    creadoPor: $contexto->usuarioId ?? 1,
                    prestaciones: $prestacionesParaCrear
                );

                $reservaCreada = $this->reservaRepo->guardar($reserva);

                $this->registrarAuditoria(
                    $contexto,
                    'RESERVA_GENERADA',
                    'reservas',
                    (int) $reservaCreada->id,
                    [
                        'correlativo'         => $correlativoReserva,
                        'venta_id'            => $ventaId,
                        'total_prestaciones' => count($prestacionesParaCrear),
                    ]
                );
            }

            // 8. Generación de Orden de Entrega si existen productos físicos tangibles
            $entregaCreada = null;
            if (!empty($itemsEntregaParaCrear)) {
                $anio = (int) date('Y');
                $correlativoEntrega = $this->entregaRepo->generarSiguienteCorrelativo($organizacionId, $anio);

                $entrega = new EntregaProducto(
                    id: null,
                    organizacionId: $organizacionId,
                    edicionId: $venta->edicionId,
                    ventaId: $ventaId,
                    clienteId: $venta->clienteId,
                    correlativo: $correlativoEntrega,
                    estado: EstadoEntregaProducto::PENDIENTE_ENTREGA,
                    contactoNombre: $venta->clienteNombreCompleto,
                    contactoTelefono: $venta->clienteTelefono,
                    direccionEntrega: null,
                    fechaEntrega: null,
                    entregadoPor: null,
                    notasDespacho: null,
                    versionBloqueo: 1,
                    creadoPor: $contexto->usuarioId ?? 1,
                    items: $itemsEntregaParaCrear
                );

                $entregaCreada = $this->entregaRepo->guardar($entrega);

                $this->registrarAuditoria(
                    $contexto,
                    'ENTREGA_GENERADA',
                    'entregas_productos',
                    (int) $entregaCreada->id,
                    [
                        'correlativo'   => $correlativoEntrega,
                        'venta_id'      => $ventaId,
                        'total_items'   => count($itemsEntregaParaCrear),
                    ]
                );
            }

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return [
                'reserva' => $reservaCreada,
                'entrega' => $entregaCreada,
            ];
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            } elseif ($savepoint !== null && $this->pdo->inTransaction()) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            throw $e;
        }
    }

    /**
     * Asigna fecha calendario y horario de ejecución a una prestación pendiente de agendamiento.
     */
    public function programarPrestacion(
        int $organizacionId,
        int $prestacionId,
        string $fechaServicio,
        ?string $horaServicio = null,
        ?string $puntoEncuentro = null,
        ?ContextoOperacion $contexto = null
    ): ReservaPrestacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('reservas.programar', $contexto);

        $prestacion = $this->reservaRepo->buscarPrestacionPorId($prestacionId);
        if ($prestacion === null) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no existe.");
        }

        $reserva = $this->reservaRepo->buscarPorId($prestacion->reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no pertenece a su organización.");
        }

        if ($prestacion->estadoAgendamiento !== EstadoAgendamientoPrestacion::PENDIENTE_PROGRAMAR) {
            throw new InvalidArgumentException(
                "Únicamente prestaciones en PENDIENTE_PROGRAMAR pueden agendarse por primera vez. Utilice reprogramarPrestacion para modificaciones."
            );
        }

        $fechaTrim = trim($fechaServicio);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTrim)) {
            throw new InvalidArgumentException("Formato de fecha de servicio inválido (debe ser YYYY-MM-DD).");
        }

        $prestacionActualizada = $this->reservaRepo->actualizarAgendamientoPrestacion(
            prestacionId: $prestacionId,
            fechaServicio: $fechaTrim,
            horaServicio: $horaServicio,
            puntoEncuentro: $puntoEncuentro,
            versionEsperada: $prestacion->versionBloqueo
        );

        $this->registrarAuditoria(
            $contexto,
            'PRESTACION_PROGRAMADA',
            'reserva_prestaciones',
            $prestacionId,
            [
                'fecha_servicio' => $fechaTrim,
                'hora_servicio'  => $horaServicio,
            ]
        );

        return $prestacionActualizada;
    }

    /**
     * Reprograma una prestación previamente programada con registro append-only obligatorio.
     */
    public function reprogramarPrestacion(
        int $organizacionId,
        int $prestacionId,
        string $nuevaFecha,
        ?string $nuevaHora,
        MotivoReprogramacion $motivoCategoria,
        string $motivoDetalle,
        ?ContextoOperacion $contexto = null
    ): ReservaPrestacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('reservas.reprogramar', $contexto);

        $prestacion = $this->reservaRepo->buscarPrestacionPorId($prestacionId);
        if ($prestacion === null) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no existe.");
        }

        $reserva = $this->reservaRepo->buscarPorId($prestacion->reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La prestación #{$prestacionId} no pertenece a su organización.");
        }

        if ($prestacion->estadoAgendamiento !== EstadoAgendamientoPrestacion::PROGRAMADA) {
            throw new InvalidArgumentException(
                "Únicamente prestaciones en estado PROGRAMADA pueden ser reprogramadas (estado actual: {$prestacion->estadoAgendamiento->value})."
            );
        }

        $fechaTrim = trim($nuevaFecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTrim)) {
            throw new InvalidArgumentException("Formato de fecha inválido (debe ser YYYY-MM-DD).");
        }

        $detalleTrim = trim($motivoDetalle);
        if ($detalleTrim === '') {
            throw new InvalidArgumentException("El detalle del motivo de reprogramación es obligatorio.");
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. Registrar histórico append-only inmutable
            $reprog = new ReservaReprogramacion(
                id: null,
                prestacionId: $prestacionId,
                fechaAnterior: $prestacion->fechaServicio,
                fechaNueva: $fechaTrim,
                horaAnterior: $prestacion->horaServicio,
                horaNueva: $nuevaHora ?? $prestacion->horaServicio,
                motivoCategoria: $motivoCategoria,
                motivoDetalle: $detalleTrim,
                creadoPor: $contexto->usuarioId ?? 1
            );
            $this->reservaRepo->registrarReprogramacion($reprog);

            // 2. Actualizar prestación: permanece en estado PROGRAMADA con nueva fecha y version incrementada
            $prestacionActualizada = $this->reservaRepo->actualizarAgendamientoPrestacion(
                prestacionId: $prestacionId,
                fechaServicio: $fechaTrim,
                horaServicio: $nuevaHora ?? $prestacion->horaServicio,
                puntoEncuentro: $prestacion->puntoEncuentro,
                versionEsperada: $prestacion->versionBloqueo
            );

            $this->registrarAuditoria(
                $contexto,
                'PRESTACION_REPROGRAMADA',
                'reserva_prestaciones',
                $prestacionId,
                [
                    'fecha_anterior'   => $prestacion->fechaServicio,
                    'fecha_nueva'      => $fechaTrim,
                    'motivo_categoria' => $motivoCategoria->value,
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $prestacionActualizada;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra un participante beneficiario de la reserva y opcionalmente lo asigna a prestaciones específicas.
     */
    public function registrarParticipante(
        int $organizacionId,
        int $reservaId,
        array $datos,
        array $prestacionIds = [],
        ?ContextoOperacion $contexto = null
    ): ReservaParticipante {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('reservas.gestionar_participantes', $contexto);

        $reserva = $this->reservaRepo->buscarPorId($reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La reserva #{$reservaId} no existe en su organización.");
        }

        if ($reserva->estado === EstadoReserva::CANCELADA) {
            throw new InvalidArgumentException("No se pueden registrar participantes en una reserva CANCELADA.");
        }

        // Validación y sanitización estricta (Minimización de datos: PII operacional legítimamente necesaria, sin campos médicos libres)
        $nombres = trim((string) ($datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $tipoDocId = (int) ($datos['tipo_documento_id'] ?? 0);
        $numDoc = trim((string) ($datos['numero_documento'] ?? ''));

        if ($nombres === '' || $apellidos === '' || $numDoc === '' || $tipoDocId <= 0) {
            throw new InvalidArgumentException("Nombres, apellidos, tipo de documento y número de documento son obligatorios.");
        }

        $nacionalidad = isset($datos['nacionalidad']) && trim((string) $datos['nacionalidad']) !== ''
            ? strtoupper(trim((string) $datos['nacionalidad']))
            : null;

        $rangoEtario = isset($datos['rango_etario']) && $datos['rango_etario'] !== null
            ? RangoEtarioParticipante::from((string) $datos['rango_etario'])
            : null;

        $regimenAlim = isset($datos['regimen_alimentario']) && $datos['regimen_alimentario'] !== null
            ? RegimenAlimentario::from((string) $datos['regimen_alimentario'])
            : null;

        $participante = new ReservaParticipante(
            id: null,
            reservaId: $reservaId,
            personaId: isset($datos['persona_id']) && (int) $datos['persona_id'] > 0 ? (int) $datos['persona_id'] : null,
            tipoDocumentoId: $tipoDocId,
            numeroDocumento: $numDoc,
            nombres: $nombres,
            apellidos: $apellidos,
            nacionalidad: $nacionalidad,
            rangoEtario: $rangoEtario,
            telefonoContacto: isset($datos['telefono_contacto']) ? trim((string) $datos['telefono_contacto']) : null,
            tallaIndumentaria: isset($datos['talla_indumentaria']) ? trim((string) $datos['talla_indumentaria']) : null,
            requiereAsistenciaMovilidad: (bool) ($datos['requiere_asistencia_movilidad'] ?? false),
            regimenAlimentario: $regimenAlim,
            esTitularReserva: (bool) ($datos['es_titular_reserva'] ?? false)
        );

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $guardado = $this->reservaRepo->guardarParticipante($participante);

            // Asignación M:N a prestaciones de la misma reserva
            if (!empty($prestacionIds)) {
                $prestacionesReserva = $this->reservaRepo->obtenerPrestacionesPorReserva($reservaId);
                $idsValidos = array_map(fn($p) => (int) $p->id, $prestacionesReserva);

                foreach ($prestacionIds as $pId) {
                    $pId = (int) $pId;
                    if (!in_array($pId, $idsValidos, true)) {
                        throw new InvalidArgumentException("La prestación #{$pId} no pertenece a la reserva #{$reservaId}.");
                    }
                    $this->reservaRepo->asignarParticipanteAPrestacion($pId, (int) $guardado->id);
                }
            }

            // Si la reserva estaba en PENDIENTE_DATOS y ahora cuenta con participantes, avanzar a CONFIRMADA
            if ($reserva->estado === EstadoReserva::PENDIENTE_DATOS) {
                $this->reservaRepo->actualizarEstadoReserva($reservaId, EstadoReserva::CONFIRMADA, $reserva->versionBloqueo);
            }

            $this->registrarAuditoria(
                $contexto,
                'PARTICIPANTE_REGISTRADO',
                'reserva_participantes',
                (int) $guardado->id,
                [
                    'reserva_id' => $reservaId,
                    'prestaciones_asignadas' => count($prestacionIds),
                ]
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return $guardado;
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela una reserva administrativamente antes de su despacho a campo.
     */
    public function cancelarReserva(
        int $organizacionId,
        int $reservaId,
        string $motivo,
        ?ContextoOperacion $contexto = null
    ): Reserva {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('reservas.cancelar', $contexto);

        $reserva = $this->reservaRepo->buscarPorId($reservaId);
        if ($reserva === null || $reserva->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La reserva #{$reservaId} no existe en su organización.");
        }

        if ($reserva->estado === EstadoReserva::CANCELADA) {
            throw new InvalidArgumentException("La reserva #{$reservaId} ya se encuentra CANCELADA.");
        }

        $reservaCancelada = $this->reservaRepo->cancelarReserva($reservaId, $reserva->versionBloqueo);

        $this->registrarAuditoria(
            $contexto,
            'RESERVA_CANCELADA',
            'reservas',
            $reservaId,
            ['motivo' => trim($motivo)]
        );

        return $reservaCancelada;
    }

    /**
     * Registra explícitamente la semántica operativa de un ítem comercial (Sin defaults automáticos).
     */
    public function configurarOperativamenteItem(
        int $organizacionId,
        int $itemComercialId,
        array $config,
        ?ContextoOperacion $contexto = null
    ): ItemConfiguracionOperativa {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $item = $this->itemRepo->buscarPorId($itemComercialId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("El ítem comercial #{$itemComercialId} no existe en su organización.");
        }

        if (!isset($config['requiere_reserva']) || !isset($config['requiere_agendamiento']) ||
            !isset($config['requiere_participantes']) || !isset($config['tipo_capacidad'])) {
            throw new InvalidArgumentException(
                "Parámetros incompletos. 'requiere_reserva', 'requiere_agendamiento', 'requiere_participantes' y 'tipo_capacidad' deben definirse explícitamente (sin defaults)."
            );
        }

        $entidadConfig = new ItemConfiguracionOperativa(
            id: null,
            itemComercialId: $itemComercialId,
            requiereReserva: (bool) $config['requiere_reserva'],
            requiereAgendamiento: (bool) $config['requiere_agendamiento'],
            requiereParticipantes: (bool) $config['requiere_participantes'],
            tipoCapacidad: is_string($config['tipo_capacidad']) ? TipoCapacidad::from($config['tipo_capacidad']) : $config['tipo_capacidad'],
            esAccesorio: (bool) ($config['es_accesorio'] ?? false),
            duracionEstimadaMinutos: isset($config['duracion_estimada_minutos']) && $config['duracion_estimada_minutos'] !== null ? (int) $config['duracion_estimada_minutos'] : null,
            puntoPartidaPredeterminado: isset($config['punto_partida_predeterminado']) ? trim((string) $config['punto_partida_predeterminado']) : null
        );

        return $this->itemCfgRepo->guardar($entidadConfig);
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        return $contexto ?? new ContextoOperacion(
            actorTipo: 'HUMANO',
            usuarioId: 1,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: 1,
            canalCodigo: 'APP',
            correlacionId: 'corr-reserva-def-01',
            origenIp: '127.0.0.1',
            agenteUsuario: 'ReservaServicio/v1',
            organizacionId: 10000
        );
    }

    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId !== null && (int) $contexto->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("Acceso denegado: El recurso pertenece a otra organización.");
        }
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId === null) {
            return;
        }

        if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
            throw new AccesoDenegadoExcepcion("Acceso denegado: Se requiere el permiso '{$permiso}'.");
        }
    }

    private function registrarAuditoria(
        ContextoOperacion $contexto,
        string $evento,
        string $entidad,
        int $entidadId,
        array $detalles
    ): void {
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'reservas',
            accion: $evento,
            entidadTipo: $entidad,
            entidadId: (string) $entidadId,
            datosPrevios: null,
            datosNuevos: $detalles
        );
    }
}

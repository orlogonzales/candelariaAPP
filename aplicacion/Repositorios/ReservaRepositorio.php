<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Reserva;
use Aplicacion\Entidades\ReservaParticipante;
use Aplicacion\Entidades\ReservaPrestacion;
use Aplicacion\Entidades\ReservaReprogramacion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\MotivoReprogramacion;
use Aplicacion\Reservas\RangoEtarioParticipante;
use Aplicacion\Reservas\RegimenAlimentario;
use Aplicacion\Reservas\TipoCapacidad;
use PDO;

/**
 * Repositorio de Persistencia Soberana para el Núcleo de Reservas.
 */
class ReservaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtInit = $this->pdo->prepare("
            INSERT IGNORE INTO `reservas_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 0)
        ");
        $stmtInit->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtLock = $this->pdo->prepare("
            SELECT `ultimo_numero` FROM `reservas_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
            FOR UPDATE
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $ultimo = (int) $stmtLock->fetchColumn();

        $siguiente = $ultimo + 1;

        $stmtUp = $this->pdo->prepare("
            UPDATE `reservas_secuencias`
            SET `ultimo_numero` = :siguiente
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtUp->execute([
            'siguiente' => $siguiente,
            'org_id'    => $organizacionId,
            'anio'      => $anio,
        ]);

        return sprintf('RSV-%04d-%06d', $anio, $siguiente);
    }

    public function guardar(Reserva $r): Reserva
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `reservas` (
                `organizacion_id`, `edicion_id`, `venta_id`, `cliente_id`,
                `correlativo`, `estado`, `contacto_nombre`, `contacto_tipo_documento`,
                `contacto_numero_documento`, `contacto_telefono`, `contacto_email`,
                `notas_operativas`, `version_bloqueo`, `creado_por`
            ) VALUES (
                :org_id, :edicion_id, :venta_id, :cliente_id,
                :correlativo, :estado, :contacto_nom, :contacto_tipo_doc,
                :contacto_num_doc, :contacto_tel, :contacto_email,
                :notas, :version_bloqueo, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'            => $r->organizacionId,
            'edicion_id'        => $r->edicionId,
            'venta_id'          => $r->ventaId,
            'cliente_id'        => $r->clienteId,
            'correlativo'       => $r->correlativo,
            'estado'            => $r->estado->value,
            'contacto_nom'      => $r->contactoNombre,
            'contacto_tipo_doc' => $r->contactoTipoDocumento,
            'contacto_num_doc'  => $r->contactoNumeroDocumento,
            'contacto_tel'      => $r->contactoTelefono,
            'contacto_email'    => $r->contactoEmail,
            'notas'             => $r->notasOperativas,
            'version_bloqueo'   => $r->versionBloqueo,
            'creado_por'        => $r->creadoPor,
        ]);

        $reservaId = (int) $this->pdo->lastInsertId();

        $stmtPrest = $this->pdo->prepare("
            INSERT INTO `reserva_prestaciones` (
                `reserva_id`, `venta_linea_id`, `venta_linea_componente_id`,
                `item_comercial_id`, `concepto_codigo`, `concepto_nombre`,
                `unidad_medida`, `cantidad`, `tipo_capacidad`,
                `requiere_agendamiento`, `requiere_participantes`,
                `estado_agendamiento`, `fecha_servicio`, `hora_servicio`,
                `punto_encuentro`, `notas_prestacion`, `version_bloqueo`
            ) VALUES (
                :reserva_id, :vta_linea_id, :vta_comp_id,
                :item_id, :concepto_cod, :concepto_nom,
                :unidad, :cantidad, :tipo_cap,
                :req_agenda, :req_part,
                :estado_agenda, :fecha_serv, :hora_serv,
                :punto_encuentro, :notas, :version_bloqueo
            )
        ");

        foreach ($r->prestaciones as $p) {
            $stmtPrest->execute([
                'reserva_id'      => $reservaId,
                'vta_linea_id'    => $p->ventaLineaId,
                'vta_comp_id'     => $p->ventaLineaComponenteId,
                'item_id'         => $p->itemComercialId,
                'concepto_cod'    => $p->conceptoCodigo,
                'concepto_nom'    => $p->conceptoNombre,
                'unidad'          => $p->unidadMedida,
                'cantidad'        => $p->cantidad,
                'tipo_cap'        => $p->tipoCapacidad->value,
                'req_agenda'      => $p->requiereAgendamiento ? 1 : 0,
                'req_part'        => $p->requiereParticipantes ? 1 : 0,
                'estado_agenda'   => $p->estadoAgendamiento->value,
                'fecha_serv'      => $p->fechaServicio,
                'hora_serv'       => $p->horaServicio,
                'punto_encuentro' => $p->puntoEncuentro,
                'notas'           => $p->notasPrestacion,
                'version_bloqueo' => $p->versionBloqueo,
            ]);
        }

        return $this->buscarPorId($reservaId);
    }

    public function buscarPorId(int $id): ?Reserva
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `reservas` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $prestaciones = $this->obtenerPrestacionesPorReserva($id);
        $participantes = $this->obtenerParticipantesPorReserva($id);

        return $this->hidratarReserva($fila, $prestaciones, $participantes);
    }

    public function buscarPorVentaId(int $ventaId): ?Reserva
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `reservas` WHERE `venta_id` = :venta_id");
        $stmt->execute(['venta_id' => $ventaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $id = (int) $fila['id'];
        $prestaciones = $this->obtenerPrestacionesPorReserva($id);
        $participantes = $this->obtenerParticipantesPorReserva($id);

        return $this->hidratarReserva($fila, $prestaciones, $participantes);
    }

    public function buscarPrestacionPorId(int $prestacionId): ?ReservaPrestacion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `reserva_prestaciones` WHERE `id` = :id");
        $stmt->execute(['id' => $prestacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $participanteIds = $this->obtenerParticipanteIdsPorPrestacion($prestacionId);
        return $this->hidratarPrestacion($fila, $participanteIds);
    }

    /**
     * Programa o reprograma una prestación bajo bloqueo optimista.
     */
    public function actualizarAgendamientoPrestacion(
        int $prestacionId,
        string $fechaServicio,
        ?string $horaServicio,
        ?string $puntoEncuentro,
        int $versionEsperada
    ): ReservaPrestacion {
        $stmt = $this->pdo->prepare("
            UPDATE `reserva_prestaciones`
            SET `fecha_servicio` = :fecha,
                `hora_servicio` = :hora,
                `punto_encuentro` = :punto,
                `estado_agendamiento` = 'PROGRAMADA',
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `version_bloqueo` = :version
        ");

        $stmt->execute([
            'fecha'   => $fechaServicio,
            'hora'    => $horaServicio,
            'punto'   => $puntoEncuentro,
            'id'      => $prestacionId,
            'version' => $versionEsperada,
        ]);

        if ($stmt->rowCount() === 0) {
            $actual = $this->buscarPrestacionPorId($prestacionId);
            if ($actual === null) {
                throw new \InvalidArgumentException("La prestación #{$prestacionId} no existe.");
            }
            throw new ConflictoConcurrenciaExcepcion(
                "La prestación #{$prestacionId} fue modificada por otro usuario concurrentemente (versión actual: {$actual->versionBloqueo}, enviada: {$versionEsperada})."
            );
        }

        return $this->buscarPrestacionPorId($prestacionId);
    }

    public function registrarReprogramacion(ReservaReprogramacion $reprog): ReservaReprogramacion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `reserva_reprogramaciones` (
                `prestacion_id`, `fecha_anterior`, `fecha_nueva`,
                `hora_anterior`, `hora_nueva`, `motivo_categoria`,
                `motivo_detalle`, `creado_por`
            ) VALUES (
                :prestacion_id, :fecha_ant, :fecha_nueva,
                :hora_ant, :hora_nueva, :motivo_cat,
                :motivo_det, :creado_por
            )
        ");

        $stmt->execute([
            'prestacion_id' => $reprog->prestacionId,
            'fecha_ant'     => $reprog->fechaAnterior,
            'fecha_nueva'   => $reprog->fechaNueva,
            'hora_ant'      => $reprog->horaAnterior,
            'hora_nueva'    => $reprog->horaNueva,
            'motivo_cat'    => $reprog->motivoCategoria->value,
            'motivo_det'    => $reprog->motivoDetalle,
            'creado_por'    => $reprog->creadoPor,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new ReservaReprogramacion(
            id: $id,
            prestacionId: $reprog->prestacionId,
            fechaAnterior: $reprog->fechaAnterior,
            fechaNueva: $reprog->fechaNueva,
            horaAnterior: $reprog->horaAnterior,
            horaNueva: $reprog->horaNueva,
            motivoCategoria: $reprog->motivoCategoria,
            motivoDetalle: $reprog->motivoDetalle,
            creadoPor: $reprog->creadoPor,
            creadoEn: date('Y-m-d H:i:s')
        );
    }

    public function guardarParticipante(ReservaParticipante $p): ReservaParticipante
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `reserva_participantes` (
                `reserva_id`, `persona_id`, `tipo_documento_id`,
                `numero_documento`, `nombres`, `apellidos`,
                `nacionalidad`, `rango_etario`, `telefono_contacto`,
                `talla_indumentaria`, `requiere_asistencia_movilidad`,
                `regimen_alimentario`, `es_titular_reserva`
            ) VALUES (
                :reserva_id, :persona_id, :tipo_doc_id,
                :num_doc, :nombres, :apellidos,
                :nacionalidad, :rango_etario, :telefono,
                :talla, :req_movilidad,
                :regimen, :es_titular
            )
        ");

        $stmt->execute([
            'reserva_id'    => $p->reservaId,
            'persona_id'    => $p->personaId,
            'tipo_doc_id'   => $p->tipoDocumentoId,
            'num_doc'       => $p->numeroDocumento,
            'nombres'       => $p->nombres,
            'apellidos'     => $p->apellidos,
            'nacionalidad'  => $p->nacionalidad,
            'rango_etario'  => $p->rangoEtario?->value,
            'telefono'      => $p->telefonoContacto,
            'talla'         => $p->tallaIndumentaria,
            'req_movilidad' => $p->requiereAsistenciaMovilidad ? 1 : 0,
            'regimen'       => $p->regimenAlimentario?->value,
            'es_titular'    => $p->esTitularReserva ? 1 : 0,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new ReservaParticipante(
            id: $id,
            reservaId: $p->reservaId,
            personaId: $p->personaId,
            tipoDocumentoId: $p->tipoDocumentoId,
            numeroDocumento: $p->numeroDocumento,
            nombres: $p->nombres,
            apellidos: $p->apellidos,
            nacionalidad: $p->nacionalidad,
            rangoEtario: $p->rangoEtario,
            telefonoContacto: $p->telefonoContacto,
            tallaIndumentaria: $p->tallaIndumentaria,
            requiereAsistenciaMovilidad: $p->requiereAsistenciaMovilidad,
            regimenAlimentario: $p->regimenAlimentario,
            esTitularReserva: $p->esTitularReserva,
            creadoEn: date('Y-m-d H:i:s'),
            actualizadoEn: date('Y-m-d H:i:s')
        );
    }

    public function asignarParticipanteAPrestacion(int $prestacionId, int $participanteId, ?string $asiento = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `prestacion_participantes` (`prestacion_id`, `participante_id`, `asiento_asignado`)
            VALUES (:prest_id, :part_id, :asiento)
            ON DUPLICATE KEY UPDATE `asiento_asignado` = VALUES(`asiento_asignado`)
        ");
        $stmt->execute([
            'prest_id' => $prestacionId,
            'part_id'  => $participanteId,
            'asiento'  => $asiento,
        ]);
    }

    public function actualizarEstadoReserva(int $reservaId, EstadoReserva $nuevoEstado, int $versionEsperada): Reserva
    {
        $stmt = $this->pdo->prepare("
            UPDATE `reservas`
            SET `estado` = :estado,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `version_bloqueo` = :version
        ");

        $stmt->execute([
            'estado'  => $nuevoEstado->value,
            'id'      => $reservaId,
            'version' => $versionEsperada,
        ]);

        if ($stmt->rowCount() === 0) {
            $actual = $this->buscarPorId($reservaId);
            if ($actual === null) {
                throw new \InvalidArgumentException("La reserva #{$reservaId} no existe.");
            }
            throw new ConflictoConcurrenciaExcepcion(
                "La reserva #{$reservaId} fue modificada por otro usuario concurrentemente."
            );
        }

        return $this->buscarPorId($reservaId);
    }

    public function cancelarReserva(int $reservaId, int $versionEsperada): Reserva
    {
        $stmt = $this->pdo->prepare("
            UPDATE `reservas`
            SET `estado` = 'CANCELADA',
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id AND `version_bloqueo` = :version
        ");

        $stmt->execute([
            'id'      => $reservaId,
            'version' => $versionEsperada,
        ]);

        if ($stmt->rowCount() === 0) {
            $actual = $this->buscarPorId($reservaId);
            if ($actual === null) {
                throw new \InvalidArgumentException("La reserva #{$reservaId} no existe.");
            }
            throw new ConflictoConcurrenciaExcepcion(
                "La reserva #{$reservaId} fue modificada concurrentemente."
            );
        }

        // Cancelar prestaciones activas
        $stmtPrest = $this->pdo->prepare("
            UPDATE `reserva_prestaciones`
            SET `estado_agendamiento` = 'CANCELADA',
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `reserva_id` = :reserva_id AND `estado_agendamiento` <> 'CANCELADA'
        ");
        $stmtPrest->execute(['reserva_id' => $reservaId]);

        return $this->buscarPorId($reservaId);
    }

    /**
     * @return ReservaPrestacion[]
     */
    public function obtenerPrestacionesPorReserva(int $reservaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_prestaciones`
            WHERE `reserva_id` = :reserva_id
            ORDER BY `id` ASC
        ");
        $stmt->execute(['reserva_id' => $reservaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $prestaciones = [];
        foreach ($filas as $f) {
            $pId = (int) $f['id'];
            $partIds = $this->obtenerParticipanteIdsPorPrestacion($pId);
            $prestaciones[] = $this->hidratarPrestacion($f, $partIds);
        }

        return $prestaciones;
    }

    /**
     * @return ReservaParticipante[]
     */
    public function obtenerParticipantesPorReserva(int $reservaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_participantes`
            WHERE `reserva_id` = :reserva_id
            ORDER BY `es_titular_reserva` DESC, `id` ASC
        ");
        $stmt->execute(['reserva_id' => $reservaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($f) {
            return new ReservaParticipante(
                id: (int) $f['id'],
                reservaId: (int) $f['reserva_id'],
                personaId: $f['persona_id'] !== null ? (int) $f['persona_id'] : null,
                tipoDocumentoId: (int) $f['tipo_documento_id'],
                numeroDocumento: (string) $f['numero_documento'],
                nombres: (string) $f['nombres'],
                apellidos: (string) $f['apellidos'],
                nacionalidad: $f['nacionalidad'],
                rangoEtario: $f['rango_etario'] !== null ? RangoEtarioParticipante::from($f['rango_etario']) : null,
                telefonoContacto: $f['telefono_contacto'],
                tallaIndumentaria: $f['talla_indumentaria'],
                requiereAsistenciaMovilidad: (bool) $f['requiere_asistencia_movilidad'],
                regimenAlimentario: $f['regimen_alimentario'] !== null ? RegimenAlimentario::from($f['regimen_alimentario']) : null,
                esTitularReserva: (bool) $f['es_titular_reserva'],
                creadoEn: $f['creado_en'],
                actualizadoEn: $f['actualizado_en']
            );
        }, $filas);
    }

    /**
     * @return int[]
     */
    private function obtenerParticipanteIdsPorPrestacion(int $prestacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT `participante_id` FROM `prestacion_participantes`
            WHERE `prestacion_id` = :prest_id
        ");
        $stmt->execute(['prest_id' => $prestacionId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return ReservaReprogramacion[]
     */
    public function obtenerReprogramacionesPorPrestacion(int $prestacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_reprogramaciones`
            WHERE `prestacion_id` = :prest_id
            ORDER BY `creado_en` DESC, `id` DESC
        ");
        $stmt->execute(['prest_id' => $prestacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($f) {
            return new ReservaReprogramacion(
                id: (int) $f['id'],
                prestacionId: (int) $f['prestacion_id'],
                fechaAnterior: $f['fecha_anterior'],
                fechaNueva: (string) $f['fecha_nueva'],
                horaAnterior: $f['hora_anterior'],
                horaNueva: $f['hora_nueva'],
                motivoCategoria: MotivoReprogramacion::from($f['motivo_categoria']),
                motivoDetalle: (string) $f['motivo_detalle'],
                creadoPor: (int) $f['creado_por'],
                creadoEn: (string) $f['creado_en']
            );
        }, $filas);
    }

    public function buscarParticipantePorId(int $participanteId): ?ReservaParticipante
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `reserva_participantes` WHERE `id` = :id");
        $stmt->execute(['id' => $participanteId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$f) {
            return null;
        }

        return new ReservaParticipante(
            id: (int) $f['id'],
            reservaId: (int) $f['reserva_id'],
            personaId: $f['persona_id'] !== null ? (int) $f['persona_id'] : null,
            tipoDocumentoId: (int) $f['tipo_documento_id'],
            numeroDocumento: (string) $f['numero_documento'],
            nombres: (string) $f['nombres'],
            apellidos: (string) $f['apellidos'],
            nacionalidad: $f['nacionalidad'],
            rangoEtario: $f['rango_etario'] !== null ? RangoEtarioParticipante::from($f['rango_etario']) : null,
            telefonoContacto: $f['telefono_contacto'],
            tallaIndumentaria: $f['talla_indumentaria'],
            requiereAsistenciaMovilidad: (bool) $f['requiere_asistencia_movilidad'],
            regimenAlimentario: $f['regimen_alimentario'] !== null ? RegimenAlimentario::from($f['regimen_alimentario']) : null,
            esTitularReserva: (bool) $f['es_titular_reserva'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }

    public function eliminarParticipante(int $reservaId, int $participanteId): void
    {
        // 1. Desasignar de prestaciones
        $stmtPrest = $this->pdo->prepare("DELETE FROM `prestacion_participantes` WHERE `participante_id` = :id");
        $stmtPrest->execute(['id' => $participanteId]);

        // 2. Eliminar participante
        $stmt = $this->pdo->prepare("DELETE FROM `reserva_participantes` WHERE `id` = :id AND `reserva_id` = :reserva_id");
        $stmt->execute(['id' => $participanteId, 'reserva_id' => $reservaId]);
    }

    private function hidratarReserva(array $f, array $prestaciones = [], array $participantes = []): Reserva
    {
        return new Reserva(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            edicionId: (int) $f['edicion_id'],
            ventaId: (int) $f['venta_id'],
            clienteId: (int) $f['cliente_id'],
            correlativo: (string) $f['correlativo'],
            estado: EstadoReserva::from($f['estado']),
            contactoNombre: (string) $f['contacto_nombre'],
            contactoTipoDocumento: $f['contacto_tipo_documento'],
            contactoNumeroDocumento: $f['contacto_numero_documento'],
            contactoTelefono: $f['contacto_telefono'],
            contactoEmail: $f['contacto_email'],
            notasOperativas: $f['notas_operativas'],
            versionBloqueo: (int) $f['version_bloqueo'],
            creadoPor: (int) $f['creado_por'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            prestaciones: $prestaciones,
            participantes: $participantes
        );
    }

    private function hidratarPrestacion(array $f, array $participanteIds = []): ReservaPrestacion
    {
        return new ReservaPrestacion(
            id: (int) $f['id'],
            reservaId: (int) $f['reserva_id'],
            ventaLineaId: (int) $f['venta_linea_id'],
            ventaLineaComponenteId: $f['venta_linea_componente_id'] !== null ? (int) $f['venta_linea_componente_id'] : null,
            itemComercialId: (int) $f['item_comercial_id'],
            conceptoCodigo: (string) $f['concepto_codigo'],
            conceptoNombre: (string) $f['concepto_nombre'],
            unidadMedida: (string) $f['unidad_medida'],
            cantidad: (float) $f['cantidad'],
            tipoCapacidad: TipoCapacidad::from($f['tipo_capacidad']),
            requiereAgendamiento: (bool) $f['requiere_agendamiento'],
            requiereParticipantes: (bool) $f['requiere_participantes'],
            estadoAgendamiento: EstadoAgendamientoPrestacion::from($f['estado_agendamiento']),
            fechaServicio: $f['fecha_servicio'],
            horaServicio: $f['hora_servicio'],
            puntoEncuentro: $f['punto_encuentro'],
            notasPrestacion: $f['notas_prestacion'],
            versionBloqueo: (int) $f['version_bloqueo'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            participanteIds: $participanteIds
        );
    }
}

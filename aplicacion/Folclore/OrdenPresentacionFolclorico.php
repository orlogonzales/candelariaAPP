<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

/**
 * Entidad de dominio para el turno u orden de presentación folclórica.
 * Integra datos de programación, control operativo de campo, concurrencia optimista y cobertura.
 */
class OrdenPresentacionFolclorico
{
    public function __construct(
        public ?int $id,
        public int $programacionOficialId,
        public int $conjuntoEdicionId,
        public int $ordenSorteo,
        public string $horaEstimadaPartida,
        public ?string $horaEstimadaLlegada = null,
        public ?string $puntoPartidaEspecifico = null,
        public EstadoTurnoPresentacion $estadoTurno = EstadoTurnoPresentacion::PROGRAMADO,
        public ?string $horaRealPartida = null,
        public ?string $horaRealFin = null,
        public ?string $observaciones = null,
        public int $versionBloqueo = 1,
        public ?int $retrasoMinutos = 0,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null,
        public ?string $conjuntoNombre = null,
        public ?string $tipoDanzaNombre = null,
        public ?string $bloqueNombre = null,
        public array $salidasCobertura = []
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $estado = is_string($datos['estado_turno'] ?? null)
            ? EstadoTurnoPresentacion::normalizar($datos['estado_turno'])
            : ($datos['estado_turno'] ?? EstadoTurnoPresentacion::PROGRAMADO);

        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            programacionOficialId: (int)$datos['programacion_oficial_id'],
            conjuntoEdicionId: (int)$datos['conjunto_edicion_id'],
            ordenSorteo: (int)$datos['orden_sorteo'],
            horaEstimadaPartida: (string)$datos['hora_estimada_partida'],
            horaEstimadaLlegada: $datos['hora_estimada_llegada'] ?? null,
            puntoPartidaEspecifico: $datos['punto_partida_especifico'] ?? null,
            estadoTurno: $estado,
            horaRealPartida: $datos['hora_real_partida'] ?? null,
            horaRealFin: $datos['hora_real_fin'] ?? null,
            observaciones: $datos['observaciones'] ?? null,
            versionBloqueo: isset($datos['version_bloqueo']) ? (int)$datos['version_bloqueo'] : 1,
            retrasoMinutos: isset($datos['retraso_minutos']) ? (int)$datos['retraso_minutos'] : 0,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null,
            conjuntoNombre: $datos['conjunto_nombre'] ?? null,
            tipoDanzaNombre: $datos['tipo_danza_nombre'] ?? null,
            bloqueNombre: $datos['bloque_nombre'] ?? null,
            salidasCobertura: $datos['salidas_cobertura'] ?? []
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                        => $this->id,
            'programacion_oficial_id'   => $this->programacionOficialId,
            'conjunto_edicion_id'       => $this->conjuntoEdicionId,
            'orden_sorteo'              => $this->ordenSorteo,
            'hora_estimada_partida'     => $this->horaEstimadaPartida,
            'hora_estimada_llegada'     => $this->horaEstimadaLlegada,
            'punto_partida_especifico'  => $this->puntoPartidaEspecifico,
            'estado_turno'              => $this->estadoTurno->value,
            'estado_operativo_etiqueta' => $this->estadoTurno->etiquetaOperativa(),
            'color_badge'               => $this->estadoTurno->colorBadgeAlina(),
            'hora_real_partida'         => $this->horaRealPartida,
            'hora_real_fin'             => $this->horaRealFin,
            'observaciones'             => $this->observaciones,
            'version_bloqueo'           => $this->versionBloqueo,
            'retraso_minutos'           => $this->retrasoMinutos,
            'conjunto_nombre'           => $this->conjuntoNombre,
            'tipo_danza_nombre'         => $this->tipoDanzaNombre,
            'bloque_nombre'             => $this->bloqueNombre,
            'salidas_cobertura'         => $this->salidasCobertura,
            'actualizado_en'            => $this->actualizadoEn,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

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
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null,
        public ?string $conjuntoNombre = null,
        public ?string $tipoDanzaNombre = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            programacionOficialId: (int)$datos['programacion_oficial_id'],
            conjuntoEdicionId: (int)$datos['conjunto_edicion_id'],
            ordenSorteo: (int)$datos['orden_sorteo'],
            horaEstimadaPartida: (string)$datos['hora_estimada_partida'],
            horaEstimadaLlegada: $datos['hora_estimada_llegada'] ?? null,
            puntoPartidaEspecifico: $datos['punto_partida_especifico'] ?? null,
            estadoTurno: is_string($datos['estado_turno'])
                ? EstadoTurnoPresentacion::from($datos['estado_turno'])
                : $datos['estado_turno'],
            horaRealPartida: $datos['hora_real_partida'] ?? null,
            horaRealFin: $datos['hora_real_fin'] ?? null,
            observaciones: $datos['observaciones'] ?? null,
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null,
            conjuntoNombre: $datos['conjunto_nombre'] ?? null,
            tipoDanzaNombre: $datos['tipo_danza_nombre'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                       => $this->id,
            'programacion_oficial_id'  => $this->programacionOficialId,
            'conjunto_edicion_id'      => $this->conjuntoEdicionId,
            'orden_sorteo'             => $this->ordenSorteo,
            'hora_estimada_partida'    => $this->horaEstimadaPartida,
            'hora_estimada_llegada'    => $this->horaEstimadaLlegada,
            'punto_partida_especifico' => $this->puntoPartidaEspecifico,
            'estado_turno'             => $this->estadoTurno->value,
            'hora_real_partida'        => $this->horaRealPartida,
            'hora_real_fin'            => $this->horaRealFin,
            'observaciones'            => $this->observaciones,
        ];
    }
}

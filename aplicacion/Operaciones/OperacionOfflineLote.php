<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Entidad de dominio que representa un lote de sincronización de validaciones offline de campo.
 */
class OperacionOfflineLote
{
    public const ESTADO_RECIBIDO = 'RECIBIDO';
    public const ESTADO_PROCESADO = 'PROCESADO';
    public const ESTADO_CON_CONFLICTOS = 'CON_CONFLICTOS';

    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $operadorId,
        public string $loteUuid,
        public ?string $dispositivoInfo = null,
        public int $totalEscaneos = 0,
        public int $totalConfirmados = 0,
        public int $totalConflictos = 0,
        public int $totalRechazados = 0,
        public string $estado = self::ESTADO_RECIBIDO,
        public ?string $ipOrigen = null,
        public ?string $creadoEn = null,
        public ?string $procesadoEn = null
    ) {
    }

    public static function desdeArreglo(array $fila): self
    {
        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            organizacionId: (int) $fila['organizacion_id'],
            edicionId: (int) $fila['edicion_id'],
            operadorId: (int) $fila['operador_id'],
            loteUuid: (string) $fila['lote_uuid'],
            dispositivoInfo: $fila['dispositivo_info'] ?? null,
            totalEscaneos: (int) ($fila['total_escaneos'] ?? 0),
            totalConfirmados: (int) ($fila['total_confirmados'] ?? 0),
            totalConflictos: (int) ($fila['total_conflictos'] ?? 0),
            totalRechazados: (int) ($fila['total_rechazados'] ?? 0),
            estado: (string) ($fila['estado'] ?? self::ESTADO_RECIBIDO),
            ipOrigen: $fila['ip_origen'] ?? null,
            creadoEn: $fila['creado_en'] ?? null,
            procesadoEn: $fila['procesado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'organizacion_id'    => $this->organizacionId,
            'edicion_id'         => $this->edicionId,
            'operador_id'        => $this->operadorId,
            'lote_uuid'          => $this->loteUuid,
            'dispositivo_info'   => $this->dispositivoInfo,
            'total_escaneos'     => $this->totalEscaneos,
            'total_confirmados'  => $this->totalConfirmados,
            'total_conflictos'   => $this->totalConflictos,
            'total_rechazados'   => $this->totalRechazados,
            'estado'             => $this->estado,
            'ip_origen'          => $this->ipOrigen,
            'creado_en'          => $this->creadoEn,
            'procesado_en'       => $this->procesadoEn,
        ];
    }
}

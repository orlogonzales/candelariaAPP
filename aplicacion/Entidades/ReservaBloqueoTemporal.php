<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use DateTimeImmutable;

/**
 * Entidad de dominio que representa un bloqueo temporal de cupos anti-sobreventa.
 */
class ReservaBloqueoTemporal
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_RETENCION_VOUCHER = 'RETENCION_VOUCHER';
    public const ESTADO_CONSUMIDO = 'CONSUMIDO';
    public const ESTADO_EXPIRADO = 'EXPIRADO';
    public const ESTADO_LIBERADO_MANUAL = 'LIBERADO_MANUAL';

    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $itemComercialId,
        public ?int $salidaId,
        public string $tokenBloqueo,
        public string $identificadorSesion,
        public int $cuposBloqueados,
        public ?string $fechaServicio,
        public ?string $horaServicio,
        public string $estado,
        public string $expiraEn,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $fila): self
    {
        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            organizacionId: (int) $fila['organizacion_id'],
            edicionId: (int) $fila['edicion_id'],
            itemComercialId: (int) $fila['item_comercial_id'],
            salidaId: isset($fila['salida_id']) && $fila['salida_id'] !== null ? (int) $fila['salida_id'] : null,
            tokenBloqueo: (string) $fila['token_bloqueo'],
            identificadorSesion: (string) $fila['identificador_sesion'],
            cuposBloqueados: (int) $fila['cupos_bloqueados'],
            fechaServicio: $fila['fecha_servicio'] ?? null,
            horaServicio: $fila['hora_servicio'] ?? null,
            estado: (string) ($fila['estado'] ?? self::ESTADO_ACTIVO),
            expiraEn: (string) $fila['expira_en'],
            creadoEn: $fila['creado_en'] ?? null,
            actualizadoEn: $fila['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'edicion_id' => $this->edicionId,
            'item_comercial_id' => $this->itemComercialId,
            'salida_id' => $this->salidaId,
            'token_bloqueo' => $this->tokenBloqueo,
            'identificador_sesion' => $this->identificadorSesion,
            'cupos_bloqueados' => $this->cuposBloqueados,
            'fecha_servicio' => $this->fechaServicio,
            'hora_servicio' => $this->horaServicio,
            'estado' => $this->estado,
            'expira_en' => $this->expiraEn,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public function estaVigente(): bool
    {
        if ($this->estado !== self::ESTADO_ACTIVO && $this->estado !== self::ESTADO_RETENCION_VOUCHER) {
            return false;
        }

        return strtotime($this->expiraEn) > time();
    }
}

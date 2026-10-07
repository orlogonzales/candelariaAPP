<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\EstadoAsistencia;
use InvalidArgumentException;

/**
 * Entidad de dominio OperacionAsistencia:
 * Control de check-in, ubicación de asiento y abordaje del participante en la salida.
 */
class OperacionAsistencia
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $salidaId,
        public readonly int $prestacionId,
        public readonly int $participanteId,
        public readonly ?string $ubicacionAsiento = null,
        public readonly EstadoAsistencia $estadoAsistencia = EstadoAsistencia::PENDIENTE,
        public readonly ?string $marcadoEn = null,
        public readonly ?int $marcadoPor = null,
        public readonly ?string $observacion = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->salidaId <= 0) {
            throw new InvalidArgumentException("El salidaId debe ser un entero positivo.");
        }
        if ($this->prestacionId <= 0) {
            throw new InvalidArgumentException("El prestacionId debe ser un entero positivo.");
        }
        if ($this->participanteId <= 0) {
            throw new InvalidArgumentException("El participanteId debe ser un entero positivo.");
        }
    }
}

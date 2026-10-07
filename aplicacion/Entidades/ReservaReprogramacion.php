<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\MotivoReprogramacion;
use InvalidArgumentException;

/**
 * Entidad de dominio ReservaReprogramacion:
 * Registro inmutable append-only de cambios de fecha/turno en prestaciones.
 */
class ReservaReprogramacion
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $prestacionId,
        public readonly ?string $fechaAnterior,
        public readonly string $fechaNueva,
        public readonly ?string $horaAnterior,
        public readonly ?string $horaNueva,
        public readonly MotivoReprogramacion $motivoCategoria,
        public readonly string $motivoDetalle,
        public readonly int $creadoPor,
        public readonly ?string $creadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->prestacionId <= 0) {
            throw new InvalidArgumentException("prestacionId debe ser un entero positivo.");
        }
        if (trim($this->fechaNueva) === '') {
            throw new InvalidArgumentException("La fechaNueva no puede estar vacía.");
        }
        if (trim($this->motivoDetalle) === '') {
            throw new InvalidArgumentException("El motivoDetalle es obligatorio para reprogramar una prestación.");
        }
    }
}

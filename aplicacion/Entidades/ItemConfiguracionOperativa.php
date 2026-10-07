<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\TipoCapacidad;
use InvalidArgumentException;

/**
 * Entidad de dominio para la configuración operativa explícita de un ítem comercial.
 * Garantiza que NO se infiera comportamiento desde el tipo o unidad de medida.
 */
class ItemConfiguracionOperativa
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $itemComercialId,
        public readonly bool $requiereReserva,
        public readonly bool $requiereAgendamiento,
        public readonly bool $requiereParticipantes,
        public readonly TipoCapacidad $tipoCapacidad,
        public readonly bool $esAccesorio = false,
        public readonly ?int $duracionEstimadaMinutos = null,
        public readonly ?string $puntoPartidaPredeterminado = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->itemComercialId <= 0) {
            throw new InvalidArgumentException("El itemComercialId debe ser un entero positivo.");
        }

        // Si requiere agendamiento, obligatoriamente requiere reserva
        if ($this->requiereAgendamiento && !$this->requiereReserva) {
            throw new InvalidArgumentException("Un ítem no puede requerir agendamiento sin requerir reserva.");
        }

        if ($this->duracionEstimadaMinutos !== null && $this->duracionEstimadaMinutos <= 0) {
            throw new InvalidArgumentException("La duración estimada en minutos debe ser mayor a 0.");
        }
    }
}

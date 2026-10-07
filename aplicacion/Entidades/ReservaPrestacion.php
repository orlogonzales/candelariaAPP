<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\EstadoAgendamientoPrestacion;
use Aplicacion\Reservas\TipoCapacidad;
use InvalidArgumentException;

/**
 * Entidad de dominio ReservaPrestacion:
 * Unidad operable/agendable individual desglosada de la reserva.
 */
class ReservaPrestacion
{
    /**
     * @param int[] $participanteIds
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $reservaId,
        public readonly int $ventaLineaId,
        public readonly ?int $ventaLineaComponenteId,
        public readonly int $itemComercialId,
        public readonly string $conceptoCodigo,
        public readonly string $conceptoNombre,
        public readonly string $unidadMedida,
        public readonly float $cantidad,
        public readonly TipoCapacidad $tipoCapacidad,
        public readonly bool $requiereAgendamiento,
        public readonly bool $requiereParticipantes,
        public readonly EstadoAgendamientoPrestacion $estadoAgendamiento,
        public readonly ?string $fechaServicio = null,
        public readonly ?string $horaServicio = null,
        public readonly ?string $puntoEncuentro = null,
        public readonly ?string $notasPrestacion = null,
        public readonly int $versionBloqueo = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $participanteIds = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad de la prestación debe ser estrictamente mayor a 0.");
        }
        if (trim($this->conceptoCodigo) === '') {
            throw new InvalidArgumentException("El código de concepto no puede estar vacío.");
        }
        if (trim($this->conceptoNombre) === '') {
            throw new InvalidArgumentException("El nombre de concepto no puede estar vacío.");
        }

        if ($this->estadoAgendamiento === EstadoAgendamientoPrestacion::PENDIENTE_PROGRAMAR && $this->fechaServicio !== null) {
            throw new InvalidArgumentException("Una prestación PENDIENTE_PROGRAMAR no debe tener fecha de servicio asignada.");
        }
        if ($this->estadoAgendamiento === EstadoAgendamientoPrestacion::PROGRAMADA && $this->fechaServicio === null) {
            throw new InvalidArgumentException("Una prestación PROGRAMADA requiere obligatoriamente fecha de servicio.");
        }
    }
}

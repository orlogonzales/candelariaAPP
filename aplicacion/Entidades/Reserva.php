<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\EstadoReserva;
use InvalidArgumentException;

/**
 * Entidad de dominio Reserva:
 * Cabecera administrativa del compromiso de servicio vinculada 1:1 con una Venta confirmada.
 */
class Reserva
{
    /**
     * @param ReservaPrestacion[] $prestaciones
     * @param ReservaParticipante[] $participantes
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $ventaId,
        public readonly int $clienteId,
        public readonly string $correlativo,
        public readonly EstadoReserva $estado,
        public readonly string $contactoNombre,
        public readonly ?string $contactoTipoDocumento,
        public readonly ?string $contactoNumeroDocumento,
        public readonly ?string $contactoTelefono,
        public readonly ?string $contactoEmail,
        public readonly ?string $notasOperativas = null,
        public readonly int $versionBloqueo = 1,
        public readonly int $creadoPor = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $prestaciones = [],
        public readonly array $participantes = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("organizacionId debe ser un entero positivo.");
        }
        if ($this->edicionId <= 0) {
            throw new InvalidArgumentException("edicionId debe ser un entero positivo.");
        }
        if ($this->ventaId <= 0) {
            throw new InvalidArgumentException("ventaId debe ser un entero positivo.");
        }
        if ($this->clienteId <= 0) {
            throw new InvalidArgumentException("clienteId debe ser un entero positivo.");
        }
        if (trim($this->correlativo) === '') {
            throw new InvalidArgumentException("El correlativo no puede estar vacío.");
        }
        if (trim($this->contactoNombre) === '') {
            throw new InvalidArgumentException("El nombre de contacto no puede estar vacío.");
        }
        if ($this->versionBloqueo < 1) {
            throw new InvalidArgumentException("versionBloqueo debe ser >= 1.");
        }
    }
}

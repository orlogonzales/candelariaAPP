<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\RangoEtarioParticipante;
use Aplicacion\Reservas\RegimenAlimentario;
use InvalidArgumentException;

/**
 * Entidad de dominio ReservaParticipante:
 * Pasajero o beneficiario operativo del servicio (PII operacional minimizada y legítimamente necesaria).
 * PARTICIPANTE != CLIENTE.
 */
class ReservaParticipante
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $reservaId,
        public readonly ?int $personaId,
        public readonly int $tipoDocumentoId,
        public readonly string $numeroDocumento,
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly ?string $nacionalidad = null,
        public readonly ?RangoEtarioParticipante $rangoEtario = null,
        public readonly ?string $telefonoContacto = null,
        public readonly ?string $tallaIndumentaria = null,
        public readonly bool $requiereAsistenciaMovilidad = false,
        public readonly ?RegimenAlimentario $regimenAlimentario = null,
        public readonly bool $esTitularReserva = false,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if (trim($this->nombres) === '') {
            throw new InvalidArgumentException("Los nombres del participante no pueden estar vacíos.");
        }
        if (trim($this->apellidos) === '') {
            throw new InvalidArgumentException("Los apellidos del participante no pueden estar vacíos.");
        }
        if (trim($this->numeroDocumento) === '') {
            throw new InvalidArgumentException("El número de documento no puede estar vacío.");
        }
        if ($this->tipoDocumentoId <= 0) {
            throw new InvalidArgumentException("tipoDocumentoId debe ser un entero positivo.");
        }
        if ($this->nacionalidad !== null && strlen(trim($this->nacionalidad)) !== 2) {
            throw new InvalidArgumentException("La nacionalidad debe ser un código ISO-2 válido (ej. PE, BO, US).");
        }
    }
}

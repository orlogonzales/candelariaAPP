<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\RolOperativoRecurso;
use InvalidArgumentException;

/**
 * Entidad de dominio OperacionSalidaRecurso:
 * Asignación específica de un recurso físico o personal a una salida operativa.
 */
class OperacionSalidaRecurso
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $salidaId,
        public readonly ?int $recursoFisicoId,
        public readonly ?int $personaId,
        public readonly RolOperativoRecurso $rolOperativo,
        public readonly ?string $notas = null,
        public readonly int $asignadoPor = 1,
        public readonly ?string $asignadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->salidaId <= 0) {
            throw new InvalidArgumentException("El salidaId debe ser un entero positivo.");
        }
        if ($this->recursoFisicoId === null && $this->personaId === null) {
            throw new InvalidArgumentException("Debe asignarse al menos un recurso físico o una persona operativa.");
        }
    }
}

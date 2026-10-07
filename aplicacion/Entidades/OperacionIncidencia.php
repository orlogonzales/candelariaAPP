<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\TipoIncidenciaOperativa;
use InvalidArgumentException;

/**
 * Entidad de dominio OperacionIncidencia:
 * Registro estructurado de incidencias operativas ocurridas en salidas de campo.
 */
class OperacionIncidencia
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $salidaId,
        public readonly TipoIncidenciaOperativa $tipoIncidencia,
        public readonly string $descripcion,
        public readonly ?string $accionesTomadas = null,
        public readonly bool $afectoContinuidad = false,
        public readonly int $registradoPor = 1,
        public readonly ?string $registradoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->salidaId <= 0) {
            throw new InvalidArgumentException("El salidaId debe ser un entero positivo.");
        }
        $descTrim = trim($this->descripcion);
        if ($descTrim === '') {
            throw new InvalidArgumentException("La descripción de la incidencia no puede estar vacía.");
        }
    }
}

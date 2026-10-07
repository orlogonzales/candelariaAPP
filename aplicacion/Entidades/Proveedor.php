<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\EstadoProveedor;
use InvalidArgumentException;

/**
 * Entidad de dominio Proveedor:
 * Prestador o socio externo para la ejecución de servicios y provisión de recursos.
 * Reutiliza 'personas' como identidad base (desacoplada).
 */
class Proveedor
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $personaId,
        public readonly ?string $tipoServicioPrincipal,
        public readonly EstadoProveedor $estado = EstadoProveedor::ACTIVO,
        public readonly ?string $notasContacto = null,
        public readonly int $creadoPor = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El organizacionId debe ser un entero positivo.");
        }
        if ($this->personaId <= 0) {
            throw new InvalidArgumentException("El personaId debe ser un entero positivo.");
        }
    }
}

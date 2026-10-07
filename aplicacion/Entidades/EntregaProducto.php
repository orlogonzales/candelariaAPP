<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Reservas\EstadoEntregaProducto;
use InvalidArgumentException;

/**
 * Entidad de dominio EntregaProducto:
 * Orden administrativa de despacho de bienes tangibles vendidos (Frontera desacoplada).
 */
class EntregaProducto
{
    /**
     * @param EntregaItem[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $ventaId,
        public readonly int $clienteId,
        public readonly string $correlativo,
        public readonly EstadoEntregaProducto $estado,
        public readonly string $contactoNombre,
        public readonly ?string $contactoTelefono = null,
        public readonly ?string $direccionEntrega = null,
        public readonly ?string $fechaEntrega = null,
        public readonly ?int $entregadoPor = null,
        public readonly ?string $notasDespacho = null,
        public readonly int $versionBloqueo = 1,
        public readonly int $creadoPor = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $items = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("organizacionId debe ser un entero positivo.");
        }
        if ($this->ventaId <= 0) {
            throw new InvalidArgumentException("ventaId debe ser un entero positivo.");
        }
        if (trim($this->correlativo) === '') {
            throw new InvalidArgumentException("El correlativo de entrega no puede estar vacío.");
        }
    }
}

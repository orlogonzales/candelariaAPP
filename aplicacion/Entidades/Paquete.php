<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Catalogo\EstadoCatalogo;
use InvalidArgumentException;

/**
 * Entidad de dominio Paquete:
 * Oferta empaquetada que agrupa múltiples ítems comerciales con precio comercial propio.
 */
class Paquete
{
    /**
     * @param PaqueteItem[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly EstadoCatalogo $estado = EstadoCatalogo::ACTIVO,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $items = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El ID de organización debe ser un entero positivo.");
        }

        if (trim($this->codigo) === '') {
            throw new InvalidArgumentException("El código del paquete no puede estar vacío.");
        }

        if (!preg_match('/^[A-Z0-9_]{2,60}$/', $this->codigo)) {
            throw new InvalidArgumentException("El código del paquete debe estar en MAYÚSCULAS y contener entre 2 y 60 caracteres alfanuméricos/guiones bajos (recibido: '{$this->codigo}').");
        }

        if (trim($this->nombre) === '') {
            throw new InvalidArgumentException("El nombre del paquete no puede estar vacío.");
        }

        if (mb_strlen($this->nombre) > 150) {
            throw new InvalidArgumentException("El nombre del paquete no puede exceder 150 caracteres.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'items' => array_map(fn(PaqueteItem $pi) => $pi->toArray(), $this->items),
        ];
    }
}

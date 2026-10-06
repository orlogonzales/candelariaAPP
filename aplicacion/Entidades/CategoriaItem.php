<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Catalogo\EstadoCatalogo;
use InvalidArgumentException;

/**
 * Entidad de dominio CategoriaItem:
 * Agrupador taxonómico maestro por tenant para el catálogo comercial.
 */
class CategoriaItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly int $orden = 0,
        public readonly EstadoCatalogo $estado = EstadoCatalogo::ACTIVO,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if (trim($this->codigo) === '') {
            throw new InvalidArgumentException("El código de la categoría no puede estar vacío.");
        }

        if (!preg_match('/^[A-Z0-9_]{2,50}$/', $this->codigo)) {
            throw new InvalidArgumentException("El código de la categoría debe estar en MAYÚSCULAS y contener entre 2 y 50 caracteres alfanuméricos/guiones bajos (recibido: '{$this->codigo}').");
        }

        if (trim($this->nombre) === '') {
            throw new InvalidArgumentException("El nombre de la categoría no puede estar vacío.");
        }

        if (mb_strlen($this->nombre) > 100) {
            throw new InvalidArgumentException("El nombre de la categoría no puede exceder 100 caracteres.");
        }

        if ($this->orden < 0) {
            throw new InvalidArgumentException("El orden de la categoría debe ser un entero no negativo.");
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
            'orden' => $this->orden,
            'estado' => $this->estado->value,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}

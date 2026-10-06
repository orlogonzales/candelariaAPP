<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use InvalidArgumentException;

/**
 * Entidad de dominio ItemComercial:
 * Maestro de bienes y prestaciones comerciales (PRODUCTO o SERVICIO) por organización.
 */
class ItemComercial
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $categoriaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly TipoItemComercial $tipo,
        public readonly UnidadMedidaItem $unidadMedida,
        public readonly ?string $descripcion = null,
        public readonly EstadoCatalogo $estado = EstadoCatalogo::ACTIVO,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El ID de organización debe ser un entero positivo.");
        }

        if ($this->categoriaId <= 0) {
            throw new InvalidArgumentException("El ID de categoría debe ser un entero positivo.");
        }

        if (trim($this->codigo) === '') {
            throw new InvalidArgumentException("El código del ítem comercial no puede estar vacío.");
        }

        if (!preg_match('/^[A-Z0-9_]{2,60}$/', $this->codigo)) {
            throw new InvalidArgumentException("El código del ítem comercial debe estar en MAYÚSCULAS y contener entre 2 y 60 caracteres alfanuméricos/guiones bajos (recibido: '{$this->codigo}').");
        }

        if (trim($this->nombre) === '') {
            throw new InvalidArgumentException("El nombre del ítem comercial no puede estar vacío.");
        }

        if (mb_strlen($this->nombre) > 150) {
            throw new InvalidArgumentException("El nombre del ítem comercial no puede exceder 150 caracteres.");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacion_id' => $this->organizacionId,
            'categoria_id' => $this->categoriaId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'unidad_medida' => $this->unidadMedida->value,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}

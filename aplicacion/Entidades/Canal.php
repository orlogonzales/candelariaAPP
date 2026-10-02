<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad Canal: Catálogo extensible de medios o interfaces de ingreso de operaciones.
 */
class Canal
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly bool $activo = true,
        public readonly ?string $creadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: (int) $datos['id'],
            codigo: (string) $datos['codigo'],
            nombre: (string) $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            activo: (bool) ($datos['activo'] ?? true),
            creadoEn: $datos['creado_en'] ?? null
        );
    }
}

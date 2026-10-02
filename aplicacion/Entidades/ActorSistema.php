<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad ActorSistema: Catálogo controlado de identidades técnicas / actores virtuales.
 */
class ActorSistema
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly bool $esCritico = false,
        public readonly bool $activo = true,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: (int) $datos['id'],
            codigo: (string) $datos['codigo'],
            nombre: (string) $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            esCritico: (bool) ($datos['es_critico'] ?? false),
            activo: (bool) ($datos['activo'] ?? true),
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }
}

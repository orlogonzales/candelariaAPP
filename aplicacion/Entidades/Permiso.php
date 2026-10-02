<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad Permiso: Privilegio atómico de ejecución en el modelo RBAC de CandelariaAPP.
 */
class Permiso
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $moduloId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            moduloId: (int) $datos['modulo_id'],
            codigo: (string) $datos['codigo'],
            nombre: (string) $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null
        );
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad Rol: Agrupador de permisos de acceso en el modelo RBAC de CandelariaAPP.
 */
class Rol
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $organizacionId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly bool $esSistema = false,
        public readonly ?string $creadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            organizacionId: isset($datos['organizacion_id']) ? (int) $datos['organizacion_id'] : null,
            codigo: (string) $datos['codigo'],
            nombre: (string) $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            esSistema: (bool) ($datos['es_sistema'] ?? false),
            creadoEn: $datos['creado_en'] ?? null
        );
    }
}

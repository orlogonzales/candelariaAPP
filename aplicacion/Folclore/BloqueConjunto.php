<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class BloqueConjunto
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $conjuntoId,
        public string $codigo,
        public string $nombre,
        public ?string $responsableNombre = null,
        public ?string $telefonoContacto = null,
        public bool $activo = true,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            organizacionId: (int)$datos['organizacion_id'],
            conjuntoId: (int)$datos['conjunto_id'],
            codigo: (string)$datos['codigo'],
            nombre: (string)$datos['nombre'],
            responsableNombre: $datos['responsable_nombre'] ?? null,
            telefonoContacto: $datos['telefono_contacto'] ?? null,
            activo: (bool)($datos['activo'] ?? true),
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'organizacion_id'    => $this->organizacionId,
            'conjunto_id'        => $this->conjuntoId,
            'codigo'             => $this->codigo,
            'nombre'             => $this->nombre,
            'responsable_nombre' => $this->responsableNombre,
            'telefono_contacto'  => $this->telefonoContacto,
            'activo'             => $this->activo ? 1 : 0,
        ];
    }
}

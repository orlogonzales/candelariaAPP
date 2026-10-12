<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class TipoDanza
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public string $codigo,
        public string $nombre,
        public CategoriaDanza $categoriaOrigen = CategoriaDanza::TRAJES_DE_LUCES,
        public ?string $descripcion = null,
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
            codigo: (string)$datos['codigo'],
            nombre: (string)$datos['nombre'],
            categoriaOrigen: is_string($datos['categoria_origen'])
                ? CategoriaDanza::from($datos['categoria_origen'])
                : $datos['categoria_origen'],
            descripcion: $datos['descripcion'] ?? null,
            activo: (bool)($datos['activo'] ?? true),
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'               => $this->id,
            'organizacion_id'  => $this->organizacionId,
            'codigo'           => $this->codigo,
            'nombre'           => $this->nombre,
            'categoria_origen' => $this->categoriaOrigen->value,
            'descripcion'      => $this->descripcion,
            'activo'           => $this->activo ? 1 : 0,
        ];
    }
}

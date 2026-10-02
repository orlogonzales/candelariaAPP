<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

/**
 * Entidad TipoDocumento: Representa los tipos oficiales de identificación (DNI, RUC, etc.).
 */
class TipoDocumento
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $aplicaA, // 'NATURAL', 'JURIDICA', 'AMBOS'
        public readonly ?int $longitudExacta = null,
        public readonly bool $esAlfanumerico = false,
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
            aplicaA: (string) $datos['aplica_a'],
            longitudExacta: isset($datos['longitud_exacta']) ? (int) $datos['longitud_exacta'] : null,
            esAlfanumerico: (bool) ($datos['es_alfanumerico'] ?? false),
            activo: (bool) ($datos['activo'] ?? true),
            creadoEn: $datos['creado_en'] ?? null
        );
    }
}

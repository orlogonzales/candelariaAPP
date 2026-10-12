<?php

declare(strict_types=1);

namespace Aplicacion\Folclore;

class ConjuntoFolclorico
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $tipoDanzaId,
        public string $codigo,
        public string $nombre,
        public ?string $sigla = null,
        public ?string $fechaFundacion = null,
        public ?string $nombrePresidente = null,
        public ?string $telefonoContacto = null,
        public ?string $colorEmblematicoHex = null,
        public ?string $resenaHistorica = null,
        public bool $activo = true,
        public int $creadoPor = 1,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            organizacionId: (int)$datos['organizacion_id'],
            tipoDanzaId: (int)$datos['tipo_danza_id'],
            codigo: (string)$datos['codigo'],
            nombre: (string)$datos['nombre'],
            sigla: $datos['sigla'] ?? null,
            fechaFundacion: $datos['fecha_fundacion'] ?? null,
            nombrePresidente: $datos['nombre_presidente'] ?? null,
            telefonoContacto: $datos['telefono_contacto'] ?? null,
            colorEmblematicoHex: $datos['color_emblematico_hex'] ?? null,
            resenaHistorica: $datos['resena_historica'] ?? null,
            activo: (bool)($datos['activo'] ?? true),
            creadoPor: (int)($datos['creado_por'] ?? 1),
            creadoEn: $datos['creado_en'] ?? null,
            actualizadoEn: $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                    => $this->id,
            'organizacion_id'       => $this->organizacionId,
            'tipo_danza_id'         => $this->tipoDanzaId,
            'codigo'                => $this->codigo,
            'nombre'                => $this->nombre,
            'sigla'                 => $this->sigla,
            'fecha_fundacion'       => $this->fechaFundacion,
            'nombre_presidente'     => $this->nombrePresidente,
            'telefono_contacto'     => $this->telefonoContacto,
            'color_emblematico_hex' => $this->colorEmblematicoHex,
            'resena_historica'      => $this->resenaHistorica,
            'activo'                => $this->activo ? 1 : 0,
            'creado_por'            => $this->creadoPor,
        ];
    }
}

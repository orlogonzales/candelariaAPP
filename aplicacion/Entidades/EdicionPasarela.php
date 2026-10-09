<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

class EdicionPasarela
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $edicionId,
        public int $organizacionPasarelaId,
        public bool $habilitado,
        public ?string $creadoEn = null,
        public ?string $pasarelaCodigo = null,
        public ?string $pasarelaNombre = null
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'id'                       => $this->id,
            'organizacion_id'          => $this->organizacionId,
            'edicion_id'               => $this->edicionId,
            'organizacion_pasarela_id' => $this->organizacionPasarelaId,
            'pasarela_codigo'          => $this->pasarelaCodigo,
            'pasarela_nombre'          => $this->pasarelaNombre,
            'habilitado'               => $this->habilitado,
            'creado_en'                => $this->creadoEn,
        ];
    }
}

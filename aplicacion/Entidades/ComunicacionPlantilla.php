<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;

class ComunicacionPlantilla
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public string $nombre,
        public string $idioma,
        public CategoriaPlantilla $categoria,
        public string $cuerpoTexto,
        public array $parametrosMapeoJson,
        public EstadoPlantillaMeta $estadoMeta = EstadoPlantillaMeta::DRAFT,
        public ?string $metaTemplateId = null,
        public string $encabezadoTipo = 'NINGUNO',
        public ?string $pieTexto = null,
        public int $versionLocal = 1,
        public bool $activo = true,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {}

    public function aArreglo(): array
    {
        return [
            'id'                    => $this->id,
            'organizacion_id'       => $this->organizacionId,
            'nombre'                => $this->nombre,
            'idioma'                => $this->idioma,
            'categoria'             => $this->categoria->value,
            'meta_template_id'      => $this->metaTemplateId,
            'estado_meta'           => $this->estadoMeta->value,
            'cuerpo_texto'          => $this->cuerpoTexto,
            'encabezado_tipo'       => $this->encabezadoTipo,
            'pie_texto'             => $this->pieTexto,
            'parametros_mapeo_json' => $this->parametrosMapeoJson,
            'version_local'         => $this->versionLocal,
            'activo'                => $this->activo,
            'creado_en'             => $this->creadoEn,
            'actualizado_en'        => $this->actualizadoEn,
        ];
    }
}

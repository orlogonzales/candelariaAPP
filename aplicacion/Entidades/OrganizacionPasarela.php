<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Finanzas\AsumeComisionPasarela;

class OrganizacionPasarela
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $pasarelaId,
        public string $modo, // TEST, PRODUCCION
        public ?string $identificadorComercio,
        public ?string $credencialSecretaEnc,
        public ?string $webhookSecretoEnc,
        public float $porcentajeComision,
        public float $comisionFija,
        public AsumeComisionPasarela $asumeComision,
        public bool $activo,
        public int $versionBloqueo = 1,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null,
        public ?string $pasarelaCodigo = null,
        public ?string $pasarelaNombre = null
    ) {
    }

    public function aArreglo(bool $incluirSecretos = false): array
    {
        $datos = [
            'id'                     => $this->id,
            'organizacion_id'        => $this->organizacionId,
            'pasarela_id'            => $this->pasarelaId,
            'pasarela_codigo'        => $this->pasarelaCodigo,
            'pasarela_nombre'        => $this->pasarelaNombre,
            'modo'                   => $this->modo,
            'identificador_comercio' => $this->identificadorComercio,
            'porcentaje_comision'    => $this->porcentajeComision,
            'comision_fija'          => $this->comisionFija,
            'asume_comision'         => $this->asumeComision->value,
            'activo'                 => $this->activo,
            'version_bloqueo'        => $this->versionBloqueo,
            'creado_en'              => $this->creadoEn,
            'actualizado_en'         => $this->actualizadoEn,
        ];

        if ($incluirSecretos) {
            $datos['credencial_secreta_enc'] = $this->credencialSecretaEnc;
            $datos['webhook_secreto_enc'] = $this->webhookSecretoEnc;
        }

        return $datos;
    }
}

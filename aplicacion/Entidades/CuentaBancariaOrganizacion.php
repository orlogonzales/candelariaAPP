<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

class CuentaBancariaOrganizacion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public string $bancoNombre,
        public string $tipoCuenta, // CORRIENTE, AHORROS, BILLETERA_DIGITAL
        public string $moneda,
        public string $titularNombre,
        public string $numeroCuenta,
        public ?string $codigoInterbancario = null,
        public ?string $aliasIdentificador = null,
        public ?string $qrImagenUrl = null,
        public ?string $instruccionesPago = null,
        public bool $activo = true,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {
    }

    public function aArreglo(): array
    {
        return [
            'id'                   => $this->id,
            'organizacion_id'      => $this->organizacionId,
            'banco_nombre'         => $this->bancoNombre,
            'tipo_cuenta'          => $this->tipoCuenta,
            'moneda'               => $this->moneda,
            'titular_nombre'       => $this->titularNombre,
            'numero_cuenta'        => $this->numeroCuenta,
            'codigo_interbancario' => $this->codigoInterbancario,
            'alias_identificador'  => $this->aliasIdentificador,
            'qr_imagen_url'        => $this->qrImagenUrl,
            'instrucciones_pago'   => $this->instruccionesPago,
            'activo'               => $this->activo,
            'creado_en'            => $this->creadoEn,
            'actualizado_en'       => $this->actualizadoEn,
        ];
    }
}

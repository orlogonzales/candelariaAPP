<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Comunicaciones\EstadoMensaje;
use Aplicacion\Comunicaciones\TipoMensaje;

class ComunicacionMensaje
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public TipoMensaje $tipoMensaje,
        public string $direccion,
        public string $canal,
        public string $destinatarioTelefono,
        public string $destinatarioNombre,
        public string $contenidoTexto,
        public string $idempotencyKey,
        public string $correlacionId,
        public EstadoMensaje $estado = EstadoMensaje::ENCOLADO,
        public int $pesoEstado = 10,
        public ?int $conversacionId = null,
        public ?int $campanaId = null,
        public ?int $clienteId = null,
        public ?int $ventaId = null,
        public ?int $reservaId = null,
        public ?int $plantillaId = null,
        public ?array $parametrosEnviadosJson = null,
        public ?string $wamid = null,
        public float $costoEstimadoUsd = 0.00,
        public float $costoCalculadoUsd = 0.00,
        public float $costoConciliadoUsd = 0.00,
        public ?string $fechaConciliacion = null,
        public int $intentosRealizados = 0,
        public int $maxIntentos = 3,
        public ?string $proximoIntentoEn = null,
        public ?string $bloqueadoHasta = null,
        public ?int $creadoPor = null,
        public ?string $creadoEn = null,
        public ?string $actualizadoEn = null
    ) {}

    public function aArreglo(): array
    {
        return [
            'id'                       => $this->id,
            'organizacion_id'          => $this->organizacionId,
            'conversacion_id'          => $this->conversacionId,
            'campana_id'               => $this->campanaId,
            'tipo_mensaje'             => $this->tipoMensaje->value,
            'direccion'                => $this->direccion,
            'canal'                    => $this->canal,
            'destinatario_telefono'    => $this->destinatarioTelefono,
            'destinatario_nombre'      => $this->destinatarioNombre,
            'cliente_id'               => $this->clienteId,
            'venta_id'                 => $this->ventaId,
            'reserva_id'               => $this->reservaId,
            'plantilla_id'             => $this->plantillaId,
            'contenido_texto'          => $this->contenidoTexto,
            'parametros_enviados_json' => $this->parametrosEnviadosJson,
            'estado'                   => $this->estado->value,
            'peso_estado'              => $this->pesoEstado,
            'wamid'                    => $this->wamid,
            'idempotency_key'          => $this->idempotencyKey,
            'costo_estimado_usd'       => $this->costoEstimadoUsd,
            'costo_calculado_usd'      => $this->costoCalculadoUsd,
            'costo_conciliado_usd'     => $this->costoConciliadoUsd,
            'fecha_conciliacion'       => $this->fechaConciliacion,
            'intentos_realizados'      => $this->intentosRealizados,
            'max_intentos'             => $this->maxIntentos,
            'proximo_intento_en'       => $this->proximoIntentoEn,
            'bloqueado_hasta'          => $this->bloqueadoHasta,
            'creado_por'               => $this->creadoPor,
            'correlacion_id'           => $this->correlacionId,
            'creado_en'                => $this->creadoEn,
            'actualizado_en'           => $this->actualizadoEn,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

class ComunicacionConversacion
{
    public function __construct(
        public ?int $id,
        public int $organizacionId,
        public int $clienteId,
        public string $telefonoCliente,
        public int $numeroConversacion = 1,
        public ?int $operadorUsuarioId = null,
        public string $estado = 'ABIERTA',
        public ?string $ultimoMensajeClienteEn = null,
        public ?string $ventanaServicioExpiraEn = null,
        public int $totalMensajes = 0,
        public ?string $creadoEn = null,
        public ?string $cerradaEn = null,
        public ?string $actualizadoEn = null
    ) {}

    public function ventanaServicioActiva(): bool
    {
        if ($this->ventanaServicioExpiraEn === null) {
            return false;
        }

        return strtotime($this->ventanaServicioExpiraEn) > time();
    }

    public function aArreglo(): array
    {
        return [
            'id'                         => $this->id,
            'organizacion_id'            => $this->organizacionId,
            'cliente_id'                 => $this->clienteId,
            'telefono_cliente'           => $this->telefonoCliente,
            'numero_conversacion'        => $this->numeroConversacion,
            'operador_usuario_id'        => $this->operadorUsuarioId,
            'estado'                     => $this->estado,
            'ultimo_mensaje_cliente_en'  => $this->ultimoMensajeClienteEn,
            'ventana_servicio_expira_en' => $this->ventanaServicioExpiraEn,
            'ventana_activa'             => $this->ventanaServicioActiva(),
            'total_mensajes'             => $this->totalMensajes,
            'creado_en'                  => $this->creadoEn,
            'cerrada_en'                 => $this->cerradaEn,
            'actualizado_en'             => $this->actualizadoEn,
        ];
    }
}

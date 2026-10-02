<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use JsonException;

/**
 * Entidad Sesión: Representa un registro de sesión persistente en base de datos.
 * El ID almacenado es una representación no reversible (SHA-256) del token del cliente.
 */
class Sesion
{
    public function __construct(
        public readonly string $id, // Hash SHA-256 de 64 caracteres hex
        public readonly ?int $usuarioId,
        public readonly string $direccionIp,
        public readonly ?string $agenteUsuario,
        public readonly array $cargaUtil,
        public readonly int $ultimaActividad
    ) {
    }

    /**
     * Comprueba si la sesión ha expirado por inactividad según el umbral en segundos.
     */
    public function haExpirado(int $timeoutSegundos): bool
    {
        return (time() - $this->ultimaActividad) > $timeoutSegundos;
    }

    /**
     * Comprueba si la sesión ha superado su tiempo de vida absoluto desde su creación.
     */
    public function haSuperadoTiempoVidaAbsoluto(int $tiempoVidaAbsolutoSegundos): bool
    {
        $creadoEn = (int) ($this->cargaUtil['creado_en_timestamp'] ?? $this->ultimaActividad);
        return (time() - $creadoEn) > $tiempoVidaAbsolutoSegundos;
    }

    /**
     * Obtiene un valor arbitrario almacenado en la carga útil de la sesión.
     */
    public function obtenerDato(string $clave, mixed $defecto = null): mixed
    {
        return $this->cargaUtil[$clave] ?? $defecto;
    }

    /**
     * Construye una instancia a partir de una fila recuperada de la base de datos.
     */
    public static function desdeArreglo(array $datos): self
    {
        $cargaUtil = [];
        if (!empty($datos['carga_util'])) {
            try {
                $decodificado = json_decode((string) $datos['carga_util'], true, 512, JSON_THROW_ON_ERROR);
                $cargaUtil = is_array($decodificado) ? $decodificado : [];
            } catch (JsonException) {
                // Fallback para datos no JSON
                $cargaUtil = ['raw' => (string) $datos['carga_util']];
            }
        }

        return new self(
            id: (string) $datos['id'],
            usuarioId: isset($datos['usuario_id']) ? (int) $datos['usuario_id'] : null,
            direccionIp: (string) ($datos['direccion_ip'] ?? '127.0.0.1'),
            agenteUsuario: $datos['agente_usuario'] ?? null,
            cargaUtil: $cargaUtil,
            ultimaActividad: (int) ($datos['ultima_actividad'] ?? time())
        );
    }
}

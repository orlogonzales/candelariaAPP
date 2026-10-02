<?php

declare(strict_types=1);

namespace Nucleo\Http;

use InvalidArgumentException;

/**
 * Value Object inmutable que transporta el contexto transversal de ejecución
 * de cada operación en CandelariaAPP (Actor, Canal, Identidad, Correlación y Origen).
 */
class ContextoOperacion
{
    public function __construct(
        public readonly string $actorTipo, // 'HUMANO' | 'SISTEMA'
        public readonly ?int $usuarioId,
        public readonly ?int $actorSistemaId,
        public readonly ?string $actorSistemaCodigo,
        public readonly int $canalId,
        public readonly string $canalCodigo,
        public readonly string $correlacionId,
        public readonly ?string $origenIp = null,
        public readonly ?string $agenteUsuario = null,
        public readonly ?int $organizacionId = null,
        public readonly array $metadatos = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->actorTipo === 'HUMANO') {
            if ($this->usuarioId === null) {
                throw new InvalidArgumentException('Un actor humano requiere obligatoriamente usuarioId.');
            }
            if ($this->actorSistemaId !== null) {
                throw new InvalidArgumentException('Un actor humano no puede registrar actorSistemaId.');
            }
        } elseif ($this->actorTipo === 'SISTEMA') {
            if ($this->actorSistemaId === null) {
                throw new InvalidArgumentException('Un actor de sistema requiere obligatoriamente actorSistemaId.');
            }
            if ($this->usuarioId !== null) {
                throw new InvalidArgumentException('Un actor de sistema no puede registrar usuarioId.');
            }
        } else {
            throw new InvalidArgumentException("Tipo de actor no soportado: {$this->actorTipo}");
        }

        if (empty($this->correlacionId) || strlen($this->correlacionId) < 16) {
            throw new InvalidArgumentException('El correlacionId debe tener al menos 16 caracteres válidos.');
        }
    }

    /**
     * Genera un nuevo identificador de correlación UUID v4 criptográficamente seguro.
     */
    public static function generarCorrelacionId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Crea un contexto para operación ejecutada por un usuario humano autenticado.
     */
    public static function paraHumano(
        int $usuarioId,
        int $canalId,
        string $canalCodigo,
        ?string $origenIp = null,
        ?string $agenteUsuario = null,
        ?int $organizacionId = null,
        ?string $correlacionId = null,
        array $metadatos = []
    ): self {
        return new self(
            actorTipo: 'HUMANO',
            usuarioId: $usuarioId,
            actorSistemaId: null,
            actorSistemaCodigo: null,
            canalId: $canalId,
            canalCodigo: $canalCodigo,
            correlacionId: $correlacionId ?: self::generarCorrelacionId(),
            origenIp: $origenIp,
            agenteUsuario: $agenteUsuario,
            organizacionId: $organizacionId,
            metadatos: $metadatos
        );
    }

    /**
     * Crea un contexto para operación desatendida ejecutada por un actor técnico del sistema.
     */
    public static function paraSistema(
        int $actorSistemaId,
        string $actorSistemaCodigo,
        int $canalId,
        string $canalCodigo,
        ?string $origenIp = null,
        ?string $agenteUsuario = null,
        ?int $organizacionId = null,
        ?string $correlacionId = null,
        array $metadatos = []
    ): self {
        return new self(
            actorTipo: 'SISTEMA',
            usuarioId: null,
            actorSistemaId: $actorSistemaId,
            actorSistemaCodigo: $actorSistemaCodigo,
            canalId: $canalId,
            canalCodigo: $canalCodigo,
            correlacionId: $correlacionId ?: self::generarCorrelacionId(),
            origenIp: $origenIp,
            agenteUsuario: $agenteUsuario,
            organizacionId: $organizacionId,
            metadatos: $metadatos
        );
    }
}

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
    private static ?self $actual = null;

    public static function establecerActual(?self $contexto): void
    {
        self::$actual = $contexto;
    }

    public static function actual(): ?self
    {
        return self::$actual;
    }

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
     * Resuelve de forma segura el identificador de correlación HTTP recibido.
     * Si el candidato cumple la política (16 a 64 caracteres alfanuméricos/guiones), se propaga.
     * Si es nulo, inválido o sospechoso, genera un nuevo UUID v4 criptográfico.
     */
    public static function resolverCorrelacionId(?string $candidato): string
    {
        if ($candidato !== null) {
            $candidatoLimpio = trim($candidato);
            if (preg_match('/^[a-zA-Z0-9\-_]{16,64}$/', $candidatoLimpio)) {
                return $candidatoLimpio;
            }
        }

        return self::generarCorrelacionId();
    }

    /**
     * Extrae y resuelve el identificador de correlación desde la matriz de servidor HTTP ($_SERVER).
     */
    public static function extraerDeEncabezados(array $servidor): string
    {
        $candidato = $servidor['HTTP_X_CORRELATION_ID']
            ?? $servidor['HTTP_X_CORRELACION_ID']
            ?? $servidor['HTTP_X_REQUEST_ID']
            ?? null;

        return self::resolverCorrelacionId(is_string($candidato) ? $candidato : null);
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
            correlacionId: self::resolverCorrelacionId($correlacionId),
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
            correlacionId: self::resolverCorrelacionId($correlacionId),
            origenIp: $origenIp,
            agenteUsuario: $agenteUsuario,
            organizacionId: $organizacionId,
            metadatos: $metadatos
        );
    }
}

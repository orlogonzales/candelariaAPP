<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

/**
 * Entidad de dominio que representa un evento individual de escaneo sincronizado desde campo.
 */
class OperacionOfflineEvento
{
    public const TIPO_BOLETO_RESERVA = 'BOLETO_RESERVA';
    public const TIPO_PARTICIPANTE_SALIDA = 'PARTICIPANTE_SALIDA';
    public const TIPO_ACREDITACION_FOLCLORE = 'ACREDITACION_FOLCLORE';
    public const TIPO_PASE_PERSONAL = 'PASE_PERSONAL';

    public const RESULTADO_CONFIRMADO = 'CONFIRMADO';
    public const RESULTADO_CONFLICTO_DUPLICADO = 'CONFLICTO_DUPLICADO';
    public const RESULTADO_RECHAZADO_REVOCADO = 'RECHAZADO_REVOCADO';
    public const RESULTADO_RECHAZADO_EXPIRADO = 'RECHAZADO_EXPIRADO';
    public const RESULTADO_RECHAZADO_INEXISTENTE = 'RECHAZADO_INEXISTENTE';

    public function __construct(
        public ?int $id,
        public int $loteId,
        public int $organizacionId,
        public int $edicionId,
        public int $operadorId,
        public string $idempotenciaHash,
        public string $tokenQrHash,
        public string $tipoCredencial = self::TIPO_BOLETO_RESERVA,
        public ?int $referenciaId = null,
        public string $resultadoLocal = 'VALIDO_PROVISIONAL',
        public string $resultadoServidor = self::RESULTADO_CONFIRMADO,
        public ?string $motivoResolucion = null,
        public string $escaneadoEn = '',
        public ?string $sincronizadoEn = null,
        public ?array $metadatosJson = null
    ) {
    }

    public static function desdeArreglo(array $fila): self
    {
        $metadatos = null;
        if (isset($fila['metadatos_json'])) {
            $metadatos = is_array($fila['metadatos_json'])
                ? $fila['metadatos_json']
                : json_decode((string) $fila['metadatos_json'], true);
        }

        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            loteId: (int) $fila['lote_id'],
            organizacionId: (int) $fila['organizacion_id'],
            edicionId: (int) $fila['edicion_id'],
            operadorId: (int) $fila['operador_id'],
            idempotenciaHash: (string) $fila['idempotencia_hash'],
            tokenQrHash: (string) $fila['token_qr_hash'],
            tipoCredencial: (string) ($fila['tipo_credencial'] ?? self::TIPO_BOLETO_RESERVA),
            referenciaId: isset($fila['referencia_id']) ? (int) $fila['referencia_id'] : null,
            resultadoLocal: (string) ($fila['resultado_local'] ?? 'VALIDO_PROVISIONAL'),
            resultadoServidor: (string) ($fila['resultado_servidor'] ?? self::RESULTADO_CONFIRMADO),
            motivoResolucion: $fila['motivo_resolucion'] ?? null,
            escaneadoEn: (string) $fila['escaneado_en'],
            sincronizadoEn: $fila['sincronizado_en'] ?? null,
            metadatosJson: $metadatos
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'lote_id'            => $this->loteId,
            'organizacion_id'    => $this->organizacionId,
            'edicion_id'         => $this->edicionId,
            'operador_id'        => $this->operadorId,
            'idempotencia_hash'  => $this->idempotenciaHash,
            'token_qr_hash'      => $this->tokenQrHash,
            'tipo_credencial'    => $this->tipoCredencial,
            'referencia_id'      => $this->referenciaId,
            'resultado_local'    => $this->resultadoLocal,
            'resultado_servidor' => $this->resultadoServidor,
            'motivo_resolucion'  => $this->motivoResolucion,
            'escaneado_en'       => $this->escaneadoEn,
            'sincronizado_en'    => $this->sincronizadoEn,
            'metadatos_json'     => $this->metadatosJson,
        ];
    }
}

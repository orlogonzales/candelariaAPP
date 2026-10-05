<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Entidad Parámetro de Configuración: Representa un valor de configuración tipado y gobernado.
 * Distingue formalmente entre el ámbito PLATAFORMA y ORGANIZACION.
 * Protege contra la inclusión de secretos técnicos e impone tipado estricto.
 */
class ParametroConfiguracion
{
    public const AMBITO_PLATAFORMA   = 'PLATAFORMA';
    public const AMBITO_ORGANIZACION = 'ORGANIZACION';

    public const TIPO_STRING   = 'STRING';
    public const TIPO_INTEGER  = 'INTEGER';
    public const TIPO_DECIMAL  = 'DECIMAL';
    public const TIPO_BOOLEAN  = 'BOOLEAN';
    public const TIPO_DATE     = 'DATE';
    public const TIPO_DATETIME = 'DATETIME';

    private const TIPOS_VALIDOS = [
        self::TIPO_STRING,
        self::TIPO_INTEGER,
        self::TIPO_DECIMAL,
        self::TIPO_BOOLEAN,
        self::TIPO_DATE,
        self::TIPO_DATETIME,
    ];

    /**
     * Palabras clave prohibidas en códigos de parámetros para impedir almacenamiento
     * indebido de secretos técnicos que deben residir en .env o infraestructura segura.
     */
    private const PATRONES_SECRETOS = [
        'password',
        'contrasena',
        'secret',
        'secreto',
        'api_key',
        'apikey',
        'private_key',
        'token_privado',
        'app_key',
        'db_pass',
        'smtp_pass',
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly ?int $organizacionId,
        public readonly string $ambito,
        public readonly string $codigo,
        public readonly string $tipoDato,
        public readonly ?string $valor,
        public readonly ?string $valorDefecto = null,
        public readonly string $etiqueta = '',
        public readonly ?string $descripcion = null,
        public readonly ?array $reglasValidacion = null,
        public readonly bool $esSistema = true,
        public readonly bool $esPublico = false,
        public readonly bool $esEditable = true,
        public readonly string $estado = 'ACTIVO',
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarConsistencia();
    }

    private function validarConsistencia(): void
    {
        // 1. Ámbitos válidos
        if (!in_array($this->ambito, [self::AMBITO_PLATAFORMA, self::AMBITO_ORGANIZACION], true)) {
            throw new InvalidArgumentException("Ámbito de configuración no válido: '{$this->ambito}'");
        }

        // 2. Invariante de aislamiento de ámbito (Constraint chk_param_ambito_org)
        if ($this->ambito === self::AMBITO_PLATAFORMA && $this->organizacionId !== null) {
            throw new InvalidArgumentException('Un parámetro de ámbito PLATAFORMA no puede estar asociado a una organización específica.');
        }

        if ($this->ambito === self::AMBITO_ORGANIZACION && ($this->organizacionId === null || $this->organizacionId <= 0)) {
            throw new InvalidArgumentException('Un parámetro de ámbito ORGANIZACION requiere obligatoriamente un identificador de organización válido.');
        }

        // 3. Tipos de datos válidos
        if (!in_array($this->tipoDato, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de dato de configuración no válido: '{$this->tipoDato}'");
        }

        // 4. Código válido
        $codigoLimpio = trim($this->codigo);
        if ($codigoLimpio === '') {
            throw new InvalidArgumentException('El código del parámetro de configuración no puede estar vacío.');
        }

        // 5. Prohibición estricta de secretos técnicos en configuración ordinaria (Mandato H-07 / Directriz F1.2)
        $codigoMin = strtolower($codigoLimpio);
        foreach (self::PATRONES_SECRETOS as $patron) {
            if (str_contains($codigoMin, $patron)) {
                throw new InvalidArgumentException(
                    "Prohibición de Seguridad: '{$codigoLimpio}' parece contener un secreto técnico. Los secretos de infraestructura (claves, tokens permanentes, passwords) deben residir exclusivamente en variables de entorno (.env)."
                );
            }
        }

        // 6. Validar compatibilidad del valor con el tipo si no es nulo
        if ($this->valor !== null) {
            self::validarValorParaTipo($this->tipoDato, $this->valor, $this->reglasValidacion);
        }
    }

    /**
     * Valida si un valor en cadena es compatible con el tipo de dato y reglas configuradas.
     */
    public static function validarValorParaTipo(string $tipoDato, mixed $valor, ?array $reglas = null): void
    {
        $cadena = is_scalar($valor) ? (string) $valor : '';

        switch ($tipoDato) {
            case self::TIPO_BOOLEAN:
                if (!in_array(strtolower($cadena), ['1', '0', 'true', 'false'], true)) {
                    throw new InvalidArgumentException("El valor '{$cadena}' no es un booleano válido (esperado: 1, 0, true, false).");
                }
                break;

            case self::TIPO_INTEGER:
                if (!filter_var($cadena, FILTER_VALIDATE_INT) && $cadena !== '0') {
                    throw new InvalidArgumentException("El valor '{$cadena}' no es un entero válido.");
                }
                $intVal = (int) $cadena;
                if ($reglas !== null) {
                    if (isset($reglas['min']) && $intVal < (int) $reglas['min']) {
                        throw new InvalidArgumentException("El valor {$intVal} es menor al límite mínimo permitido ({$reglas['min']}).");
                    }
                    if (isset($reglas['max']) && $intVal > (int) $reglas['max']) {
                        throw new InvalidArgumentException("El valor {$intVal} supera el límite máximo permitido ({$reglas['max']}).");
                    }
                }
                break;

            case self::TIPO_DECIMAL:
                if (!is_numeric($cadena)) {
                    throw new InvalidArgumentException("El valor '{$cadena}' no es un número decimal válido.");
                }
                $floatVal = (float) $cadena;
                if ($reglas !== null) {
                    if (isset($reglas['min']) && $floatVal < (float) $reglas['min']) {
                        throw new InvalidArgumentException("El valor {$floatVal} es menor al límite mínimo permitido ({$reglas['min']}).");
                    }
                    if (isset($reglas['max']) && $floatVal > (float) $reglas['max']) {
                        throw new InvalidArgumentException("El valor {$floatVal} supera el límite máximo permitido ({$reglas['max']}).");
                    }
                }
                break;

            case self::TIPO_DATE:
                $d = DateTimeImmutable::createFromFormat('Y-m-d', $cadena);
                if (!$d || $d->format('Y-m-d') !== $cadena) {
                    throw new InvalidArgumentException("El valor '{$cadena}' no cumple con el formato de fecha requerido 'YYYY-MM-DD'.");
                }
                break;

            case self::TIPO_DATETIME:
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cadena);
                if (!$dt || $dt->format('Y-m-d H:i:s') !== $cadena) {
                    throw new InvalidArgumentException("El valor '{$cadena}' no cumple con el formato fecha-hora 'YYYY-MM-DD HH:MM:SS'.");
                }
                break;

            case self::TIPO_STRING:
            default:
                if ($reglas !== null) {
                    if (!empty($reglas['opciones']) && is_array($reglas['opciones'])) {
                        if (!in_array($cadena, $reglas['opciones'], true)) {
                            $opcionesStr = implode(', ', $reglas['opciones']);
                            throw new InvalidArgumentException("El valor '{$cadena}' no pertenece a las opciones permitidas: [{$opcionesStr}].");
                        }
                    }
                    if (!empty($reglas['longitud_exacta']) && strlen($cadena) !== (int) $reglas['longitud_exacta']) {
                        throw new InvalidArgumentException("La longitud del texto debe ser exactamente {$reglas['longitud_exacta']} caracteres.");
                    }
                    if (!empty($reglas['regex']) && !preg_match((string) $reglas['regex'], $cadena)) {
                        throw new InvalidArgumentException("El valor '{$cadena}' no cumple con la expresión regular de validación.");
                    }
                }
                break;
        }
    }

    /**
     * Retorna el valor nativo casteado según su definición de tipo.
     */
    public function obtenerValorCasteado(): mixed
    {
        $valorRaw = $this->valor ?? $this->valorDefecto;
        if ($valorRaw === null) {
            return null;
        }

        return match ($this->tipoDato) {
            self::TIPO_BOOLEAN  => in_array(strtolower($valorRaw), ['1', 'true', 'on', 'yes'], true),
            self::TIPO_INTEGER  => (int) $valorRaw,
            self::TIPO_DECIMAL  => (float) $valorRaw,
            self::TIPO_DATE,
            self::TIPO_DATETIME,
            self::TIPO_STRING   => (string) $valorRaw,
            default             => (string) $valorRaw,
        };
    }

    public static function desdeArreglo(array $fila): self
    {
        $reglas = null;
        if (!empty($fila['reglas_validacion_json'])) {
            $reglas = is_array($fila['reglas_validacion_json'])
                ? $fila['reglas_validacion_json']
                : json_decode((string) $fila['reglas_validacion_json'], true);
        }

        return new self(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            organizacionId: isset($fila['organizacion_id']) && $fila['organizacion_id'] !== null ? (int) $fila['organizacion_id'] : null,
            ambito: strtoupper(trim((string) ($fila['ambito'] ?? self::AMBITO_PLATAFORMA))),
            codigo: trim((string) ($fila['codigo'] ?? '')),
            tipoDato: strtoupper(trim((string) ($fila['tipo_dato'] ?? self::TIPO_STRING))),
            valor: isset($fila['valor']) ? (string) $fila['valor'] : null,
            valorDefecto: isset($fila['valor_defecto']) ? (string) $fila['valor_defecto'] : null,
            etiqueta: trim((string) ($fila['etiqueta'] ?? '')),
            descripcion: isset($fila['descripcion']) ? (string) $fila['descripcion'] : null,
            reglasValidacion: $reglas,
            esSistema: (bool) ($fila['es_sistema'] ?? true),
            esPublico: (bool) ($fila['es_publico'] ?? false),
            esEditable: (bool) ($fila['es_editable'] ?? true),
            estado: strtoupper(trim((string) ($fila['estado'] ?? 'ACTIVO'))),
            creadoEn: $fila['creado_en'] ?? null,
            actualizadoEn: $fila['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id'                     => $this->id,
            'organizacion_id'        => $this->organizacionId,
            'ambito'                 => $this->ambito,
            'codigo'                 => $this->codigo,
            'tipo_dato'              => $this->tipoDato,
            'valor'                  => $this->valor,
            'valor_defecto'          => $this->valorDefecto,
            'etiqueta'               => $this->etiqueta,
            'descripcion'            => $this->descripcion,
            'reglas_validacion_json' => $this->reglasValidacion ? json_encode($this->reglasValidacion, JSON_UNESCAPED_UNICODE) : null,
            'es_sistema'             => $this->esSistema ? 1 : 0,
            'es_publico'             => $this->esPublico ? 1 : 0,
            'es_editable'            => $this->esEditable ? 1 : 0,
            'estado'                 => $this->estado,
            'creado_en'              => $this->creadoEn,
            'actualizado_en'         => $this->actualizadoEn,
        ];
    }
}

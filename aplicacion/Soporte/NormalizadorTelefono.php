<?php

declare(strict_types=1);

namespace Aplicacion\Soporte;

use InvalidArgumentException;

/**
 * Normalizador canónico de números telefónicos a formato internacional E.164.
 * Regla de negocio: El backend es la autoridad de validación y persistencia.
 * E.164 requiere: signo '+' seguido por código de país y número nacional (8 a 15 dígitos).
 */
class NormalizadorTelefono
{
    /**
     * Normaliza un número telefónico a formato canónico E.164 (ej. +51987654321).
     * Retorna null si el valor es vacío o no se puede normalizar a un E.164 válido.
     */
    public static function normalizar(?string $telefono, string $codigoPaisPorDefecto = 'PE'): ?string
    {
        if ($telefono === null) {
            return null;
        }

        $cadena = trim($telefono);
        if ($cadena === '') {
            return null;
        }

        // Si contiene letras o caracteres no permitidos
        if (preg_match('/[a-zA-Z]/', $cadena)) {
            return null;
        }

        $tieneMasInicial = str_starts_with($cadena, '+');

        // Extraer únicamente los dígitos
        $soloDigitos = preg_replace('/\D+/', '', $cadena);
        if ($soloDigitos === '' || $soloDigitos === null) {
            return null;
        }

        $codigoPais = strtoupper(trim($codigoPaisPorDefecto));

        if ($tieneMasInicial) {
            $longitud = strlen($soloDigitos);
            // E.164 estándar internacional: entre 8 y 15 dígitos numéricos tras el '+'
            if ($longitud >= 8 && $longitud <= 15) {
                return '+' . $soloDigitos;
            }
            return null;
        }

        // Si no tiene '+' inicial, interpretar según el código de país
        if ($codigoPais === 'PE') {
            // Celular peruano estándar: 9 dígitos comenzando con 9
            if (strlen($soloDigitos) === 9 && str_starts_with($soloDigitos, '9')) {
                return '+51' . $soloDigitos;
            }

            // Celular peruano con prefijo 51 sin '+': 11 dígitos comenzando con 519
            if (strlen($soloDigitos) === 11 && str_starts_with($soloDigitos, '519')) {
                return '+' . $soloDigitos;
            }

            // Si tiene entre 8 y 15 dígitos y ya comienza con 51
            if (strlen($soloDigitos) >= 10 && strlen($soloDigitos) <= 15 && str_starts_with($soloDigitos, '51')) {
                return '+' . $soloDigitos;
            }
        }

        // Fallback internacional genérico si ya tiene longitud E.164 válida
        if (strlen($soloDigitos) >= 8 && strlen($soloDigitos) <= 15) {
            return '+' . $soloDigitos;
        }

        return null;
    }

    /**
     * Evalúa si un número telefónico es válido bajo el estándar canónico E.164.
     */
    public static function esValido(?string $telefono, string $codigoPaisPorDefecto = 'PE'): bool
    {
        return self::normalizar($telefono, $codigoPaisPorDefecto) !== null;
    }

    /**
     * Normaliza un número telefónico exigiendo obligatoriamente que cumpla E.164.
     * @throws InvalidArgumentException Si el número no es válido o está vacío.
     */
    public static function requerirE164(string $telefono, string $codigoPaisPorDefecto = 'PE'): string
    {
        $normalizado = self::normalizar($telefono, $codigoPaisPorDefecto);
        if ($normalizado === null) {
            throw new InvalidArgumentException(
                "El número de teléfono '{$telefono}' no corresponde a un formato internacional E.164 válido."
            );
        }

        return $normalizado;
    }
}

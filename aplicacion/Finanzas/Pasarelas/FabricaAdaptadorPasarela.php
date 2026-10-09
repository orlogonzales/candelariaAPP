<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use InvalidArgumentException;

/**
 * Fábrica de adaptadores de pasarela de pago.
 * Resuelve la instancia correspondiente según el código institucional del catálogo.
 */
class FabricaAdaptadorPasarela
{
    /** @var array<string, AdaptadorPasarelaInterfaz> */
    private static array $adaptadores = [];

    public static function crear(string $codigo): AdaptadorPasarelaInterfaz
    {
        return self::obtener($codigo);
    }

    public static function obtener(string $codigo): AdaptadorPasarelaInterfaz
    {
        $codigoNorm = strtoupper(trim($codigo));

        if (isset(self::$adaptadores[$codigoNorm])) {
            return self::$adaptadores[$codigoNorm];
        }

        $instancia = match ($codigoNorm) {
            'CULQI'       => new CulqiAdaptador(),
            'STRIPE'      => new StripeAdaptador(),
            'NIUBIZ'      => new NiubizAdaptador(),
            'MERCADOPAGO' => new MercadoPagoAdaptador(),
            default       => throw new InvalidArgumentException("Pasarela no soportada o sin adaptador automatizado: '{$codigoNorm}'.")
        };

        self::$adaptadores[$codigoNorm] = $instancia;
        return $instancia;
    }

    public static function registrarAdaptador(string $codigo, AdaptadorPasarelaInterfaz $adaptador): void
    {
        self::$adaptadores[strtoupper(trim($codigo))] = $adaptador;
    }

    public static function resetear(): void
    {
        self::$adaptadores = [];
    }
}

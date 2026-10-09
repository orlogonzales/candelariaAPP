<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

use InvalidArgumentException;

/**
 * Calculadora financiera de alta precisión basada en aritmética decimal BCMath.
 * Evita cualquier imprecisión de punto flotante binario (IEEE 754).
 */
class CalculadoraFinanciera
{
    private const ESCALA_INTERNA = 4;
    private const ESCALA_MONETARIA = 2;

    public static function sumar(string|float|int $a, string|float|int $b): string
    {
        $res = bcadd(self::formatear($a), self::formatear($b), self::ESCALA_INTERNA);
        return self::redondearMoneda($res);
    }

    public static function restar(string|float|int $a, string|float|int $b): string
    {
        $res = bcsub(self::formatear($a), self::formatear($b), self::ESCALA_INTERNA);
        return self::redondearMoneda($res);
    }

    public static function multiplicar(string|float|int $a, string|float|int $b): string
    {
        $res = bcmul(self::formatear($a), self::formatear($b), self::ESCALA_INTERNA);
        return self::redondearMoneda($res);
    }

    public static function dividir(string|float|int $a, string|float|int $b): string
    {
        $bForm = self::formatear($b);
        if (bccomp($bForm, '0.0000', self::ESCALA_INTERNA) === 0) {
            throw new InvalidArgumentException('División financiera por cero no permitida.');
        }
        $res = bcdiv(self::formatear($a), $bForm, self::ESCALA_INTERNA);
        return self::redondearMoneda($res);
    }

    public static function comparar(string|float|int $a, string|float|int $b): int
    {
        return bccomp(self::formatear($a), self::formatear($b), self::ESCALA_MONETARIA);
    }

    /**
     * Calcula los 4 importes financieros según la regla de comisión D-01:
     * - Si ORGANIZACION: El cliente paga el valor comercial ($vAmortizar); la organización asume el costo.
     * - Si CLIENTE: El cliente paga un recargo para que la organización reciba líquido exactamente $vAmortizar.
     *
     * @return array{
     *   monto_cobrado_cliente: string,
     *   comision_pasarela: string,
     *   monto_neto_recibido: string,
     *   monto_aplicado_venta: string
     * }
     */
    public static function calcularComision(
        string|float|int $vAmortizar,
        string|float|int $porcentajeComision,
        string|float|int $comisionFija,
        AsumeComisionPasarela $asume
    ): array {
        $amortizar = self::redondearMoneda(self::formatear($vAmortizar));
        $pct = bcdiv(self::formatear($porcentajeComision), '100.0000', self::ESCALA_INTERNA);
        $fija = self::redondearMoneda(self::formatear($comisionFija));

        if ($asume === AsumeComisionPasarela::ORGANIZACION) {
            $montoCobrado = $amortizar;
            $comisionPorcentual = bcmul($montoCobrado, $pct, self::ESCALA_INTERNA);
            $comisionTotal = self::redondearMoneda(bcadd($comisionPorcentual, $fija, self::ESCALA_INTERNA));
            $montoNeto = self::redondearMoneda(bcsub($montoCobrado, $comisionTotal, self::ESCALA_INTERNA));

            return [
                'monto_cobrado_cliente' => $montoCobrado,
                'comision_pasarela'     => $comisionTotal,
                'monto_neto_recibido'   => $montoNeto,
                'monto_aplicado_venta'  => $amortizar,
            ];
        }

        // Asume CLIENTE: montoCobrado = (amortizar + fija) / (1 - pct)
        $unoMenosPct = bcsub('1.0000', $pct, self::ESCALA_INTERNA);
        if (bccomp($unoMenosPct, '0.0000', self::ESCALA_INTERNA) <= 0) {
            throw new InvalidArgumentException('Porcentaje de comisión no puede ser igual o mayor al 100%.');
        }

        $numerador = bcadd($amortizar, $fija, self::ESCALA_INTERNA);
        $montoCobrado = self::redondearMoneda(bcdiv($numerador, $unoMenosPct, self::ESCALA_INTERNA));
        $comisionTotal = self::redondearMoneda(bcsub($montoCobrado, $amortizar, self::ESCALA_INTERNA));
        $montoNeto = $amortizar;

        return [
            'monto_cobrado_cliente' => $montoCobrado,
            'comision_pasarela'     => $comisionTotal,
            'monto_neto_recibido'   => $montoNeto,
            'monto_aplicado_venta'  => $amortizar,
        ];
    }

    public static function redondearMoneda(string $valor): string
    {
        return number_format(round((float) $valor, self::ESCALA_MONETARIA, PHP_ROUND_HALF_UP), self::ESCALA_MONETARIA, '.', '');
    }

    private static function formatear(string|float|int $valor): string
    {
        if (is_int($valor)) {
            return sprintf('%d.0000', $valor);
        }
        if (is_float($valor)) {
            return sprintf('%.4f', $valor);
        }
        return number_format((float) $valor, self::ESCALA_INTERNA, '.', '');
    }
}

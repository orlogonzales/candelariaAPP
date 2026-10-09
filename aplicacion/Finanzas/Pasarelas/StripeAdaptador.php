<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;

/**
 * Adaptador para Stripe Payments.
 * Valida firma Stripe-Signature con timestamp t= y firma v1= HMAC-SHA256.
 * Protección contra ataques de repetición (tolerancia máxima de 300 segundos).
 */
class StripeAdaptador implements AdaptadorPasarelaInterfaz
{
    use ProcesarEventoTrait;

    private const TOLERANCIA_SEGUNDOS = 300;

    public function obtenerCodigo(): string
    {
        return 'STRIPE';
    }

    public function verificarFirmaWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados,
        string $secretoPlano
    ): bool {
        if (trim($secretoPlano) === '') {
            return false;
        }

        $headerFirma = $encabezados['http_stripe_signature']
            ?? $encabezados['stripe-signature']
            ?? $encabezados['HTTP_STRIPE_SIGNATURE']
            ?? null;

        if ($headerFirma === null || trim((string) $headerFirma) === '') {
            return false;
        }

        // Parsear t=123456,v1=abcdef...
        $partes = explode(',', (string) $headerFirma);
        $timestamp = null;
        $firmasV1 = [];

        foreach ($partes as $p) {
            $kv = explode('=', trim($p), 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') {
                    $timestamp = (int) $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $firmasV1[] = $kv[1];
                }
            }
        }

        if ($timestamp === null || empty($firmasV1)) {
            return false;
        }

        // Validar tolerancia temporal contra ataques de repetición
        if (abs(time() - $timestamp) > self::TOLERANCIA_SEGUNDOS) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payloadRaw}";
        $firmaEsperada = hash_hmac('sha256', $signedPayload, $secretoPlano);

        foreach ($firmasV1 as $firma) {
            if (hash_equals($firmaEsperada, $firma)) {
                return true;
            }
        }

        return false;
    }

    public function parsearEventoWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados
    ): EventoWebhookResultado {
        $data = json_decode($payloadRaw, true);
        if (!is_array($data)) {
            return EventoWebhookResultado::invalido('Payload JSON Stripe no válido.');
        }

        $tipo = (string) ($data['type'] ?? '');
        $dataObject = is_array($data['data']['object'] ?? null) ? $data['data']['object'] : [];

        $esExitoso = ($tipo === 'payment_intent.succeeded' || $tipo === 'charge.succeeded');
        $tipoEvento = $esExitoso ? 'CARGO_EXITOSO' : ($tipo === 'payment_intent.payment_failed' ? 'CARGO_FALLIDO' : 'OTRO');

        $transaccionId = (string) ($dataObject['id'] ?? '');
        $montoCents = (float) ($dataObject['amount_received'] ?? $dataObject['amount'] ?? 0);
        $monto = round($montoCents / 100.0, 2);
        $moneda = strtoupper((string) ($dataObject['currency'] ?? 'PEN'));

        $metadata = is_array($dataObject['metadata'] ?? null) ? $dataObject['metadata'] : [];
        $ventaId = isset($metadata['venta_id']) && is_numeric($metadata['venta_id']) ? (int) $metadata['venta_id'] : null;

        $metodoDetalles = $dataObject['payment_method_details']['card'] ?? [];
        $marca = $metodoDetalles['brand'] ?? null;
        $ultimosCuatro = $metodoDetalles['last4'] ?? null;

        $claveIdemp = (string) ($data['id'] ?? $transaccionId);

        return new EventoWebhookResultado(
            esValido: true,
            tipoEvento: $tipoEvento,
            transaccionExternaId: $transaccionId !== '' ? $transaccionId : null,
            ordenCheckoutId: null,
            monto: $monto,
            moneda: $moneda,
            claveIdempotencia: $claveIdemp !== '' ? $claveIdemp : null,
            tarjetaMarca: $marca ? strtoupper((string) $marca) : null,
            tarjetaUltimosCuatro: $ultimosCuatro ? (string) $ultimosCuatro : null,
            codigoRespuesta: (string) ($dataObject['status'] ?? 'succeeded'),
            mensajeRespuesta: 'Pago Stripe procesado',
            payloadSanitizado: $data,
            ventaId: $ventaId
        );
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;

/**
 * Adaptador para Mercado Pago Perú.
 * Valida cabecera x-signature (ts=...,v1=...) y normaliza eventos de pago.
 */
class MercadoPagoAdaptador implements AdaptadorPasarelaInterfaz
{
    use ProcesarEventoTrait;

    private const TOLERANCIA_SEGUNDOS = 300;

    public function obtenerCodigo(): string
    {
        return 'MERCADOPAGO';
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

        $headerFirma = $encabezados['http_x_signature']
            ?? $encabezados['x-signature']
            ?? $encabezados['HTTP_X_SIGNATURE']
            ?? null;

        if ($headerFirma === null || trim((string) $headerFirma) === '') {
            return false;
        }

        // Parsear ts=...,v1=...
        $partes = explode(',', (string) $headerFirma);
        $ts = null;
        $hashV1 = null;

        foreach ($partes as $p) {
            $kv = explode('=', trim($p), 2);
            if (count($kv) === 2) {
                if ($kv[0] === 'ts') {
                    $ts = (int) $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $hashV1 = $kv[1];
                }
            }
        }

        if ($ts === null || $hashV1 === null) {
            return false;
        }

        if (abs(time() - $ts) > self::TOLERANCIA_SEGUNDOS) {
            return false;
        }

        // Generar hash esperado
        $manifest = "id:{$payloadRaw};ts:{$ts};";
        // Si el payload es JSON, MP usa el query param o data.id en el manifest
        $data = json_decode($payloadRaw, true);
        $dataId = (string) ($data['data']['id'] ?? $data['id'] ?? '');
        if ($dataId !== '') {
            $manifest = "id:{$dataId};ts:{$ts};";
        }

        $calculado = hash_hmac('sha256', $manifest, $secretoPlano);
        if (hash_equals($calculado, $hashV1)) {
            return true;
        }

        $requestId = $encabezados['x-request-id'] ?? $encabezados['http_x_request_id'] ?? null;
        if ($requestId !== null && $dataId !== '') {
            $manifestWithReq = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
            if (hash_equals(hash_hmac('sha256', $manifestWithReq, $secretoPlano), $hashV1)) {
                return true;
            }
        }

        // Fallback: HMAC directo sobre payloadRaw
        $calculadoDirecto = hash_hmac('sha256', "ts={$ts}&data={$payloadRaw}", $secretoPlano);
        return hash_equals($calculadoDirecto, $hashV1);
    }

    public function parsearEventoWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados
    ): EventoWebhookResultado {
        $data = json_decode($payloadRaw, true);
        if (!is_array($data)) {
            return EventoWebhookResultado::invalido('Payload JSON Mercado Pago no válido.');
        }

        $action = (string) ($data['action'] ?? $data['type'] ?? '');
        $paymentData = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $status = strtolower((string) ($paymentData['status'] ?? $data['status'] ?? 'approved'));
        $esExitoso = in_array($status, ['approved', 'exitoso'], true);
        $tipoEvento = $esExitoso ? 'CARGO_EXITOSO' : ($status === 'rejected' ? 'CARGO_FALLIDO' : 'OTRO');

        $transaccionId = (string) ($paymentData['id'] ?? $data['id'] ?? '');
        $monto = round((float) ($paymentData['transaction_amount'] ?? $data['transaction_amount'] ?? 0), 2);
        $moneda = strtoupper((string) ($paymentData['currency_id'] ?? $data['currency_id'] ?? 'PEN'));

        $metadata = is_array($paymentData['metadata'] ?? null) ? $paymentData['metadata'] : [];
        $ventaId = isset($metadata['venta_id']) && is_numeric($metadata['venta_id']) ? (int) $metadata['venta_id'] : null;

        $metodo = $paymentData['payment_method'] ?? [];
        $marca = isset($metodo['id']) ? strtoupper((string) $metodo['id']) : null;
        $ultimosCuatro = isset($paymentData['card']['last_four_digits']) ? (string) $paymentData['card']['last_four_digits'] : null;

        $claveIdemp = (string) ($data['id'] ?? $transaccionId);

        $comisionMP = 0.0;
        if (!empty($paymentData['fee_details']) && is_array($paymentData['fee_details'])) {
            foreach ($paymentData['fee_details'] as $fee) {
                $comisionMP += (float) ($fee['amount'] ?? 0);
            }
        }

        return new EventoWebhookResultado(
            esValido: true,
            tipoEvento: $tipoEvento,
            transaccionExternaId: $transaccionId !== '' ? $transaccionId : null,
            ordenCheckoutId: null,
            monto: $monto,
            comisionPasarela: round($comisionMP, 2),
            moneda: $moneda,
            claveIdempotencia: $claveIdemp !== '' ? $claveIdemp : null,
            tarjetaMarca: $marca,
            tarjetaUltimosCuatro: $ultimosCuatro,
            codigoRespuesta: $status,
            mensajeRespuesta: (string) ($paymentData['status_detail'] ?? 'Pago Mercado Pago'),
            payloadSanitizado: $data,
            ventaId: $ventaId
        );
    }
}

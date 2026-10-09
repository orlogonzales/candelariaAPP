<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;

/**
 * Adaptador para Niubiz Pago Web (VisaNet Perú).
 * Valida autenticidad mediante Bearer Token o HMAC-SHA256 en cabecera de confirmación.
 */
class NiubizAdaptador implements AdaptadorPasarelaInterfaz
{
    use ProcesarEventoTrait;

    public function obtenerCodigo(): string
    {
        return 'NIUBIZ';
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

        // 1. Verificación por Bearer Token en Authorization
        $authHeader = $encabezados['http_authorization']
            ?? $encabezados['authorization']
            ?? $encabezados['HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', (string) $authHeader, $matches)) {
            if (hash_equals($secretoPlano, trim($matches[1]))) {
                return true;
            }
        }

        // 2. Verificación por cabecera de firma Niubiz
        $firmaHeader = $encabezados['http_x_niubiz_signature']
            ?? $encabezados['x-niubiz-signature']
            ?? $encabezados['HTTP_X_NIUBIZ_SIGNATURE']
            ?? null;

        if ($firmaHeader !== null && trim((string) $firmaHeader) !== '') {
            $calculada = hash_hmac('sha256', $payloadRaw, $secretoPlano);
            return hash_equals($calculada, trim((string) $firmaHeader));
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
            return EventoWebhookResultado::invalido('Payload JSON Niubiz malformado.');
        }

        $order = is_array($data['order'] ?? null) ? $data['order'] : $data;
        $dataMap = is_array($data['dataMap'] ?? null) ? $data['dataMap'] : $data;

        $actionCode = (string) ($dataMap['ACTION_CODE'] ?? $data['action_code'] ?? '000');
        $status = strtoupper((string) ($order['status'] ?? $data['status'] ?? 'AUTHORIZED'));

        $esExitoso = ($actionCode === '000' && in_array($status, ['AUTHORIZED', 'PAID', 'EXITOSO'], true));
        $tipoEvento = $esExitoso ? 'CARGO_EXITOSO' : 'CARGO_FALLIDO';

        $transaccionId = (string) ($dataMap['TRANSACTION_ID'] ?? $data['transaction_id'] ?? $data['id'] ?? '');
        $purchaseNumber = (string) ($order['purchaseNumber'] ?? $dataMap['PURCHASE_NUMBER'] ?? $data['purchase_number'] ?? '');

        $monto = round((float) ($order['amount'] ?? $dataMap['AMOUNT'] ?? $data['amount'] ?? 0), 2);
        $moneda = strtoupper((string) ($order['currency'] ?? $dataMap['CURRENCY'] ?? $data['currency'] ?? 'PEN'));

        $marca = (string) ($dataMap['BRAND'] ?? $data['brand'] ?? 'VISA');
        $tarjeta = (string) ($dataMap['CARD'] ?? $data['card'] ?? '');
        $ultimosCuatro = strlen($tarjeta) >= 4 ? substr($tarjeta, -4) : null;

        $ventaId = isset($data['venta_id']) && is_numeric($data['venta_id'])
            ? (int) $data['venta_id']
            : (isset($dataMap['MERCHANT_CUSTOM_DATA']) && is_numeric($dataMap['MERCHANT_CUSTOM_DATA']) ? (int) $dataMap['MERCHANT_CUSTOM_DATA'] : null);

        $claveIdemp = (string) ($dataMap['TRANSACTION_ID'] ?? $transaccionId ?: $purchaseNumber);

        return new EventoWebhookResultado(
            esValido: true,
            tipoEvento: $tipoEvento,
            transaccionExternaId: $transaccionId !== '' ? $transaccionId : null,
            ordenCheckoutId: $purchaseNumber !== '' ? $purchaseNumber : null,
            monto: $monto,
            moneda: $moneda,
            claveIdempotencia: $claveIdemp !== '' ? $claveIdemp : null,
            tarjetaMarca: $marca,
            tarjetaUltimosCuatro: $ultimosCuatro,
            codigoRespuesta: $actionCode,
            mensajeRespuesta: (string) ($dataMap['ACTION_DESCRIPTION'] ?? 'Transacción Niubiz completada'),
            payloadSanitizado: $data,
            ventaId: $ventaId
        );
    }
}

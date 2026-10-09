<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;

/**
 * Adaptador para Culqi Online (Checkout Web y Webhooks).
 * Valida firma HMAC-SHA256 y normaliza eventos charge.succeeded / charge.failed.
 */
class CulqiAdaptador implements AdaptadorPasarelaInterfaz
{
    use ProcesarEventoTrait;

    public function obtenerCodigo(): string
    {
        return 'CULQI';
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

        $firmaRecibida = $encabezados['http_x_culqi_signature']
            ?? $encabezados['x-culqi-signature']
            ?? $encabezados['HTTP_X_CULQI_SIGNATURE']
            ?? null;

        if ($firmaRecibida === null || trim((string) $firmaRecibida) === '') {
            return false;
        }

        $firmaCalculada = hash_hmac('sha256', $payloadRaw, $secretoPlano);
        return hash_equals($firmaCalculada, trim((string) $firmaRecibida));
    }

    public function parsearEventoWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados
    ): EventoWebhookResultado {
        $data = json_decode($payloadRaw, true);
        if (!is_array($data)) {
            return EventoWebhookResultado::invalido('Payload JSON no estructurado o malformado.');
        }

        $tipo = (string) ($data['type'] ?? $data['object'] ?? '');
        $dataObjeto = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $esExitoso = in_array($tipo, ['charge.succeeded', 'order.status.paid', 'pago.exitoso'], true);
        $tipoEvento = $esExitoso ? 'CARGO_EXITOSO' : ($tipo === 'charge.failed' ? 'CARGO_FALLIDO' : 'OTRO');

        $transaccionId = (string) ($dataObjeto['id'] ?? $dataObjeto['charge_id'] ?? '');
        $ordenId = isset($dataObjeto['order_id']) ? (string) $dataObjeto['order_id'] : null;

        // Culqi procesa montos en céntimos enteros (ej. 10000 = S/ 100.00) si es entero grande, o float decimal
        $montoRaw = (float) ($dataObjeto['amount'] ?? 0);
        $monto = ($montoRaw >= 100 && floor($montoRaw) === $montoRaw && !isset($dataObjeto['es_decimal']))
            ? round($montoRaw / 100.0, 2)
            : round($montoRaw, 2);

        $moneda = strtoupper((string) ($dataObjeto['currency_code'] ?? $dataObjeto['currency'] ?? 'PEN'));

        // Extraer venta_id desde metadata
        $metadata = is_array($dataObjeto['metadata'] ?? null) ? $dataObjeto['metadata'] : [];
        $ventaId = isset($metadata['venta_id']) && is_numeric($metadata['venta_id']) ? (int) $metadata['venta_id'] : null;

        $tarjeta = is_array($dataObjeto['source'] ?? null) ? $dataObjeto['source'] : [];
        $marca = isset($tarjeta['iin']['card_brand']) ? (string) $tarjeta['iin']['card_brand'] : ($tarjeta['card_brand'] ?? null);
        $ultimosCuatro = isset($tarjeta['last_four']) ? substr((string) $tarjeta['last_four'], -4) : null;

        // Clave de idempotencia del evento
        $claveIdemp = (string) ($data['id'] ?? $encabezados['http_x_idempotency_key'] ?? $transaccionId);

        // Sanitización Zero-PAN (eliminar datos de tarjeta sensibles si vinieran)
        $sanitizado = $data;
        if (isset($sanitizado['source']['card_number'])) {
            unset($sanitizado['source']['card_number']);
        }
        if (isset($sanitizado['source']['cvv'])) {
            unset($sanitizado['source']['cvv']);
        }

        $feeRaw = (float) ($dataObjeto['fee'] ?? 0);
        $comisionPasarela = ($feeRaw >= 100 && floor($feeRaw) === $feeRaw)
            ? round($feeRaw / 100.0, 2)
            : round($feeRaw, 2);

        return new EventoWebhookResultado(
            esValido: true,
            tipoEvento: $tipoEvento,
            transaccionExternaId: $transaccionId !== '' ? $transaccionId : null,
            ordenCheckoutId: $ordenId,
            monto: $monto,
            comisionPasarela: $comisionPasarela,
            moneda: $moneda,
            claveIdempotencia: $claveIdemp !== '' ? $claveIdemp : null,
            tarjetaMarca: $marca,
            tarjetaUltimosCuatro: $ultimosCuatro,
            codigoRespuesta: (string) ($dataObjeto['outcome']['code'] ?? 'OK'),
            mensajeRespuesta: (string) ($dataObjeto['outcome']['user_message'] ?? 'Cargo procesado'),
            payloadSanitizado: $sanitizado,
            ventaId: $ventaId
        );
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones\Proveedores;

use Aplicacion\Comunicaciones\CategoriaPlantilla;
use RuntimeException;

/**
 * Adaptador desacoplado para Meta WhatsApp Cloud API.
 * No realiza llamadas reales en modo SIMULADOR.
 */
class MetaCloudApiAdaptador implements WhatsAppProveedorInterface
{
    public const CODIGO = 'META_CLOUD_API';

    public function __construct(private string $apiVersion = 'v21.0') {}

    public function obtenerCodigo(): string
    {
        return self::CODIGO;
    }

    public function enviarPlantilla(
        string $telefonoDestino,
        string $nombrePlantilla,
        string $codigoIdioma,
        array $parametros,
        CategoriaPlantilla $categoria,
        string $idempotencyKey,
        array $configuracionTenant
    ): ResultadoDespachoWhatsApp {
        $modo = $configuracionTenant['modo'] ?? 'SIMULADOR';
        if ($modo !== 'PRODUCCION') {
            throw new RuntimeException('MetaCloudApiAdaptador solo opera en modo PRODUCCION.');
        }

        $phoneNumberId = $configuracionTenant['meta_phone_number_id'] ?? '';
        $accessToken   = $configuracionTenant['token_acceso'] ?? '';

        if (empty($phoneNumberId) || empty($accessToken)) {
            return ResultadoDespachoWhatsApp::fallido(
                httpStatus: 400,
                errorCode: 100,
                errorMessage: 'Credenciales de Meta Cloud API no configuradas en el tenant',
                esReintentable: false
            );
        }

        // Estructurar componentes con parámetros posicionales
        $parametersFormatted = [];
        foreach ($parametros as $p) {
            $parametersFormatted[] = [
                'type' => 'text',
                'text' => (string) $p
            ];
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => ltrim($telefonoDestino, '+'),
            'type'              => 'template',
            'template'          => [
                'name'     => $nombrePlantilla,
                'language' => ['code' => $codigoIdioma],
                'components' => !empty($parametersFormatted) ? [
                    [
                        'type'       => 'body',
                        'parameters' => $parametersFormatted
                    ]
                ] : []
            ]
        ];

        return $this->ejecutarPeticionHttp($phoneNumberId, $accessToken, $body);
    }

    public function enviarTextoLibre(
        string $telefonoDestino,
        string $texto,
        string $idempotencyKey,
        array $configuracionTenant
    ): ResultadoDespachoWhatsApp {
        $modo = $configuracionTenant['modo'] ?? 'SIMULADOR';
        if ($modo !== 'PRODUCCION') {
            throw new RuntimeException('MetaCloudApiAdaptador solo opera en modo PRODUCCION.');
        }

        $phoneNumberId = $configuracionTenant['meta_phone_number_id'] ?? '';
        $accessToken   = $configuracionTenant['token_acceso'] ?? '';

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => ltrim($telefonoDestino, '+'),
            'type'              => 'text',
            'text'              => ['body' => $texto]
        ];

        return $this->ejecutarPeticionHttp($phoneNumberId, $accessToken, $body);
    }

    public function validarFirmaWebhook(string $rawPayload, string $firmaCabecera, string $webhookSecret): bool
    {
        if (empty($firmaCabecera) || empty($webhookSecret)) {
            return false;
        }

        $firmaEsperada = 'sha256=' . hash_hmac('sha256', $rawPayload, $webhookSecret);
        return hash_equals($firmaEsperada, $firmaCabecera);
    }

    private function ejecutarPeticionHttp(string $phoneNumberId, string $accessToken, array $body): ResultadoDespachoWhatsApp
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$phoneNumberId}/messages";
        $payloadJson = json_encode($body, JSON_UNESCAPED_UNICODE);

        $inicio = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);

        $respuestaRaw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $latenciaMs = (int) ((microtime(true) - $inicio) * 1000);

        if ($respuestaRaw === false || !empty($curlError)) {
            return ResultadoDespachoWhatsApp::fallido(
                httpStatus: 504,
                errorCode: null,
                errorMessage: 'Error de red o timeout conectando a Meta: ' . $curlError,
                esReintentable: true,
                latenciaMs: $latenciaMs
            );
        }

        $json = json_decode((string) $respuestaRaw, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300 && !empty($json['messages'][0]['id'])) {
            return ResultadoDespachoWhatsApp::exitoso(
                wamid: (string) $json['messages'][0]['id'],
                latenciaMs: $latenciaMs,
                raw: $json
            );
        }

        $metaError = $json['error'] ?? [];
        $errorCode = isset($metaError['code']) ? (int) $metaError['code'] : null;
        $errorSubcode = isset($metaError['error_subcode']) ? (int) $metaError['error_subcode'] : null;
        $errorMessage = $metaError['message'] ?? 'Error desconocido devuelto por Meta Cloud API';

        // Clasificación de reintentos
        // Errores 5xx o rate limits (130429, 80007) son reintentables
        $esReintentable = ($httpCode >= 500) || in_array($errorCode, [130429, 80007, 4], true);

        return ResultadoDespachoWhatsApp::fallido(
            httpStatus: $httpCode,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            esReintentable: $esReintentable,
            latenciaMs: $latenciaMs,
            errorSubcode: $errorSubcode,
            raw: $json
        );
    }
}

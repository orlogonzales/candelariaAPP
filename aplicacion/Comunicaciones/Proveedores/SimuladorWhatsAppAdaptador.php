<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones\Proveedores;

use Aplicacion\Comunicaciones\CategoriaPlantilla;

class SimuladorWhatsAppAdaptador implements WhatsAppProveedorInterface
{
    public const CODIGO = 'SIMULADOR_SANDBOX';

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
        $inicio = microtime(true);

        // Simular caso de fallo para pruebas de resiliencia si el número termina en '0000' o 'FAIL'
        if (str_ends_with($telefonoDestino, '0000') || str_contains($telefonoDestino, 'FAIL')) {
            $latencia = (int) ((microtime(true) - $inicio) * 1000);
            return ResultadoDespachoWhatsApp::fallido(
                httpStatus: 400,
                errorCode: 131026,
                errorMessage: 'Simulación: Número no registrado en WhatsApp',
                esReintentable: false,
                latenciaMs: $latencia
            );
        }

        // Simular fallo transitorio reintentable si termina en '9999'
        if (str_ends_with($telefonoDestino, '9999')) {
            $latencia = (int) ((microtime(true) - $inicio) * 1000);
            return ResultadoDespachoWhatsApp::fallido(
                httpStatus: 503,
                errorCode: 130429,
                errorMessage: 'Simulación: Servicio temporalmente congestionado (Rate Limit)',
                esReintentable: true,
                latenciaMs: $latencia
            );
        }

        // Éxito simulado determinista
        $wamid = 'wamid.simulado_' . substr(hash('sha256', $idempotencyKey . $telefonoDestino), 0, 32);
        $latencia = (int) ((microtime(true) - $inicio) * 1000);

        return ResultadoDespachoWhatsApp::exitoso(
            wamid: $wamid,
            latenciaMs: max(1, $latencia),
            raw: [
                'simulado' => true,
                'proveedor' => self::CODIGO,
                'plantilla' => $nombrePlantilla,
                'idioma'    => $codigoIdioma,
                'categoria' => $categoria->value
            ]
        );
    }

    public function enviarTextoLibre(
        string $telefonoDestino,
        string $texto,
        string $idempotencyKey,
        array $configuracionTenant
    ): ResultadoDespachoWhatsApp {
        $inicio = microtime(true);
        $wamid = 'wamid.simulado_txt_' . substr(hash('sha256', $idempotencyKey . $telefonoDestino), 0, 32);
        $latencia = (int) ((microtime(true) - $inicio) * 1000);

        return ResultadoDespachoWhatsApp::exitoso(
            wamid: $wamid,
            latenciaMs: max(1, $latencia),
            raw: ['simulado' => true, 'tipo' => 'texto_libre']
        );
    }

    public function validarFirmaWebhook(string $rawPayload, string $firmaCabecera, string $webhookSecret): bool
    {
        if (empty($firmaCabecera) || empty($webhookSecret)) {
            return false;
        }

        $firmaEsperada = 'sha256=' . hash_hmac('sha256', $rawPayload, $webhookSecret);
        return hash_equals($firmaEsperada, $firmaCabecera);
    }
}

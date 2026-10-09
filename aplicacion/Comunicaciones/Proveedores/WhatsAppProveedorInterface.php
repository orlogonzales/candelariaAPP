<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones\Proveedores;

use Aplicacion\Comunicaciones\CategoriaPlantilla;

interface WhatsAppProveedorInterface
{
    public function obtenerCodigo(): string;

    /**
     * Envía un mensaje basado en plantilla oficial aprobada.
     */
    public function enviarPlantilla(
        string $telefonoDestino,
        string $nombrePlantilla,
        string $codigoIdioma,
        array $parametros,
        CategoriaPlantilla $categoria,
        string $idempotencyKey,
        array $configuracionTenant
    ): ResultadoDespachoWhatsApp;

    /**
     * Envía un mensaje de texto libre dentro de una ventana de servicio de 24h activa.
     */
    public function enviarTextoLibre(
        string $telefonoDestino,
        string $texto,
        string $idempotencyKey,
        array $configuracionTenant
    ): ResultadoDespachoWhatsApp;

    /**
     * Valida la firma criptográfica HMAC-SHA256 del webhook entrante.
     */
    public function validarFirmaWebhook(string $rawPayload, string $firmaCabecera, string $webhookSecret): bool;
}

<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;

/**
 * Contrato formal para los adaptadores de pasarelas de pago.
 * Desacopla la lógica de verificación de firmas criptográficas,
 * sanitización de payloads y normalización de eventos de cobro.
 */
interface AdaptadorPasarelaInterfaz
{
    /**
     * Retorna el código de pasarela institucional (ej. 'CULQI', 'NIUBIZ', 'STRIPE', 'MERCADOPAGO').
     */
    public function obtenerCodigo(): string;

    /**
     * Valida la firma digital o autenticidad del webhook recibido según el protocolo del proveedor.
     */
    public function verificarFirmaWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados,
        string $secretoPlano
    ): bool;

    /**
     * Parsea y normaliza el payload en un EventoWebhookResultado soberano.
     */
    public function parsearEventoWebhook(
        OrganizacionPasarela $config,
        string $payloadRaw,
        array $encabezados
    ): EventoWebhookResultado;
}

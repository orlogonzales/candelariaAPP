<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas\Pasarelas;

use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use InvalidArgumentException;

trait ProcesarEventoTrait
{
    public function procesarEvento(
        string $payloadRaw,
        array $encabezados,
        string $secretoPlano,
        string $ipOrigen = '127.0.0.1',
        ?OrganizacionPasarela $config = null
    ): EventoWebhookResultado {
        $configObj = $config ?? new OrganizacionPasarela(
            id: null,
            organizacionId: 1,
            pasarelaId: 1,
            modo: 'TEST',
            identificadorComercio: null,
            credencialSecretaEnc: null,
            webhookSecretoEnc: null,
            porcentajeComision: 0.0,
            comisionFija: 0.0,
            asumeComision: AsumeComisionPasarela::ORGANIZACION,
            activo: true
        );

        $valido = $this->verificarFirmaWebhook($configObj, $payloadRaw, $encabezados, $secretoPlano);
        if (!$valido) {
            throw new InvalidArgumentException("Firma criptográfica inválida para pasarela {$this->obtenerCodigo()}.");
        }

        $evento = $this->parsearEventoWebhook($configObj, $payloadRaw, $encabezados);
        if (!$evento->esValido) {
            throw new InvalidArgumentException("Payload de evento inválido para pasarela {$this->obtenerCodigo()}: " . $evento->mensajeRespuesta);
        }

        return $evento;
    }
}

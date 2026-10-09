<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Comunicaciones\Proveedores\ResultadoDespachoWhatsApp;
use Aplicacion\Entidades\ComunicacionMensaje;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;

class OutboxWorker
{
    public function __construct(
        private ComunicacionMensajeRepositorio $mensajeRepo,
        private ComunicacionConsentimientoRepositorio $consentimientoRepo,
        private ComunicacionPlantillaRepositorio $plantillaRepo,
        private ComunicacionConfigRepositorio $configRepo,
        private FabricaProveedorWhatsApp $fabricaProveedores
    ) {}

    /**
     * Procesa un lote de mensajes pendientes con reserva atómica, pre-flight check y backoff.
     */
    public function procesarLote(int $limite = 20): array
    {
        // Fase 1: Reserva atómica de mensajes candidatos liberando locks de BD
        $mensajes = $this->mensajeRepo->reservarLoteOutbox($limite);
        $resultados = [];

        foreach ($mensajes as $msg) {
            $resultados[$msg->id] = $this->despacharMensaje($msg);
        }

        return $resultados;
    }

    /**
     * Recupera mensajes que quedaron bloqueados si un worker previo cayó abruptamente.
     */
    public function recuperarHuerfanos(): int
    {
        return $this->mensajeRepo->recuperarMensajesAbandonados();
    }

    private function despacharMensaje(ComunicacionMensaje $msg): bool
    {
        $orgId = $msg->organizacionId;
        $config = $this->configRepo->obtenerPorOrganizacion($orgId);

        if (!$config || !$config->activo) {
            $this->mensajeRepo->marcarFallido($msg->id, $orgId, 'Organización sin configuración de mensajería activa');
            return false;
        }

        // Fase 2: Pre-flight check de consentimiento inmediatamente antes del despacho
        if ($msg->clienteId !== null && $msg->plantillaId !== null) {
            $plantilla = $this->plantillaRepo->buscarPorId($msg->plantillaId, $orgId);
            if ($plantilla) {
                $ultimoConsentimiento = $this->consentimientoRepo->obtenerUltimoEstado(
                    $orgId,
                    $msg->clienteId,
                    'WHATSAPP',
                    $plantilla->categoria->finalidadRequerida()
                );

                if ($ultimoConsentimiento === null || !$ultimoConsentimiento->esValido()) {
                    $this->mensajeRepo->cancelarMensaje(
                        $msg->id,
                        $orgId,
                        'Consentimiento revocado por el cliente antes del despacho físico'
                    );
                    return false;
                }
            }
        }

        // Fase 3: Despacho a través del adaptador correspondiente (fuera de transacción BD)
        // Regla Fail-Closed: en PRODUCCION jamás se permite operar con simulador ni fallback silencioso
        if ($config->modo === ModoComunicacion::PRODUCCION && $config->proveedorCodigo === 'SIMULADOR_SANDBOX') {
            $this->mensajeRepo->registrarIntento(
                mensajeId: $msg->id,
                intentoNumero: $msg->intentosRealizados,
                httpStatus: 400,
                errorCode: 4001,
                errorSubcode: 0,
                errorMessage: 'Configuración ilegal: Organización en modo PRODUCCION no puede despachar con SIMULADOR_SANDBOX (Fail-Closed)',
                latenciaMs: 0
            );
            $this->mensajeRepo->marcarFallido($msg->id, $orgId, 'Fallo de configuración: modo PRODUCCION exige proveedor productivo configurado');
            return false;
        }

        $adaptador = $this->fabricaProveedores->obtenerPorCodigo($config->proveedorCodigo);
        $secretos = $this->configRepo->descifrarSecretos($config);
        $configTenant = array_merge($config->aArreglo(), $secretos);

        $resultado = null;
        if ($msg->plantillaId !== null) {
            $plantilla = $this->plantillaRepo->buscarPorId($msg->plantillaId, $orgId);
            $resultado = $adaptador->enviarPlantilla(
                telefonoDestino: $msg->destinatarioTelefono,
                nombrePlantilla: $plantilla->nombre,
                codigoIdioma: $plantilla->idioma,
                parametros: $msg->parametrosEnviadosJson ?? [],
                categoria: $plantilla->categoria,
                idempotencyKey: $msg->idempotencyKey,
                configuracionTenant: $configTenant
            );
        } else {
            $resultado = $adaptador->enviarTextoLibre(
                telefonoDestino: $msg->destinatarioTelefono,
                texto: $msg->contenidoTexto,
                idempotencyKey: $msg->idempotencyKey,
                configuracionTenant: $configTenant
            );
        }

        // Fase 4: Registro del intento y telemetría
        $this->mensajeRepo->registrarIntento(
            mensajeId: $msg->id,
            intentoNumero: $msg->intentosRealizados,
            httpStatus: $resultado->httpStatus,
            errorCode: $resultado->errorCode,
            errorSubcode: $resultado->errorSubcode,
            errorMessage: $resultado->errorMessage,
            latenciaMs: $resultado->latenciaMs
        );

        // Fase 5: Transición de estado
        if ($resultado->exito && !empty($resultado->wamid)) {
            $this->mensajeRepo->actualizarAEnviado(
                id: $msg->id,
                orgId: $orgId,
                wamid: $resultado->wamid,
                costoCalculado: $msg->costoEstimadoUsd
            );
            $this->configRepo->incrementarGasto($orgId, $msg->costoEstimadoUsd);
            return true;
        }

        // Manejo de fallo y reintentos
        if ($resultado->esReintentable && $msg->intentosRealizados < $msg->maxIntentos) {
            $backoff = (int) (60 * pow(2, $msg->intentosRealizados - 1)); // 60s, 120s, 240s
            $this->mensajeRepo->programarReintento($msg->id, $orgId, $backoff);
        } else {
            $this->mensajeRepo->marcarFallido($msg->id, $orgId, $resultado->errorMessage ?? 'Error no recuperable');
        }

        return false;
    }
}

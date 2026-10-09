<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use Aplicacion\Comunicaciones\Proveedores\FabricaProveedorWhatsApp;
use Aplicacion\Entidades\ComunicacionConfiguracion;
use Aplicacion\Entidades\ComunicacionConsentimiento;
use Aplicacion\Entidades\ComunicacionConversacion;
use Aplicacion\Entidades\ComunicacionMensaje;
use Aplicacion\Entidades\ComunicacionPlantilla;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use InvalidArgumentException;
use Nucleo\Http\ContextoOperacion;
use RuntimeException;

class ComunicacionServicio
{
    private CalculadoraTarifas $calculadoraTarifas;
    private FabricaProveedorWhatsApp $fabricaProveedores;

    public function __construct(
        private ComunicacionMensajeRepositorio $mensajeRepo,
        private ComunicacionPlantillaRepositorio $plantillaRepo,
        private ComunicacionConsentimientoRepositorio $consentimientoRepo,
        private ComunicacionConversacionRepositorio $conversacionRepo,
        private ComunicacionConfigRepositorio $configRepo,
        ?CalculadoraTarifas $calculadora = null,
        ?FabricaProveedorWhatsApp $fabrica = null
    ) {
        $this->calculadoraTarifas = $calculadora ?? new CalculadoraTarifas();
        $this->fabricaProveedores = $fabrica ?? new FabricaProveedorWhatsApp();
    }

    /**
     * Encola un mensaje transaccional oficial en el Outbox (Non-blocking HTTP).
     */
    public function encolarMensajeTransaccional(
        int $orgId,
        string $telefonoDestino,
        string $nombreDestinatario,
        string $nombrePlantilla,
        string $idioma,
        array $parametros,
        string $eventoOrigen,
        string $entidadOrigenId,
        ?int $clienteId = null,
        ?int $ventaId = null,
        ?int $reservaId = null,
        ?ContextoOperacion $contexto = null
    ): ComunicacionMensaje {
        $config = $this->configRepo->obtenerPorOrganizacion($orgId);
        if (!$config || !$config->activo) {
            throw new RuntimeException("La organización {$orgId} no tiene configurado o activo el canal de comunicaciones WhatsApp.");
        }

        // 1. Obtener y validar plantilla
        $plantilla = $this->plantillaRepo->buscarPorNombre($nombrePlantilla, $idioma, $orgId);
        if (!$plantilla || !$plantilla->activo) {
            throw new InvalidArgumentException("Plantilla '{$nombrePlantilla}' ({$idioma}) no existe o está inactiva en el tenant.");
        }

        if (!$plantilla->estadoMeta->permiteEnvio()) {
            throw new RuntimeException("La plantilla '{$nombrePlantilla}' no está aprobada por Meta (Estado actual: {$plantilla->estadoMeta->value}).");
        }

        // 2. Validación de Consentimiento específico de canal
        if ($clienteId !== null) {
            $estadoConsentimiento = $this->consentimientoRepo->obtenerUltimoEstado(
                $orgId,
                $clienteId,
                'WHATSAPP',
                $plantilla->categoria->finalidadRequerida()
            );

            if ($estadoConsentimiento === null || !$estadoConsentimiento->esValido()) {
                throw new RuntimeException("El cliente no cuenta con consentimiento válido para recibir comunicaciones en canal WhatsApp.");
            }
        }

        // 3. Obtener conversación o abrir sesión
        $conversacion = null;
        $dentroDeVentana = false;
        if ($clienteId !== null) {
            $conversacion = $this->conversacionRepo->obtenerOCrear($orgId, $clienteId, $telefonoDestino);
            $dentroDeVentana = $conversacion->ventanaServicioActiva();
        }

        // 4. Calcular costo estimado
        $costoEstimado = $this->calculadoraTarifas->obtenerTarifa(
            $config->proveedorId,
            'PE',
            $plantilla->categoria,
            $dentroDeVentana
        );

        // 5. Control de presupuesto mensual
        if ($config->presupuestoExcedido($costoEstimado)) {
            throw new RuntimeException("Límite de presupuesto mensual de comunicaciones superado en la organización (Límite: USD {$config->presupuestoMensualLimiteUsd}).");
        }

        // 6. Generar Idempotency Key determinista
        $idempotencyKey = hash('sha256', "{$orgId}:{$eventoOrigen}:{$entidadOrigenId}:{$plantilla->nombre}:{$telefonoDestino}");

        // Verificar duplicado
        $existente = $this->mensajeRepo->buscarPorIdempotencyKey($idempotencyKey, $orgId);
        if ($existente !== null) {
            return $existente;
        }

        // 7. Formatear contenido textual minimizado
        $cuerpoFinal = $this->renderizarPlantilla($plantilla->cuerpoTexto, $parametros);

        $correlacionId = $contexto?->correlacionId ?? bin2hex(random_bytes(16));

        $mensaje = new ComunicacionMensaje(
            id: null,
            organizacionId: $orgId,
            tipoMensaje: TipoMensaje::TRANSACCIONAL,
            direccion: 'SALIENTE',
            canal: 'WHATSAPP',
            destinatarioTelefono: $telefonoDestino,
            destinatarioNombre: $nombreDestinatario,
            contenidoTexto: $cuerpoFinal,
            idempotencyKey: $idempotencyKey,
            correlacionId: $correlacionId,
            estado: EstadoMensaje::ENCOLADO,
            pesoEstado: 10,
            conversacionId: $conversacion?->id,
            clienteId: $clienteId,
            ventaId: $ventaId,
            reservaId: $reservaId,
            plantillaId: $plantilla->id,
            parametrosEnviadosJson: $parametros,
            costoEstimadoUsd: $costoEstimado,
            creadoPor: $contexto?->usuarioId
        );

        return $this->mensajeRepo->guardar($mensaje);
    }

    /**
     * Encola una respuesta de operador humano en texto libre dentro de una ventana de 24h.
     */
    public function encolarRespuestaOperador(
        int $orgId,
        int $conversacionId,
        string $textoLibre,
        ContextoOperacion $contexto
    ): ComunicacionMensaje {
        $conv = $this->conversacionRepo->buscarPorId($conversacionId, $orgId);
        if (!$conv) {
            throw new InvalidArgumentException("Conversación {$conversacionId} no encontrada.");
        }

        if (!$conv->ventanaServicioActiva()) {
            throw new RuntimeException("La ventana de atención de 24 horas ha expirado. No es posible enviar texto libre; debe enviar una plantilla transaccional autorizada para reiniciar el contacto.");
        }

        $idempotencyKey = hash('sha256', "{$orgId}:conv_{$conversacionId}:" . microtime(true) . ":{$contexto->usuarioId}");

        $mensaje = new ComunicacionMensaje(
            id: null,
            organizacionId: $orgId,
            tipoMensaje: TipoMensaje::RESPUESTA_OPERADOR,
            direccion: 'SALIENTE',
            canal: 'WHATSAPP',
            destinatarioTelefono: $conv->telefonoCliente,
            destinatarioNombre: 'Cliente',
            contenidoTexto: trim($textoLibre),
            idempotencyKey: $idempotencyKey,
            correlacionId: $contexto->correlacionId,
            estado: EstadoMensaje::ENCOLADO,
            pesoEstado: 10,
            conversacionId: $conv->id,
            clienteId: $conv->clienteId,
            costoEstimadoUsd: 0.00, // Gratuito en ventana de servicio
            creadoPor: $contexto->usuarioId
        );

        return $this->mensajeRepo->guardar($mensaje);
    }

    /**
     * Registra o revoca consentimiento formal para el canal WhatsApp.
     */
    public function gestionarConsentimiento(
        int $orgId,
        int $clienteId,
        string $telefonoDestino,
        FinalidadConsentimiento $finalidad,
        EstadoConsentimiento $estado,
        string $origenEvidencia,
        ?string $textoClausula = null,
        ?ContextoOperacion $contexto = null
    ): ComunicacionConsentimiento {
        $consentimiento = new ComunicacionConsentimiento(
            id: null,
            organizacionId: $orgId,
            clienteId: $clienteId,
            canal: 'WHATSAPP',
            telefonoDestino: $telefonoDestino,
            finalidad: $finalidad,
            estado: $estado,
            origenEvidencia: $origenEvidencia,
            correlacionId: $contexto?->correlacionId ?? bin2hex(random_bytes(16)),
            textoClausulaAceptada: $textoClausula,
            direccionIpRegistro: $contexto?->direccionIp ?? '127.0.0.1',
            actorTipo: ($contexto?->usuarioId !== null) ? 'HUMANO' : 'SISTEMA',
            usuarioId: $contexto?->usuarioId,
            revocadoEn: ($estado === EstadoConsentimiento::REVOCADO) ? date('Y-m-d H:i:s') : null
        );

        return $this->consentimientoRepo->guardar($consentimiento);
    }

    private function renderizarPlantilla(string $cuerpo, array $parametros): string
    {
        $texto = $cuerpo;
        foreach ($parametros as $idx => $valor) {
            $num = $idx + 1;
            $texto = str_replace("{{{$num}}}", (string) $valor, $texto);
        }
        return $texto;
    }
}

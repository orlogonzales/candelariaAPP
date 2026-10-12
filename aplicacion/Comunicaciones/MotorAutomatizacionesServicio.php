<?php

declare(strict_types=1);

namespace Aplicacion\Comunicaciones;

use Aplicacion\Entidades\ComunicacionMensaje;
use Aplicacion\Repositorios\ComunicacionAutomatizacionRepositorio;
use Aplicacion\Repositorios\ComunicacionConfigRepositorio;
use Aplicacion\Repositorios\ComunicacionConsentimientoRepositorio;
use Aplicacion\Repositorios\ComunicacionConversacionRepositorio;
use Aplicacion\Repositorios\ComunicacionMensajeRepositorio;
use Aplicacion\Repositorios\ComunicacionPlantillaRepositorio;
use InvalidArgumentException;
use Nucleo\Http\ContextoOperacion;
use RuntimeException;

class MotorAutomatizacionesServicio
{
    private CalculadoraTarifas $calculadoraTarifas;

    public function __construct(
        private ComunicacionAutomatizacionRepositorio $autRepo,
        private ComunicacionPlantillaRepositorio $plantillaRepo,
        private ComunicacionConsentimientoRepositorio $consentimientoRepo,
        private ComunicacionConversacionRepositorio $conversacionRepo,
        private ComunicacionConfigRepositorio $configRepo,
        private ComunicacionMensajeRepositorio $mensajeRepo,
        ?CalculadoraTarifas $calculadora = null
    ) {
        $this->calculadoraTarifas = $calculadora ?? new CalculadoraTarifas();
    }

    /**
     * Procesa un evento de dominio disparando todas las reglas activas correspondientes.
     */
    public function dispararEvento(
        int $orgId,
        string $eventoCodigo,
        array $payload,
        ?ContextoOperacion $contexto = null
    ): array {
        $config = $this->configRepo->obtenerPorOrganizacion($orgId);
        if (!$config || !$config->activo) {
            return [
                'exito'            => false,
                'mensaje'          => "Canal WhatsApp no configurado o inactivo para la organización {$orgId}",
                'evento'           => $eventoCodigo,
                'total_disparados' => 0,
                'disparados'       => [],
                'omitidos'         => []
            ];
        }

        $reglas = $this->autRepo->listarActivasPorEvento($orgId, $eventoCodigo);
        if (empty($reglas)) {
            return [
                'exito'            => true,
                'mensaje'          => "No hay reglas activas para el evento {$eventoCodigo}",
                'evento'           => $eventoCodigo,
                'total_disparados' => 0,
                'disparados'       => [],
                'omitidos'         => []
            ];
        }

        $disparados = [];
        $omitidos = [];

        foreach ($reglas as $regla) {
            // 1. Evaluar filtros/condiciones de la regla
            if (!$regla->cumpleCondiciones($payload)) {
                $omitidos[] = [
                    'regla_id' => $regla->id,
                    'codigo'   => $regla->codigo,
                    'motivo'   => 'CONDICIONES_NO_CUMPLIDAS'
                ];
                continue;
            }

            // 2. Extraer destinatario
            $telefono = (string)($payload['destinatario_telefono'] ?? $payload['telefono'] ?? $payload['cliente_telefono'] ?? '');
            $telefono = preg_replace('/[^0-9]/', '', $telefono);
            if ($telefono === '') {
                $omitidos[] = [
                    'regla_id' => $regla->id,
                    'codigo'   => $regla->codigo,
                    'motivo'   => 'TELEFONO_FALTANTE'
                ];
                continue;
            }

            $nombre = (string)($payload['destinatario_nombre'] ?? $payload['nombre'] ?? $payload['cliente_nombre'] ?? 'Participante / Cliente');
            $clienteId = isset($payload['cliente_id']) ? (int)$payload['cliente_id'] : null;
            $ventaId = isset($payload['venta_id']) ? (int)$payload['venta_id'] : null;
            $reservaId = isset($payload['reserva_id']) ? (int)$payload['reserva_id'] : null;

            // 3. Obtener y validar plantilla
            $plantilla = $this->plantillaRepo->buscarPorId($regla->plantillaId, $orgId);
            if (!$plantilla || !$plantilla->activo) {
                $omitidos[] = [
                    'regla_id' => $regla->id,
                    'codigo'   => $regla->codigo,
                    'motivo'   => 'PLANTILLA_INEXISTENTE_O_INACTIVA'
                ];
                continue;
            }

            // 4. Validación de Consentimiento específico
            if ($clienteId !== null) {
                $finalidad = $regla->tipoComunicacion === 'PROMOCIONAL'
                    ? FinalidadConsentimiento::PROMOCIONAL_MARKETING
                    : FinalidadConsentimiento::TRANSACCIONAL_OPERATIVO;

                $estadoConsentimiento = $this->consentimientoRepo->obtenerUltimoEstado(
                    $orgId,
                    $clienteId,
                    'WHATSAPP',
                    $finalidad
                );

                if ($estadoConsentimiento === null || !$estadoConsentimiento->esValido()) {
                    $omitidos[] = [
                        'regla_id'   => $regla->id,
                        'codigo'     => $regla->codigo,
                        'cliente_id' => $clienteId,
                        'motivo'     => 'SIN_CONSENTIMIENTO_VALIDO'
                    ];
                    continue;
                }
            }

            // 5. Mapeo de parámetros y renderizado de texto
            $parametros = $regla->mapearParametros($payload);
            $cuerpoRenderizado = $this->renderizarPlantilla($plantilla->cuerpoTexto, $parametros);

            // 6. Conversación / Ventana 24h
            $conversacion = null;
            $dentroDeVentana = false;
            if ($clienteId !== null) {
                $conversacion = $this->conversacionRepo->obtenerOCrear($orgId, $clienteId, $telefono);
                $dentroDeVentana = $conversacion->ventanaServicioActiva();
            }

            // 7. Costo estimado y control presupuestal
            $costoEstimado = $this->calculadoraTarifas->obtenerTarifa(
                $config->proveedorId,
                'PE',
                $plantilla->categoria,
                $dentroDeVentana
            );

            if ($config->presupuestoExcedido($costoEstimado)) {
                $omitidos[] = [
                    'regla_id' => $regla->id,
                    'codigo'   => $regla->codigo,
                    'motivo'   => 'PRESUPUESTO_MENSUAL_EXCEDIDO'
                ];
                continue;
            }

            // 8. Idempotencia determinista
            $entidadId = (string)($payload['entidad_id'] ?? $payload['reserva_id'] ?? $payload['venta_id'] ?? $payload['recibo_id'] ?? $payload['presentacion_id'] ?? 'auto');
            $idempotencyKey = hash('sha256', "aut:{$regla->id}:{$eventoCodigo}:{$entidadId}:{$telefono}");

            $existente = $this->mensajeRepo->buscarPorIdempotencyKey($idempotencyKey, $orgId);
            if ($existente !== null) {
                $omitidos[] = [
                    'regla_id'        => $regla->id,
                    'codigo'          => $regla->codigo,
                    'mensaje_id'      => $existente->id,
                    'idempotency_key' => $idempotencyKey,
                    'motivo'          => 'DUPLICADO_IDEMPOTENTE'
                ];
                continue;
            }

            // 9. Cálculo de programación diferida
            $proximoIntentoEn = date('Y-m-d H:i:s');
            if ($regla->minutosDiferidos > 0) {
                $proximoIntentoEn = date('Y-m-d H:i:s', time() + ($regla->minutosDiferidos * 60));
            }

            $correlacionId = $contexto?->correlacionId ?? bin2hex(random_bytes(16));

            $mensaje = new ComunicacionMensaje(
                id: null,
                organizacionId: $orgId,
                tipoMensaje: TipoMensaje::from($regla->tipoComunicacion),
                direccion: 'SALIENTE',
                canal: 'WHATSAPP',
                destinatarioTelefono: $telefono,
                destinatarioNombre: $nombre,
                contenidoTexto: $cuerpoRenderizado,
                idempotencyKey: $idempotencyKey,
                correlacionId: $correlacionId,
                estado: EstadoMensaje::ENCOLADO,
                pesoEstado: 10,
                conversacionId: $conversacion?->id,
                campanaId: null,
                clienteId: $clienteId,
                ventaId: $ventaId,
                reservaId: $reservaId,
                plantillaId: $plantilla->id,
                automatizacionReglaId: $regla->id,
                parametrosEnviadosJson: $parametros,
                costoEstimadoUsd: $costoEstimado,
                proximoIntentoEn: $proximoIntentoEn,
                creadoPor: $contexto?->usuarioId
            );

            $guardado = $this->mensajeRepo->guardar($mensaje);
            $this->autRepo->incrementarDisparos($regla->id, $orgId);

            $disparados[] = [
                'regla_id'           => $regla->id,
                'codigo'             => $regla->codigo,
                'mensaje_id'         => $guardado->id,
                'idempotency_key'    => $idempotencyKey,
                'proximo_intento_en' => $proximoIntentoEn,
                'destinatario'       => substr($telefono, 0, 4) . '***' . substr($telefono, -2),
                'costo_estimado_usd' => $costoEstimado
            ];
        }

        return [
            'exito'            => true,
            'evento'           => $eventoCodigo,
            'total_disparados' => count($disparados),
            'disparados'       => $disparados,
            'omitidos'         => $omitidos
        ];
    }

    /**
     * Simulación pre-flight para validar condiciones y renderizado de una regla.
     */
    public function probarRegla(
        int $orgId,
        int $reglaId,
        array $payloadSimulado,
        ?ContextoOperacion $contexto = null
    ): array {
        $regla = $this->autRepo->buscarPorId($reglaId, $orgId);
        if (!$regla) {
            throw new InvalidArgumentException("Regla de automatización {$reglaId} no encontrada en el tenant.");
        }

        $cumpleCondiciones = $regla->cumpleCondiciones($payloadSimulado);
        $plantilla = $this->plantillaRepo->buscarPorId($regla->plantillaId, $orgId);
        $parametrosMapeados = $regla->mapearParametros($payloadSimulado);

        $textoSimulado = $plantilla
            ? $this->renderizarPlantilla($plantilla->cuerpoTexto, $parametrosMapeados)
            : 'Error: Plantilla no disponible';

        $telefono = (string)($payloadSimulado['destinatario_telefono'] ?? $payloadSimulado['telefono'] ?? '');
        $entidadId = (string)($payloadSimulado['entidad_id'] ?? 'sim-1');
        $idempotencyKeySimulada = hash('sha256', "aut:{$regla->id}:{$regla->eventoDisparador}:{$entidadId}:{$telefono}");

        return [
            'regla_id'                 => $regla->id,
            'codigo'                   => $regla->codigo,
            'nombre'                   => $regla->nombre,
            'evento'                   => $regla->eventoDisparador,
            'cumple_condiciones'       => $cumpleCondiciones,
            'parametros_extraidos'     => $parametrosMapeados,
            'texto_renderizado'        => $textoSimulado,
            'idempotency_key_prevista' => $idempotencyKeySimulada,
            'minutos_diferidos'        => $regla->minutosDiferidos,
            'tipo_comunicacion'        => $regla->tipoComunicacion
        ];
    }

    private function renderizarPlantilla(string $cuerpo, array $parametros): string
    {
        $salida = $cuerpo;
        foreach ($parametros as $clave => $valor) {
            $salida = str_replace('{{' . $clave . '}}', (string)$valor, $salida);
        }
        return $salida;
    }
}

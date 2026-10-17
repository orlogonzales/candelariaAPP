<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

use Aplicacion\Entidades\Pago;
use Aplicacion\Entidades\PagoIntentoPasarela;
use Aplicacion\Finanzas\Pasarelas\FabricaAdaptadorPasarela;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Servicio orquestador de recepción, sanitización, verificación e imputación
 * atómica de webhooks provenientes de pasarelas de pago.
 */
class WebhookPasarelaServicio
{
    private PDO $pdo;

    public function __construct(
        private PagoRepositorio $pagoRepo,
        private PasarelaRepositorio $pasarelaRepo,
        private VentaRepositorio $ventaRepo,
        private PagoServicio $pagoServicio,
        private CifradorFinanciero $cifrador,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Procesa de forma segura, atómica y sanitizada un webhook entrante de pasarela de pagos.
     *
     * @param string $codigoPasarela CULQI | STRIPE | NIUBIZ | MERCADOPAGO
     * @param string $cuerpoRaw Payload JSON sin procesar tal como llegó al socket
     * @param array<string, string> $cabeceras Cabeceras HTTP recibidas
     * @param array $parametrosQuery Parámetros $_GET
     * @param string $ipOrigen Dirección IP del cliente/servidor pasarela
     * @return array
     */
    public function procesarWebhook(
        string $codigoPasarela,
        string $cuerpoRaw,
        array $cabeceras,
        array $parametrosQuery = [],
        string $ipOrigen = '127.0.0.1'
    ): array {
        $codigoNormalizado = strtoupper(trim($codigoPasarela));

        // 1. Determinar el Tenant / Organización
        $organizacionId = $this->resolverOrganizacionId($codigoNormalizado, $cuerpoRaw, $parametrosQuery);
        if ($organizacionId === null || $organizacionId <= 0) {
            throw new InvalidArgumentException("No fue posible identificar la organización/tenant para la pasarela {$codigoNormalizado}.");
        }

        // 2. Obtener configuración de la pasarela para este Tenant
        $orgPasarela = $this->pasarelaRepo->buscarOrganizacionPasarelaPorCodigo($organizacionId, $codigoNormalizado);
        if ($orgPasarela === null || !$orgPasarela->activo) {
            throw new InvalidArgumentException("La pasarela {$codigoNormalizado} no está configurada o se encuentra inactiva para la organización #{$organizacionId}.");
        }

        // 3. Obtener y descifrar el secreto de webhook o credencial privada (AES-256-GCM)
        $secreto = '';
        if (!empty($orgPasarela->webhookSecretoEnc)) {
            $secreto = $this->cifrador->descifrar($orgPasarela->webhookSecretoEnc);
        } elseif (!empty($orgPasarela->credencialSecretaEnc)) {
            $secreto = $this->cifrador->descifrar($orgPasarela->credencialSecretaEnc);
        }

        // Crear contexto de sistema para trazabilidad y auditoría
        $contexto = ContextoOperacion::paraSistema(
            actorSistemaId: 2,
            actorSistemaCodigo: 'CHECKOUT_PASARELA',
            canalId: 3,
            canalCodigo: 'API',
            origenIp: $ipOrigen,
            agenteUsuario: $cabeceras['user-agent'] ?? 'Gateway-Webhook-Dispatcher',
            organizacionId: $organizacionId
        );

        // 4. Resolver adaptador e invocar verificación y normalización (Fail-Closed)
        $adaptador = FabricaAdaptadorPasarela::obtener($codigoNormalizado);

        $firmaValida = $adaptador->verificarFirmaWebhook($orgPasarela, $cuerpoRaw, $cabeceras, $secreto);
        if (!$firmaValida) {
            try {
                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'pagos_caja',
                    accion: 'WEBHOOK_FIRMA_INVALIDA_RECHAZADA',
                    entidadTipo: 'PASARELA',
                    entidadId: $codigoNormalizado,
                    datosPrevios: null,
                    datosNuevos: [
                        'pasarela'  => $codigoNormalizado,
                        'ip_origen' => $ipOrigen,
                        'motivo'    => 'Firma criptográfica ausente, expirada o inválida',
                    ]
                );
            } catch (\Throwable) {
                // No interrumpir el rechazo fail-closed si la auditoría falla
            }
            throw new InvalidArgumentException("Firma criptográfica de webhook inválida para la pasarela {$codigoNormalizado}.");
        }

        $evento = $adaptador->parsearEventoWebhook($orgPasarela, $cuerpoRaw, $cabeceras);
        if (!$evento->esValido) {
            throw new InvalidArgumentException("Payload de webhook no estructurado o inválido: " . ($evento->mensajeRespuesta ?? 'Desconocido'));
        }

        $claveIdempotencia = $evento->claveIdempotencia ?? ('wh_' . strtolower($codigoNormalizado) . '_' . ($evento->transaccionExternaId ?? md5($cuerpoRaw)));

        // Validar o resolver la venta vinculada
        $ventaId = $evento->ventaId;
        if ($ventaId === null && !empty($evento->ordenCheckoutId) && is_numeric($evento->ordenCheckoutId)) {
            $ventaId = (int) $evento->ordenCheckoutId;
        }

        // 5. Bloqueo pesimista transaccional (FOR UPDATE), idempotencia y monotonicidad de estados
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            // 5.1 Si hay venta vinculada, adquirir bloqueo pesimista de fila para serializar webhooks concurrentes
            $filaVentaBloqueada = null;
            if ($ventaId !== null && $ventaId > 0) {
                $stmtLockVenta = $this->pdo->prepare("
                    SELECT `id`, `organizacion_id`, `estado`, `estado_financiero`, `total`, `monto_pagado`, `saldo_pendiente`
                    FROM `ventas`
                    WHERE `id` = :id AND `organizacion_id` = :org_id
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtLockVenta->execute(['id' => $ventaId, 'org_id' => $organizacionId]);
                $filaVentaBloqueada = $stmtLockVenta->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            // 5.2 Verificar idempotencia estricta por clave de evento con bloqueo FOR UPDATE
            $stmtLockIntento = $this->pdo->prepare("
                SELECT * FROM `pago_intentos_pasarela`
                WHERE `organizacion_id` = :org_id
                  AND `clave_idempotencia_webhook` = :clave
                LIMIT 1
                FOR UPDATE
            ");
            $stmtLockIntento->execute([
                'org_id' => $organizacionId,
                'clave'  => $claveIdempotencia,
            ]);
            $intentoExistente = $stmtLockIntento->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($intentoExistente !== null) {
                if ($transaccionPropia && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
                return [
                    'exito'                   => true,
                    'mensaje'                 => 'Evento ya procesado previamente (idempotente).',
                    'duplicado'               => true,
                    'ignorado_fuera_de_orden' => false,
                    'intento_id'              => (int) $intentoExistente['id'],
                    'pago_id'                 => $intentoExistente['pago_id'] !== null ? (int) $intentoExistente['pago_id'] : null,
                    'evento'                  => $evento->tipoEvento,
                ];
            }

            // 5.3 Verificar monotonicidad por transaccion_externa_id (protección contra eventos fuera de orden o reintentos con distinto ID de sobre)
            $txExterna = $evento->transaccionExternaId;
            if ($txExterna !== null && $txExterna !== '') {
                $stmtPagoPrevio = $this->pdo->prepare("
                    SELECT `id`, `estado`, `monto_reembolsado_acumulado`
                    FROM `pagos`
                    WHERE `organizacion_id` = :org_id
                      AND `numero_operacion_bancaria` = :tx
                    ORDER BY `id` DESC
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtPagoPrevio->execute([
                    'org_id' => $organizacionId,
                    'tx'     => $txExterna,
                ]);
                $pagoPrevio = $stmtPagoPrevio->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($pagoPrevio !== null && $pagoPrevio['estado'] === EstadoPago::APROBADO->value) {
                    $pagoPrevioId = (int) $pagoPrevio['id'];
                    $tieneReembolsos = (float) ($pagoPrevio['monto_reembolsado_acumulado'] ?? 0) > 0.0;

                    // Caso A: Llega otro CARGO_EXITOSO para la misma transacción ya aprobada (o ya reembolsada)
                    if (in_array($evento->tipoEvento, ['CARGO_EXITOSO', 'PAGO_EXITOSO'], true)) {
                        if ($transaccionPropia && $this->pdo->inTransaction()) {
                            $this->pdo->commit();
                        }
                        return [
                            'exito'                   => true,
                            'mensaje'                 => $tieneReembolsos
                                ? 'Evento ignorado: el pago asociado ya registra reembolsos ejecutados (estado monotónico superior).'
                                : 'Transacción externa ya imputada previamente como pago aprobado (idempotente).',
                            'duplicado'               => !$tieneReembolsos,
                            'ignorado_fuera_de_orden' => $tieneReembolsos,
                            'intento_id'              => null,
                            'pago_id'                 => $pagoPrevioId,
                            'evento'                  => $evento->tipoEvento,
                        ];
                    }

                    // Caso B: Llega un CARGO_FALLIDO / PENDIENTE / OTRO retrasado (fuera de orden) después de que el pago ya fue APROBADO
                    $this->auditoriaRepo->registrar(
                        contexto: $contexto,
                        modulo: 'pagos_caja',
                        accion: 'WEBHOOK_FUERA_DE_ORDEN_IGNORADO',
                        entidadTipo: 'PAGO',
                        entidadId: (string) $pagoPrevioId,
                        datosPrevios: ['estado_pago' => $pagoPrevio['estado']],
                        datosNuevos: [
                            'evento_retrasado'       => $evento->tipoEvento,
                            'transaccion_externa_id' => $txExterna,
                            'clave_idempotencia'     => $claveIdempotencia,
                            'motivo'                 => 'Invariante monotónica: un evento de fallo o pendiente retrasado no puede degradar un pago APROBADO.',
                        ]
                    );

                    if ($transaccionPropia && $this->pdo->inTransaction()) {
                        $this->pdo->commit();
                    }
                    return [
                        'exito'                   => true,
                        'mensaje'                 => 'Evento fuera de orden ignorado: la transacción ya cuenta con un pago aprobado monotónico.',
                        'duplicado'               => false,
                        'ignorado_fuera_de_orden' => true,
                        'intento_id'              => null,
                        'pago_id'                 => $pagoPrevioId,
                        'evento'                  => $evento->tipoEvento,
                    ];
                }
            }

            // 5.4 Si la venta ya está en estado terminal (LIQUIDADA, CANCELADA, ANULADA), no imputar nuevos cobros tardíos
            $ventaEnEstadoTerminal = $filaVentaBloqueada !== null
                && in_array((string) $filaVentaBloqueada['estado'], ['LIQUIDADA', 'CANCELADA', 'ANULADA'], true);

            $pagoId = null;
            $pagoRegistrado = null;

            // 6. Si el evento es un pago exitoso y la venta admite cobros, registrar y aplicar pago a la venta
            if (in_array($evento->tipoEvento, ['CARGO_EXITOSO', 'PAGO_EXITOSO'], true) && $ventaId !== null && !$ventaEnEstadoTerminal) {
                $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
                if ($venta !== null) {
                    $comisionEstimada = round(($evento->monto * (float) $orgPasarela->porcentajeComision / 100.0) + (float) $orgPasarela->comisionFija, 2);
                    $pagoRegistrado = $this->pagoServicio->registrarPagoPasarelaWeb(
                        organizacionId: $organizacionId,
                        ventaId: $ventaId,
                        organizacionPasarelaId: (int) $orgPasarela->id,
                        monto: $evento->monto,
                        comisionPasarela: $comisionEstimada,
                        transaccionExternaId: $evento->transaccionExternaId ?? ('EXT-' . time()),
                        claveIdempotencia: $claveIdempotencia,
                        datosMetadatos: [
                            'notas' => "Pago vía {$codigoNormalizado} webhook (Ref: " . ($evento->transaccionExternaId ?? 'N/A') . ")",
                        ],
                        contexto: $contexto
                    );
                    $pagoId = $pagoRegistrado->id;
                }
            }

            // 7. Registrar el intento/evento en `pago_intentos_pasarela` para trazabilidad inmutable
            $estadoIntento = in_array($evento->tipoEvento, ['CARGO_EXITOSO', 'PAGO_EXITOSO'], true)
                ? EstadoIntentoPasarela::EXITOSO
                : EstadoIntentoPasarela::FALLIDO;

            $intento = new PagoIntentoPasarela(
                id: null,
                organizacionId: $organizacionId,
                pagoId: $pagoId,
                ventaId: $ventaId ?? 1,
                organizacionPasarelaId: (int) $orgPasarela->id,
                transaccionExternaId: $evento->transaccionExternaId ?? ('TRANS-' . time()),
                ordenCheckoutId: $evento->ordenCheckoutId,
                monto: $evento->monto,
                moneda: $evento->moneda,
                estadoIntento: $estadoIntento,
                codigoRespuestaPasarela: $evento->codigoRespuesta,
                mensajeRespuestaPasarela: $evento->mensajeRespuesta,
                payloadSolicitudSanitizado: ['headers' => array_intersect_key($cabeceras, array_flip(['content-type', 'user-agent', 'x-signature', 'stripe-signature', 'x-niubiz-signature', 'x-culqi-signature']))],
                payloadRespuestaSanitizado: $evento->payloadSanitizado,
                tarjetaMarca: $evento->tarjetaMarca,
                tarjetaUltimosCuatro: $evento->tarjetaUltimosCuatro,
                ipOrigen: $ipOrigen,
                firmaWebhookRecibida: $cabeceras['stripe-signature'] ?? $cabeceras['x-signature'] ?? $cabeceras['x-culqi-signature'] ?? $cabeceras['authorization'] ?? null,
                claveIdempotenciaWebhook: $claveIdempotencia
            );

            $intentoId = $this->pagoRepo->registrarIntentoPasarela($intento);

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return [
                'exito'                   => true,
                'mensaje'                 => $ventaEnEstadoTerminal
                    ? 'Evento registrado sin imputación contable por encontrarse la venta en estado terminal.'
                    : 'Webhook procesado satisfactoriamente.',
                'duplicado'               => false,
                'ignorado_fuera_de_orden' => $ventaEnEstadoTerminal,
                'intento_id'              => $intentoId,
                'pago_id'                 => $pagoId,
                'evento'                  => $evento->tipoEvento,
            ];
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resuelve el tenant organizacionId a partir de la URL query, cabeceras o cuerpo del webhook.
     */
    private function resolverOrganizacionId(string $codigoPasarela, string $cuerpoRaw, array $parametrosQuery): ?int
    {
        // 1. Directo en query parameter (e.g. ?org_id=1 o ?tenant=1)
        if (!empty($parametrosQuery['org_id']) && is_numeric($parametrosQuery['org_id'])) {
            return (int) $parametrosQuery['org_id'];
        }
        if (!empty($parametrosQuery['tenant_id']) && is_numeric($parametrosQuery['tenant_id'])) {
            return (int) $parametrosQuery['tenant_id'];
        }

        // 2. Parseo preliminar del JSON para buscar metadatos del tenant
        $json = json_decode($cuerpoRaw, true);
        if (is_array($json)) {
            // Culqi / Stripe metadata
            if (!empty($json['metadata']['organizacion_id']) && is_numeric($json['metadata']['organizacion_id'])) {
                return (int) $json['metadata']['organizacion_id'];
            }
            if (!empty($json['data']['object']['metadata']['organizacion_id']) && is_numeric($json['data']['object']['metadata']['organizacion_id'])) {
                return (int) $json['data']['object']['metadata']['organizacion_id'];
            }
            if (!empty($json['order']['metadata']['organizacion_id']) && is_numeric($json['order']['metadata']['organizacion_id'])) {
                return (int) $json['order']['metadata']['organizacion_id'];
            }

            // Buscar si viene venta_id en metadata o root
            $ventaId = $json['metadata']['venta_id']
                ?? $json['data']['object']['metadata']['venta_id']
                ?? $json['venta_id']
                ?? null;

            if ($ventaId !== null && is_numeric($ventaId)) {
                $stmt = $this->pdo->prepare("SELECT `organizacion_id` FROM `ventas` WHERE `id` = :id LIMIT 1");
                $stmt->execute(['id' => (int) $ventaId]);
                $org = $stmt->fetchColumn();
                if ($org) {
                    return (int) $org;
                }
            }
        }

        // 3. Fallback: Si la pasarela está configurada para una única organización activa
        $stmtPas = $this->pdo->prepare("
            SELECT op.`organizacion_id`
            FROM `organizacion_pasarelas` op
            INNER JOIN `pasarelas_pago` p ON p.id = op.pasarela_id
            WHERE p.codigo = :cod AND op.activo = 1
            LIMIT 2
        ");
        $stmtPas->execute(['cod' => $codigoPasarela]);
        $orgs = $stmtPas->fetchAll(PDO::FETCH_COLUMN);
        if (count($orgs) === 1) {
            return (int) $orgs[0];
        }

        // 4. Default tenant 1
        return 1;
    }
}

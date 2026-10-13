<?php

declare(strict_types=1);

namespace Aplicacion\Operaciones;

use Aplicacion\Excepciones\ValidacionExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OperacionOfflineRepositorio;
use Aplicacion\Servicios\FachadaPortalPublicoServicio;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Servicio de Operación Móvil, Validación Criptográfica y Sincronización QR Offline (DT-V11-010 / Fase 3.3).
 */
class OperacionOfflineServicio
{
    public function __construct(
        private OperacionOfflineRepositorio $offlineRepo,
        private ?AuditoriaRepositorio $auditoriaRepo = null,
        private ?PDO $pdo = null
    ) {
        $this->pdo = $this->pdo ?? \Nucleo\BaseDatos\Conexion::obtenerInstancia();
        $this->auditoriaRepo = $this->auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
    }

    /**
     * Descarga el paquete de validación offline (manifest y lista de revocaciones) para el operador.
     * Restringe datos sensibles y aplica enmascaramiento estricto de PII.
     */
    public function descargarPaqueteOffline(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        ?int $salidaId = null
    ): array {
        // 1. Validar que la edición exista y se encuentre activa/operativa
        $stmtEd = $this->pdo->prepare("
            SELECT id, nombre, anio, estado FROM `ediciones_candelaria`
            WHERE `id` = :ed_id AND `organizacion_id` = :org_id
        ");
        $stmtEd->execute(['ed_id' => $edicionId, 'org_id' => $organizacionId]);
        $edicion = $stmtEd->fetch(PDO::FETCH_ASSOC);

        if (!$edicion) {
            throw new ValidacionExcepcion("La edición solicitada (#{$edicionId}) no existe en esta organización.");
        }

        if ($edicion['estado'] === 'CERRADA') {
            throw new ValidacionExcepcion("La edición Candelaria {$edicion['anio']} se encuentra CERRADA. No se permite operación offline.");
        }

        // 2. Generar UUID del lote de sesión del operador y ventana de vigencia (12 horas)
        $loteUuid = sprintf('lote-%s-%s', bin2hex(random_bytes(8)), date('YmdHis'));
        $emitidoEn = date('Y-m-d H:i:s');
        $expiraEn = date('Y-m-d H:i:s', strtotime('+12 hours'));

        // 3. Consultar boletos y acreditaciones vigentes de la edición
        // Se seleccionan reservas confirmadas con ventas pagadas
        $sqlBoletos = "
            SELECT r.id as reserva_id, r.correlativo, r.contacto_nombre, r.notas_operativas,
                   v.id as venta_id, rp.concepto_nombre
            FROM `reservas` r
            JOIN `ventas` v ON r.venta_id = v.id
            LEFT JOIN `reserva_prestaciones` rp ON rp.reserva_id = r.id
            WHERE r.organizacion_id = :org_id
              AND r.edicion_id = :ed_id
              AND r.estado = 'CONFIRMADA'
              AND v.estado = 'CONFIRMADA'
              AND v.estado_financiero IN ('PAGADA_TOTAL', 'SOBREPAGADA')
        ";

        $stmtB = $this->pdo->prepare($sqlBoletos);
        $stmtB->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $boletos = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        $resumenCredenciales = [];
        $listaRevocacion = [];

        $secretoOrg = hash('sha256', 'candelaria_qr_secret_org_' . $organizacionId);

        foreach ($boletos as $b) {
            // El hash de coincidencia puede ser el sha256 del correlativo o el token emitido
            $qrHash = hash('sha256', 'CORR:' . $organizacionId . ':' . $b['correlativo']);

            // Verificar si ya tiene checkin registrado en servidor
            $notas = (string) ($b['notas_operativas'] ?? '');
            $yaCanjeado = str_contains($notas, '[CHECKIN_QR');

            if ($yaCanjeado) {
                $listaRevocacion[] = [
                    'qr_hash' => $qrHash,
                    'motivo'  => 'CANJEADO_PREVIAMENTE',
                ];
                continue;
            }

            // Enmascarar titular (Principio de minimización de PII)
            $nombreOriginal = trim((string) ($b['contacto_nombre'] ?? ''));
            $nombreEnmascarado = $this->enmascararNombre($nombreOriginal);

            $resumenCredenciales[$qrHash] = [
                'reserva_id'       => (int) $b['reserva_id'],
                'correlativo'      => (string) $b['correlativo'],
                'titular_mascarado'=> $nombreEnmascarado,
                'concepto'         => (string) ($b['concepto_nombre'] ?? 'Acceso General'),
                'asiento'          => (string) ($b['ubicacion_asiento'] ?? 'S/N'),
                'tipo'             => OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
            ];
        }

        // 4. Incorporar boletos expresamente cancelados o reembolsados a la lista de revocaciones
        $stmtCancel = $this->pdo->prepare("
            SELECT r.correlativo
            FROM `reservas` r
            WHERE r.organizacion_id = :org_id
              AND r.edicion_id = :ed_id
              AND r.estado IN ('CANCELADA', 'EXPIRADA')
        ");
        $stmtCancel->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        while ($corr = $stmtCancel->fetchColumn()) {
            $qrHashCanc = hash('sha256', 'CORR:' . $organizacionId . ':' . $corr);
            $listaRevocacion[] = [
                'qr_hash' => $qrHashCanc,
                'motivo'  => 'RESERVA_CANCELADA',
            ];
        }

        $paquete = [
            'version_protocolo'    => '1.1-OFFLINE-QR',
            'lote_uuid'            => $loteUuid,
            'organizacion_id'      => $organizacionId,
            'edicion_id'           => $edicionId,
            'operador_id'          => $operadorId,
            'salida_id'            => $salidaId,
            'emitido_en'           => $emitidoEn,
            'expira_en'            => $expiraEn,
            'total_credenciales'   => count($resumenCredenciales),
            'total_revocadas'      => count($listaRevocacion),
            'resumen_credenciales' => $resumenCredenciales,
            'lista_revocacion'     => $listaRevocacion,
        ];

        // Firma del paquete para validar integridad en el cliente
        $paqueteJson = json_encode($paquete, JSON_UNESCAPED_UNICODE);
        $paquete['firma_paquete'] = hash_hmac('sha256', (string) $paqueteJson, $secretoOrg);

        return $paquete;
    }

    /**
     * Valida una credencial localmente contra el paquete offline cargado en el dispositivo.
     */
    public function validarCredencialLocal(
        array $paquete,
        string $tokenQr,
        array $escaneosPrevios = []
    ): array {
        $ahora = date('Y-m-d H:i:s');
        if (!empty($paquete['expira_en']) && $ahora > $paquete['expira_en']) {
            return [
                'valido'            => false,
                'resultado_codigo'  => OperacionOfflineEvento::RESULTADO_RECHAZADO_EXPIRADO,
                'motivo'            => 'El paquete de validación offline ha expirado. Requiere reconexión para actualizar turno.',
                'es_provisional'    => true,
            ];
        }

        $orgId = (int) ($paquete['organizacion_id'] ?? 0);
        $tokenQrLimpio = trim($tokenQr);

        // Resolver hash del QR
        $qrHash = $this->extraerHashDeToken($orgId, $tokenQrLimpio);

        // 1. Verificar si está en la lista de revocación
        $revocadas = $paquete['lista_revocacion'] ?? [];
        foreach ($revocadas as $rev) {
            if ($rev['qr_hash'] === $qrHash) {
                return [
                    'valido'           => false,
                    'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_REVOCADO,
                    'motivo'           => 'Credencial revocada o canjeada antes de la descarga del lote: ' . ($rev['motivo'] ?? ''),
                    'es_provisional'   => true,
                ];
            }
        }

        // 2. Control Anti-Replay Local (Doble escaneo en el mismo dispositivo)
        if (in_array($qrHash, $escaneosPrevios, true)) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_CONFLICTO_DUPLICADO,
                'motivo'           => 'Duplicado local: Esta credencial ya fue escaneada previamente en este dispositivo.',
                'es_provisional'   => true,
            ];
        }

        // 3. Buscar en el catálogo de credenciales válidas
        $credenciales = $paquete['resumen_credenciales'] ?? [];
        if (!isset($credenciales[$qrHash])) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                'motivo'           => 'Credencial no encontrada en el paquete local. Requiere verificación online en servidor.',
                'es_provisional'   => true,
            ];
        }

        $cred = $credenciales[$qrHash];

        return [
            'valido'            => true,
            'resultado_codigo'  => 'VALIDO_LOCAL_PROVISIONAL',
            'motivo'            => 'Credencial válida localmente (Confirmación provisional sujeta a sincronización).',
            'es_provisional'    => true,
            'qr_hash'           => $qrHash,
            'credencial'        => $cred,
        ];
    }

    /**
     * Valida una credencial en línea directamente contra el servidor central (fallback online).
     */
    public function validarCredencialOnline(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        string $tokenQr,
        bool $marcarIngreso = true
    ): array {
        $tokenQrLimpio = trim($tokenQr);

        // Si es un token QR estructurado (base64.firma)
        if (str_contains($tokenQrLimpio, '.')) {
            return $this->validarTokenCriptograficoBoleto(
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                operadorId: $operadorId,
                tokenQr: $tokenQrLimpio,
                marcarIngreso: $marcarIngreso
            );
        }

        // Si es un correlativo manual o código simple (ej. "RES-2026-0001")
        return $this->validarCorrelativoManualOnline($organizacionId, $edicionId, $operadorId, $tokenQrLimpio, $marcarIngreso);
    }

    /**
     * Valida la firma criptográfica y concilia un token QR estructurado en línea.
     */
    public function validarTokenCriptograficoBoleto(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        string $tokenQr,
        bool $marcarIngreso = true
    ): array {
        $partes = explode('.', $tokenQr);
        if (count($partes) !== 2) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                'motivo'           => 'Formato de token QR estructurado inválido.',
                'es_provisional'   => false,
            ];
        }

        [$payloadB64, $firmaRecibida] = $partes;
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
        if ($payloadJson === false) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                'motivo'           => 'Payload de boleto no decodificable.',
                'es_provisional'   => false,
            ];
        }

        $secretoQr = hash('sha256', 'candelaria_qr_secret_org_' . $organizacionId);
        $firmaCalculada = hash_hmac('sha256', $payloadJson, $secretoQr);

        if (!hash_equals($firmaCalculada, $firmaRecibida)) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_REVOCADO,
                'motivo'           => 'Firma digital de boleto inválida o adulterada.',
                'es_provisional'   => false,
            ];
        }

        $datos = json_decode($payloadJson, true);
        if (!is_array($datos) || empty($datos['reserva_id'])) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                'motivo'           => 'Estructura interna del boleto inválida.',
                'es_provisional'   => false,
            ];
        }

        $reservaId = (int) $datos['reserva_id'];
        $correlativo = (string) ($datos['correlativo'] ?? '');

        $qrHash = $this->extraerHashDeToken($organizacionId, $tokenQr);
        $conciliacion = $this->conciliarEventoIndividual(
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            operadorId: $operadorId,
            tokenQr: $tokenQr,
            qrHash: $qrHash,
            escaneadoEn: date('Y-m-d H:i:s')
        );

        return [
            'valido'           => ($conciliacion['resultado_servidor'] === OperacionOfflineEvento::RESULTADO_CONFIRMADO),
            'resultado_codigo' => $conciliacion['resultado_servidor'],
            'motivo'           => $conciliacion['motivo'],
            'es_provisional'   => false,
            'datos'            => [
                'reserva_id'  => $conciliacion['referencia_id'] ?? $reservaId,
                'correlativo' => $correlativo,
                'titular'     => $this->enmascararNombre((string) ($datos['titular'] ?? 'Cliente')),
            ],
        ];
    }

    /**
     * Sincroniza un lote de eventos offline de campo hacia la base de datos central.
     * Aplica transacciones atómicas, resolución de conflictos y bloqueo pesimista.
     */
    public function sincronizarLoteOffline(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        array $datosLote,
        ?string $ipOrigen = null
    ): array {
        $loteUuid = trim((string) ($datosLote['lote_uuid'] ?? ''));
        if (empty($loteUuid)) {
            throw new ValidacionExcepcion("El identificador único del lote (lote_uuid) es obligatorio.");
        }

        $eventos = $datosLote['eventos'] ?? [];
        if (!is_array($eventos)) {
            throw new ValidacionExcepcion("La lista de eventos de escaneo tiene un formato inválido.");
        }

        // 1. Verificar idempotencia del lote
        $loteExistente = $this->offlineRepo->buscarLotePorUuid($loteUuid);
        if ($loteExistente !== null && $loteExistente->estado === OperacionOfflineLote::ESTADO_PROCESADO) {
            return [
                'exito'             => true,
                'lote_id'           => $loteExistente->id,
                'lote_uuid'         => $loteExistente->loteUuid,
                'mensaje'           => 'Lote ya sincronizado previamente (Respuesta idempotente).',
                'total_escaneos'    => $loteExistente->totalEscaneos,
                'total_confirmados' => $loteExistente->totalConfirmados,
                'total_conflictos'  => $loteExistente->totalConflictos,
                'total_rechazados'  => $loteExistente->totalRechazados,
                'estado'            => $loteExistente->estado,
            ];
        }

        // 2. Iniciar o recuperar lote
        $lote = $loteExistente ?? new OperacionOfflineLote(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            operadorId: $operadorId,
            loteUuid: $loteUuid,
            dispositivoInfo: isset($datosLote['dispositivo_info']) ? (string) $datosLote['dispositivo_info'] : 'Navegador Web Móvil',
            totalEscaneos: count($eventos),
            totalConfirmados: 0,
            totalConflictos: 0,
            totalRechazados: 0,
            estado: OperacionOfflineLote::ESTADO_RECIBIDO,
            ipOrigen: $ipOrigen
        );

        if ($lote->id === null) {
            $lote = $this->offlineRepo->crearLote($lote);
        }

        $confirmados = 0;
        $conflictos = 0;
        $rechazados = 0;
        $resultadosEventos = [];

        // 3. Procesar cada evento individual en transacción
        foreach ($eventos as $ev) {
            $tokenQr = trim((string) ($ev['token_qr'] ?? ''));
            $escaneadoEn = !empty($ev['escaneado_en']) ? (string) $ev['escaneado_en'] : date('Y-m-d H:i:s');
            $idempotenciaHash = trim((string) ($ev['idempotencia_hash'] ?? ''));

            $qrHash = $this->extraerHashDeToken($organizacionId, $tokenQr);

            if (empty($idempotenciaHash)) {
                $idempotenciaHash = hash('sha256', "{$operadorId}:{$loteUuid}:{$qrHash}:{$escaneadoEn}");
            }

            // Si el evento ya fue registrado (idempotencia individual)
            if ($this->offlineRepo->existeEventoPorIdempotencia($idempotenciaHash)) {
                $confirmados++;
                $resultadosEventos[] = [
                    'idempotencia_hash'  => $idempotenciaHash,
                    'resultado_servidor' => OperacionOfflineEvento::RESULTADO_CONFIRMADO,
                    'motivo'             => 'Evento ya registrado previamente.',
                ];
                continue;
            }

            // Conciliación con bloqueo pesimista en servidor
            $resultadoConciliacion = $this->conciliarEventoIndividual(
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                operadorId: $operadorId,
                tokenQr: $tokenQr,
                qrHash: $qrHash,
                escaneadoEn: $escaneadoEn
            );

            $eventoEntidad = new OperacionOfflineEvento(
                id: null,
                loteId: (int) $lote->id,
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                operadorId: $operadorId,
                idempotenciaHash: $idempotenciaHash,
                tokenQrHash: $qrHash,
                tipoCredencial: $resultadoConciliacion['tipo_credencial'] ?? OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
                referenciaId: $resultadoConciliacion['referencia_id'] ?? null,
                resultadoLocal: (string) ($ev['resultado_local'] ?? 'VALIDO_PROVISIONAL'),
                resultadoServidor: $resultadoConciliacion['resultado_servidor'],
                motivoResolucion: $resultadoConciliacion['motivo'],
                escaneadoEn: $escaneadoEn,
                metadatosJson: [
                    'dispositivo'    => $datosLote['dispositivo_info'] ?? null,
                    'ip'             => $ipOrigen,
                    'datos_reserva'  => $resultadoConciliacion['datos_reserva'] ?? null,
                ]
            );

            $this->offlineRepo->crearEvento($eventoEntidad);

            if ($resultadoConciliacion['resultado_servidor'] === OperacionOfflineEvento::RESULTADO_CONFIRMADO) {
                $confirmados++;
            } elseif ($resultadoConciliacion['resultado_servidor'] === OperacionOfflineEvento::RESULTADO_CONFLICTO_DUPLICADO) {
                $conflictos++;
            } else {
                $rechazados++;
            }

            $resultadosEventos[] = [
                'idempotencia_hash'  => $idempotenciaHash,
                'resultado_servidor' => $resultadoConciliacion['resultado_servidor'],
                'motivo'             => $resultadoConciliacion['motivo'],
            ];
        }

        $estadoFinal = ($conflictos > 0)
            ? OperacionOfflineLote::ESTADO_CON_CONFLICTOS
            : OperacionOfflineLote::ESTADO_PROCESADO;

        $this->offlineRepo->actualizarTotalesLote(
            loteId: (int) $lote->id,
            totalEscaneos: count($eventos),
            confirmados: $confirmados,
            conflictos: $conflictos,
            rechazados: $rechazados,
            estado: $estadoFinal
        );

        return [
            'exito'             => true,
            'lote_id'           => $lote->id,
            'lote_uuid'         => $lote->loteUuid,
            'mensaje'           => 'Lote sincronizado y conciliado correctamente.',
            'total_escaneos'    => count($eventos),
            'total_confirmados' => $confirmados,
            'total_conflictos'  => $conflictos,
            'total_rechazados'  => $rechazados,
            'estado'            => $estadoFinal,
            'detalles_eventos'  => $resultadosEventos,
        ];
    }

    /**
     * Concilia un escaneo individual contra la base de datos central.
     */
    private function conciliarEventoIndividual(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        string $tokenQr,
        string $qrHash,
        string $escaneadoEn
    ): array {
        $correlativo = '';
        $reservaId = null;

        // Si el QR tiene formato token
        if (str_contains($tokenQr, '.')) {
            [$payloadB64] = explode('.', $tokenQr, 2);
            $json = base64_decode(strtr($payloadB64, '-_', '+/'));
            if ($json) {
                $datos = json_decode($json, true);
                if (is_array($datos)) {
                    $reservaId = isset($datos['reserva_id']) ? (int) $datos['reserva_id'] : null;
                    $correlativo = (string) ($datos['correlativo'] ?? '');
                }
            }
        } else {
            // Es correlativo directo (ej. "RES-2026-0001")
            $correlativo = $tokenQr;
        }

        // Buscar reserva con bloqueo pesimista
        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            if ($reservaId !== null && $reservaId > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT r.id, r.correlativo, r.estado, r.notas_operativas, v.estado as venta_estado, v.estado_financiero
                    FROM `reservas` r
                    JOIN `ventas` v ON r.venta_id = v.id
                    WHERE r.id = :id AND r.organizacion_id = :org_id
                    FOR UPDATE
                ");
                $stmt->execute(['id' => $reservaId, 'org_id' => $organizacionId]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT r.id, r.correlativo, r.estado, r.notas_operativas, v.estado as venta_estado, v.estado_financiero
                    FROM `reservas` r
                    JOIN `ventas` v ON r.venta_id = v.id
                    WHERE r.correlativo = :corr AND r.organizacion_id = :org_id
                    FOR UPDATE
                ");
                $stmt->execute(['corr' => $correlativo, 'org_id' => $organizacionId]);
            }

            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                if ($transaccionIniciada) { $this->pdo->commit(); }
                return [
                    'resultado_servidor' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                    'motivo'             => 'La reserva referenciada no existe en esta organización.',
                    'tipo_credencial'    => OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
                ];
            }

            $resId = (int) $reserva['id'];

            // Comprobar estado de reserva y pago
            if ($reserva['estado'] !== 'CONFIRMADA' || $reserva['venta_estado'] !== 'CONFIRMADA') {
                if ($transaccionIniciada) { $this->pdo->commit(); }
                return [
                    'resultado_servidor' => OperacionOfflineEvento::RESULTADO_RECHAZADO_REVOCADO,
                    'motivo'             => "Reserva cancelada o inactiva en servidor (Estado: {$reserva['estado']}).",
                    'referencia_id'      => $resId,
                    'tipo_credencial'    => OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
                ];
            }

            // Comprobar si ya fue canjeada en el servidor (Conflicto de Duplicado)
            $notas = (string) ($reserva['notas_operativas'] ?? '');
            if (str_contains($notas, '[CHECKIN_QR')) {
                if ($transaccionIniciada) { $this->pdo->commit(); }
                return [
                    'resultado_servidor' => OperacionOfflineEvento::RESULTADO_CONFLICTO_DUPLICADO,
                    'motivo'             => 'Conflicto: La credencial ya fue canjeada en servidor o por otro operador de campo.',
                    'referencia_id'      => $resId,
                    'tipo_credencial'    => OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
                ];
            }

            // Registrar Check-in oficial en servidor
            $notaCheckin = " [CHECKIN_QR_OFFLINE_SYNC: {$escaneadoEn} por Operador #{$operadorId}]";
            $this->pdo->prepare("
                UPDATE `reservas`
                SET `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), :nota)
                WHERE `id` = :id
            ")->execute(['nota' => $notaCheckin, 'id' => $resId]);

            // Si hay prestaciones asociadas y operacion_asistencias, marcar asistencia
            $this->pdo->prepare("
                UPDATE `operacion_asistencias`
                SET `estado_asistencia` = 'PRESENTE',
                    `marcado_en` = :marcado_en,
                    `marcado_por` = :marcado_por,
                    `observacion` = 'Sincronizado desde validador móvil offline'
                WHERE `prestacion_id` IN (SELECT id FROM `reserva_prestaciones` WHERE `reserva_id` = :res_id)
                  AND `estado_asistencia` = 'PENDIENTE'
            ")->execute([
                'marcado_en'  => $escaneadoEn,
                'marcado_por' => $operadorId,
                'res_id'      => $resId,
            ]);

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return [
                'resultado_servidor' => OperacionOfflineEvento::RESULTADO_CONFIRMADO,
                'motivo'             => 'Check-in confirmado y asentado en el sistema central.',
                'referencia_id'      => $resId,
                'tipo_credencial'    => OperacionOfflineEvento::TIPO_BOLETO_RESERVA,
                'datos_reserva'      => [
                    'correlativo' => $reserva['correlativo'],
                    'estado'      => $reserva['estado'],
                ],
            ];
        } catch (Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Valida un código o correlativo ingresado manualmente en modo online.
     */
    private function validarCorrelativoManualOnline(
        int $organizacionId,
        int $edicionId,
        int $operadorId,
        string $correlativo,
        bool $marcarIngreso
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.correlativo, r.contacto_nombre, r.estado, r.notas_operativas,
                   v.estado as venta_estado, v.estado_financiero
            FROM `reservas` r
            JOIN `ventas` v ON r.venta_id = v.id
            WHERE (r.correlativo = :corr OR r.id = :id_alt)
              AND r.organizacion_id = :org_id
              AND r.edicion_id = :ed_id
        ");
        $stmt->execute([
            'corr'   => $correlativo,
            'id_alt' => is_numeric($correlativo) ? (int) $correlativo : 0,
            'org_id' => $organizacionId,
            'ed_id'  => $edicionId,
        ]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_INEXISTENTE,
                'motivo'           => "No se encontró ninguna reserva con el código '{$correlativo}'.",
                'es_provisional'   => false,
            ];
        }

        if ($reserva['estado'] !== 'CONFIRMADA' || $reserva['venta_estado'] !== 'CONFIRMADA') {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_RECHAZADO_REVOCADO,
                'motivo'           => "La reserva se encuentra en estado {$reserva['estado']} (Venta: {$reserva['venta_estado']}).",
                'es_provisional'   => false,
            ];
        }

        $notas = (string) ($reserva['notas_operativas'] ?? '');
        if (str_contains($notas, '[CHECKIN_QR')) {
            return [
                'valido'           => false,
                'resultado_codigo' => OperacionOfflineEvento::RESULTADO_CONFLICTO_DUPLICADO,
                'motivo'           => 'Boleto ya canjeado previamente en puerta.',
                'es_provisional'   => false,
            ];
        }

        if ($marcarIngreso) {
            $timestamp = date('Y-m-d H:i:s');
            $nota = " [CHECKIN_QR_MANUAL_ONLINE: {$timestamp} por Operador #{$operadorId}]";
            $this->pdo->prepare("
                UPDATE `reservas`
                SET `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), :nota)
                WHERE `id` = :id
            ")->execute(['nota' => $nota, 'id' => (int) $reserva['id']]);
        }

        return [
            'valido'           => true,
            'resultado_codigo' => OperacionOfflineEvento::RESULTADO_CONFIRMADO,
            'motivo'           => 'Acceso validado y check-in confirmado correctamente.',
            'es_provisional'   => false,
            'datos'            => [
                'reserva_id'  => (int) $reserva['id'],
                'correlativo' => $reserva['correlativo'],
                'titular'     => $this->enmascararNombre((string) $reserva['contacto_nombre']),
            ],
        ];
    }

    private function extraerHashDeToken(int $organizacionId, string $tokenQr): string
    {
        if (str_contains($tokenQr, '.')) {
            [$payloadB64] = explode('.', $tokenQr, 2);
            $json = base64_decode(strtr($payloadB64, '-_', '+/'));
            if ($json) {
                $d = json_decode($json, true);
                if (is_array($d) && !empty($d['correlativo'])) {
                    return hash('sha256', 'CORR:' . $organizacionId . ':' . $d['correlativo']);
                }
            }
        }

        return hash('sha256', 'CORR:' . $organizacionId . ':' . $tokenQr);
    }

    private function enmascararNombre(string $nombre): string
    {
        $palabras = explode(' ', trim($nombre));
        $enmascaradas = [];
        foreach ($palabras as $p) {
            $len = mb_strlen($p, 'UTF-8');
            if ($len <= 2) {
                $enmascaradas[] = $p;
            } else {
                $primera = mb_substr($p, 0, 1, 'UTF-8');
                $enmascaradas[] = $primera . str_repeat('*', min(4, $len - 1));
            }
        }
        return implode(' ', $enmascaradas);
    }
}

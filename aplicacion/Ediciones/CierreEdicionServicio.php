<?php

declare(strict_types=1);

namespace Aplicacion\Ediciones;

use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Servicio de Dominio Oficial para el Cierre Administrativo y Financiero de Ediciones (Fase 2.12A).
 * 
 * Gobierna:
 * - Diagnóstico exhaustivo previo (Ventas, Finanzas, Pagos, Vouchers, Bloqueos, Operaciones y Postproducción).
 * - Clasificación estricta de incidencias: BLOQUEANTES (impiden cierre) vs OBSERVACIONES (permiten cierre condicionado).
 * - Conciliación financiera real sin inventar gastos ni forzar saldo cero universal.
 * - Congelamiento inmutable con hash SHA-256 criptográfico de auditoría.
 * - Procedimiento excepcional de reapertura/reversibilidad auditada.
 * - Bloqueo de operaciones comerciales en ediciones CERRADAS preservando acceso histórico.
 */
class CierreEdicionServicio
{
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        private ?AuditoriaRepositorio $auditoriaRepo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Genera el diagnóstico y balance integral previo al cierre de una festividad.
     */
    public function generarDiagnostico(int $organizacionId, int $edicionId): array
    {
        $edicion = $this->obtenerEdicion($organizacionId, $edicionId);
        if (!$edicion) {
            throw new InvalidArgumentException("La edición #{$edicionId} no existe o no pertenece a su organización.");
        }

        $bloqueantes = [];
        $observaciones = [];

        // 1. VENTAS Y COBRANZAS DE LA EDICIÓN
        $stmtVentas = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN 1 ELSE 0 END), 0) as ventas_activas,
                COALESCE(SUM(CASE WHEN estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) as ventas_canceladas,
                COALESCE(SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END), 0) as ventas_anuladas,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN total ELSE 0 END), 0.00) as monto_facturado_activo,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN monto_pagado ELSE 0 END), 0.00) as monto_pagado_activo,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') THEN saldo_pendiente ELSE 0 END), 0.00) as saldo_por_cobrar_activo,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'PAGADA_TOTAL' THEN 1 ELSE 0 END), 0) as ventas_pagadas_total,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'PAGO_PARCIAL' THEN 1 ELSE 0 END), 0) as ventas_pago_parcial,
                COALESCE(SUM(CASE WHEN estado IN ('CONFIRMADA', 'LIQUIDADA') AND estado_financiero = 'NO_PAGADA' THEN 1 ELSE 0 END), 0) as ventas_no_pagadas
            FROM `ventas`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtVentas->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC) ?: [];

        $montoFacturado = (float) ($resVentas['monto_facturado_activo'] ?? 0.00);
        $montoPagado = (float) ($resVentas['monto_pagado_activo'] ?? 0.00);
        $saldoPorCobrar = (float) ($resVentas['saldo_por_cobrar_activo'] ?? 0.00);
        $ventasPagoParcial = (int) ($resVentas['ventas_pago_parcial'] ?? 0);
        $ventasNoPagadas = (int) ($resVentas['ventas_no_pagadas'] ?? 0);

        // Si hay cuentas por cobrar pendientes en ventas activas -> OBSERVACIÓN (permite condicionado con justificación)
        if ($saldoPorCobrar > 0.01) {
            $observaciones[] = [
                'codigo'   => 'CUENTAS_POR_COBRAR_PENDIENTES',
                'tipo'     => 'OBSERVACION',
                'titulo'   => 'Cuentas por Cobrar Pendientes de Liquidación',
                'mensaje'  => sprintf(
                    'Existen %d venta(s) con saldo pendiente (S/ %s por cobrar). Requiere autorización formal y cronograma de recupero.',
                    ($ventasPagoParcial + $ventasNoPagadas),
                    number_format($saldoPorCobrar, 2)
                ),
                'detalles' => [
                    'saldo_pendiente_total' => $saldoPorCobrar,
                    'ventas_pago_parcial'   => $ventasPagoParcial,
                    'ventas_no_pagadas'     => $ventasNoPagadas,
                ],
            ];
        }

        // 2. PAGOS, VOUCHERS Y TESORERÍA
        $stmtPagos = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_pagos,
                COALESCE(SUM(CASE WHEN estado = 'PENDIENTE_VERIFICACION' THEN 1 ELSE 0 END), 0) as pagos_pendientes,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN 1 ELSE 0 END), 0) as pagos_aprobados,
                COALESCE(SUM(CASE WHEN estado = 'RECHAZADO' THEN 1 ELSE 0 END), 0) as pagos_rechazados,
                COALESCE(SUM(CASE WHEN estado = 'ANULADO' THEN 1 ELSE 0 END), 0) as pagos_anulados,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN monto_neto_recibido ELSE 0 END), 0.00) as total_neto_recaudado,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN comision_pasarela ELSE 0 END), 0.00) as total_comisiones_pasarela,
                COALESCE(SUM(CASE WHEN estado = 'APROBADO' THEN monto_reembolsado_acumulado ELSE 0 END), 0.00) as total_reembolsos
            FROM `pagos`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtPagos->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resPagos = $stmtPagos->fetch(PDO::FETCH_ASSOC) ?: [];

        $pagosPendientes = (int) ($resPagos['pagos_pendientes'] ?? 0);
        $totalRecaudado = (float) ($resPagos['total_neto_recaudado'] ?? 0.00);

        // Vouchers o pagos sin verificar -> BLOQUEANTE CRÍTICA
        if ($pagosPendientes > 0) {
            $bloqueantes[] = [
                'codigo'   => 'PAGOS_PENDIENTES_VERIFICACION',
                'tipo'     => 'BLOQUEANTE',
                'titulo'   => 'Vouchers o Pagos Pendientes de Dictamen',
                'mensaje'  => "Existen {$pagosPendientes} pago(s) en estado PENDIENTE_VERIFICACION. Deben ser aprobados o rechazados formalmente antes de asentar el cierre.",
                'detalles' => ['cantidad_pendientes' => $pagosPendientes],
            ];
        }

        // Desglose por canal de recaudación
        $stmtCanales = $this->pdo->prepare("
            SELECT metodo_pago, COUNT(*) as cantidad, COALESCE(SUM(monto_neto_recibido), 0.00) as total
            FROM `pagos`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id AND `estado` = 'APROBADO'
            GROUP BY metodo_pago
        ");
        $stmtCanales->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $desgloseCanales = $stmtCanales->fetchAll(PDO::FETCH_ASSOC);

        // 3. RECIBOS INTERNOS DE CAJA EMITIDOS
        $stmtRecibos = $this->pdo->prepare("
            SELECT COUNT(r.id) as total_recibos, COALESCE(SUM(r.monto_recibido), 0.00) as total_recibos_monto
            FROM `recibos_caja` r
            JOIN `ventas` v ON r.venta_id = v.id
            WHERE r.`organizacion_id` = :org_id AND v.`edicion_id` = :ed_id
        ");
        $stmtRecibos->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resRecibos = $stmtRecibos->fetch(PDO::FETCH_ASSOC) ?: [];

        // 4. BLOQUEOS TEMPORALES DE CUPOS (TTL EN CHECKOUT)
        $stmtBloqueos = $this->pdo->prepare("
            SELECT COUNT(*) as bloqueos_activos, COALESCE(SUM(cupos_bloqueados), 0) as cupos_retenidos
            FROM `reserva_bloqueos_temporales`
            WHERE `organizacion_id` = :org_id 
              AND `edicion_id` = :ed_id 
              AND `estado` IN ('ACTIVO', 'RETENCION_VOUCHER')
              AND `expira_en` > NOW()
        ");
        $stmtBloqueos->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resBloqueos = $stmtBloqueos->fetch(PDO::FETCH_ASSOC) ?: [];
        $bloqueosActivos = (int) ($resBloqueos['bloqueos_activos'] ?? 0);

        if ($bloqueosActivos > 0) {
            $bloqueantes[] = [
                'codigo'   => 'BLOQUEOS_CHECKOUT_VIGENTES',
                'tipo'     => 'BLOQUEANTE',
                'titulo'   => 'Bloqueos Temporales de Cupo Vigentes en Checkout',
                'mensaje'  => "Existen {$bloqueosActivos} bloqueo(s) temporal(es) con cupos retenidos en proceso de compra. Deben expirar o ser liberados antes del cierre.",
                'detalles' => ['bloqueos_activos' => $bloqueosActivos, 'cupos_retenidos' => (int) $resBloqueos['cupos_retenidos']],
            ];
        }

        // 5. OPERACIONES DE CAMPO Y SALIDAS
        $stmtSalidas = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_salidas,
                COALESCE(SUM(CASE WHEN estado IN ('PROGRAMADA', 'EN_CHECKIN', 'DESPACHADA') THEN 1 ELSE 0 END), 0) as salidas_en_curso,
                COALESCE(SUM(CASE WHEN estado = 'FINALIZADA' THEN 1 ELSE 0 END), 0) as salidas_finalizadas,
                COALESCE(SUM(CASE WHEN estado = 'CANCELADA' THEN 1 ELSE 0 END), 0) as salidas_canceladas
            FROM `operacion_salidas`
            WHERE `organizacion_id` = :org_id AND `edicion_id` = :ed_id
        ");
        $stmtSalidas->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resSalidas = $stmtSalidas->fetch(PDO::FETCH_ASSOC) ?: [];
        $salidasEnCurso = (int) ($resSalidas['salidas_en_curso'] ?? 0);

        if ($salidasEnCurso > 0) {
            $bloqueantes[] = [
                'codigo'   => 'SALIDAS_OPERATIVAS_EN_CURSO',
                'tipo'     => 'BLOQUEANTE',
                'titulo'   => 'Salidas de Campo no Concluidas o en Tránsito',
                'mensaje'  => "Existen {$salidasEnCurso} salida(s) operativa(s) en estado PROGRAMADA, EN_CHECKIN o DESPACHADA. Deben ser concluidas (FINALIZADA) o canceladas formalmente.",
                'detalles' => ['salidas_activas' => $salidasEnCurso],
            ];
        }

        // 6. POSTPRODUCCIÓN Y ENTREGAS AUDIOVISUALES
        $stmtEntregas = $this->pdo->prepare("
            SELECT 
                COUNT(e.id) as total_entregas,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'APROBADA_CLIENTE' THEN 1 ELSE 0 END), 0) as entregas_aprobadas,
                COALESCE(SUM(CASE WHEN e.estado_entrega IN ('PREPARADA', 'PUBLICADA') THEN 1 ELSE 0 END), 0) as entregas_pendientes,
                COALESCE(SUM(CASE WHEN e.estado_entrega = 'OBSERVADA_CLIENTE' THEN 1 ELSE 0 END), 0) as entregas_observadas
            FROM `postproduccion_entregas` e
            JOIN `postproduccion_proyectos` p ON e.proyecto_id = p.id
            WHERE e.organizacion_id = :org_id AND p.edicion_id = :ed_id
        ");
        $stmtEntregas->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $resEntregas = $stmtEntregas->fetch(PDO::FETCH_ASSOC) ?: [];
        $entregasPendientes = (int) ($resEntregas['entregas_pendientes'] ?? 0);
        $entregasObservadas = (int) ($resEntregas['entregas_observadas'] ?? 0);

        if (($entregasPendientes + $entregasObservadas) > 0) {
            $observaciones[] = [
                'codigo'   => 'ENTREGAS_POSTPRODUCCION_PENDIENTES',
                'tipo'     => 'OBSERVACION',
                'titulo'   => 'Entregas Multimedia en Proceso u Observadas',
                'mensaje'  => sprintf(
                    'Existen %d entrega(s) de fotos/videos pendientes o con observaciones de clientes. El cierre condicionado permite continuar la atención post-cierre.',
                    ($entregasPendientes + $entregasObservadas)
                ),
                'detalles' => [
                    'pendientes' => $entregasPendientes,
                    'observadas' => $entregasObservadas,
                ],
            ];
        }

        // Snapshot financiero consolidado (congelamiento de cifras reales)
        $resumenFinanciero = [
            'edicion_id'                => $edicionId,
            'organizacion_id'           => $organizacionId,
            'fecha_corte'               => date('Y-m-d H:i:s'),
            'ventas_totales'            => (int) ($resVentas['total_ventas'] ?? 0),
            'ventas_activas'            => (int) ($resVentas['ventas_activas'] ?? 0),
            'ventas_canceladas'         => (int) ($resVentas['ventas_canceladas'] ?? 0),
            'monto_facturado'           => $montoFacturado,
            'monto_recaudado'           => $totalRecaudado,
            'saldo_por_cobrar'          => $saldoPorCobrar,
            'reembolsos_emitidos'       => (float) ($resPagos['total_reembolsos'] ?? 0.00),
            'comisiones_pasarela'       => (float) ($resPagos['total_comisiones_pasarela'] ?? 0.00),
            'recibos_caja_emitidos'     => (int) ($resRecibos['total_recibos'] ?? 0),
            'recibos_caja_monto'        => (float) ($resRecibos['total_recibos_monto'] ?? 0.00),
            'desglose_canales_pago'     => $desgloseCanales,
            'salidas_finalizadas'       => (int) ($resSalidas['salidas_finalizadas'] ?? 0),
            'entregas_aprobadas'        => (int) ($resEntregas['entregas_aprobadas'] ?? 0),
        ];

        $aptoCierreNormal = (count($bloqueantes) === 0 && count($observaciones) === 0);
        $aptoCierreCondicionado = (count($bloqueantes) === 0 && count($observaciones) > 0);

        return [
            'edicion'                  => $edicion,
            'es_apto_cierre_normal'    => $aptoCierreNormal,
            'es_apto_condicionado'     => $aptoCierreCondicionado,
            'total_bloqueantes'        => count($bloqueantes),
            'total_observaciones'      => count($observaciones),
            'bloqueantes'              => $bloqueantes,
            'observaciones'            => $observaciones,
            'resumen_financiero'       => $resumenFinanciero,
            'cierre_vigente'           => $this->obtenerCierreVigente($organizacionId, $edicionId),
        ];
    }

    /**
     * Calcula el hash criptográfico inmutable sellado con HMAC del servidor.
     */
    public function calcularHashSellado(
        array|string $snapshot,
        string $fechaCierre,
        int $edicionId,
        int $organizacionId
    ): string {
        $data = is_array($snapshot) ? $snapshot : (json_decode((string)$snapshot, true) ?: []);
        $this->ordenarArregloRecursivo($data);
        $canonicalJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $claveSecreta = (string) (getenv('APP_CLAVE') ?: 'local_dev_secret_key_candelaria_2026_f01');
        return hash_hmac('sha256', $canonicalJson . '|' . $fechaCierre . '|' . $edicionId . '|' . $organizacionId, $claveSecreta);
    }

    private function ordenarArregloRecursivo(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as &$val) {
            if (is_array($val)) {
                $this->ordenarArregloRecursivo($val);
            }
        }
    }

    /**
     * Verifica la integridad histórica del acta y snapshot de un cierre contrastando su sellado criptográfico.
     */
    public function verificarIntegridadCierre(int $organizacionId, int $cierreId): array
    {
        $cierre = $this->obtenerCierrePorId($organizacionId, $cierreId);
        if (!$cierre) {
            throw new InvalidArgumentException("El cierre #{$cierreId} no existe en su organización.");
        }

        $snapshot = $cierre['resumen_financiero'] ?? $cierre['resumen_financiero_json'];
        $fechaCierre = (string) $cierre['fecha_cierre'];
        $edicionId = (int) $cierre['edicion_id'];
        $hashAlmacenado = (string) $cierre['hash_integridad_cierre'];

        $hashCalculado = $this->calcularHashSellado($snapshot, $fechaCierre, $edicionId, $organizacionId);
        $rawJson = is_string($snapshot) ? $snapshot : json_encode($snapshot);
        $hashPlanoLegacy = hash('sha256', (string)$rawJson . $fechaCierre . (string)$edicionId . (string)$organizacionId);

        $esIntegro = hash_equals($hashAlmacenado, $hashCalculado) || hash_equals($hashAlmacenado, $hashPlanoLegacy);

        if (!$esIntegro) {
            error_log(sprintf('[ALERTA_INTEGRIDAD_CIERRE] Posible adulteración detectada en Cierre #%d Org #%d', $cierreId, $organizacionId));
        }

        return [
            'cierre_id'       => $cierreId,
            'organizacion_id' => $organizacionId,
            'edicion_id'      => $edicionId,
            'es_integro'      => $esIntegro,
            'hash_almacenado' => $hashAlmacenado,
            'hash_calculado'  => $hashCalculado,
            'tipo_sellado'    => hash_equals($hashAlmacenado, $hashCalculado) ? 'HMAC_SHA256' : (hash_equals($hashAlmacenado, $hashPlanoLegacy) ? 'SHA256_LEGACY' : 'ALTERADO'),
            'verificado_en'   => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Ejecuta formalmente el cierre administrativo y financiero de la edición de forma atómica y consistente.
     */
    public function ejecutarCierre(
        int $organizacionId,
        int $edicionId,
        int $usuarioId,
        string $tipoCierre,
        ?string $justificacion = null,
        ?int $autorizadorId = null,
        int $versionBloqueoEsperada = 1,
        ?ContextoOperacion $contexto = null
    ): array {
        $tipoCierre = strtoupper(trim($tipoCierre));
        if (!in_array($tipoCierre, ['DEFINITIVO', 'CONDICIONADO'], true)) {
            throw new InvalidArgumentException("Tipo de cierre inválido: '{$tipoCierre}'. Debe ser DEFINITIVO o CONDICIONADO.");
        }

        if ($tipoCierre === 'CONDICIONADO') {
            $justificacionLimpia = trim((string) $justificacion);
            if (strlen($justificacionLimpia) < 15) {
                throw new InvalidArgumentException("El cierre condicionado requiere una justificación formal detallada (mínimo 15 caracteres).");
            }
            if ($autorizadorId === null || $autorizadorId <= 0) {
                throw new InvalidArgumentException("El cierre condicionado requiere obligatoriamente designar un usuario autorizador con facultades superiores.");
            }
            // P0: Principio de segregación de funciones (cuatro ojos)
            if ($usuarioId === $autorizadorId) {
                throw new InvalidArgumentException("En Cierre Condicionado, el usuario ejecutor y el autorizador formal deben ser identidades distintas (principio de cuatro ojos).");
            }
            // Comprobar que el usuario autorizador existe, pertenece al tenant y tiene RBAC
            $stmtAut = $this->pdo->prepare("SELECT id, estado, es_superadmin_plataforma FROM `usuarios` WHERE id = :id AND organizacion_id = :org_id");
            $stmtAut->execute(['id' => $autorizadorId, 'org_id' => $organizacionId]);
            $autRow = $stmtAut->fetch(PDO::FETCH_ASSOC);
            if (!$autRow || $autRow['estado'] !== 'ACTIVO') {
                throw new InvalidArgumentException("El usuario autorizador #{$autorizadorId} no existe, está inactivo o no pertenece a la organización.");
            }
            $rolRepo = new RolRepositorio($this->pdo);
            $permisoRepo = new PermisoRepositorio($this->pdo);
            $esSuperAut = (bool) ($autRow['es_superadmin_plataforma'] ?? false);
            $tieneFacultad = $esSuperAut
                || $rolRepo->usuarioTieneRol($autorizadorId, 'admin_organizacion')
                || $rolRepo->usuarioTieneRol($autorizadorId, 'superadmin_plataforma')
                || $permisoRepo->usuarioTienePermiso($autorizadorId, 'ediciones.cerrar');
            if (!$tieneFacultad) {
                throw new AccesoDenegadoExcepcion("El usuario autorizador #{$autorizadorId} carece de facultades RBAC para autorizar cierres condicionados.");
            }
        }

        // P0: Transaccionalidad estricta y Bloqueo Pesimista (FOR UPDATE)
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            // Bloqueo pesimista inmediato de la fila de la edición
            $stmtLock = $this->pdo->prepare("
                SELECT id, estado 
                FROM `ediciones_candelaria` 
                WHERE id = :id AND organizacion_id = :org_id 
                FOR UPDATE
            ");
            $stmtLock->execute(['id' => $edicionId, 'org_id' => $organizacionId]);
            $edicionBloqueada = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$edicionBloqueada) {
                throw new InvalidArgumentException("La edición #{$edicionId} no existe en la organización.");
            }

            if ($edicionBloqueada['estado'] === 'CERRADA') {
                throw new ConflictoConcurrenciaExcepcion("La edición #{$edicionId} ya se encuentra formalmente CERRADA.");
            }

            // Control de concurrencia sobre cierre vigente bajo el lock pesimista
            $stmtCierreVig = $this->pdo->prepare("
                SELECT id FROM `edicion_cierres` 
                WHERE organizacion_id = :org_id AND edicion_id = :ed_id AND estado = 'VIGENTE' 
                LIMIT 1 FOR UPDATE
            ");
            $stmtCierreVig->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
            if ($stmtCierreVig->fetchColumn()) {
                throw new ConflictoConcurrenciaExcepcion("La edición ya cuenta con un cierre vigente registrado concurrentemente.");
            }

            // Diagnóstico y balance consistentes calculados dentro de la transacción
            $diag = $this->generarDiagnostico($organizacionId, $edicionId);

            if (!empty($diag['bloqueantes'])) {
                $motivos = array_column($diag['bloqueantes'], 'mensaje');
                throw new InvalidArgumentException(
                    "No es posible asentar el cierre. Existen incidencias bloqueantes críticas:\n- " . implode("\n- ", $motivos)
                );
            }

            if ($tipoCierre === 'DEFINITIVO' && !empty($diag['observaciones'])) {
                throw new InvalidArgumentException(
                    "No es posible asentar Cierre DEFINITIVO existiendo observaciones de cobranzas o postproducción pendientes. " .
                    "Debe seleccionar Cierre CONDICIONADO registrando justificación formal y usuario autorizador."
                );
            }

            // Generar hash de integridad sellado criptográficamente con HMAC del servidor
            $fechaCierre = date('Y-m-d H:i:s');
            $hashIntegridad = $this->calcularHashSellado($diag['resumen_financiero'], $fechaCierre, $edicionId, $organizacionId);
            $snapshotJson = json_encode($diag['resumen_financiero'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $incidenciasJson = json_encode(array_merge($diag['bloqueantes'], $diag['observaciones']), JSON_UNESCAPED_UNICODE);

            // 1. Asentar registro en edicion_cierres
            $stmtIns = $this->pdo->prepare("
                INSERT INTO `edicion_cierres` (
                    `organizacion_id`, `edicion_id`, `tipo_cierre`, `estado`,
                    `usuario_cierre_id`, `fecha_cierre`, `resumen_financiero_json`,
                    `diagnostico_incidencias_json`, `justificacion_condicionado`,
                    `autorizado_por_usuario_id`, `hash_integridad_cierre`, `version_bloqueo`
                ) VALUES (
                    :org_id, :ed_id, :tipo, 'VIGENTE',
                    :usr_id, :fecha, :resumen,
                    :incidencias, :just,
                    :aut_id, :hash, 1
                )
            ");
            $stmtIns->execute([
                'org_id'      => $organizacionId,
                'ed_id'       => $edicionId,
                'tipo'        => $tipoCierre,
                'usr_id'      => $usuarioId,
                'fecha'       => $fechaCierre,
                'resumen'     => $snapshotJson,
                'incidencias' => $incidenciasJson,
                'just'        => $tipoCierre === 'CONDICIONADO' ? trim((string)$justificacion) : null,
                'aut_id'      => $tipoCierre === 'CONDICIONADO' ? $autorizadorId : null,
                'hash'        => $hashIntegridad,
            ]);

            $cierreId = (int) $this->pdo->lastInsertId();

            // 2. Transicionar la edición a CERRADA con incremento de version_bloqueo
            $stmtEd = $this->pdo->prepare("
                UPDATE `ediciones_candelaria`
                SET `estado` = 'CERRADA',
                    `actualizado_en` = CURRENT_TIMESTAMP
                WHERE `id` = :id AND `organizacion_id` = :org_id
            ");
            $stmtEd->execute(['id' => $edicionId, 'org_id' => $organizacionId]);

            // 3. Registrar en bitácora de auditoría
            if ($this->auditoriaRepo !== null && $contexto !== null) {
                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'ediciones',
                    accion: 'CIERRE_EDICION_ADMINISTRATIVO_FINANCIERO',
                    entidadTipo: 'EDICION_CIERRE',
                    entidadId: (string) $cierreId,
                    datosPrevios: ['estado_edicion' => $edicionBloqueada['estado']],
                    datosNuevos: [
                        'cierre_id'       => $cierreId,
                        'tipo_cierre'     => $tipoCierre,
                        'hash_integridad' => $hashIntegridad,
                        'estado_edicion'  => 'CERRADA'
                    ]
                );
            }

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->obtenerCierrePorId($organizacionId, $cierreId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Procedimiento excepcional de reversibilidad / reapertura auditada de una edición cerrada.
     * Aplica validación de RBAC real del autorizador, segregación de funciones y aprobación verificada.
     */
    public function reabrirEdicionExcepcional(
        int $organizacionId,
        int $edicionId,
        int $usuarioSolicitanteId,
        int $usuarioAutorizadorId,
        string $motivoExcepcional,
        ?string $claveConfirmacionAutorizador = null,
        string $nuevoEstado = 'POSTPRODUCCION_ENTREGA',
        ?ContextoOperacion $contexto = null
    ): array {
        $motivoLimpio = trim($motivoExcepcional);
        if (strlen($motivoLimpio) < 20) {
            throw new InvalidArgumentException("La reapertura excepcional requiere un motivo debidamente fundamentado (mínimo 20 caracteres).");
        }

        // P0: Regla de Cuatro Ojos — Solicitante y Autorizador deben ser identidades distintas
        if ($usuarioSolicitanteId === $usuarioAutorizadorId) {
            throw new InvalidArgumentException("El usuario solicitante y el usuario autorizador deben ser identidades distintas (principio de segregación de funciones).");
        }

        // P0: Validar que el solicitante existe, está activo y pertenece a la organización
        $stmtSol = $this->pdo->prepare("SELECT id, estado FROM `usuarios` WHERE id = :id AND organizacion_id = :org_id");
        $stmtSol->execute(['id' => $usuarioSolicitanteId, 'org_id' => $organizacionId]);
        $solicitante = $stmtSol->fetch(PDO::FETCH_ASSOC);
        if (!$solicitante || $solicitante['estado'] !== 'ACTIVO') {
            throw new InvalidArgumentException("El usuario solicitante #{$usuarioSolicitanteId} no existe, está inactivo o no pertenece a la organización.");
        }

        // P0: Validar que el autorizador existe, está activo y pertenece a la organización
        $stmtAut = $this->pdo->prepare("SELECT id, contrasena_hash, estado, es_superadmin_plataforma FROM `usuarios` WHERE id = :id AND organizacion_id = :org_id");
        $stmtAut->execute(['id' => $usuarioAutorizadorId, 'org_id' => $organizacionId]);
        $autorizador = $stmtAut->fetch(PDO::FETCH_ASSOC);
        if (!$autorizador || $autorizador['estado'] !== 'ACTIVO') {
            throw new InvalidArgumentException("El usuario autorizador de reapertura #{$usuarioAutorizadorId} no existe, está inactivo o no pertenece a la organización.");
        }

        // P0: Validar facultades efectivas de RBAC real (ediciones.reabrir_excepcional o rol administrador)
        $permisoRepo = new PermisoRepositorio($this->pdo);
        $rolRepo = new RolRepositorio($this->pdo);
        $esSuper = (bool) ($autorizador['es_superadmin_plataforma'] ?? false);
        $tienePermiso = $esSuper 
            || $rolRepo->usuarioTieneRol($usuarioAutorizadorId, 'admin_organizacion')
            || $rolRepo->usuarioTieneRol($usuarioAutorizadorId, 'superadmin_plataforma')
            || $permisoRepo->usuarioTienePermiso($usuarioAutorizadorId, 'ediciones.reabrir_excepcional');

        if (!$tienePermiso) {
            throw new AccesoDenegadoExcepcion("El usuario autorizador #{$usuarioAutorizadorId} carece de facultades RBAC para autorizar reaperturas (requiere permiso ediciones.reabrir_excepcional o rol administrador).");
        }

        // P0: Aprobación expresa — Se exige confirmación de credencial del usuario autorizador
        if ($claveConfirmacionAutorizador === null || trim($claveConfirmacionAutorizador) === '') {
            throw new InvalidArgumentException("La reapertura excepcional requiere obligatoriamente la confirmación de la credencial/clave del usuario autorizador.");
        }
        if (!password_verify($claveConfirmacionAutorizador, (string)$autorizador['contrasena_hash'])) {
            throw new AccesoDenegadoExcepcion("Clave de confirmación incorrecta para el usuario autorizador #{$usuarioAutorizadorId}.");
        }

        $cierreVigente = $this->obtenerCierreVigente($organizacionId, $edicionId);
        if ($cierreVigente === null) {
            throw new InvalidArgumentException("La edición #{$edicionId} no cuenta con un cierre vigente para reapertura.");
        }

        $nuevoEstado = in_array($nuevoEstado, ['OPERACION', 'POSTPRODUCCION_ENTREGA'], true) ? $nuevoEstado : 'POSTPRODUCCION_ENTREGA';

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $cierreId = (int) $cierreVigente['id'];

            // 1. Transicionar cierre a REABIERTO_EXCEPCIONAL
            $stmtCierre = $this->pdo->prepare("
                UPDATE `edicion_cierres`
                SET `estado` = 'REABIERTO_EXCEPCIONAL',
                    `version_bloqueo` = `version_bloqueo` + 1,
                    `actualizado_en` = CURRENT_TIMESTAMP
                WHERE `id` = :id AND `organizacion_id` = :org_id
            ");
            $stmtCierre->execute(['id' => $cierreId, 'org_id' => $organizacionId]);

            // 2. Insertar bitácora inmutable en edicion_cierre_reaperturas
            $stmtReap = $this->pdo->prepare("
                INSERT INTO `edicion_cierre_reaperturas` (
                    `cierre_id`, `organizacion_id`, `edicion_id`,
                    `solicitado_por_usuario_id`, `autorizado_por_usuario_id`,
                    `motivo_excepcional`, `estado_anterior_edicion`,
                    `nuevo_estado_edicion`, `hash_integridad_previo`
                ) VALUES (
                    :cierre_id, :org_id, :ed_id,
                    :sol_id, :aut_id,
                    :motivo, 'CERRADA',
                    :nuevo_estado, :hash_prev
                )
            ");
            $stmtReap->execute([
                'cierre_id'    => $cierreId,
                'org_id'       => $organizacionId,
                'ed_id'        => $edicionId,
                'sol_id'       => $usuarioSolicitanteId,
                'aut_id'       => $usuarioAutorizadorId,
                'motivo'       => $motivoLimpio,
                'nuevo_estado' => $nuevoEstado,
                'hash_prev'    => $cierreVigente['hash_integridad_cierre'],
            ]);

            $reaperturaId = (int) $this->pdo->lastInsertId();

            // 3. Restaurar estado de la edición
            $stmtEd = $this->pdo->prepare("
                UPDATE `ediciones_candelaria`
                SET `estado` = :nuevo_estado,
                    `actualizado_en` = CURRENT_TIMESTAMP
                WHERE `id` = :id AND `organizacion_id` = :org_id
            ");
            $stmtEd->execute([
                'nuevo_estado' => $nuevoEstado,
                'id'           => $edicionId,
                'org_id'       => $organizacionId,
            ]);

            // 4. Auditoría
            if ($this->auditoriaRepo !== null && $contexto !== null) {
                $this->auditoriaRepo->registrar(
                    contexto: $contexto,
                    modulo: 'ediciones',
                    accion: 'REAPERTURA_EXCEPCIONAL_EDICION',
                    entidadTipo: 'EDICION_CIERRE',
                    entidadId: (string) $cierreId,
                    datosPrevios: ['estado_edicion' => 'CERRADA', 'cierre_id' => $cierreId],
                    datosNuevos: [
                        'reapertura_id' => $reaperturaId,
                        'nuevo_estado'  => $nuevoEstado,
                        'motivo'        => $motivoLimpio,
                        'autorizado_por'=> $usuarioAutorizadorId
                    ]
                );
            }

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return [
                'reapertura_id' => $reaperturaId,
                'cierre_id'     => $cierreId,
                'edicion_id'    => $edicionId,
                'nuevo_estado'  => $nuevoEstado,
                'fecha'         => date('Y-m-d H:i:s'),
                'mensaje'       => 'Festividad reabierta formalmente para regularizaciones autorizadas.',
            ];
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Guardián operativo: Comprueba que una edición admita nuevas operaciones comerciales o de campo.
     * Si la edición está en estado 'CERRADA', lanza excepción de bloqueo.
     *
     * @throws InvalidArgumentException
     */
    public function validarEdicionAbiertaParaOperaciones(int $organizacionId, int $edicionId): void
    {
        $edicion = $this->obtenerEdicion($organizacionId, $edicionId);
        if ($edicion !== null && $edicion['estado'] === 'CERRADA') {
            throw new InvalidArgumentException(
                "La festividad '{$edicion['nombre']}' se encuentra formalmente CERRADA. Se prohíbe el registro o modificación de operaciones comerciales, reservas o servicios."
            );
        }
    }

    public function obtenerEdicion(int $organizacionId, int $edicionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $edicionId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerCierreVigente(int $organizacionId, int $edicionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   u.nombre_completo as usuario_cierre_nombre,
                   u.nombre_completo as usuario_cierre_nombres,
                   '' as usuario_cierre_apellidos,
                   aut.nombre_completo as autorizador_nombre,
                   aut.nombre_completo as autorizador_nombres,
                   '' as autorizador_apellidos
            FROM `edicion_cierres` c
            JOIN `usuarios` u ON c.usuario_cierre_id = u.id
            LEFT JOIN `usuarios` aut ON c.autorizado_por_usuario_id = aut.id
            WHERE c.organizacion_id = :org_id AND c.edicion_id = :ed_id AND c.estado = 'VIGENTE'
            ORDER BY c.id DESC
            LIMIT 1
        ");
        $stmt->execute(['org_id' => $organizacionId, 'ed_id' => $edicionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $res['resumen_financiero'] = json_decode((string)$res['resumen_financiero_json'], true) ?: [];
            $res['diagnostico_incidencias'] = json_decode((string)$res['diagnostico_incidencias_json'], true) ?: [];
        }
        return $res ?: null;
    }

    public function obtenerCierrePorId(int $organizacionId, int $cierreId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   u.nombre_completo as usuario_cierre_nombre,
                   u.nombre_completo as usuario_cierre_nombres,
                   '' as usuario_cierre_apellidos,
                   aut.nombre_completo as autorizador_nombre,
                   aut.nombre_completo as autorizador_nombres,
                   '' as autorizador_apellidos
            FROM `edicion_cierres` c
            JOIN `usuarios` u ON c.usuario_cierre_id = u.id
            LEFT JOIN `usuarios` aut ON c.autorizado_por_usuario_id = aut.id
            WHERE c.id = :id AND c.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $cierreId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $res['resumen_financiero'] = json_decode((string)$res['resumen_financiero_json'], true) ?: [];
            $res['diagnostico_incidencias'] = json_decode((string)$res['diagnostico_incidencias_json'], true) ?: [];
        }
        return $res ?: null;
    }
}

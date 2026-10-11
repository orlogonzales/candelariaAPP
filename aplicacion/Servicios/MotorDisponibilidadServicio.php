<?php

declare(strict_types=1);

namespace Aplicacion\Servicios;

use Aplicacion\Entidades\ReservaBloqueoTemporal;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Reservas\EstadoReserva;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use PDO;
use RuntimeException;

class MotorDisponibilidadServicio
{
    private PDO $pdo;

    public function __construct(
        private ReservaBloqueoTemporalRepositorio $bloqueoRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Calcula la disponibilidad real en cupos para un ítem comercial en una fecha dada.
     */
    public function calcularDisponibilidadItem(
        int $organizacionId,
        int $edicionId,
        int $itemComercialId,
        ?string $fechaServicio = null,
        ?int $salidaId = null
    ): int {
        // 1. Consultar configuración operativa del ítem
        $stmtCfg = $this->pdo->prepare("
            SELECT ico.*, ic.tipo, ic.nombre
            FROM `items_comerciales` ic
            LEFT JOIN `item_configuracion_operativa` ico ON ico.item_comercial_id = ic.id
            WHERE ic.id = :item_id AND ic.organizacion_id = :org_id
        ");
        $stmtCfg->execute(['item_id' => $itemComercialId, 'org_id' => $organizacionId]);
        $cfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            return 0;
        }

        // Si es producto tangible o servicio sin control de aforo
        if (($cfg['tipo'] ?? '') === 'PRODUCTO' || ($cfg['tipo_capacidad'] ?? 'SIN_CONTROL') === 'SIN_CONTROL') {
            return 9999;
        }

        // 2. Si se especifica salida operativa puntual
        if ($salidaId !== null) {
            return $this->calcularDisponibilidadSalida($organizacionId, $salidaId);
        }

        // 3. Si se especifica fecha de servicio, buscar salidas programadas
        if ($fechaServicio !== null) {
            $stmtSalidas = $this->pdo->prepare("
                SELECT id FROM `operacion_salidas`
                WHERE `organizacion_id` = :org_id
                  AND `edicion_id` = :edicion_id
                  AND `item_comercial_id` = :item_id
                  AND `fecha_salida` = :fecha
                  AND `estado` NOT IN ('CANCELADA', 'INTERRUMPIDA', 'FINALIZADA')
            ");
            $stmtSalidas->execute([
                'org_id'     => $organizacionId,
                'edicion_id' => $edicionId,
                'item_id'    => $itemComercialId,
                'fecha'      => $fechaServicio,
            ]);
            $salidas = $stmtSalidas->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($salidas)) {
                $totalDisp = 0;
                foreach ($salidas as $sId) {
                    $totalDisp += $this->calcularDisponibilidadSalida($organizacionId, (int) $sId);
                }
                return $totalDisp;
            }
        }

        // 4. Fallback a capacidad referencial de la oferta del ítem en la edición
        $stmtOferta = $this->pdo->prepare("
            SELECT `capacidad_referencial`
            FROM `ofertas_items_edicion`
            WHERE `organizacion_id` = :org_id
              AND `edicion_id` = :edicion_id
              AND `item_comercial_id` = :item_id
              AND `estado` = 'ACTIVO'
        ");
        $stmtOferta->execute([
            'org_id'     => $organizacionId,
            'edicion_id' => $edicionId,
            'item_id'    => $itemComercialId,
        ]);
        $capRef = $stmtOferta->fetchColumn();

        $capacidadBase = ($capRef !== false && $capRef !== null) ? (int) $capRef : 100;

        // Descontar prestaciones de todas las reservas formalmente activas (CONFIRMADA, REGISTRADA, PENDIENTE_DATOS)
        // Reservas CANCELADA se omiten para liberar aforo automáticamente.
        $stmtReservados = $this->pdo->prepare("
            SELECT COALESCE(SUM(rp.cantidad), 0)
            FROM `reserva_prestaciones` rp
            JOIN `reservas` r ON rp.reserva_id = r.id
            WHERE r.organizacion_id = :org_id
              AND r.edicion_id = :edicion_id
              AND rp.item_comercial_id = :item_id
              AND r.estado IN ('" . EstadoReserva::CONFIRMADA->value . "', '" . EstadoReserva::REGISTRADA->value . "', '" . EstadoReserva::PENDIENTE_DATOS->value . "')
        ");
        $stmtReservados->execute([
            'org_id'     => $organizacionId,
            'edicion_id' => $edicionId,
            'item_id'    => $itemComercialId,
        ]);
        $reservados = (int) $stmtReservados->fetchColumn();

        // Descontar bloqueos temporales en carrito previos a la formalización (estado ACTIVO)
        $bloqueados = $this->bloqueoRepo->sumarCuposBloqueadosActivos($organizacionId, $itemComercialId, null, $fechaServicio);

        $disponible = $capacidadBase - $reservados - $bloqueados;
        return max(0, $disponible);
    }

    /**
     * Calcula la disponibilidad específica de una salida operativa.
     */
    public function calcularDisponibilidadSalida(int $organizacionId, int $salidaId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT `capacidad_maxima`, `tipo_capacidad`, `item_comercial_id`
            FROM `operacion_salidas`
            WHERE `id` = :salida_id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['salida_id' => $salidaId, 'org_id' => $organizacionId]);
        $salida = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$salida) {
            return 0;
        }

        if ($salida['tipo_capacidad'] === 'SIN_CONTROL') {
            return 9999;
        }

        $capacidadMaxima = (int) ($salida['capacidad_maxima'] ?? 0);

        // Pasajeros en esta salida de reservas formalmente activas (CONFIRMADA, REGISTRADA, PENDIENTE_DATOS)
        $stmtOcupados = $this->pdo->prepare("
            SELECT COALESCE(SUM(osp.cantidad_pasajeros), 0)
            FROM `operacion_salida_prestaciones` osp
            JOIN `reserva_prestaciones` rp ON osp.prestacion_id = rp.id
            JOIN `reservas` r ON rp.reserva_id = r.id
            WHERE osp.salida_id = :salida_id
              AND r.estado IN ('" . EstadoReserva::CONFIRMADA->value . "', '" . EstadoReserva::REGISTRADA->value . "', '" . EstadoReserva::PENDIENTE_DATOS->value . "')
        ");
        $stmtOcupados->execute(['salida_id' => $salidaId]);
        $ocupados = (int) $stmtOcupados->fetchColumn();

        // Bloqueos temporales activos asociados a esta salida
        $bloqueados = $this->bloqueoRepo->sumarCuposBloqueadosActivos(
            $organizacionId,
            (int) $salida['item_comercial_id'],
            $salidaId
        );

        $disponibles = $capacidadMaxima - $ocupados - $bloqueados;
        return max(0, $disponibles);
    }

    /**
     * Calcula la disponibilidad de un paquete como el cuello de botella (mínimo)
     * de sus componentes que son servicios con control de aforo.
     */
    public function calcularDisponibilidadPaquete(
        int $organizacionId,
        int $edicionId,
        int $paqueteId,
        ?string $fechaServicio = null
    ): int {
        $stmtItems = $this->pdo->prepare("
            SELECT pi.item_comercial_id, pi.cantidad
            FROM `paquete_items` pi
            JOIN `items_comerciales` ic ON pi.item_comercial_id = ic.id
            WHERE pi.paquete_id = :paquete_id
        ");
        $stmtItems->execute(['paquete_id' => $paqueteId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return 0;
        }

        $minDisponibles = 9999;

        foreach ($items as $item) {
            $itemId = (int) $item['item_comercial_id'];
            $cantRequerida = (int) ceil((float) $item['cantidad']);
            $dispItem = $this->calcularDisponibilidadItem($organizacionId, $edicionId, $itemId, $fechaServicio);

            $cuposPaquetePorItem = (int) floor($dispItem / max(1, $cantRequerida));
            if ($cuposPaquetePorItem < $minDisponibles) {
                $minDisponibles = $cuposPaquetePorItem;
            }
        }

        return max(0, $minDisponibles);
    }

    /**
     * Bloquea cupos temporalmente con exclusión mutua pesimista (TTL 15 min por defecto).
     *
     * @return array{exito: bool, token_bloqueo: ?string, expira_en: ?string, mensaje: string}
     */
    public function bloquearCupos(
        int $organizacionId,
        int $edicionId,
        int $itemComercialId,
        int $cuposSolicitados,
        string $identificadorSesion,
        ?string $fechaServicio = null,
        ?string $horaServicio = null,
        ?int $salidaId = null,
        int $ttlMinutos = 15
    ): array {
        if ($cuposSolicitados <= 0) {
            throw new InvalidArgumentException("Los cupos solicitados deben ser mayores a 0.");
        }

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. Exclusión mutua pesimista (FOR UPDATE) para serializar solicitudes concurrentes sobre el último cupo
            if ($salidaId !== null) {
                $stmtLock = $this->pdo->prepare("
                    SELECT `id` FROM `operacion_salidas`
                    WHERE `id` = :salida_id AND `organizacion_id` = :org_id
                    FOR UPDATE
                ");
                $stmtLock->execute(['salida_id' => $salidaId, 'org_id' => $organizacionId]);
            } else {
                $stmtLockOferta = $this->pdo->prepare("
                    SELECT `id` FROM `ofertas_items_edicion`
                    WHERE `organizacion_id` = :org_id
                      AND `edicion_id` = :edicion_id
                      AND `item_comercial_id` = :item_id
                    FOR UPDATE
                ");
                $stmtLockOferta->execute([
                    'org_id'     => $organizacionId,
                    'edicion_id' => $edicionId,
                    'item_id'    => $itemComercialId,
                ]);
            }

            // 2. Comprobar aforo disponible
            $disponibles = $this->calcularDisponibilidadItem(
                $organizacionId,
                $edicionId,
                $itemComercialId,
                $fechaServicio,
                $salidaId
            );

            if ($disponibles < $cuposSolicitados) {
                if ($transaccionIniciada) {
                    $this->pdo->rollBack();
                }
                return [
                    'exito'          => false,
                    'token_bloqueo'  => null,
                    'expira_en'      => null,
                    'mensaje'        => "No hay suficientes cupos disponibles ({$disponibles} disponibles, {$cuposSolicitados} solicitados).",
                ];
            }

            // 3. Crear el bloqueo temporal
            $token = 'blk_' . bin2hex(random_bytes(24));
            $expiraEn = date('Y-m-d H:i:s', time() + ($ttlMinutos * 60));

            $bloqueo = new ReservaBloqueoTemporal(
                id: null,
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                itemComercialId: $itemComercialId,
                salidaId: $salidaId,
                tokenBloqueo: $token,
                identificadorSesion: $identificadorSesion,
                cuposBloqueados: $cuposSolicitados,
                fechaServicio: $fechaServicio,
                horaServicio: $horaServicio,
                estado: ReservaBloqueoTemporal::ESTADO_ACTIVO,
                expiraEn: $expiraEn
            );

            $this->bloqueoRepo->crear($bloqueo);

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return [
                'exito'         => true,
                'token_bloqueo' => $token,
                'expira_en'     => $expiraEn,
                'mensaje'       => "Cupos bloqueados exitosamente por {$ttlMinutos} minutos.",
            ];
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException("Error al bloquear cupos temporales: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extiende un bloqueo activo al estado RETENCION_VOUCHER con vigencia de 24 horas.
     */
    public function extenderParaVoucher(string $tokenBloqueo, int $horasRetencion = 24): bool
    {
        $bloqueo = $this->bloqueoRepo->buscarPorToken($tokenBloqueo);
        if (!$bloqueo || !$bloqueo->estaVigente()) {
            return false;
        }

        $nuevaExpiracion = date('Y-m-d H:i:s', time() + ($horasRetencion * 3600));
        return $this->bloqueoRepo->cambiarEstado(
            (int) $bloqueo->id,
            ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER,
            $nuevaExpiracion
        );
    }

    /**
     * Marca un bloqueo como consumido tras la formalización de la compra.
     */
    public function consumirBloqueo(string $tokenBloqueo): bool
    {
        $bloqueo = $this->bloqueoRepo->buscarPorToken($tokenBloqueo);
        if (!$bloqueo) {
            return false;
        }

        return $this->bloqueoRepo->cambiarEstado((int) $bloqueo->id, ReservaBloqueoTemporal::ESTADO_CONSUMIDO);
    }

    /**
     * Libera manualmente un bloqueo por desistimiento del usuario.
     */
    public function liberarBloqueo(string $tokenBloqueo): bool
    {
        $bloqueo = $this->bloqueoRepo->buscarPorToken($tokenBloqueo);
        if (!$bloqueo) {
            return false;
        }

        return $this->bloqueoRepo->cambiarEstado((int) $bloqueo->id, ReservaBloqueoTemporal::ESTADO_LIBERADO_MANUAL);
    }

    /**
     * Purga y marca como expirados los bloqueos vencidos.
     */
    public function purgarExpirados(): int
    {
        return $this->bloqueoRepo->liberarExpirados();
    }
}

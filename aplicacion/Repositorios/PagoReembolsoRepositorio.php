<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\PagoReembolso;
use Aplicacion\Finanzas\EstadoReembolso;
use Aplicacion\Finanzas\MotivoReembolso;
use PDO;

class PagoReembolsoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        $stmtSec = $this->pdo->prepare("
            INSERT INTO `reembolsos_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 1)
            ON DUPLICATE KEY UPDATE `ultimo_numero` = `ultimo_numero` + 1
        ");
        $stmtSec->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtNum = $this->pdo->prepare("
            SELECT `ultimo_numero` FROM `reembolsos_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtNum->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $num = (int) $stmtNum->fetchColumn();

        return sprintf('REEM-%04d-%06d', $anio, $num);
    }

    public function registrarReembolso(PagoReembolso $reembolso): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `pago_reembolsos` (
                `organizacion_id`, `pago_id`, `venta_id`, `correlativo`,
                `monto_reembolsado`, `estado`, `motivo`, `motivo_detalle`,
                `transaccion_reembolso_externa_id`, `autorizado_por`, `ejecutado_en`
            ) VALUES (
                :org_id, :pago_id, :venta_id, :correlativo,
                :monto, :estado, :motivo, :detalle,
                :trans_ext, :autorizado_por, :ejecutado_en
            )
        ");

        $stmt->execute([
            'org_id'         => $reembolso->organizacionId,
            'pago_id'        => $reembolso->pagoId,
            'venta_id'       => $reembolso->ventaId,
            'correlativo'    => $reembolso->correlativo,
            'monto'          => $reembolso->montoReembolsado,
            'estado'         => $reembolso->estado->value,
            'motivo'         => $reembolso->motivo->value,
            'detalle'        => $reembolso->motivoDetalle,
            'trans_ext'      => $reembolso->transaccionReembolsoExternaId,
            'autorizado_por' => $reembolso->autorizadoPor,
            'ejecutado_en'   => $reembolso->ejecutadoEn,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id, int $organizacionId): ?PagoReembolso
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, p.correlativo AS pago_correlativo, v.correlativo AS venta_correlativo
            FROM `pago_reembolsos` r
            INNER JOIN `pagos` p ON p.id = r.pago_id
            INNER JOIN `ventas` v ON v.id = r.venta_id
            WHERE r.id = :id AND r.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    /**
     * @return PagoReembolso[]
     */
    public function listarPorPago(int $pagoId, int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, p.correlativo AS pago_correlativo, v.correlativo AS venta_correlativo
            FROM `pago_reembolsos` r
            INNER JOIN `pagos` p ON p.id = r.pago_id
            INNER JOIN `ventas` v ON v.id = r.venta_id
            WHERE r.pago_id = :pago_id AND r.organizacion_id = :org_id
            ORDER BY r.id DESC
        ");
        $stmt->execute(['pago_id' => $pagoId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratar'], $filas);
    }

    private function hidratar(array $f): PagoReembolso
    {
        return new PagoReembolso(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            pagoId: (int) $f['pago_id'],
            ventaId: (int) $f['venta_id'],
            correlativo: (string) $f['correlativo'],
            montoReembolsado: (float) $f['monto_reembolsado'],
            estado: EstadoReembolso::from($f['estado']),
            motivo: MotivoReembolso::from($f['motivo']),
            motivoDetalle: (string) $f['motivo_detalle'],
            transaccionReembolsoExternaId: $f['transaccion_reembolso_externa_id'],
            autorizadoPor: (int) $f['autorizado_por'],
            ejecutadoEn: $f['ejecutado_en'],
            creadoEn: $f['creado_en'],
            pagoCorrelativo: $f['pago_correlativo'] ?? null,
            ventaCorrelativo: $f['venta_correlativo'] ?? null
        );
    }
}

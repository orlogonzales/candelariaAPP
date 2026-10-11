<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ReservaBloqueoTemporal;
use PDO;

class ReservaBloqueoTemporalRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ReservaBloqueoTemporal $bloqueo): ReservaBloqueoTemporal
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `reserva_bloqueos_temporales` (
                `organizacion_id`, `edicion_id`, `item_comercial_id`, `salida_id`,
                `token_bloqueo`, `identificador_sesion`, `cupos_bloqueados`,
                `fecha_servicio`, `hora_servicio`, `estado`, `expira_en`
            ) VALUES (
                :org_id, :edicion_id, :item_id, :salida_id,
                :token, :sesion_id, :cupos,
                :fecha, :hora, :estado, :expira_en
            )
        ");

        $stmt->execute([
            'org_id'        => $bloqueo->organizacionId,
            'edicion_id'    => $bloqueo->edicionId,
            'item_id'       => $bloqueo->itemComercialId,
            'salida_id'     => $bloqueo->salidaId,
            'token'         => $bloqueo->tokenBloqueo,
            'sesion_id'     => $bloqueo->identificadorSesion,
            'cupos'         => $bloqueo->cuposBloqueados,
            'fecha'         => $bloqueo->fechaServicio,
            'hora'          => $bloqueo->horaServicio,
            'estado'        => $bloqueo->estado,
            'expira_en'     => $bloqueo->expiraEn,
        ]);

        $bloqueo->id = (int) $this->pdo->lastInsertId();
        return $bloqueo;
    }

    public function buscarPorToken(string $token): ?ReservaBloqueoTemporal
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_bloqueos_temporales`
            WHERE `token_bloqueo` = :token
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ReservaBloqueoTemporal::desdeArreglo($fila) : null;
    }

    public function buscarPorTokenParaActualizar(string $token): ?ReservaBloqueoTemporal
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_bloqueos_temporales`
            WHERE `token_bloqueo` = :token
            FOR UPDATE
        ");
        $stmt->execute(['token' => $token]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ReservaBloqueoTemporal::desdeArreglo($fila) : null;
    }

    public function sumarCuposBloqueadosActivos(
        int $organizacionId,
        int $itemComercialId,
        ?int $salidaId = null,
        ?string $fechaServicio = null
    ): int {
        $sql = "
            SELECT COALESCE(SUM(`cupos_bloqueados`), 0)
            FROM `reserva_bloqueos_temporales`
            WHERE `organizacion_id` = :org_id
              AND `item_comercial_id` = :item_id
              AND `estado` IN ('" . ReservaBloqueoTemporal::ESTADO_ACTIVO . "', '" . ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER . "')
              AND `expira_en` > NOW()
        ";
        $params = [
            'org_id'  => $organizacionId,
            'item_id' => $itemComercialId,
        ];

        if ($salidaId !== null) {
            $sql .= " AND `salida_id` = :salida_id";
            $params['salida_id'] = $salidaId;
        }

        if ($fechaServicio !== null) {
            $sql .= " AND (`fecha_servicio` = :fecha OR `fecha_servicio` IS NULL)";
            $params['fecha'] = $fechaServicio;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function cambiarEstado(int $id, string $nuevoEstado, ?string $nuevaExpiracion = null): bool
    {
        if ($nuevaExpiracion !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE `reserva_bloqueos_temporales`
                SET `estado` = :estado, `expira_en` = :expira
                WHERE `id` = :id
            ");
            return $stmt->execute([
                'estado' => $nuevoEstado,
                'expira' => $nuevaExpiracion,
                'id'     => $id,
            ]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE `reserva_bloqueos_temporales`
            SET `estado` = :estado
            WHERE `id` = :id
        ");
        return $stmt->execute([
            'estado' => $nuevoEstado,
            'id'     => $id,
        ]);
    }

    public function liberarExpirados(): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE `reserva_bloqueos_temporales`
            SET `estado` = :expirado
            WHERE `estado` IN (:activo, :retencion)
              AND `expira_en` <= NOW()
        ");
        $stmt->execute([
            'expirado'  => ReservaBloqueoTemporal::ESTADO_EXPIRADO,
            'activo'    => ReservaBloqueoTemporal::ESTADO_ACTIVO,
            'retencion' => ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<ReservaBloqueoTemporal>
     */
    public function listarPorSesion(string $identificadorSesion): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `reserva_bloqueos_temporales`
            WHERE `identificador_sesion` = :sesion
              AND `estado` IN ('" . ReservaBloqueoTemporal::ESTADO_ACTIVO . "', '" . ReservaBloqueoTemporal::ESTADO_RETENCION_VOUCHER . "')
              AND `expira_en` > NOW()
            ORDER BY `id` DESC
        ");
        $stmt->execute(['sesion' => $identificadorSesion]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => ReservaBloqueoTemporal::desdeArreglo($f), $filas);
    }
}

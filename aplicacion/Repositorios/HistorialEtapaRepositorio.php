<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\HistorialEtapaOportunidad;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio inmutable (Append-Only) para el historial de transiciones de etapas en oportunidades.
 */
class HistorialEtapaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function registrar(HistorialEtapaOportunidad $registro): int
    {
        $sql = "INSERT INTO `crm_oportunidad_historial_etapas` (
                    `organizacion_id`, `oportunidad_id`, `etapa_anterior`,
                    `etapa_nueva`, `motivo`, `actor_tipo`, `usuario_id`,
                    `actor_sistema_id`, `correlacion_id`
                ) VALUES (
                    :organizacion_id, :oportunidad_id, :etapa_anterior,
                    :etapa_nueva, :motivo, :actor_tipo, :usuario_id,
                    :actor_sistema_id, :correlacion_id
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'  => $registro->organizacionId,
            ':oportunidad_id'   => $registro->oportunidadId,
            ':etapa_anterior'   => $registro->etapaAnterior,
            ':etapa_nueva'      => $registro->etapaNueva,
            ':motivo'           => $registro->motivo !== null ? trim($registro->motivo) : null,
            ':actor_tipo'       => $registro->actorTipo,
            ':usuario_id'       => $registro->usuarioId,
            ':actor_sistema_id' => $registro->actorSistemaId,
            ':correlacion_id'   => $registro->correlacionId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return HistorialEtapaOportunidad[]
     */
    public function listarPorOportunidad(int $organizacionId, int $oportunidadId): array
    {
        $sql = "SELECT * FROM `crm_oportunidad_historial_etapas`
                WHERE `organizacion_id` = :organizacion_id AND `oportunidad_id` = :oportunidad_id
                ORDER BY `id` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':oportunidad_id'  => $oportunidadId,
        ]);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = HistorialEtapaOportunidad::desdeArreglo($fila);
        }

        return $resultados;
    }
}

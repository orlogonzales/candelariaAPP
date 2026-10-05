<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\InteraccionCrm;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio inmutable (Append-Only) para la bitácora de interacciones comerciales de CRM.
 */
class InteraccionCrmRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function registrar(InteraccionCrm $interaccion): int
    {
        $sql = "INSERT INTO `crm_interacciones` (
                    `organizacion_id`, `cliente_id`, `oportunidad_id`,
                    `canal_id`, `tipo`, `direccion`, `resumen`, `detalle`,
                    `actor_tipo`, `usuario_id`, `actor_sistema_id`, `correlacion_id`
                ) VALUES (
                    :organizacion_id, :cliente_id, :oportunidad_id,
                    :canal_id, :tipo, :direccion, :resumen, :detalle,
                    :actor_tipo, :usuario_id, :actor_sistema_id, :correlacion_id
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'  => $interaccion->organizacionId,
            ':cliente_id'       => $interaccion->clienteId,
            ':oportunidad_id'   => $interaccion->oportunidadId,
            ':canal_id'         => $interaccion->canalId,
            ':tipo'             => $interaccion->tipo->value,
            ':direccion'        => $interaccion->direccion->value,
            ':resumen'          => trim($interaccion->resumen),
            ':detalle'          => $interaccion->detalle !== null ? trim($interaccion->detalle) : null,
            ':actor_tipo'       => $interaccion->actorTipo,
            ':usuario_id'       => $interaccion->usuarioId,
            ':actor_sistema_id' => $interaccion->actorSistemaId,
            ':correlacion_id'   => $interaccion->correlacionId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id): ?InteraccionCrm
    {
        $sql = "SELECT * FROM `crm_interacciones` WHERE `id` = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? InteraccionCrm::desdeArreglo($fila) : null;
    }

    /**
     * @return InteraccionCrm[]
     */
    public function listarPorCliente(int $organizacionId, int $clienteId): array
    {
        $sql = "SELECT * FROM `crm_interacciones`
                WHERE `organizacion_id` = :organizacion_id AND `cliente_id` = :cliente_id
                ORDER BY `id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':cliente_id'      => $clienteId,
        ]);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = InteraccionCrm::desdeArreglo($fila);
        }

        return $resultados;
    }

    /**
     * @return InteraccionCrm[]
     */
    public function listarPorOportunidad(int $organizacionId, int $oportunidadId): array
    {
        $sql = "SELECT * FROM `crm_interacciones`
                WHERE `organizacion_id` = :organizacion_id AND `oportunidad_id` = :oportunidad_id
                ORDER BY `id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':oportunidad_id'  => $oportunidadId,
        ]);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = InteraccionCrm::desdeArreglo($fila);
        }

        return $resultados;
    }
}

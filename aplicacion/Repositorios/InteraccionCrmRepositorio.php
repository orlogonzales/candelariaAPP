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

    /**
     * Retorna interacciones con joins a canales y usuarios para el timeline comercial.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarConDetalles(
        int $organizacionId,
        ?int $clienteId = null,
        ?int $oportunidadId = null,
        int $limite = 50
    ): array {
        $sql = "SELECT 
                    i.*,
                    c.codigo AS canal_codigo,
                    c.nombre AS canal_nombre,
                    u.nombre_completo AS usuario_nombre,
                    u.nombre_usuario AS usuario_login,
                    s.codigo AS actor_sistema_codigo,
                    s.nombre AS actor_sistema_nombre
                FROM `crm_interacciones` i
                INNER JOIN `canales` c ON c.id = i.canal_id
                LEFT JOIN `usuarios` u ON u.id = i.usuario_id
                LEFT JOIN `actores_sistema` s ON s.id = i.actor_sistema_id
                WHERE i.organizacion_id = :organizacion_id";

        $params = [':organizacion_id' => $organizacionId];

        if ($clienteId !== null && $clienteId > 0) {
            $sql .= " AND i.cliente_id = :cliente_id";
            $params[':cliente_id'] = $clienteId;
        }

        if ($oportunidadId !== null && $oportunidadId > 0) {
            $sql .= " AND i.oportunidad_id = :oportunidad_id";
            $params[':oportunidad_id'] = $oportunidadId;
        }

        $sql .= " ORDER BY i.id DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

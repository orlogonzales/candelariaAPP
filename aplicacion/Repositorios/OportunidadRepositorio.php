<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Oportunidad;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio de dominio para Oportunidades Comerciales.
 * Garantiza:
 * - Integridad relacional y aislamiento por tenant.
 * - Bloqueo optimista mediante versión de control (version_bloqueo).
 * - Prohibición absoluta de borrado físico.
 */
class OportunidadRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function crear(Oportunidad $oportunidad): int
    {
        $sql = "INSERT INTO `crm_oportunidades` (
                    `organizacion_id`, `edicion_id`, `cliente_id`,
                    `usuario_asignado_id`, `origen_comercial_id`,
                    `titulo`, `etapa`, `valor_estimado`, `moneda`,
                    `proximo_seguimiento_en`, `motivo_perdida`, `motivo_perdida_detalle`,
                    `notas`, `version_bloqueo`
                ) VALUES (
                    :organizacion_id, :edicion_id, :cliente_id,
                    :usuario_asignado_id, :origen_comercial_id,
                    :titulo, :etapa, :valor_estimado, :moneda,
                    :proximo_seguimiento_en, :motivo_perdida, :motivo_perdida_detalle,
                    :notas, 1
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'        => $oportunidad->organizacionId,
            ':edicion_id'             => $oportunidad->edicionId,
            ':cliente_id'             => $oportunidad->clienteId,
            ':usuario_asignado_id'    => $oportunidad->usuarioAsignadoId,
            ':origen_comercial_id'    => $oportunidad->origenComercialId,
            ':titulo'                 => trim($oportunidad->titulo),
            ':etapa'                  => $oportunidad->etapa->value,
            ':valor_estimado'         => $oportunidad->valorEstimado,
            ':moneda'                 => strtoupper(trim($oportunidad->moneda)),
            ':proximo_seguimiento_en' => $oportunidad->proximoSeguimientoEn,
            ':motivo_perdida'         => $oportunidad->motivoPerdida?->value,
            ':motivo_perdida_detalle' => $oportunidad->motivoPerdidaDetalle,
            ':notas'                  => $oportunidad->notas !== null ? trim($oportunidad->notas) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id): ?Oportunidad
    {
        $sql = "SELECT * FROM `crm_oportunidades` WHERE `id` = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Oportunidad::desdeArreglo($fila) : null;
    }

    public function buscarPorIdBloqueante(int $id): ?Oportunidad
    {
        $sql = "SELECT * FROM `crm_oportunidades` WHERE `id` = :id LIMIT 1 FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Oportunidad::desdeArreglo($fila) : null;
    }

    /**
     * Actualiza datos comerciales generales con bloqueo optimista estricto.
     *
     * @throws ConflictoConcurrenciaExcepcion si la versión de bloqueo difiere
     */
    public function actualizarConcurrente(Oportunidad $oportunidad): bool
    {
        $sql = "UPDATE `crm_oportunidades`
                SET `titulo`                 = :titulo,
                    `usuario_asignado_id`    = :usuario_asignado_id,
                    `origen_comercial_id`    = :origen_comercial_id,
                    `valor_estimado`         = :valor_estimado,
                    `proximo_seguimiento_en` = :proximo_seguimiento_en,
                    `notas`                  = :notas,
                    `version_bloqueo`        = `version_bloqueo` + 1
                WHERE `id` = :id
                  AND `organizacion_id` = :organizacion_id
                  AND `version_bloqueo` = :version_bloqueo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'                     => $oportunidad->id,
            ':organizacion_id'        => $oportunidad->organizacionId,
            ':titulo'                 => trim($oportunidad->titulo),
            ':usuario_asignado_id'    => $oportunidad->usuarioAsignadoId,
            ':origen_comercial_id'    => $oportunidad->origenComercialId,
            ':valor_estimado'         => $oportunidad->valorEstimado,
            ':proximo_seguimiento_en' => $oportunidad->proximoSeguimientoEn,
            ':notas'                  => $oportunidad->notas !== null ? trim($oportunidad->notas) : null,
            ':version_bloqueo'        => $oportunidad->versionBloqueo,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ConflictoConcurrenciaExcepcion(
                "Conflicto de concurrencia: la oportunidad #{$oportunidad->id} fue modificada por otro proceso (versión esperada: {$oportunidad->versionBloqueo})."
            );
        }

        return true;
    }

    /**
     * Actualiza la etapa y motivos con bloqueo optimista.
     */
    public function cambiarEtapaConcurrente(
        int $id,
        int $organizacionId,
        string $nuevaEtapa,
        ?string $motivoPerdida,
        ?string $motivoPerdidaDetalle,
        int $versionBloqueo
    ): bool {
        $sql = "UPDATE `crm_oportunidades`
                SET `etapa`                  = :etapa,
                    `motivo_perdida`         = :motivo_perdida,
                    `motivo_perdida_detalle` = :motivo_perdida_detalle,
                    `version_bloqueo`        = `version_bloqueo` + 1
                WHERE `id` = :id
                  AND `organizacion_id` = :organizacion_id
                  AND `version_bloqueo` = :version_bloqueo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'                     => $id,
            ':organizacion_id'        => $organizacionId,
            ':etapa'                  => $nuevaEtapa,
            ':motivo_perdida'         => $motivoPerdida,
            ':motivo_perdida_detalle' => $motivoPerdidaDetalle,
            ':version_bloqueo'        => $versionBloqueo,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ConflictoConcurrenciaExcepcion(
                "Conflicto de concurrencia al cambiar etapa de oportunidad #{$id} (versión esperada: {$versionBloqueo})."
            );
        }

        return true;
    }

    /**
     * Asigna o reasigna el asesor responsable con control de versiones.
     */
    public function asignarResponsableConcurrente(
        int $id,
        int $organizacionId,
        ?int $usuarioAsignadoId,
        int $versionBloqueo
    ): bool {
        $sql = "UPDATE `crm_oportunidades`
                SET `usuario_asignado_id` = :usuario_asignado_id,
                    `version_bloqueo`     = `version_bloqueo` + 1
                WHERE `id` = :id
                  AND `organizacion_id` = :organizacion_id
                  AND `version_bloqueo` = :version_bloqueo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'                  => $id,
            ':organizacion_id'     => $organizacionId,
            ':usuario_asignado_id' => $usuarioAsignadoId,
            ':version_bloqueo'     => $versionBloqueo,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ConflictoConcurrenciaExcepcion(
                "Conflicto de concurrencia al asignar asesor a oportunidad #{$id} (versión esperada: {$versionBloqueo})."
            );
        }

        return true;
    }

    /**
     * @return Oportunidad[]
     */
    public function listarPorCliente(int $organizacionId, int $clienteId): array
    {
        $sql = "SELECT * FROM `crm_oportunidades`
                WHERE `organizacion_id` = :organizacion_id AND `cliente_id` = :cliente_id
                ORDER BY `id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':cliente_id'      => $clienteId,
        ]);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = Oportunidad::desdeArreglo($fila);
        }

        return $resultados;
    }

    /**
     * @return Oportunidad[]
     */
    public function listarPorEdicion(int $organizacionId, int $edicionId, ?string $etapa = null): array
    {
        $sql = "SELECT * FROM `crm_oportunidades`
                WHERE `organizacion_id` = :organizacion_id AND `edicion_id` = :edicion_id"
                . ($etapa !== null ? " AND `etapa` = :etapa" : "")
                . " ORDER BY `id` DESC";

        $params = [
            ':organizacion_id' => $organizacionId,
            ':edicion_id'      => $edicionId,
        ];
        if ($etapa !== null) {
            $params[':etapa'] = $etapa;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = Oportunidad::desdeArreglo($fila);
        }

        return $resultados;
    }
}

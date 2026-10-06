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

    /**
     * Retorna oportunidades enriquecidas con joins a cliente, edición, asesor y origen para DataTables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarConDetalles(
        int $organizacionId,
        ?int $edicionId = null,
        ?string $etapa = null,
        ?int $usuarioAsignadoId = null,
        ?int $origenComercialId = null,
        ?string $busqueda = null,
        int $limite = 50,
        int $offset = 0
    ): array {
        $sql = "SELECT 
                    op.id,
                    op.organizacion_id,
                    op.edicion_id,
                    op.cliente_id,
                    op.usuario_asignado_id,
                    op.origen_comercial_id,
                    op.titulo,
                    op.etapa,
                    op.valor_estimado,
                    op.moneda,
                    op.proximo_seguimiento_en,
                    op.motivo_perdida,
                    op.motivo_perdida_detalle,
                    op.notas,
                    op.version_bloqueo,
                    op.creado_en,
                    op.actualizado_en,
                    e.codigo AS edicion_codigo,
                    e.nombre AS edicion_nombre,
                    e.anio AS edicion_anio,
                    p.id AS persona_id,
                    p.tipo_persona,
                    p.nombres AS cliente_nombres,
                    p.apellidos AS cliente_apellidos,
                    p.razon_social AS cliente_razon_social,
                    p.nombre_comercial AS cliente_nombre_comercial,
                    p.telefono_whatsapp AS cliente_whatsapp,
                    p.numero_documento AS cliente_documento,
                    c.estado_comercial AS cliente_estado_comercial,
                    u.nombre_usuario AS asesor_usuario,
                    u.nombre_completo AS asesor_nombre,
                    oc.codigo AS origen_codigo,
                    oc.nombre AS origen_nombre,
                    (
                        SELECT COUNT(*)
                        FROM `crm_interacciones` i
                        WHERE i.oportunidad_id = op.id
                    ) AS total_interacciones
                FROM `crm_oportunidades` op
                INNER JOIN `clientes` c ON c.id = op.cliente_id
                INNER JOIN `personas` p ON p.id = c.persona_id
                INNER JOIN `ediciones_candelaria` e ON e.id = op.edicion_id
                LEFT JOIN `usuarios` u ON u.id = op.usuario_asignado_id
                LEFT JOIN `origenes_comerciales` oc ON oc.id = op.origen_comercial_id
                WHERE op.organizacion_id = :organizacion_id";

        $params = [':organizacion_id' => $organizacionId];

        if ($edicionId !== null && $edicionId > 0) {
            $sql .= " AND op.edicion_id = :edicion_id";
            $params[':edicion_id'] = $edicionId;
        }

        if ($etapa !== null && $etapa !== '' && $etapa !== 'TODAS') {
            $sql .= " AND op.etapa = :etapa";
            $params[':etapa'] = strtoupper(trim($etapa));
        }

        if ($usuarioAsignadoId !== null && $usuarioAsignadoId > 0) {
            $sql .= " AND op.usuario_asignado_id = :usuario_asignado_id";
            $params[':usuario_asignado_id'] = $usuarioAsignadoId;
        }

        if ($origenComercialId !== null && $origenComercialId > 0) {
            $sql .= " AND op.origen_comercial_id = :origen_comercial_id";
            $params[':origen_comercial_id'] = $origenComercialId;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= " AND (
                op.titulo LIKE :busq
                OR p.nombres LIKE :busq
                OR p.apellidos LIKE :busq
                OR p.razon_social LIKE :busq
                OR p.nombre_comercial LIKE :busq
                OR p.numero_documento LIKE :busq
                OR p.telefono_whatsapp LIKE :busq
            )";
            $params[':busq'] = '%' . trim($busqueda) . '%';
        }

        $sql .= " ORDER BY op.id DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta total de oportunidades con filtros para DataTables.
     */
    public function contarConDetalles(
        int $organizacionId,
        ?int $edicionId = null,
        ?string $etapa = null,
        ?int $usuarioAsignadoId = null,
        ?int $origenComercialId = null,
        ?string $busqueda = null
    ): int {
        $sql = "SELECT COUNT(*)
                FROM `crm_oportunidades` op
                INNER JOIN `clientes` c ON c.id = op.cliente_id
                INNER JOIN `personas` p ON p.id = c.persona_id
                INNER JOIN `ediciones_candelaria` e ON e.id = op.edicion_id
                LEFT JOIN `usuarios` u ON u.id = op.usuario_asignado_id
                LEFT JOIN `origenes_comerciales` oc ON oc.id = op.origen_comercial_id
                WHERE op.organizacion_id = :organizacion_id";

        $params = [':organizacion_id' => $organizacionId];

        if ($edicionId !== null && $edicionId > 0) {
            $sql .= " AND op.edicion_id = :edicion_id";
            $params[':edicion_id'] = $edicionId;
        }

        if ($etapa !== null && $etapa !== '' && $etapa !== 'TODAS') {
            $sql .= " AND op.etapa = :etapa";
            $params[':etapa'] = strtoupper(trim($etapa));
        }

        if ($usuarioAsignadoId !== null && $usuarioAsignadoId > 0) {
            $sql .= " AND op.usuario_asignado_id = :usuario_asignado_id";
            $params[':usuario_asignado_id'] = $usuarioAsignadoId;
        }

        if ($origenComercialId !== null && $origenComercialId > 0) {
            $sql .= " AND op.origen_comercial_id = :origen_comercial_id";
            $params[':origen_comercial_id'] = $origenComercialId;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= " AND (
                op.titulo LIKE :busq
                OR p.nombres LIKE :busq
                OR p.apellidos LIKE :busq
                OR p.razon_social LIKE :busq
                OR p.nombre_comercial LIKE :busq
                OR p.numero_documento LIKE :busq
                OR p.telefono_whatsapp LIKE :busq
            )";
            $params[':busq'] = '%' . trim($busqueda) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca una oportunidad con todos sus detalles para modal o ficha.
     */
    public function buscarDetallePorId(int $organizacionId, int $id): ?array
    {
        $sql = "SELECT 
                    op.*,
                    e.codigo AS edicion_codigo,
                    e.nombre AS edicion_nombre,
                    e.anio AS edicion_anio,
                    p.id AS persona_id,
                    p.tipo_persona,
                    p.nombres AS cliente_nombres,
                    p.apellidos AS cliente_apellidos,
                    p.razon_social AS cliente_razon_social,
                    p.nombre_comercial AS cliente_nombre_comercial,
                    p.telefono_whatsapp AS cliente_whatsapp,
                    p.numero_documento AS cliente_documento,
                    c.estado_comercial AS cliente_estado_comercial,
                    u.nombre_usuario AS asesor_usuario,
                    u.nombre_completo AS asesor_nombre,
                    oc.codigo AS origen_codigo,
                    oc.nombre AS origen_nombre
                FROM `crm_oportunidades` op
                INNER JOIN `clientes` c ON c.id = op.cliente_id
                INNER JOIN `personas` p ON p.id = c.persona_id
                INNER JOIN `ediciones_candelaria` e ON e.id = op.edicion_id
                LEFT JOIN `usuarios` u ON u.id = op.usuario_asignado_id
                LEFT JOIN `origenes_comerciales` oc ON oc.id = op.origen_comercial_id
                WHERE op.organizacion_id = :organizacion_id AND op.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':id'              => $id,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}

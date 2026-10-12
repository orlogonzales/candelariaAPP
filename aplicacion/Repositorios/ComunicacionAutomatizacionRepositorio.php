<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Comunicaciones\AutomatizacionRegla;
use PDO;

class ComunicacionAutomatizacionRepositorio
{
    public function __construct(private PDO $pdo) {}

    public function guardar(AutomatizacionRegla $regla): AutomatizacionRegla
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comunicacion_automatizacion_reglas (
                organizacion_id, codigo, nombre, descripcion, evento_disparador,
                plantilla_id, tipo_comunicacion, minutos_diferidos, condiciones_json,
                mapeo_parametros_json, activo, total_disparados, ultimo_disparo_en,
                creado_por
            ) VALUES (
                :org_id, :codigo, :nombre, :descripcion, :evento,
                :plantilla_id, :tipo_com, :minutos, :condiciones,
                :mapeo, :activo, :total, :ultimo_disparo,
                :creado_por
            )
        ");

        $stmt->execute([
            'org_id'         => $regla->organizacionId,
            'codigo'         => $regla->codigo,
            'nombre'         => $regla->nombre,
            'descripcion'    => $regla->descripcion,
            'evento'         => $regla->eventoDisparador,
            'plantilla_id'   => $regla->plantillaId,
            'tipo_com'       => $regla->tipoComunicacion,
            'minutos'        => $regla->minutosDiferidos,
            'condiciones'    => $regla->condicionesJson !== null ? json_encode($regla->condicionesJson, JSON_UNESCAPED_UNICODE) : null,
            'mapeo'          => json_encode($regla->mapeoParametrosJson, JSON_UNESCAPED_UNICODE),
            'activo'         => $regla->activo ? 1 : 0,
            'total'          => $regla->totalDisparados,
            'ultimo_disparo' => $regla->ultimoDisparoEn,
            'creado_por'     => $regla->creadoPor,
        ]);

        $regla->id = (int)$this->pdo->lastInsertId();
        return $regla;
    }

    public function actualizar(AutomatizacionRegla $regla): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_automatizacion_reglas SET
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                evento_disparador = :evento,
                plantilla_id = :plantilla_id,
                tipo_comunicacion = :tipo_com,
                minutos_diferidos = :minutos,
                condiciones_json = :condiciones,
                mapeo_parametros_json = :mapeo,
                activo = :activo,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");

        return $stmt->execute([
            'id'           => $regla->id,
            'org_id'       => $regla->organizacionId,
            'codigo'       => $regla->codigo,
            'nombre'       => $regla->nombre,
            'descripcion'  => $regla->descripcion,
            'evento'       => $regla->eventoDisparador,
            'plantilla_id' => $regla->plantillaId,
            'tipo_com'     => $regla->tipoComunicacion,
            'minutos'      => $regla->minutosDiferidos,
            'condiciones'  => $regla->condicionesJson !== null ? json_encode($regla->condicionesJson, JSON_UNESCAPED_UNICODE) : null,
            'mapeo'        => json_encode($regla->mapeoParametrosJson, JSON_UNESCAPED_UNICODE),
            'activo'       => $regla->activo ? 1 : 0,
        ]);
    }

    public function buscarPorId(int $id, int $orgId): ?AutomatizacionRegla
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_automatizacion_reglas
            WHERE id = :id AND organizacion_id = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function buscarPorCodigo(string $codigo, int $orgId): ?AutomatizacionRegla
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_automatizacion_reglas
            WHERE codigo = :codigo AND organizacion_id = :org_id
            LIMIT 1
        ");
        $stmt->execute(['codigo' => $codigo, 'org_id' => $orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hidratar($row) : null;
    }

    public function listarPorOrganizacion(int $orgId, ?string $evento = null, ?bool $soloActivos = null): array
    {
        $sql = "SELECT r.*, p.nombre AS plantilla_nombre, p.categoria AS plantilla_categoria
                FROM comunicacion_automatizacion_reglas r
                LEFT JOIN comunicacion_plantillas p ON p.id = r.plantilla_id AND p.organizacion_id = r.organizacion_id
                WHERE r.organizacion_id = :org_id";
        $params = ['org_id' => $orgId];

        if ($evento !== null && $evento !== '') {
            $sql .= " AND r.evento_disparador = :evento";
            $params['evento'] = $evento;
        }

        if ($soloActivos !== null) {
            $sql .= " AND r.activo = :activo";
            $params['activo'] = $soloActivos ? 1 : 0;
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $row) {
            $regla = $this->hidratar($row);
            $arr = $regla->aArreglo();
            $arr['plantilla_nombre'] = $row['plantilla_nombre'] ?? 'N/A';
            $arr['plantilla_categoria'] = $row['plantilla_categoria'] ?? 'N/A';
            $resultado[] = $arr;
        }

        return $resultado;
    }

    public function listarActivasPorEvento(int $orgId, string $evento): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_automatizacion_reglas
            WHERE organizacion_id = :org_id 
              AND evento_disparador = :evento 
              AND activo = 1
            ORDER BY id ASC
        ");
        $stmt->execute([
            'org_id' => $orgId,
            'evento' => $evento
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reglas = [];
        foreach ($rows as $row) {
            $reglas[] = $this->hidratar($row);
        }

        return $reglas;
    }

    public function incrementarDisparos(int $reglaId, int $orgId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_automatizacion_reglas SET
                total_disparados = total_disparados + 1,
                ultimo_disparo_en = NOW(),
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $reglaId, 'org_id' => $orgId]);
    }

    public function cambiarEstadoActivo(int $id, int $orgId, bool $activo): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_automatizacion_reglas SET
                activo = :activo,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        return $stmt->execute([
            'id'     => $id,
            'org_id' => $orgId,
            'activo' => $activo ? 1 : 0
        ]);
    }

    public function eliminar(int $id, int $orgId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM comunicacion_automatizacion_reglas
            WHERE id = :id AND organizacion_id = :org_id
        ");
        return $stmt->execute(['id' => $id, 'org_id' => $orgId]);
    }

    private function hidratar(array $row): AutomatizacionRegla
    {
        return new AutomatizacionRegla(
            id: (int)$row['id'],
            organizacionId: (int)$row['organizacion_id'],
            codigo: (string)$row['codigo'],
            nombre: (string)$row['nombre'],
            descripcion: $row['descripcion'] !== null ? (string)$row['descripcion'] : null,
            eventoDisparador: (string)$row['evento_disparador'],
            plantillaId: (int)$row['plantilla_id'],
            tipoComunicacion: (string)$row['tipo_comunicacion'],
            minutosDiferidos: (int)$row['minutos_diferidos'],
            condicionesJson: $row['condiciones_json'] !== null ? json_decode((string)$row['condiciones_json'], true) : null,
            mapeoParametrosJson: json_decode((string)$row['mapeo_parametros_json'], true) ?: [],
            activo: (bool)$row['activo'],
            totalDisparados: (int)$row['total_disparados'],
            ultimoDisparoEn: $row['ultimo_disparo_en'] !== null ? (string)$row['ultimo_disparo_en'] : null,
            creadoPor: $row['creado_por'] !== null ? (int)$row['creado_por'] : null,
            creadoEn: (string)$row['creado_en'],
            actualizadoEn: $row['actualizado_en'] !== null ? (string)$row['actualizado_en'] : null
        );
    }
}

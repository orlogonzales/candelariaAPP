<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\OperacionRecurso;
use Aplicacion\Operaciones\EstadoRecursoFisico;
use Aplicacion\Operaciones\PropiedadRecurso;
use Aplicacion\Operaciones\TipoRecursoFisico;
use PDO;

/**
 * Repositorio de Persistencia Soberana para Recursos Físicos y Flota.
 */
class OperacionRecursoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(OperacionRecurso $r): OperacionRecurso
    {
        if ($r->id === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO `operacion_recursos` (
                    `organizacion_id`, `tipo_recurso`, `codigo_interno`,
                    `nombre`, `propiedad_tipo`, `proveedor_id`,
                    `capacidad_maxima`, `identificacion_oficial`, `estado`, `notas`
                ) VALUES (
                    :org_id, :tipo_recurso, :codigo_interno,
                    :nombre, :propiedad_tipo, :proveedor_id,
                    :capacidad, :identificacion, :estado, :notas
                )
            ");
            $stmt->execute([
                'org_id'         => $r->organizacionId,
                'tipo_recurso'   => $r->tipoRecurso->value,
                'codigo_interno' => $r->codigoInterno,
                'nombre'         => $r->nombre,
                'propiedad_tipo' => $r->propiedadTipo->value,
                'proveedor_id'   => $r->proveedorId,
                'capacidad'      => $r->capacidadMaxima,
                'identificacion' => $r->identificacionOficial,
                'estado'         => $r->estado->value,
                'notas'          => $r->notas,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            return $this->buscarPorId($id, $r->organizacionId) ?? $r;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `operacion_recursos`
            SET `tipo_recurso` = :tipo_recurso,
                `nombre` = :nombre,
                `propiedad_tipo` = :propiedad_tipo,
                `proveedor_id` = :proveedor_id,
                `capacidad_maxima` = :capacidad,
                `identificacion_oficial` = :identificacion,
                `estado` = :estado,
                `notas` = :notas
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'             => $r->id,
            'org_id'         => $r->organizacionId,
            'tipo_recurso'   => $r->tipoRecurso->value,
            'nombre'         => $r->nombre,
            'propiedad_tipo' => $r->propiedadTipo->value,
            'proveedor_id'   => $r->proveedorId,
            'capacidad'      => $r->capacidadMaxima,
            'identificacion' => $r->identificacionOficial,
            'estado'         => $r->estado->value,
            'notas'          => $r->notas,
        ]);
        return $this->buscarPorId($r->id, $r->organizacionId) ?? $r;
    }

    public function buscarPorId(int $id, int $organizacionId): ?OperacionRecurso
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_recursos`
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId): ?OperacionRecurso
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `operacion_recursos`
            WHERE `codigo_interno` = :codigo AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['codigo' => $codigo, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * @return OperacionRecurso[]
     */
    public function listarPorOrganizacion(
        int $organizacionId,
        ?TipoRecursoFisico $tipo = null,
        ?EstadoRecursoFisico $estado = null
    ): array {
        $sql = "SELECT * FROM `operacion_recursos` WHERE `organizacion_id` = :org_id";
        $params = ['org_id' => $organizacionId];
        if ($tipo !== null) {
            $sql .= " AND `tipo_recurso` = :tipo";
            $params['tipo'] = $tipo->value;
        }
        if ($estado !== null) {
            $sql .= " AND `estado` = :estado";
            $params['estado'] = $estado->value;
        }
        $sql .= " ORDER BY `id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => $this->hidratar($f), $filas);
    }

    public function actualizarEstado(int $id, int $organizacionId, EstadoRecursoFisico $nuevoEstado): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `operacion_recursos`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'     => $id,
            'org_id' => $organizacionId,
            'estado' => $nuevoEstado->value,
        ]);
    }

    private function hidratar(array $f): OperacionRecurso
    {
        return new OperacionRecurso(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            tipoRecurso: TipoRecursoFisico::from($f['tipo_recurso']),
            codigoInterno: $f['codigo_interno'],
            nombre: $f['nombre'],
            propiedadTipo: PropiedadRecurso::from($f['propiedad_tipo']),
            proveedorId: $f['proveedor_id'] !== null ? (int) $f['proveedor_id'] : null,
            capacidadMaxima: (int) $f['capacidad_maxima'],
            identificacionOficial: $f['identificacion_oficial'],
            estado: EstadoRecursoFisico::from($f['estado']),
            notas: $f['notas'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }
}

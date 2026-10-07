<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Proveedor;
use Aplicacion\Operaciones\EstadoProveedor;
use PDO;

/**
 * Repositorio de Persistencia Soberana para Proveedores.
 */
class ProveedorRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(Proveedor $p): Proveedor
    {
        if ($p->id === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO `proveedores` (
                    `organizacion_id`, `persona_id`, `tipo_servicio_principal`,
                    `estado`, `notas_contacto`, `creado_por`
                ) VALUES (
                    :org_id, :persona_id, :tipo_servicio,
                    :estado, :notas, :creado_por
                )
            ");
            $stmt->execute([
                'org_id'        => $p->organizacionId,
                'persona_id'    => $p->personaId,
                'tipo_servicio' => $p->tipoServicioPrincipal,
                'estado'        => $p->estado->value,
                'notas'         => $p->notasContacto,
                'creado_por'    => $p->creadoPor,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            return $this->buscarPorId($id, $p->organizacionId) ?? $p;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `proveedores`
            SET `tipo_servicio_principal` = :tipo_servicio,
                `estado` = :estado,
                `notas_contacto` = :notas
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'            => $p->id,
            'org_id'        => $p->organizacionId,
            'tipo_servicio' => $p->tipoServicioPrincipal,
            'estado'        => $p->estado->value,
            'notas'         => $p->notasContacto,
        ]);
        return $this->buscarPorId($p->id, $p->organizacionId) ?? $p;
    }

    public function buscarPorId(int $id, int $organizacionId): ?Proveedor
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `proveedores`
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    public function buscarPorPersonaId(int $personaId, int $organizacionId): ?Proveedor
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `proveedores`
            WHERE `persona_id` = :persona_id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['persona_id' => $personaId, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * @return Proveedor[]
     */
    public function listarPorOrganizacion(int $organizacionId, ?EstadoProveedor $estado = null): array
    {
        $sql = "SELECT * FROM `proveedores` WHERE `organizacion_id` = :org_id";
        $params = ['org_id' => $organizacionId];
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

    public function actualizarEstado(int $id, int $organizacionId, EstadoProveedor $nuevoEstado): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `proveedores`
            SET `estado` = :estado
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'     => $id,
            'org_id' => $organizacionId,
            'estado' => $nuevoEstado->value,
        ]);
    }

    private function hidratar(array $f): Proveedor
    {
        return new Proveedor(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            personaId: (int) $f['persona_id'],
            tipoServicioPrincipal: $f['tipo_servicio_principal'],
            estado: EstadoProveedor::from($f['estado']),
            notasContacto: $f['notas_contacto'],
            creadoPor: (int) $f['creado_por'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }
}

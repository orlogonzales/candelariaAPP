<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Comunicaciones\CategoriaPlantilla;
use Aplicacion\Comunicaciones\EstadoPlantillaMeta;
use Aplicacion\Entidades\ComunicacionPlantilla;
use PDO;

class ComunicacionPlantillaRepositorio
{
    public function __construct(private PDO $pdo) {}

    public function guardar(ComunicacionPlantilla $p): ComunicacionPlantilla
    {
        if ($p->id === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO comunicacion_plantillas (
                    organizacion_id, nombre, idioma, categoria, meta_template_id,
                    estado_meta, cuerpo_texto, encabezado_tipo, pie_texto,
                    parametros_mapeo_json, version_local, activo
                ) VALUES (
                    :org_id, :nombre, :idioma, :categoria, :meta_id,
                    :estado_meta, :cuerpo, :encabezado, :pie,
                    :parametros, :version_local, :activo
                )
            ");
            $stmt->execute([
                'org_id'        => $p->organizacionId,
                'nombre'        => $p->nombre,
                'idioma'        => $p->idioma,
                'categoria'     => $p->categoria->value,
                'meta_id'       => $p->metaTemplateId,
                'estado_meta'   => $p->estadoMeta->value,
                'cuerpo'        => $p->cuerpoTexto,
                'encabezado'    => $p->encabezadoTipo,
                'pie'           => $p->pieTexto,
                'parametros'    => json_encode($p->parametrosMapeoJson, JSON_UNESCAPED_UNICODE),
                'version_local' => $p->versionLocal,
                'activo'        => $p->activo ? 1 : 0
            ]);
            $p->id = (int) $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE comunicacion_plantillas SET
                    categoria = :categoria,
                    meta_template_id = :meta_id,
                    estado_meta = :estado_meta,
                    cuerpo_texto = :cuerpo,
                    encabezado_tipo = :encabezado,
                    pie_texto = :pie,
                    parametros_mapeo_json = :parametros,
                    version_local = version_local + 1,
                    activo = :activo
                WHERE id = :id AND organizacion_id = :org_id
            ");
            $stmt->execute([
                'categoria'   => $p->categoria->value,
                'meta_id'     => $p->metaTemplateId,
                'estado_meta' => $p->estadoMeta->value,
                'cuerpo'      => $p->cuerpoTexto,
                'encabezado'  => $p->encabezadoTipo,
                'pie'         => $p->pieTexto,
                'parametros'  => json_encode($p->parametrosMapeoJson, JSON_UNESCAPED_UNICODE),
                'activo'      => $p->activo ? 1 : 0,
                'id'          => $p->id,
                'org_id'      => $p->organizacionId
            ]);
        }

        return $p;
    }

    public function buscarPorId(int $id, int $orgId): ?ComunicacionPlantilla
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_plantillas 
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    public function buscarPorNombre(string $nombre, string $idioma, int $orgId): ?ComunicacionPlantilla
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_plantillas 
            WHERE nombre = :nombre AND idioma = :idioma AND organizacion_id = :org_id
        ");
        $stmt->execute(['nombre' => $nombre, 'idioma' => $idioma, 'org_id' => $orgId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    public function listar(int $orgId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_plantillas 
            WHERE organizacion_id = :org_id 
            ORDER BY nombre ASC
        ");
        $stmt->execute(['org_id' => $orgId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => $this->hidratar($f), $filas);
    }

    private function hidratar(array $f): ComunicacionPlantilla
    {
        return new ComunicacionPlantilla(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            nombre: (string) $f['nombre'],
            idioma: (string) $f['idioma'],
            categoria: CategoriaPlantilla::from((string) $f['categoria']),
            cuerpoTexto: (string) $f['cuerpo_texto'],
            parametrosMapeoJson: json_decode((string) ($f['parametros_mapeo_json'] ?? '[]'), true) ?? [],
            estadoMeta: EstadoPlantillaMeta::from((string) $f['estado_meta']),
            metaTemplateId: $f['meta_template_id'] !== null ? (string) $f['meta_template_id'] : null,
            encabezadoTipo: (string) $f['encabezado_tipo'],
            pieTexto: $f['pie_texto'] !== null ? (string) $f['pie_texto'] : null,
            versionLocal: (int) $f['version_local'],
            activo: (bool) $f['activo'],
            creadoEn: (string) $f['creado_en'],
            actualizadoEn: $f['actualizado_en'] !== null ? (string) $f['actualizado_en'] : null
        );
    }
}

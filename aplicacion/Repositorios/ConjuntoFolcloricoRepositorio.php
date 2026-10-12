<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Folclore\BloqueConjunto;
use Aplicacion\Folclore\ConjuntoFolclorico;
use Aplicacion\Folclore\InscripcionConjuntoEdicion;
use PDO;

class ConjuntoFolcloricoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ConjuntoFolclorico $conjunto): ConjuntoFolclorico
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `conjuntos_folcloricos` (
                `organizacion_id`, `tipo_danza_id`, `codigo`, `nombre`, `sigla`,
                `fecha_fundacion`, `nombre_presidente`, `telefono_contacto`,
                `color_emblematico_hex`, `resena_historica`, `activo`, `creado_por`
            ) VALUES (
                :org_id, :tipo_id, :codigo, :nombre, :sigla,
                :fecha_fundacion, :presidente, :telefono,
                :color, :resena, :activo, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'          => $conjunto->organizacionId,
            'tipo_id'         => $conjunto->tipoDanzaId,
            'codigo'          => strtoupper(trim($conjunto->codigo)),
            'nombre'          => strtoupper(trim($conjunto->nombre)),
            'sigla'           => $conjunto->sigla ? strtoupper(trim($conjunto->sigla)) : null,
            'fecha_fundacion' => $conjunto->fechaFundacion,
            'presidente'      => $conjunto->nombrePresidente,
            'telefono'        => $conjunto->telefonoContacto,
            'color'           => $conjunto->colorEmblematicoHex,
            'resena'          => $conjunto->resenaHistorica,
            'activo'          => $conjunto->activo ? 1 : 0,
            'creado_por'      => $conjunto->creadoPor,
        ]);

        $conjunto->id = (int)$this->pdo->lastInsertId();
        return $conjunto;
    }

    public function buscarPorId(int $id, int $organizacionId): ?ConjuntoFolclorico
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `conjuntos_folcloricos`
            WHERE `id` = :id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ConjuntoFolclorico::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, int $organizacionId): ?ConjuntoFolclorico
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `conjuntos_folcloricos`
            WHERE `codigo` = :codigo AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['codigo' => strtoupper(trim($codigo)), 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ConjuntoFolclorico::desdeArreglo($fila) : null;
    }

    /**
     * @return array<ConjuntoFolclorico>
     */
    public function listarPorOrganizacion(int $organizacionId, ?int $tipoDanzaId = null, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM `conjuntos_folcloricos` WHERE `organizacion_id` = :org_id";
        $params = ['org_id' => $organizacionId];

        if ($tipoDanzaId !== null) {
            $sql .= " AND `tipo_danza_id` = :tipo_id";
            $params['tipo_id'] = $tipoDanzaId;
        }

        if ($soloActivos) {
            $sql .= " AND `activo` = 1";
        }

        $sql .= " ORDER BY `nombre` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => ConjuntoFolclorico::desdeArreglo($f), $filas);
    }

    // --- BLOQUES ---

    public function crearBloque(BloqueConjunto $bloque): BloqueConjunto
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `bloques_conjunto` (
                `organizacion_id`, `conjunto_id`, `codigo`, `nombre`,
                `responsable_nombre`, `telefono_contacto`, `activo`
            ) VALUES (
                :org_id, :conjunto_id, :codigo, :nombre,
                :responsable, :telefono, :activo
            )
        ");

        $stmt->execute([
            'org_id'      => $bloque->organizacionId,
            'conjunto_id' => $bloque->conjuntoId,
            'codigo'      => strtoupper(trim($bloque->codigo)),
            'nombre'      => strtoupper(trim($bloque->nombre)),
            'responsable' => $bloque->responsableNombre,
            'telefono'    => $bloque->telefonoContacto,
            'activo'      => $bloque->activo ? 1 : 0,
        ]);

        $bloque->id = (int)$this->pdo->lastInsertId();
        return $bloque;
    }

    /**
     * @return array<BloqueConjunto>
     */
    public function listarBloquesPorConjunto(int $conjuntoId, int $organizacionId, bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM `bloques_conjunto` WHERE `conjunto_id` = :conj_id AND `organizacion_id` = :org_id";
        if ($soloActivos) {
            $sql .= " AND `activo` = 1";
        }
        $sql .= " ORDER BY `nombre` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conj_id' => $conjuntoId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => BloqueConjunto::desdeArreglo($f), $filas);
    }

    // --- PARTICIPACIÓN EN EDICIÓN ---

    public function inscribirEnEdicion(InscripcionConjuntoEdicion $inscripcion): InscripcionConjuntoEdicion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `edicion_conjunto_inscripciones` (
                `organizacion_id`, `edicion_id`, `conjunto_id`, `numero_registro_oficial`,
                `nombre_delegado_edicion`, `telefono_delegado_edicion`,
                `cantidad_estimada_danzarines`, `cantidad_bandas`, `estado`,
                `observaciones`, `creado_por`
            ) VALUES (
                :org_id, :ed_id, :conjunto_id, :registro,
                :delegado, :telefono,
                :danzarines, :bandas, :estado,
                :obs, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'      => $inscripcion->organizacionId,
            'ed_id'       => $inscripcion->edicionId,
            'conjunto_id' => $inscripcion->conjuntoId,
            'registro'    => $inscripcion->numeroRegistroOficial,
            'delegado'    => $inscripcion->nombreDelegadoEdicion,
            'telefono'    => $inscripcion->telefonoDelegadoEdicion,
            'danzarines'  => $inscripcion->cantidadEstimadaDanzarines,
            'bandas'      => $inscripcion->cantidadBandas,
            'estado'      => $inscripcion->estado->value,
            'obs'         => $inscripcion->observaciones,
            'creado_por'  => $inscripcion->creadoPor,
        ]);

        $inscripcion->id = (int)$this->pdo->lastInsertId();
        return $inscripcion;
    }

    public function buscarInscripcion(int $edicionId, int $conjuntoId, int $organizacionId): ?InscripcionConjuntoEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nombre as conjunto_nombre, c.codigo as conjunto_codigo
            FROM `edicion_conjunto_inscripciones` i
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            WHERE i.edicion_id = :ed_id AND i.conjunto_id = :conj_id AND i.organizacion_id = :org_id
            LIMIT 1
        ");
        $stmt->execute(['ed_id' => $edicionId, 'conj_id' => $conjuntoId, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? InscripcionConjuntoEdicion::desdeArreglo($fila) : null;
    }

    public function buscarInscripcionPorId(int $id, int $organizacionId): ?InscripcionConjuntoEdicion
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nombre as conjunto_nombre, c.codigo as conjunto_codigo
            FROM `edicion_conjunto_inscripciones` i
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            WHERE i.id = :id AND i.organizacion_id = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? InscripcionConjuntoEdicion::desdeArreglo($fila) : null;
    }

    /**
     * @return array<InscripcionConjuntoEdicion>
     */
    public function listarInscritosPorEdicion(int $edicionId, int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nombre as conjunto_nombre, c.codigo as conjunto_codigo
            FROM `edicion_conjunto_inscripciones` i
            JOIN `conjuntos_folcloricos` c ON i.conjunto_id = c.id
            WHERE i.edicion_id = :ed_id AND i.organizacion_id = :org_id
            ORDER BY c.nombre ASC
        ");
        $stmt->execute(['ed_id' => $edicionId, 'org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => InscripcionConjuntoEdicion::desdeArreglo($f), $filas);
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Edicion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio relacional para la entidad de dominio Edicion.
 *
 * Mandato de Gobernanza:
 * - Prohibición absoluta de métodos de eliminación física (DELETE/TRUNCATE).
 * - Las ediciones son contenedores históricos inmutables una vez cerradas.
 * - Soporte nativo de concurrencia optimista vía actualizado_en.
 */
class EdicionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Inserta una nueva edición Candelaria en la base de datos.
     */
    public function crear(Edicion $edicion): int
    {
        $sql = "INSERT INTO `ediciones_candelaria` (
                    `organizacion_id`, `codigo`, `nombre`, `anio`, `estado`,
                    `fecha_inicio`, `fecha_fin`, `descripcion`, `es_actual`,
                    `flyer_oficial_url`, `configuracion_json`
                ) VALUES (
                    :organizacion_id, :codigo, :nombre, :anio, :estado,
                    :fecha_inicio, :fecha_fin, :descripcion, :es_actual,
                    :flyer_oficial_url, :configuracion_json
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'    => $edicion->organizacionId,
            ':codigo'             => $edicion->codigo,
            ':nombre'             => $edicion->nombre,
            ':anio'               => $edicion->anio,
            ':estado'             => $edicion->estado->value,
            ':fecha_inicio'       => $edicion->fechaInicio,
            ':fecha_fin'          => $edicion->fechaFin,
            ':descripcion'        => $edicion->descripcion,
            ':es_actual'          => $edicion->esActual ? 1 : 0,
            ':flyer_oficial_url'  => $edicion->flyerOficialUrl,
            ':configuracion_json' => $edicion->configuracion !== null ? json_encode($edicion->configuracion, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza atributos mutables de una edición con soporte de concurrencia optimista.
     *
     * @throws ConflictoConcurrenciaExcepcion
     */
    public function actualizar(Edicion $edicion, ?string $actualizadoEnEsperado = null): bool
    {
        if ($edicion->id === null) {
            return false;
        }

        if ($actualizadoEnEsperado !== null) {
            $sqlCheck = "SELECT `actualizado_en` FROM `ediciones_candelaria` WHERE `id` = :id";
            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute([':id' => $edicion->id]);
            $actualizadoEnActual = $stmtCheck->fetchColumn();

            if ($actualizadoEnActual !== false && $actualizadoEnActual !== null) {
                $tsEsperado = strtotime($actualizadoEnEsperado);
                $tsActual = strtotime((string) $actualizadoEnActual);

                if ($tsEsperado !== false && $tsActual !== false && $tsEsperado !== $tsActual) {
                    throw new ConflictoConcurrenciaExcepcion(
                        "Conflicto de concurrencia: la edición fue modificada por otro usuario ({$actualizadoEnActual}). Actualice la vista antes de intentar guardar."
                    );
                }
            }
        }

        $sql = "UPDATE `ediciones_candelaria`
                SET `nombre`             = :nombre,
                    `estado`             = :estado,
                    `fecha_inicio`       = :fecha_inicio,
                    `fecha_fin`          = :fecha_fin,
                    `descripcion`        = :descripcion,
                    `flyer_oficial_url`  = :flyer_oficial_url,
                    `configuracion_json` = :configuracion_json
                WHERE `id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'                 => $edicion->id,
            ':organizacion_id'    => $edicion->organizacionId,
            ':nombre'             => $edicion->nombre,
            ':estado'             => $edicion->estado->value,
            ':fecha_inicio'       => $edicion->fechaInicio,
            ':fecha_fin'          => $edicion->fechaFin,
            ':descripcion'        => $edicion->descripcion,
            ':flyer_oficial_url'  => $edicion->flyerOficialUrl,
            ':configuracion_json' => $edicion->configuracion !== null ? json_encode($edicion->configuracion, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * Busca una edición por su ID primario.
     */
    public function buscarPorId(int $id): ?Edicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Busca una edición por su código slug dentro de una organización.
     */
    public function buscarPorCodigo(int $organizacionId, string $codigo): ?Edicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `codigo` = :codigo");
        $stmt->execute([':org_id' => $organizacionId, ':codigo' => strtolower(trim($codigo))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Busca una edición por su año dentro de una organización.
     */
    public function buscarPorAnio(int $organizacionId, int $anio): ?Edicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `anio` = :anio");
        $stmt->execute([':org_id' => $organizacionId, ':anio' => $anio]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Retorna la edición operativa actual por defecto de la organización.
     */
    public function obtenerActual(int $organizacionId): ?Edicion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `es_actual` = 1 LIMIT 1");
        $stmt->execute([':org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Lista todas las ediciones de una organización ordenadas cronológicamente descendente.
     * @return Edicion[]
     */
    public function listarPorOrganizacion(int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id ORDER BY `anio` DESC");
        $stmt->execute([':org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($f) => $this->hidratar($f), $filas);
    }

    /**
     * Establece de forma atómica y exclusiva una edición como la actual de la organización.
     */
    public function establecerComoActual(int $id, int $organizacionId): void
    {
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            // 0. Bloqueo pesimista sobre la organización para serializar concurrencia estricta por tenant
            $stmtLock = $this->pdo->prepare("SELECT `id` FROM `organizaciones` WHERE `id` = :org_id FOR UPDATE");
            $stmtLock->execute([':org_id' => $organizacionId]);

            // 1. Desmarcar todas las ediciones del tenant
            $stmtReset = $this->pdo->prepare("UPDATE `ediciones_candelaria` SET `es_actual` = 0 WHERE `organizacion_id` = :org_id");
            $stmtReset->execute([':org_id' => $organizacionId]);

            // 2. Marcar la edición seleccionada
            $stmtSet = $this->pdo->prepare("UPDATE `ediciones_candelaria` SET `es_actual` = 1 WHERE `id` = :id AND `organizacion_id` = :org_id");
            $stmtSet->execute([':id' => $id, ':org_id' => $organizacionId]);

            if ($transaccionPropia) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Comprueba si un año ya se encuentra registrado en una organización.
     */
    public function existeAnio(int $organizacionId, int $anio, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `anio` = :anio";
        $params = [':org_id' => $organizacionId, ':anio' => $anio];

        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Comprueba si un código ya se encuentra registrado en una organización.
     */
    public function existeCodigo(int $organizacionId, string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `ediciones_candelaria` WHERE `organizacion_id` = :org_id AND `codigo` = :codigo";
        $params = [':org_id' => $organizacionId, ':codigo' => strtolower(trim($codigo))];

        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Hidrata una fila relacional a una instancia inmutable de Edicion.
     */
    private function hidratar(array $f): Edicion
    {
        $configuracion = null;
        if (!empty($f['configuracion_json'])) {
            $configuracion = is_array($f['configuracion_json'])
                ? $f['configuracion_json']
                : json_decode((string) $f['configuracion_json'], true);
        }

        return new Edicion(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            codigo: (string) $f['codigo'],
            nombre: (string) $f['nombre'],
            anio: (int) $f['anio'],
            fechaInicio: (string) $f['fecha_inicio'],
            fechaFin: (string) $f['fecha_fin'],
            estado: (string) $f['estado'],
            descripcion: $f['descripcion'] !== null ? (string) $f['descripcion'] : null,
            esActual: (bool) $f['es_actual'],
            flyerOficialUrl: $f['flyer_oficial_url'] !== null ? (string) $f['flyer_oficial_url'] : null,
            configuracion: $configuracion,
            creadoEn: (string) $f['creado_en'],
            actualizadoEn: (string) $f['actualizado_en']
        );
    }
}

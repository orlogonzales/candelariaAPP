<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ParametroConfiguracion;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión y persistencia de parámetros tipados de configuración.
 * Distingue explícitamente entre el ámbito PLATAFORMA y ORGANIZACION.
 */
class ConfiguracionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Busca un parámetro específico según su ámbito (Plataforma si organizacionId es null, u Organización).
     */
    public function buscarParametro(string $codigo, ?int $organizacionId = null): ?ParametroConfiguracion
    {
        if ($organizacionId === null) {
            $sql = "SELECT * FROM `parametros_configuracion`
                    WHERE `ambito` = 'PLATAFORMA' AND `organizacion_id` IS NULL AND `codigo` = :codigo
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':codigo' => trim($codigo)]);
        } else {
            $sql = "SELECT * FROM `parametros_configuracion`
                    WHERE `ambito` = 'ORGANIZACION' AND `organizacion_id` = :org_id AND `codigo` = :codigo
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':org_id' => $organizacionId, ':codigo' => trim($codigo)]);
        }

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? ParametroConfiguracion::desdeArreglo($fila) : null;
    }

    /**
     * Comprueba si un parámetro existe en el catálogo gobernado.
     */
    public function existeParametro(string $codigo, ?int $organizacionId = null): bool
    {
        if ($organizacionId === null) {
            $sql = "SELECT 1 FROM `parametros_configuracion`
                    WHERE `ambito` = 'PLATAFORMA' AND `organizacion_id` IS NULL AND `codigo` = :codigo
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':codigo' => trim($codigo)]);
        } else {
            $sql = "SELECT 1 FROM `parametros_configuracion`
                    WHERE `ambito` = 'ORGANIZACION' AND `organizacion_id` = :org_id AND `codigo` = :codigo
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':org_id' => $organizacionId, ':codigo' => trim($codigo)]);
        }

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Retorna todos los parámetros soberanos del ámbito PLATAFORMA.
     * @return ParametroConfiguracion[]
     */
    public function obtenerParametrosPlataforma(): array
    {
        $sql = "SELECT * FROM `parametros_configuracion`
                WHERE `ambito` = 'PLATAFORMA' AND `organizacion_id` IS NULL
                ORDER BY `id` ASC";
        $stmt = $this->pdo->query($sql);

        return array_map(fn(array $f) => ParametroConfiguracion::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Retorna todos los parámetros del ámbito ORGANIZACION para un tenant dado.
     * @return ParametroConfiguracion[]
     */
    public function obtenerParametrosOrganizacion(int $organizacionId): array
    {
        $sql = "SELECT * FROM `parametros_configuracion`
                WHERE `ambito` = 'ORGANIZACION' AND `organizacion_id` = :org_id
                ORDER BY `id` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':org_id' => $organizacionId]);

        return array_map(fn(array $f) => ParametroConfiguracion::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Actualiza el valor de un parámetro existente.
     */
    public function actualizarValor(string $codigo, ?int $organizacionId, ?string $nuevoValor): bool
    {
        if ($organizacionId === null) {
            $sql = "UPDATE `parametros_configuracion`
                    SET `valor` = :valor
                    WHERE `ambito` = 'PLATAFORMA' AND `organizacion_id` IS NULL AND `codigo` = :codigo";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':valor' => $nuevoValor, ':codigo' => trim($codigo)]);
        }

        $sql = "UPDATE `parametros_configuracion`
                SET `valor` = :valor
                WHERE `ambito` = 'ORGANIZACION' AND `organizacion_id` = :org_id AND `codigo` = :codigo";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':valor' => $nuevoValor, ':org_id' => $organizacionId, ':codigo' => trim($codigo)]);
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ClienteCodigoAcceso;
use PDO;

class ClienteCodigoAccesoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ClienteCodigoAcceso $codigo): ClienteCodigoAcceso
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cliente_codigos_acceso` (
                `organizacion_id`, `cliente_id`, `tipo_acceso`,
                `codigo_hash`, `destino`, `utilizado`,
                `intentos_fallidos`, `expira_en`
            ) VALUES (
                :org_id, :cliente_id, :tipo,
                :hash, :destino, :utilizado,
                :intentos, :expira_en
            )
        ");

        $stmt->execute([
            'org_id'     => $codigo->organizacionId,
            'cliente_id' => $codigo->clienteId,
            'tipo'       => $codigo->tipoAcceso,
            'hash'       => $codigo->codigoHash,
            'destino'    => $codigo->destino,
            'utilizado'  => $codigo->utilizado ? 1 : 0,
            'intentos'   => $codigo->intentosFallidos,
            'expira_en'  => $codigo->expiraEn,
        ]);

        $codigo->id = (int) $this->pdo->lastInsertId();
        return $codigo;
    }

    public function buscarActivoPorHash(
        int $organizacionId,
        int $clienteId,
        string $tipoAcceso,
        string $codigoHash
    ): ?ClienteCodigoAcceso {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cliente_codigos_acceso`
            WHERE `organizacion_id` = :org_id
              AND `cliente_id` = :cliente_id
              AND `tipo_acceso` = :tipo
              AND `codigo_hash` = :hash
              AND `utilizado` = 0
              AND `intentos_fallidos` < 3
              AND `expira_en` > NOW()
            ORDER BY `id` DESC
            LIMIT 1
        ");
        $stmt->execute([
            'org_id'     => $organizacionId,
            'cliente_id' => $clienteId,
            'tipo'       => $tipoAcceso,
            'hash'       => $codigoHash,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ClienteCodigoAcceso::desdeArreglo($fila) : null;
    }

    public function buscarUltimoActivoPorDestino(
        int $organizacionId,
        string $tipoAcceso,
        string $destino
    ): ?ClienteCodigoAcceso {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cliente_codigos_acceso`
            WHERE `organizacion_id` = :org_id
              AND `tipo_acceso` = :tipo
              AND `destino` = :destino
              AND `utilizado` = 0
              AND `intentos_fallidos` < 3
              AND `expira_en` > NOW()
            ORDER BY `id` DESC
            LIMIT 1
        ");
        $stmt->execute([
            'org_id'  => $organizacionId,
            'tipo'    => $tipoAcceso,
            'destino' => $destino,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ClienteCodigoAcceso::desdeArreglo($fila) : null;
    }

    public function incrementarIntentosFallidos(int $id): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cliente_codigos_acceso`
            SET `intentos_fallidos` = `intentos_fallidos` + 1
            WHERE `id` = :id
        ");
        $stmt->execute(['id' => $id]);

        $stmtSel = $this->pdo->prepare("SELECT `intentos_fallidos` FROM `cliente_codigos_acceso` WHERE `id` = :id");
        $stmtSel->execute(['id' => $id]);

        return (int) $stmtSel->fetchColumn();
    }

    /**
     * Marca un código como utilizado mediante una operación estrictamente atómica de un solo uso.
     * Retorna true si y solo si la fila pasó de utilizado=0 a utilizado=1 (evitando race conditions).
     */
    public function marcarUtilizado(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cliente_codigos_acceso`
            SET `utilizado` = 1
            WHERE `id` = :id AND `utilizado` = 0
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function contarSolicitudesRecientes(
        int $organizacionId,
        string $destino,
        int $minutosVentana = 15
    ): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM `cliente_codigos_acceso`
            WHERE `organizacion_id` = :org_id
              AND `destino` = :destino
              AND `creado_en` >= (NOW() - INTERVAL :minutos MINUTE)
        ");
        $stmt->execute([
            'org_id'  => $organizacionId,
            'destino' => $destino,
            'minutos' => $minutosVentana,
        ]);

        return (int) $stmt->fetchColumn();
    }
}

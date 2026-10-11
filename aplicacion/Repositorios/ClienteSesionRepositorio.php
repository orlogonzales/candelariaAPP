<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ClienteSesion;
use PDO;

class ClienteSesionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ClienteSesion $sesion): ClienteSesion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cliente_sesiones` (
                `organizacion_id`, `cliente_id`, `token_sesion_hash`,
                `ip_creacion`, `user_agent`, `expira_en`, `revocado`
            ) VALUES (
                :org_id, :cliente_id, :token_hash,
                :ip, :ua, :expira_en, :revocado
            )
        ");

        $stmt->execute([
            'org_id'     => $sesion->organizacionId,
            'cliente_id' => $sesion->clienteId,
            'token_hash' => $sesion->tokenSesionHash,
            'ip'         => $sesion->ipCreacion,
            'ua'         => $sesion->userAgent,
            'expira_en'  => $sesion->expiraEn,
            'revocado'   => $sesion->revocado ? 1 : 0,
        ]);

        $sesion->id = (int) $this->pdo->lastInsertId();
        return $sesion;
    }

    public function buscarPorTokenHash(string $tokenHash): ?ClienteSesion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cliente_sesiones`
            WHERE `token_sesion_hash` = :hash
              AND `revocado` = 0
              AND `expira_en` > NOW()
            LIMIT 1
        ");
        $stmt->execute(['hash' => $tokenHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ClienteSesion::desdeArreglo($fila) : null;
    }

    public function actualizarUltimoAcceso(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cliente_sesiones`
            SET `ultimo_acceso_en` = NOW()
            WHERE `id` = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function revocar(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cliente_sesiones`
            SET `revocado` = 1
            WHERE `id` = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function revocarPorCliente(int $organizacionId, int $clienteId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cliente_sesiones`
            SET `revocado` = 1
            WHERE `organizacion_id` = :org_id AND `cliente_id` = :cliente_id
        ");
        $stmt->execute([
            'org_id'     => $organizacionId,
            'cliente_id' => $clienteId,
        ]);

        return $stmt->rowCount();
    }
}

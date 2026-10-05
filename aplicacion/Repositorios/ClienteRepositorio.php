<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Clientes\EstadoCliente;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Soporte\NormalizadorTelefono;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión del perfil comercial de clientes.
 * Prohíbe categóricamente el borrado físico; toda desactivación es un cambio de estado comercial.
 */
class ClienteRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra un nuevo perfil comercial de cliente para una persona.
     */
    public function crear(Cliente $cliente): int
    {
        $sql = "INSERT INTO `clientes` (
                    `organizacion_id`, `persona_id`, `estado_comercial`,
                    `consentimiento_operativo`, `consentimiento_operativo_en`,
                    `consentimiento_promocional`, `consentimiento_promocional_en`,
                    `notas_comerciales`
                ) VALUES (
                    :organizacion_id, :persona_id, :estado_comercial,
                    :consentimiento_operativo, :consentimiento_operativo_en,
                    :consentimiento_promocional, :consentimiento_promocional_en,
                    :notas_comerciales
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'             => $cliente->organizacionId,
            ':persona_id'                  => $cliente->personaId,
            ':estado_comercial'            => $cliente->estadoComercial->value,
            ':consentimiento_operativo'    => $cliente->consentimientoOperativo ? 1 : 0,
            ':consentimiento_operativo_en' => $cliente->consentimientoOperativoEn,
            ':consentimiento_promocional'  => $cliente->consentimientoPromocional ? 1 : 0,
            ':consentimiento_promocional_en'=> $cliente->consentimientoPromocionalEn,
            ':notas_comerciales'           => $cliente->notasComerciales !== null ? trim($cliente->notasComerciales) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca un cliente por su ID primario.
     */
    public function buscarPorId(int $id): ?Cliente
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `clientes` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Busca un cliente por ID con bloqueo pesimista FOR UPDATE.
     */
    public function buscarPorIdBloqueante(int $id): ?Cliente
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `clientes` WHERE `id` = :id LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Busca un perfil comercial por persona dentro del tenant.
     */
    public function buscarPorPersona(int $organizacionId, int $personaId): ?Cliente
    {
        $sql = "SELECT * FROM `clientes`
                WHERE `organizacion_id` = :organizacion_id
                  AND `persona_id` = :persona_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':persona_id'      => $personaId,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Busca un perfil comercial por persona con bloqueo pesimista FOR UPDATE.
     */
    public function buscarPorPersonaBloqueante(int $organizacionId, int $personaId): ?Cliente
    {
        $sql = "SELECT * FROM `clientes`
                WHERE `organizacion_id` = :organizacion_id
                  AND `persona_id` = :persona_id
                LIMIT 1 FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id' => $organizacionId,
            ':persona_id'      => $personaId,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Busca si ya existe un cliente activo/comercial en la organización con el WhatsApp normalizado indicado.
     * Útil para asegurar la invariante: 1 WhatsApp normalizado = máximo 1 perfil comercial activo por organización.
     */
    public function buscarPorWhatsapp(int $organizacionId, string $telefonoWhatsapp): ?Cliente
    {
        $whatsappNormalizado = NormalizadorTelefono::normalizar($telefonoWhatsapp) ?? trim($telefonoWhatsapp);

        $sql = "SELECT c.*
                FROM `clientes` c
                INNER JOIN `personas` p ON p.id = c.persona_id
                WHERE c.organizacion_id = :organizacion_id
                  AND p.telefono_whatsapp = :telefono_whatsapp
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'    => $organizacionId,
            ':telefono_whatsapp' => $whatsappNormalizado,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Busca cliente por WhatsApp con bloqueo pesimista FOR UPDATE.
     */
    public function buscarPorWhatsappBloqueante(int $organizacionId, string $telefonoWhatsapp): ?Cliente
    {
        $whatsappNormalizado = NormalizadorTelefono::normalizar($telefonoWhatsapp) ?? trim($telefonoWhatsapp);

        $sql = "SELECT c.*
                FROM `clientes` c
                INNER JOIN `personas` p ON p.id = c.persona_id
                WHERE c.organizacion_id = :organizacion_id
                  AND p.telefono_whatsapp = :telefono_whatsapp
                LIMIT 1 FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'    => $organizacionId,
            ':telefono_whatsapp' => $whatsappNormalizado,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Cliente::desdeArreglo($fila) : null;
    }

    /**
     * Actualiza el estado comercial gobernado.
     */
    public function actualizarEstadoComercial(int $clienteId, EstadoCliente $nuevoEstado): bool
    {
        $sql = "UPDATE `clientes`
                SET `estado_comercial` = :estado_comercial
                WHERE `id` = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'               => $clienteId,
            ':estado_comercial' => $nuevoEstado->value,
        ]);
    }

    /**
     * Actualiza el estado materializado de los consentimientos operativos y promocionales.
     */
    public function actualizarConsentimientos(
        int $clienteId,
        bool $operativo,
        ?string $operativoEn,
        bool $promocional,
        ?string $promocionalEn
    ): bool {
        $sql = "UPDATE `clientes`
                SET `consentimiento_operativo`    = :operativo,
                    `consentimiento_operativo_en` = :operativo_en,
                    `consentimiento_promocional`  = :promocional,
                    `consentimiento_promocional_en`= :promocional_en
                WHERE `id` = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'             => $clienteId,
            ':operativo'      => $operativo ? 1 : 0,
            ':operativo_en'   => $operativoEn,
            ':promocional'    => $promocional ? 1 : 0,
            ':promocional_en' => $promocionalEn,
        ]);
    }

    /**
     * Actualiza notas comerciales generales del cliente.
     */
    public function actualizarNotasComerciales(int $clienteId, ?string $notasComerciales): bool
    {
        $sql = "UPDATE `clientes`
                SET `notas_comerciales` = :notas
                WHERE `id` = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'     => $clienteId,
            ':notas'  => $notasComerciales !== null ? trim($notasComerciales) : null,
        ]);
    }

    /**
     * Retorna clientes paginados de un tenant.
     * @return Cliente[]
     */
    public function buscarPorOrganizacion(
        int $organizacionId,
        ?EstadoCliente $estado = null,
        int $limite = 50,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM `clientes`
                WHERE `organizacion_id` = :organizacion_id";

        if ($estado !== null) {
            $sql .= " AND `estado_comercial` = :estado";
        }

        $sql .= " ORDER BY `id` DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':organizacion_id', $organizacionId, PDO::PARAM_INT);
        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado->value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn(array $f) => Cliente::desdeArreglo($f), $stmt->fetchAll());
    }

    /**
     * Cuenta total de clientes en la organización.
     */
    public function contarPorOrganizacion(int $organizacionId, ?EstadoCliente $estado = null): int
    {
        $sql = "SELECT COUNT(*) FROM `clientes` WHERE `organizacion_id` = :organizacion_id";
        if ($estado !== null) {
            $sql .= " AND `estado_comercial` = :estado";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':organizacion_id', $organizacionId, PDO::PARAM_INT);
        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado->value, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}

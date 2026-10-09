<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\ComunicacionConversacion;
use PDO;

class ComunicacionConversacionRepositorio
{
    public function __construct(private PDO $pdo) {}

    public function obtenerOCrear(int $orgId, int $clienteId, string $telefonoCliente): ComunicacionConversacion
    {
        // Buscar conversación abierta existente
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_conversaciones
            WHERE organizacion_id = :org_id 
              AND telefono_cliente = :tel 
              AND estado != 'CERRADA'
            ORDER BY numero_conversacion DESC 
            LIMIT 1
        ");
        $stmt->execute(['org_id' => $orgId, 'tel' => $telefonoCliente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila) {
            return $this->hidratar($fila);
        }

        // Obtener siguiente número de conversación
        $stmtNum = $this->pdo->prepare("
            SELECT COALESCE(MAX(numero_conversacion), 0) + 1 
            FROM comunicacion_conversaciones 
            WHERE organizacion_id = :org_id AND telefono_cliente = :tel
        ");
        $stmtNum->execute(['org_id' => $orgId, 'tel' => $telefonoCliente]);
        $siguienteNum = (int) $stmtNum->fetchColumn();

        $stmtIns = $this->pdo->prepare("
            INSERT INTO comunicacion_conversaciones (
                organizacion_id, cliente_id, telefono_cliente, numero_conversacion,
                estado, total_mensajes
            ) VALUES (
                :org_id, :cliente_id, :tel, :num, 'ABIERTA', 0
            )
        ");
        $stmtIns->execute([
            'org_id'     => $orgId,
            'cliente_id' => $clienteId,
            'tel'        => $telefonoCliente,
            'num'        => $siguienteNum
        ]);

        return new ComunicacionConversacion(
            id: (int) $this->pdo->lastInsertId(),
            organizacionId: $orgId,
            clienteId: $clienteId,
            telefonoCliente: $telefonoCliente,
            numeroConversacion: $siguienteNum,
            estado: 'ABIERTA'
        );
    }

    public function buscarPorId(int $id, int $orgId): ?ComunicacionConversacion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comunicacion_conversaciones 
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $orgId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->hidratar($fila) : null;
    }

    public function registrarMensajeEntrante(int $conversacionId, int $orgId, string $timestamp): void
    {
        // Al recibir mensaje del cliente, inicia la ventana gratuita de 24 horas de Meta
        $expira = date('Y-m-d H:i:s', strtotime($timestamp . ' +24 hours'));
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_conversaciones SET
                ultimo_mensaje_cliente_en = :ultimo,
                ventana_servicio_expira_en = :expira,
                total_mensajes = total_mensajes + 1,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute([
            'ultimo' => $timestamp,
            'expira' => $expira,
            'id'     => $conversacionId,
            'org_id' => $orgId
        ]);
    }

    public function cerrar(int $conversacionId, int $orgId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_conversaciones SET
                estado = 'CERRADA',
                cerrada_en = NOW(),
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $conversacionId, 'org_id' => $orgId]);
    }

    public function asignarOperador(int $conversacionId, int $orgId, int $operadorUsuarioId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE comunicacion_conversaciones SET
                operador_usuario_id = :operador_id,
                estado = CASE WHEN estado = 'ABIERTA' THEN 'EN_ATENCION' ELSE estado END,
                actualizado_en = NOW()
            WHERE id = :id AND organizacion_id = :org_id
        ");
        $stmt->execute([
            'operador_id' => $operadorUsuarioId,
            'id'          => $conversacionId,
            'org_id'      => $orgId
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarConDetalles(int $orgId, ?string $estado = null, ?string $busqueda = null, int $limite = 50): array
    {
        $sql = "
            SELECT c.*,
                   COALESCE(
                       NULLIF(TRIM(CONCAT(p.nombres, ' ', COALESCE(p.apellidos, ''))), ''),
                       p.razon_social,
                       c.telefono_cliente
                   ) AS cliente_nombre,
                   p.numero_documento AS cliente_documento,
                   u.nombre_completo AS operador_nombre
            FROM comunicacion_conversaciones c
            LEFT JOIN clientes cl ON cl.id = c.cliente_id AND cl.organizacion_id = c.organizacion_id
            LEFT JOIN personas p ON p.id = cl.persona_id
            LEFT JOIN usuarios u ON u.id = c.operador_usuario_id
            WHERE c.organizacion_id = :org_id
        ";
        $params = ['org_id' => $orgId];

        if (!empty($estado)) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }

        if (!empty($busqueda)) {
            $sql .= " AND (c.telefono_cliente LIKE :busq OR p.nombres LIKE :busq OR p.apellidos LIKE :busq OR p.razon_social LIKE :busq)";
            $params['busq'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY COALESCE(c.ultimo_mensaje_cliente_en, c.creado_en) DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buscarMensajesPorConversacion(int $conversacionId, int $orgId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, p.nombre AS plantilla_nombre
            FROM comunicacion_mensajes m
            LEFT JOIN comunicacion_plantillas p ON p.id = m.plantilla_id
            WHERE m.conversacion_id = :conv_id AND m.organizacion_id = :org_id
            ORDER BY m.id ASC
        ");
        $stmt->execute([
            'conv_id' => $conversacionId,
            'org_id'  => $orgId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hidratar(array $f): ComunicacionConversacion
    {
        return new ComunicacionConversacion(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            clienteId: (int) $f['cliente_id'],
            telefonoCliente: (string) $f['telefono_cliente'],
            numeroConversacion: (int) $f['numero_conversacion'],
            operadorUsuarioId: $f['operador_usuario_id'] !== null ? (int) $f['operador_usuario_id'] : null,
            estado: (string) $f['estado'],
            ultimoMensajeClienteEn: $f['ultimo_mensaje_cliente_en'] !== null ? (string) $f['ultimo_mensaje_cliente_en'] : null,
            ventanaServicioExpiraEn: $f['ventana_servicio_expira_en'] !== null ? (string) $f['ventana_servicio_expira_en'] : null,
            totalMensajes: (int) $f['total_mensajes'],
            creadoEn: (string) $f['creado_en'],
            cerradaEn: $f['cerrada_en'] !== null ? (string) $f['cerrada_en'] : null,
            actualizadoEn: $f['actualizado_en'] !== null ? (string) $f['actualizado_en'] : null
        );
    }
}

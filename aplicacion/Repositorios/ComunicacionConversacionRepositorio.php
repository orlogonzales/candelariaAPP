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

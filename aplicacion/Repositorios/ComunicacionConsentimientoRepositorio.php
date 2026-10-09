<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Comunicaciones\EstadoConsentimiento;
use Aplicacion\Comunicaciones\FinalidadConsentimiento;
use Aplicacion\Entidades\ComunicacionConsentimiento;
use PDO;

class ComunicacionConsentimientoRepositorio
{
    public function __construct(private PDO $pdo) {}

    public function guardar(ComunicacionConsentimiento $c): ComunicacionConsentimiento
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comunicacion_consentimientos_canal (
                organizacion_id, cliente_id, canal, telefono_destino, finalidad,
                estado, origen_evidencia, texto_clausula_aceptada, direccion_ip_registro,
                actor_tipo, usuario_id, correlacion_id, revocado_en
            ) VALUES (
                :org_id, :cliente_id, :canal, :telefono, :finalidad,
                :estado, :origen, :clausula, :ip,
                :actor_tipo, :usuario_id, :correlacion_id, :revocado_en
            )
        ");

        $stmt->execute([
            'org_id'         => $c->organizacionId,
            'cliente_id'     => $c->clienteId,
            'canal'          => $c->canal,
            'telefono'       => $c->telefonoDestino,
            'finalidad'      => $c->finalidad->value,
            'estado'         => $c->estado->value,
            'origen'         => $c->origenEvidencia,
            'clausula'       => $c->textoClausulaAceptada,
            'ip'             => $c->direccionIpRegistro,
            'actor_tipo'     => $c->actorTipo,
            'usuario_id'     => $c->usuarioId,
            'correlacion_id' => $c->correlacionId,
            'revocado_en'    => $c->revocadoEn
        ]);

        $c->id = (int) $this->pdo->lastInsertId();
        return $c;
    }

    /**
     * Regla inequívoca de resolución de consentimiento vigente:
     * El registro más reciente en el tiempo determina el estado actual.
     */
    public function obtenerUltimoEstado(
        int $orgId,
        int $clienteId,
        string $canal,
        FinalidadConsentimiento $finalidad
    ): ?EstadoConsentimiento {
        $stmt = $this->pdo->prepare("
            SELECT estado 
            FROM comunicacion_consentimientos_canal 
            WHERE organizacion_id = :org_id 
              AND cliente_id = :cliente_id 
              AND canal = :canal 
              AND finalidad = :finalidad 
            ORDER BY creado_en DESC, id DESC 
            LIMIT 1
        ");

        $stmt->execute([
            'org_id'     => $orgId,
            'cliente_id' => $clienteId,
            'canal'      => strtoupper($canal),
            'finalidad'  => $finalidad->value
        ]);

        $estadoStr = $stmt->fetchColumn();
        if ($estadoStr === false) {
            return null;
        }

        return EstadoConsentimiento::from((string) $estadoStr);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarConDetalles(
        int $orgId,
        ?string $canal = null,
        ?string $finalidad = null,
        ?string $estado = null,
        int $limite = 100
    ): array {
        $sql = "
            SELECT cc.*,
                   COALESCE(
                       NULLIF(TRIM(CONCAT(p.nombres, ' ', COALESCE(p.apellidos, ''))), ''),
                       p.razon_social,
                       CONCAT('Cliente #', cc.cliente_id)
                   ) AS cliente_nombre,
                   p.numero_documento AS cliente_documento,
                   u.nombre_completo AS usuario_nombre
            FROM comunicacion_consentimientos_canal cc
            LEFT JOIN clientes cl ON cl.id = cc.cliente_id AND cl.organizacion_id = cc.organizacion_id
            LEFT JOIN personas p ON p.id = cl.persona_id
            LEFT JOIN usuarios u ON u.id = cc.usuario_id
            WHERE cc.organizacion_id = :org_id
        ";
        $params = ['org_id' => $orgId];

        if (!empty($canal)) {
            $sql .= " AND cc.canal = :canal";
            $params['canal'] = strtoupper($canal);
        }

        if (!empty($finalidad)) {
            $sql .= " AND cc.finalidad = :finalidad";
            $params['finalidad'] = $finalidad;
        }

        if (!empty($estado)) {
            $sql .= " AND cc.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY cc.id DESC LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

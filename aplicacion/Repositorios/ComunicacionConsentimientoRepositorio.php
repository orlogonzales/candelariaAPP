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
}

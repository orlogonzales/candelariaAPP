<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Comunicaciones\CifradorComunicacion;
use Aplicacion\Comunicaciones\ModoComunicacion;
use Aplicacion\Entidades\ComunicacionConfiguracion;
use PDO;

class ComunicacionConfigRepositorio
{
    private CifradorComunicacion $cifrador;

    public function __construct(private PDO $pdo, ?CifradorComunicacion $cifrador = null)
    {
        $this->cifrador = $cifrador ?? new CifradorComunicacion();
    }

    public function obtenerPorOrganizacion(int $orgId): ?ComunicacionConfiguracion
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, p.codigo AS proveedor_codigo 
            FROM organizacion_comunicacion_config c
            INNER JOIN comunicacion_proveedores p ON p.id = c.proveedor_id
            WHERE c.organizacion_id = :org_id
        ");
        $stmt->execute(['org_id' => $orgId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            return null;
        }

        return $this->hidratar($f);
    }

    public function guardar(
        int $orgId,
        string $proveedorCodigo,
        ModoComunicacion $modo,
        string $numeroTelefonoIdentificador,
        string $webhookVerifyToken,
        ?string $metaPhoneNumberId = null,
        ?string $metaWabaId = null,
        ?string $metaAppId = null,
        ?string $tokenAccesoPlano = null,
        ?string $webhookSecretPlano = null,
        float $presupuestoMensualLimite = 50.00,
        int $limiteMensajesPorSegundo = 10,
        bool $activo = true
    ): ComunicacionConfiguracion {
        // Resolver ID del proveedor por código canónico
        $stmtProv = $this->pdo->prepare("SELECT id FROM comunicacion_proveedores WHERE codigo = :cod");
        $stmtProv->execute(['cod' => strtoupper($proveedorCodigo)]);
        $proveedorId = $stmtProv->fetchColumn();

        if ($proveedorId === false) {
            throw new \InvalidArgumentException("Código de proveedor desconocido: {$proveedorCodigo}");
        }

        $tokenCifrado = !empty($tokenAccesoPlano) ? $this->cifrador->cifrar($tokenAccesoPlano) : null;
        $secretCifrado = !empty($webhookSecretPlano) ? $this->cifrador->cifrar($webhookSecretPlano) : null;
        $tokenVerifyHash = hash('sha256', $webhookVerifyToken);

        $stmt = $this->pdo->prepare("
            INSERT INTO organizacion_comunicacion_config (
                organizacion_id, proveedor_id, modo, numero_telefono_identificador,
                meta_phone_number_id, meta_waba_id, meta_app_id,
                token_acceso_cifrado, webhook_secret_cifrado, webhook_verify_token_hash,
                limite_mensajes_por_segundo, presupuesto_mensual_limite_usd, activo
            ) VALUES (
                :org_id, :prov_id, :modo, :num,
                :phone_id, :waba_id, :app_id,
                :token_cif, :secret_cif, :token_hash,
                :limite_mps, :presupuesto, :activo
            )
            ON DUPLICATE KEY UPDATE
                proveedor_id = VALUES(proveedor_id),
                modo = VALUES(modo),
                numero_telefono_identificador = VALUES(numero_telefono_identificador),
                meta_phone_number_id = VALUES(meta_phone_number_id),
                meta_waba_id = VALUES(meta_waba_id),
                meta_app_id = VALUES(meta_app_id),
                token_acceso_cifrado = COALESCE(VALUES(token_acceso_cifrado), token_acceso_cifrado),
                webhook_secret_cifrado = COALESCE(VALUES(webhook_secret_cifrado), webhook_secret_cifrado),
                webhook_verify_token_hash = VALUES(webhook_verify_token_hash),
                limite_mensajes_por_segundo = VALUES(limite_mensajes_por_segundo),
                presupuesto_mensual_limite_usd = VALUES(presupuesto_mensual_limite_usd),
                activo = VALUES(activo),
                actualizado_en = NOW()
        ");

        $stmt->execute([
            'org_id'      => $orgId,
            'prov_id'     => (int) $proveedorId,
            'modo'        => $modo->value,
            'num'         => $numeroTelefonoIdentificador,
            'phone_id'    => $metaPhoneNumberId,
            'waba_id'     => $metaWabaId,
            'app_id'      => $metaAppId,
            'token_cif'   => $tokenCifrado,
            'secret_cif'  => $secretCifrado,
            'token_hash'  => $tokenVerifyHash,
            'limite_mps'  => $limiteMensajesPorSegundo,
            'presupuesto' => $presupuestoMensualLimite,
            'activo'      => $activo ? 1 : 0
        ]);

        return $this->obtenerPorOrganizacion($orgId);
    }

    public function incrementarGasto(int $orgId, float $montoUsd): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE organizacion_comunicacion_config SET
                gasto_acumulado_mes_actual_usd = gasto_acumulado_mes_actual_usd + :monto,
                actualizado_en = NOW()
            WHERE organizacion_id = :org_id
        ");
        $stmt->execute(['monto' => $montoUsd, 'org_id' => $orgId]);
    }

    public function descifrarSecretos(ComunicacionConfiguracion $c): array
    {
        return [
            'token_acceso'   => !empty($c->tokenAccesoCifrado) ? $this->cifrador->descifrar($c->tokenAccesoCifrado) : '',
            'webhook_secret' => !empty($c->webhookSecretCifrado) ? $this->cifrador->descifrar($c->webhookSecretCifrado) : '',
        ];
    }

    public function verificarChallengeToken(int $orgId, string $tokenCandidato): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT webhook_verify_token_hash 
            FROM organizacion_comunicacion_config 
            WHERE organizacion_id = :org_id
        ");
        $stmt->execute(['org_id' => $orgId]);
        $hashEsperado = $stmt->fetchColumn();

        if (!$hashEsperado) {
            return false;
        }

        $hashCandidato = hash('sha256', $tokenCandidato);
        return hash_equals((string) $hashEsperado, $hashCandidato);
    }

    private function hidratar(array $f): ComunicacionConfiguracion
    {
        return new ComunicacionConfiguracion(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            proveedorId: (int) $f['proveedor_id'],
            proveedorCodigo: (string) $f['proveedor_codigo'],
            modo: ModoComunicacion::from((string) $f['modo']),
            numeroTelefonoIdentificador: (string) $f['numero_telefono_identificador'],
            webhookVerifyTokenHash: (string) $f['webhook_verify_token_hash'],
            metaPhoneNumberId: $f['meta_phone_number_id'] !== null ? (string) $f['meta_phone_number_id'] : null,
            metaWabaId: $f['meta_waba_id'] !== null ? (string) $f['meta_waba_id'] : null,
            metaAppId: $f['meta_app_id'] !== null ? (string) $f['meta_app_id'] : null,
            tokenAccesoCifrado: $f['token_acceso_cifrado'] !== null ? (string) $f['token_acceso_cifrado'] : null,
            webhookSecretCifrado: $f['webhook_secret_cifrado'] !== null ? (string) $f['webhook_secret_cifrado'] : null,
            limiteMensajesPorSegundo: (int) $f['limite_mensajes_por_segundo'],
            presupuestoMensualLimiteUsd: (float) $f['presupuesto_mensual_limite_usd'],
            gastoAcumuladoMesActualUsd: (float) $f['gasto_acumulado_mes_actual_usd'],
            activo: (bool) $f['activo'],
            creadoEn: (string) $f['creado_en'],
            actualizadoEn: $f['actualizado_en'] !== null ? (string) $f['actualizado_en'] : null
        );
    }
}

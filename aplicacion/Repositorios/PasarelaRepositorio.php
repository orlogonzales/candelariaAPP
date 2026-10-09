<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\EdicionPasarela;
use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Entidades\PasarelaPago;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use PDO;

class PasarelaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return PasarelaPago[]
     */
    public function listarCatalogo(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `pasarelas_pago` WHERE `activo` = 1 ORDER BY `id` ASC");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($f) {
            return new PasarelaPago(
                id: (int) $f['id'],
                codigo: (string) $f['codigo'],
                nombre: (string) $f['nombre'],
                descripcion: $f['descripcion'],
                tipoIntegracion: (string) $f['tipo_integracion'],
                protocoloWebhook: (string) $f['protocolo_webhook'],
                soportaReembolsos: (bool) $f['soporta_reembolsos'],
                activo: (bool) $f['activo'],
                creadoEn: $f['creado_en'],
                actualizadoEn: $f['actualizado_en']
            );
        }, $filas);
    }

    public function buscarPasarelaPorCodigo(string $codigo): ?PasarelaPago
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `pasarelas_pago` WHERE `codigo` = :codigo");
        $stmt->execute(['codigo' => strtoupper($codigo)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$f) {
            return null;
        }

        return new PasarelaPago(
            id: (int) $f['id'],
            codigo: (string) $f['codigo'],
            nombre: (string) $f['nombre'],
            descripcion: $f['descripcion'],
            tipoIntegracion: (string) $f['tipo_integracion'],
            protocoloWebhook: (string) $f['protocolo_webhook'],
            soportaReembolsos: (bool) $f['soporta_reembolsos'],
            activo: (bool) $f['activo'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }

    public function buscarOrganizacionPasarelaPorId(int $id, int $organizacionId): ?OrganizacionPasarela
    {
        $stmt = $this->pdo->prepare("
            SELECT op.*, p.codigo AS pasarela_codigo, p.nombre AS pasarela_nombre
            FROM `organizacion_pasarelas` op
            INNER JOIN `pasarelas_pago` p ON p.id = op.pasarela_id
            WHERE op.id = :id AND op.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratarOrganizacionPasarela($f) : null;
    }

    public function buscarOrganizacionPasarelaPorCodigo(int $organizacionId, string $pasarelaCodigo): ?OrganizacionPasarela
    {
        $stmt = $this->pdo->prepare("
            SELECT op.*, p.codigo AS pasarela_codigo, p.nombre AS pasarela_nombre
            FROM `organizacion_pasarelas` op
            INNER JOIN `pasarelas_pago` p ON p.id = op.pasarela_id
            WHERE op.organizacion_id = :org_id AND p.codigo = :codigo
        ");
        $stmt->execute(['org_id' => $organizacionId, 'codigo' => strtoupper($pasarelaCodigo)]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratarOrganizacionPasarela($f) : null;
    }

    /**
     * @return OrganizacionPasarela[]
     */
    public function listarPorOrganizacion(int $organizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT op.*, p.codigo AS pasarela_codigo, p.nombre AS pasarela_nombre
            FROM `organizacion_pasarelas` op
            INNER JOIN `pasarelas_pago` p ON p.id = op.pasarela_id
            WHERE op.organizacion_id = :org_id
            ORDER BY p.nombre ASC
        ");
        $stmt->execute(['org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratarOrganizacionPasarela'], $filas);
    }

    public function guardarConfiguracionOrganizacion(OrganizacionPasarela $config): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `organizacion_pasarelas` (
                `organizacion_id`, `pasarela_id`, `modo`, `identificador_comercio`,
                `credencial_secreta_enc`, `webhook_secreto_enc`, `porcentaje_comision`,
                `comision_fija`, `asume_comision`, `activo`, `version_bloqueo`
            ) VALUES (
                :org_id, :pas_id, :modo, :identificador,
                :credencial, :webhook, :porcentaje,
                :fija, :asume, :activo, 1
            )
            ON DUPLICATE KEY UPDATE
                `modo` = VALUES(`modo`),
                `identificador_comercio` = VALUES(`identificador_comercio`),
                `credencial_secreta_enc` = COALESCE(VALUES(`credencial_secreta_enc`), `credencial_secreta_enc`),
                `webhook_secreto_enc` = COALESCE(VALUES(`webhook_secreto_enc`), `webhook_secreto_enc`),
                `porcentaje_comision` = VALUES(`porcentaje_comision`),
                `comision_fija` = VALUES(`comision_fija`),
                `asume_comision` = VALUES(`asume_comision`),
                `activo` = VALUES(`activo`),
                `version_bloqueo` = `version_bloqueo` + 1
        ");
        $stmt->execute([
            'org_id'        => $config->organizacionId,
            'pas_id'        => $config->pasarelaId,
            'modo'          => $config->modo,
            'identificador' => $config->identificadorComercio,
            'credencial'    => $config->credencialSecretaEnc,
            'webhook'       => $config->webhookSecretoEnc,
            'porcentaje'    => $config->porcentajeComision,
            'fija'          => $config->comisionFija,
            'asume'         => $config->asumeComision->value,
            'activo'        => $config->activo ? 1 : 0,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $existente = $this->buscarOrganizacionPasarelaPorId($config->id ?? 0, $config->organizacionId);
            return $existente ? (int) $existente->id : 0;
        }
        return $id;
    }

    public function habilitarPasarelaEdicion(int $organizacionId, int $edicionId, int $orgPasarelaId, bool $habilitar): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `edicion_pasarelas` (`organizacion_id`, `edicion_id`, `organizacion_pasarela_id`, `habilitado`)
            VALUES (:org_id, :edic_id, :org_pas_id, :hab)
            ON DUPLICATE KEY UPDATE `habilitado` = VALUES(`habilitado`)
        ");
        $stmt->execute([
            'org_id'     => $organizacionId,
            'edic_id'    => $edicionId,
            'org_pas_id' => $orgPasarelaId,
            'hab'        => $habilitar ? 1 : 0,
        ]);
    }

    /**
     * @return EdicionPasarela[]
     */
    public function listarPasarelasHabilitadasEdicion(int $organizacionId, int $edicionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*, p.codigo AS pasarela_codigo, p.nombre AS pasarela_nombre
            FROM `edicion_pasarelas` ep
            INNER JOIN `organizacion_pasarelas` op ON op.id = ep.organizacion_pasarela_id
            INNER JOIN `pasarelas_pago` p ON p.id = op.pasarela_id
            WHERE ep.organizacion_id = :org_id AND ep.edicion_id = :edic_id AND ep.habilitado = 1 AND op.activo = 1
            ORDER BY p.nombre ASC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'edic_id' => $edicionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($f) {
            return new EdicionPasarela(
                id: (int) $f['id'],
                organizacionId: (int) $f['organizacion_id'],
                edicionId: (int) $f['edicion_id'],
                organizacionPasarelaId: (int) $f['organizacion_pasarela_id'],
                habilitado: (bool) $f['habilitado'],
                creadoEn: $f['creado_en'],
                pasarelaCodigo: $f['pasarela_codigo'],
                pasarelaNombre: $f['pasarela_nombre']
            );
        }, $filas);
    }

    private function hidratarOrganizacionPasarela(array $f): OrganizacionPasarela
    {
        return new OrganizacionPasarela(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            pasarelaId: (int) $f['pasarela_id'],
            modo: (string) $f['modo'],
            identificadorComercio: $f['identificador_comercio'],
            credencialSecretaEnc: $f['credencial_secreta_enc'],
            webhookSecretoEnc: $f['webhook_secreto_enc'],
            porcentajeComision: (float) $f['porcentaje_comision'],
            comisionFija: (float) $f['comision_fija'],
            asumeComision: AsumeComisionPasarela::from($f['asume_comision']),
            activo: (bool) $f['activo'],
            versionBloqueo: (int) $f['version_bloqueo'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en'],
            pasarelaCodigo: $f['pasarela_codigo'] ?? null,
            pasarelaNombre: $f['pasarela_nombre'] ?? null
        );
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\CuentaBancariaOrganizacion;
use PDO;

class CuentaBancariaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(CuentaBancariaOrganizacion $cuenta): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cuentas_bancarias_organizacion` (
                `organizacion_id`, `banco_nombre`, `tipo_cuenta`, `moneda`,
                `titular_nombre`, `numero_cuenta`, `codigo_interbancario`,
                `alias_identificador`, `qr_imagen_url`, `instrucciones_pago`, `activo`
            ) VALUES (
                :org_id, :banco, :tipo, :moneda,
                :titular, :num_cuenta, :cci,
                :alias, :qr, :instrucciones, :activo
            )
        ");
        $stmt->execute([
            'org_id'        => $cuenta->organizacionId,
            'banco'         => $cuenta->bancoNombre,
            'tipo'          => $cuenta->tipoCuenta,
            'moneda'        => $cuenta->moneda,
            'titular'       => $cuenta->titularNombre,
            'num_cuenta'    => $cuenta->numeroCuenta,
            'cci'           => $cuenta->codigoInterbancario,
            'alias'         => $cuenta->aliasIdentificador,
            'qr'            => $cuenta->qrImagenUrl,
            'instrucciones' => $cuenta->instruccionesPago,
            'activo'        => $cuenta->activo ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id, int $organizacionId): ?CuentaBancariaOrganizacion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cuentas_bancarias_organizacion`
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        return $f ? $this->hidratar($f) : null;
    }

    /**
     * @return CuentaBancariaOrganizacion[]
     */
    public function listarPorOrganizacion(int $organizacionId, bool $soloActivas = true): array
    {
        $sql = "SELECT * FROM `cuentas_bancarias_organizacion` WHERE `organizacion_id` = :org_id";
        if ($soloActivas) {
            $sql .= " AND `activo` = 1";
        }
        $sql .= " ORDER BY `banco_nombre` ASC, `id` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['org_id' => $organizacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hidratar'], $filas);
    }

    public function actualizar(CuentaBancariaOrganizacion $cuenta): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cuentas_bancarias_organizacion`
            SET `banco_nombre` = :banco,
                `tipo_cuenta` = :tipo,
                `titular_nombre` = :titular,
                `numero_cuenta` = :num_cuenta,
                `codigo_interbancario` = :cci,
                `alias_identificador` = :alias,
                `qr_imagen_url` = :qr,
                `instrucciones_pago` = :instrucciones,
                `activo` = :activo
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'id'            => $cuenta->id,
            'org_id'        => $cuenta->organizacionId,
            'banco'         => $cuenta->bancoNombre,
            'tipo'          => $cuenta->tipoCuenta,
            'titular'       => $cuenta->titularNombre,
            'num_cuenta'    => $cuenta->numeroCuenta,
            'cci'           => $cuenta->codigoInterbancario,
            'alias'         => $cuenta->aliasIdentificador,
            'qr'            => $cuenta->qrImagenUrl,
            'instrucciones' => $cuenta->instruccionesPago,
            'activo'        => $cuenta->activo ? 1 : 0,
        ]);
    }

    private function hidratar(array $f): CuentaBancariaOrganizacion
    {
        return new CuentaBancariaOrganizacion(
            id: (int) $f['id'],
            organizacionId: (int) $f['organizacion_id'],
            bancoNombre: (string) $f['banco_nombre'],
            tipoCuenta: (string) $f['tipo_cuenta'],
            moneda: (string) $f['moneda'],
            titularNombre: (string) $f['titular_nombre'],
            numeroCuenta: (string) $f['numero_cuenta'],
            codigoInterbancario: $f['codigo_interbancario'],
            aliasIdentificador: $f['alias_identificador'],
            qrImagenUrl: $f['qr_imagen_url'],
            instruccionesPago: $f['instrucciones_pago'],
            activo: (bool) $f['activo'],
            creadoEn: $f['creado_en'],
            actualizadoEn: $f['actualizado_en']
        );
    }
}

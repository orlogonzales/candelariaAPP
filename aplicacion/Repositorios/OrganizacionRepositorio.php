<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Organizacion;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión y consulta de Organizaciones / Tenants.
 */
class OrganizacionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    public function buscarPorId(int $id): ?Organizacion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `organizaciones` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Organizacion::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?Organizacion
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `organizaciones` WHERE `codigo` = :codigo LIMIT 1");
        $stmt->execute([':codigo' => normalizar_minusculas(trim($codigo))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? Organizacion::desdeArreglo($fila) : null;
    }

    /**
     * Retorna todas las organizaciones registradas.
     * @return Organizacion[]
     */
    public function obtenerTodas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `organizaciones` ORDER BY `id` ASC");
        return array_map(fn(array $f) => Organizacion::desdeArreglo($f), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Actualiza atributos institucionales del perfil de la organización.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $camposPermitidos = [
            'nombre_comercial',
            'razon_social',
            'tipo_documento_id',
            'numero_documento',
            'direccion',
            'codigo_pais',
            'departamento',
            'provincia',
            'distrito',
            'correo_contacto',
            'sitio_web',
            'telefono_contacto',
            'telefono_whatsapp',
            'contacto_nombre',
            'contacto_cargo',
            'logo_url',
            'isotipo_url',
            'marca_configuracion_json',
            'estado',
        ];

        $set = [];
        $params = [':id' => $id];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $set[] = "`{$campo}` = :{$campo}";
                $params[":{$campo}"] = $datos[$campo];
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE `organizaciones` SET " . implode(', ', $set) . " WHERE `id` = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Actualiza los elementos de branding de la organización.
     */
    public function actualizarBranding(int $id, ?string $logoUrl, ?string $isotipoUrl, ?array $marcaConfiguracion): bool
    {
        $sql = "UPDATE `organizaciones` SET
                    `logo_url` = :logo_url,
                    `isotipo_url` = :isotipo_url,
                    `marca_configuracion_json` = :marca_json
                WHERE `id` = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':logo_url'    => $logoUrl,
            ':isotipo_url' => $isotipoUrl,
            ':marca_json'  => $marcaConfiguracion !== null ? json_encode($marcaConfiguracion, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}

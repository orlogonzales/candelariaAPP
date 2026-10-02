<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Persona;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión de identidades en tabla personas.
 * Aplica normalización de datos conforme a Sección 17 de la gobernanza.
 */
class PersonaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra una nueva persona en la base de datos.
     */
    public function crear(Persona $persona): int
    {
        $sql = "INSERT INTO `personas` (
                    `organizacion_id`, `tipo_persona`, `tipo_documento_id`, `numero_documento`,
                    `nombres`, `apellidos`, `razon_social`, `nombre_comercial`,
                    `correo_electronico`, `telefono_movil`, `telefono_whatsapp`,
                    `direccion`, `ciudad`, `codigo_pais`, `estado`, `metadatos_json`
                ) VALUES (
                    :organizacion_id, :tipo_persona, :tipo_documento_id, :numero_documento,
                    :nombres, :apellidos, :razon_social, :nombre_comercial,
                    :correo_electronico, :telefono_movil, :telefono_whatsapp,
                    :direccion, :ciudad, :codigo_pais, :estado, :metadatos_json
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'    => $persona->organizacionId,
            ':tipo_persona'       => $persona->tipoPersona,
            ':tipo_documento_id'  => $persona->tipoDocumentoId,
            ':numero_documento'   => trim($persona->numeroDocumento),
            ':nombres'            => $persona->nombres ? normalizar_mayusculas($persona->nombres) : null,
            ':apellidos'          => $persona->apellidos ? normalizar_mayusculas($persona->apellidos) : null,
            ':razon_social'       => $persona->razonSocial ? normalizar_mayusculas($persona->razonSocial) : null,
            ':nombre_comercial'   => $persona->nombreComercial ? normalizar_mayusculas($persona->nombreComercial) : null,
            ':correo_electronico' => $persona->correoElectronico ? normalizar_minusculas($persona->correoElectronico) : null,
            ':telefono_movil'     => $persona->telefonoMovil ? trim($persona->telefonoMovil) : null,
            ':telefono_whatsapp'  => $persona->telefonoWhatsapp ? trim($persona->telefonoWhatsapp) : null,
            ':direccion'          => $persona->direccion ? normalizar_mayusculas($persona->direccion) : null,
            ':ciudad'             => $persona->ciudad ? normalizar_mayusculas($persona->ciudad) : null,
            ':codigo_pais'        => normalizar_mayusculas($persona->codigoPais),
            ':estado'             => $persona->estado,
            ':metadatos_json'     => $persona->metadatos ? json_encode($persona->metadatos, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca una persona por su identificador primario.
     */
    public function buscarPorId(int $id): ?Persona
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `personas` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? Persona::desdeArreglo($fila) : null;
    }

    /**
     * Busca una persona por documento dentro del tenant correspondiente.
     */
    public function buscarPorDocumento(int $organizacionId, int $tipoDocumentoId, string $numeroDocumento): ?Persona
    {
        $sql = "SELECT * FROM `personas`
                WHERE `organizacion_id` = :organizacion_id
                  AND `tipo_documento_id` = :tipo_documento_id
                  AND `numero_documento` = :numero_documento
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'   => $organizacionId,
            ':tipo_documento_id' => $tipoDocumentoId,
            ':numero_documento'  => trim($numeroDocumento),
        ]);
        $fila = $stmt->fetch();

        return $fila ? Persona::desdeArreglo($fila) : null;
    }

    /**
     * Retorna lista paginada de personas de una organización.
     * @return Persona[]
     */
    public function buscarPorOrganizacion(int $organizacionId, int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM `personas`
                WHERE `organizacion_id` = :organizacion_id
                ORDER BY `id` DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':organizacion_id', $organizacionId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn(array $f) => Persona::desdeArreglo($f), $stmt->fetchAll());
    }
}

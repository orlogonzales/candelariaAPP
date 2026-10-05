<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Persona;
use Aplicacion\Soporte\NormalizadorTelefono;
use Nucleo\BaseDatos\Conexion;
use PDO;

/**
 * Repositorio PDO nativo para gestión de identidades en tabla personas.
 * Aplica normalización de datos conforme a la gobernanza institucional.
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
            ':numero_documento'   => $persona->numeroDocumento !== null ? trim($persona->numeroDocumento) : null,
            ':nombres'            => $persona->nombres ? normalizar_mayusculas($persona->nombres) : null,
            ':apellidos'          => $persona->apellidos ? normalizar_mayusculas($persona->apellidos) : null,
            ':razon_social'       => $persona->razonSocial ? normalizar_mayusculas($persona->razonSocial) : null,
            ':nombre_comercial'   => $persona->nombreComercial ? normalizar_mayusculas($persona->nombreComercial) : null,
            ':correo_electronico' => $persona->correoElectronico ? normalizar_minusculas($persona->correoElectronico) : null,
            ':telefono_movil'     => $persona->telefonoMovil ? trim($persona->telefonoMovil) : null,
            ':telefono_whatsapp'  => $persona->telefonoWhatsapp ? (NormalizadorTelefono::normalizar($persona->telefonoWhatsapp) ?? trim($persona->telefonoWhatsapp)) : null,
            ':direccion'          => $persona->direccion ? normalizar_mayusculas($persona->direccion) : null,
            ':ciudad'             => $persona->ciudad ? normalizar_mayusculas($persona->ciudad) : null,
            ':codigo_pais'        => normalizar_mayusculas($persona->codigoPais),
            ':estado'             => $persona->estado,
            ':metadatos_json'     => $persona->metadatos ? json_encode($persona->metadatos, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza una persona existente.
     */
    public function actualizar(Persona $persona): bool
    {
        if ($persona->id === null) {
            return false;
        }

        $sql = "UPDATE `personas` SET
                    `tipo_persona`       = :tipo_persona,
                    `tipo_documento_id` = :tipo_documento_id,
                    `numero_documento`  = :numero_documento,
                    `nombres`           = :nombres,
                    `apellidos`         = :apellidos,
                    `razon_social`      = :razon_social,
                    `nombre_comercial`  = :nombre_comercial,
                    `correo_electronico`= :correo_electronico,
                    `telefono_movil`    = :telefono_movil,
                    `telefono_whatsapp` = :telefono_whatsapp,
                    `direccion`         = :direccion,
                    `ciudad`            = :ciudad,
                    `codigo_pais`       = :codigo_pais,
                    `estado`            = :estado,
                    `metadatos_json`    = :metadatos_json
                WHERE `id` = :id AND `organizacion_id` = :organizacion_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'                 => $persona->id,
            ':organizacion_id'    => $persona->organizacionId,
            ':tipo_persona'       => $persona->tipoPersona,
            ':tipo_documento_id'  => $persona->tipoDocumentoId,
            ':numero_documento'   => $persona->numeroDocumento !== null ? trim($persona->numeroDocumento) : null,
            ':nombres'            => $persona->nombres ? normalizar_mayusculas($persona->nombres) : null,
            ':apellidos'          => $persona->apellidos ? normalizar_mayusculas($persona->apellidos) : null,
            ':razon_social'       => $persona->razonSocial ? normalizar_mayusculas($persona->razonSocial) : null,
            ':nombre_comercial'   => $persona->nombreComercial ? normalizar_mayusculas($persona->nombreComercial) : null,
            ':correo_electronico' => $persona->correoElectronico ? normalizar_minusculas($persona->correoElectronico) : null,
            ':telefono_movil'     => $persona->telefonoMovil ? trim($persona->telefonoMovil) : null,
            ':telefono_whatsapp'  => $persona->telefonoWhatsapp ? (NormalizadorTelefono::normalizar($persona->telefonoWhatsapp) ?? trim($persona->telefonoWhatsapp)) : null,
            ':direccion'          => $persona->direccion ? normalizar_mayusculas($persona->direccion) : null,
            ':ciudad'             => $persona->ciudad ? normalizar_mayusculas($persona->ciudad) : null,
            ':codigo_pais'        => normalizar_mayusculas($persona->codigoPais),
            ':estado'             => $persona->estado,
            ':metadatos_json'     => $persona->metadatos ? json_encode($persona->metadatos, JSON_UNESCAPED_UNICODE) : null,
        ]);
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
     * Busca una persona por ID con bloqueo pesimista FOR UPDATE.
     */
    public function buscarPorIdBloqueante(int $id): ?Persona
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `personas` WHERE `id` = :id LIMIT 1 FOR UPDATE");
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
     * Busca una persona por WhatsApp dentro de la organización.
     */
    public function buscarPorWhatsapp(int $organizacionId, string $telefonoWhatsapp): ?Persona
    {
        $whatsappNormalizado = NormalizadorTelefono::normalizar($telefonoWhatsapp) ?? trim($telefonoWhatsapp);

        $sql = "SELECT * FROM `personas`
                WHERE `organizacion_id` = :organizacion_id
                  AND `telefono_whatsapp` = :telefono_whatsapp
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'     => $organizacionId,
            ':telefono_whatsapp'  => $whatsappNormalizado,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Persona::desdeArreglo($fila) : null;
    }

    /**
     * Busca una persona por WhatsApp con bloqueo pesimista FOR UPDATE dentro del tenant.
     */
    public function buscarPorWhatsappBloqueante(int $organizacionId, string $telefonoWhatsapp): ?Persona
    {
        $whatsappNormalizado = NormalizadorTelefono::normalizar($telefonoWhatsapp) ?? trim($telefonoWhatsapp);

        $sql = "SELECT * FROM `personas`
                WHERE `organizacion_id` = :organizacion_id
                  AND `telefono_whatsapp` = :telefono_whatsapp
                LIMIT 1 FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':organizacion_id'     => $organizacionId,
            ':telefono_whatsapp'  => $whatsappNormalizado,
        ]);
        $fila = $stmt->fetch();

        return $fila ? Persona::desdeArreglo($fila) : null;
    }

    /**
     * Verifica si un número de WhatsApp ya se encuentra registrado en una persona de la organización.
     */
    public function existeWhatsappEnOrganizacion(int $organizacionId, string $telefonoWhatsapp, ?int $excluirPersonaId = null): bool
    {
        $whatsappNormalizado = NormalizadorTelefono::normalizar($telefonoWhatsapp) ?? trim($telefonoWhatsapp);

        $sql = "SELECT COUNT(*) FROM `personas`
                WHERE `organizacion_id` = :organizacion_id
                  AND `telefono_whatsapp` = :telefono_whatsapp";

        $params = [
            ':organizacion_id'    => $organizacionId,
            ':telefono_whatsapp' => $whatsappNormalizado,
        ];

        if ($excluirPersonaId !== null) {
            $sql .= " AND `id` != :excluir_id";
            $params[':excluir_id'] = $excluirPersonaId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
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

    /**
     * Retorna la lista de personas activas de una organización que aún no tienen una cuenta de usuario vinculada.
     * Reutilizado en modal de creación para vincular persona existente (respetando restricción uk_usuarios_persona).
     * @return array
     */
    public function buscarDisponiblesSinUsuario(int $organizacionId): array
    {
        $sql = "SELECT p.*, td.codigo AS tipo_documento_codigo, td.nombre AS tipo_documento_nombre
                FROM `personas` p
                LEFT JOIN `tipos_documento` td ON td.id = p.tipo_documento_id
                LEFT JOIN `usuarios` u ON u.persona_id = p.id
                WHERE p.organizacion_id = :organizacion_id
                  AND p.estado = 'ACTIVO'
                  AND u.id IS NULL
                ORDER BY p.apellidos ASC, p.nombres ASC, p.razon_social ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':organizacion_id' => $organizacionId]);

        return array_map(function (array $f) {
            $persona = Persona::desdeArreglo($f);
            return [
                'id'                    => $persona->id,
                'tipo_persona'          => $persona->tipoPersona,
                'nombre_completo'       => $persona->obtenerNombreCompleto(),
                'tipo_documento_id'     => $persona->tipoDocumentoId,
                'tipo_documento_codigo' => $f['tipo_documento_codigo'] ?? null,
                'numero_documento'      => $persona->numeroDocumento,
                'correo_electronico'    => $persona->correoElectronico,
                'telefono_whatsapp'     => $persona->telefonoWhatsapp,
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

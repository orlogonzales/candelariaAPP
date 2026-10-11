<?php

declare(strict_types=1);

namespace Aplicacion\Postproduccion;

use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver;
use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoMultimediaInterface;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio Central de Dominio de Postproducción y Galería Multimedia.
 * Gobierna el flujo audiovisual: Proyectos -> Galerías -> Archivos (Fotos/Videos) -> Entregas Parciales -> Descargas.
 * Garantiza aislamiento multitenant estricto, protección anti-IDOR y preservación de acceso histórico en ediciones cerradas.
 */
class PostproduccionServicio
{
    private const TIPOS_MIME_PERMITIDOS = [
        'image/jpeg'        => 'FOTO',
        'image/png'         => 'FOTO',
        'image/webp'        => 'FOTO',
        'image/tiff'        => 'FOTO',
        'video/mp4'         => 'VIDEO',
        'video/quicktime'   => 'VIDEO',
        'video/x-matroska'  => 'VIDEO',
        'video/webm'        => 'VIDEO',
        'application/pdf'   => 'DOCUMENTO',
    ];

    public function __construct(
        private PDO $pdo,
        private ?AlmacenamientoMultimediaInterface $almacenamiento = null
    ) {
        $this->almacenamiento = $almacenamiento ?? new AlmacenamientoLocalDriver();
    }

    /**
     * Registra un nuevo proyecto de postproducción audiovisual.
     */
    public function crearProyecto(
        int $organizacionId,
        int $edicionId,
        int $clienteId,
        string $titulo,
        ?string $descripcion = null,
        ?int $reservaId = null,
        ?int $prestacionId = null,
        ?int $fotografoId = null,
        ?int $editorId = null,
        ?string $fechaLimiteEntrega = null
    ): array {
        // Validar existencia de edición en el tenant
        $stmtEd = $this->pdo->prepare("SELECT id, estado FROM `ediciones_candelaria` WHERE id = :id AND organizacion_id = :org_id");
        $stmtEd->execute(['id' => $edicionId, 'org_id' => $organizacionId]);
        $edicion = $stmtEd->fetch(PDO::FETCH_ASSOC);
        if (!$edicion) {
            throw new InvalidArgumentException("La edición #{$edicionId} no pertenece a su organización.");
        }

        // Validar titular cliente
        $stmtCli = $this->pdo->prepare("SELECT id FROM `clientes` WHERE id = :id AND organizacion_id = :org_id");
        $stmtCli->execute(['id' => $clienteId, 'org_id' => $organizacionId]);
        if (!$stmtCli->fetchColumn()) {
            throw new InvalidArgumentException("El cliente #{$clienteId} no existe en su organización.");
        }

        // Validar reserva si se provee
        if ($reservaId !== null && $reservaId > 0) {
            $stmtRes = $this->pdo->prepare("SELECT id, cliente_id FROM `reservas` WHERE id = :id AND organizacion_id = :org_id");
            $stmtRes->execute(['id' => $reservaId, 'org_id' => $organizacionId]);
            $res = $stmtRes->fetch(PDO::FETCH_ASSOC);
            if (!$res) {
                throw new InvalidArgumentException("La reserva #{$reservaId} no existe en su organización.");
            }
            if ((int) $res['cliente_id'] !== $clienteId) {
                throw new InvalidArgumentException("La reserva #{$reservaId} no pertenece al cliente titular seleccionado.");
            }
        }

        // Generar correlativo PRY-POST-YYYY-NNNN
        $anio = date('Y');
        $stmtSec = $this->pdo->prepare("SELECT COUNT(*) FROM `postproduccion_proyectos` WHERE `organizacion_id` = :org_id");
        $stmtSec->execute(['org_id' => $organizacionId]);
        $secuencia = (int) $stmtSec->fetchColumn() + 1;
        $codigoProyecto = sprintf('PRY-POST-%s-%04d', $anio, $secuencia);

        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_proyectos` (
                `organizacion_id`, `edicion_id`, `reserva_id`, `prestacion_id`, `cliente_id`,
                `codigo_proyecto`, `titulo`, `descripcion`, `estado`,
                `fotografo_usuario_id`, `editor_usuario_id`, `fecha_inicio_edicion`, `fecha_limite_entrega`
            ) VALUES (
                :org_id, :ed_id, :res_id, :prest_id, :cli_id,
                :codigo, :titulo, :descripcion, 'EN_EDICION',
                :fotografo, :editor, CURDATE(), :limite
            )
        ");
        $stmtIns->execute([
            'org_id'      => $organizacionId,
            'ed_id'       => $edicionId,
            'res_id'      => $reservaId,
            'prest_id'    => $prestacionId,
            'cli_id'      => $clienteId,
            'codigo'      => $codigoProyecto,
            'titulo'      => trim($titulo),
            'descripcion' => $descripcion,
            'fotografo'   => $fotografoId,
            'editor'      => $editorId,
            'limite'      => $fechaLimiteEntrega,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->obtenerProyecto($organizacionId, $id);
    }

    /**
     * Crea un álbum o galería temática dentro de un proyecto.
     */
    public function crearGaleria(
        int $organizacionId,
        int $proyectoId,
        string $titulo,
        string $tipoGaleria = 'FOTOGRAFIA',
        ?string $descripcion = null,
        bool $esPublicaCliente = true,
        int $orden = 1
    ): array {
        $proyecto = $this->obtenerProyecto($organizacionId, $proyectoId);
        if (!$proyecto) {
            throw new InvalidArgumentException("El proyecto #{$proyectoId} no existe en su organización.");
        }

        $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $titulo), '-'));
        $slug = $slugBase ?: 'galeria-' . uniqid();

        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_galerias` (
                `organizacion_id`, `proyecto_id`, `titulo`, `slug`, `descripcion`,
                `tipo_galeria`, `es_publica_cliente`, `orden`
            ) VALUES (
                :org_id, :pry_id, :titulo, :slug, :desc,
                :tipo, :publica, :orden
            )
        ");
        $stmtIns->execute([
            'org_id'   => $organizacionId,
            'pry_id'   => $proyectoId,
            'titulo'   => trim($titulo),
            'slug'     => $slug,
            'desc'     => $descripcion,
            'tipo'     => in_array($tipoGaleria, ['FOTOGRAFIA', 'VIDEO', 'MIXTO'], true) ? $tipoGaleria : 'FOTOGRAFIA',
            'publica'  => $esPublicaCliente ? 1 : 0,
            'orden'    => $orden,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->obtenerGaleria($organizacionId, $id);
    }

    /**
     * Sube un archivo fotográfico o de video procesándolo directamente por streams sin cargarlo en memoria.
     *
     * @param resource $streamOrigen Flujo de datos abierto para lectura.
     */
    public function subirArchivoStream(
        int $organizacionId,
        int $proyectoId,
        int $galeriaId,
        string $nombreOriginal,
        $streamOrigen,
        string $mimeType,
        ?int $subidoPorUsuarioId = null,
        ?int $anchoPx = null,
        ?int $altoPx = null,
        ?int $duracionSegundos = null
    ): array {
        // 1. Validar proyecto y galería
        $galeria = $this->obtenerGaleria($organizacionId, $galeriaId);
        if (!$galeria || (int) $galeria['proyecto_id'] !== $proyectoId) {
            throw new InvalidArgumentException("La galería #{$galeriaId} no pertenece al proyecto #{$proyectoId}.");
        }

        // 2. Validación estricta de Whitelist MIME
        $mimeNormalizado = strtolower(trim($mimeType));
        if (!isset(self::TIPOS_MIME_PERMITIDOS[$mimeNormalizado])) {
            throw new InvalidArgumentException("Tipo MIME no permitido para material multimedia: {$mimeNormalizado}. Solo se admiten formatos JPG, PNG, WEBP, TIFF, MP4, MOV, MKV o PDF.");
        }
        $tipoArchivoEnum = self::TIPOS_MIME_PERMITIDOS[$mimeNormalizado];

        // 3. Generar ruta privada unívoca por tenant
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION) ?: ($tipoArchivoEnum === 'FOTO' ? 'jpg' : 'mp4');
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
        $nombreAlmacenamiento = sprintf('%s_%s_%s.%s', strtolower($tipoArchivoEnum), uniqid(), $nombreLimpio, $extension);
        $rutaRelativa = sprintf('%d/proyectos/%d/galerias/%d/%s', $organizacionId, $proyectoId, $galeriaId, $nombreAlmacenamiento);

        // 4. Procesar y almacenar mediante stream en el driver desacoplado
        $guardado = $this->almacenamiento->guardarStream($streamOrigen, $rutaRelativa);

        // 5. Registrar en postproduccion_archivos
        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_archivos` (
                `organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`,
                `nombre_almacenamiento`, `driver_almacenamiento`, `ruta_privada`,
                `tipo_archivo`, `mime_type`, `peso_bytes`, `checksum_sha256`,
                `ancho_px`, `alto_px`, `duracion_segundos`, `version`, `estado`,
                `subido_por_usuario_id`
            ) VALUES (
                :org_id, :pry_id, :gal_id, :nom_orig,
                :nom_alm, 'LOCAL', :ruta,
                :tipo_arch, :mime, :peso, :hash,
                :ancho, :alto, :duracion, 1, 'LISTO',
                :usr_id
            )
        ");
        $stmtIns->execute([
            'org_id'     => $organizacionId,
            'pry_id'     => $proyectoId,
            'gal_id'     => $galeriaId,
            'nom_orig'   => $nombreOriginal,
            'nom_alm'    => $nombreAlmacenamiento,
            'ruta'       => $guardado['ruta_relativa'],
            'tipo_arch'  => $tipoArchivoEnum,
            'mime'       => $mimeNormalizado,
            'peso'       => $guardado['peso_bytes'],
            'hash'       => $guardado['checksum_sha256'],
            'ancho'      => $anchoPx,
            'alto'       => $altoPx,
            'duracion'   => $duracionSegundos,
            'usr_id'     => $subidoPorUsuarioId,
        ]);

        $archivoId = (int) $this->pdo->lastInsertId();

        // 6. Actualizar contadores del proyecto
        $this->pdo->prepare("
            UPDATE `postproduccion_proyectos`
            SET `total_archivos` = `total_archivos` + 1,
                `peso_total_bytes` = `peso_total_bytes` + :peso
            WHERE `id` = :id
        ")->execute(['peso' => $guardado['peso_bytes'], 'id' => $proyectoId]);

        return $this->obtenerArchivo($organizacionId, $archivoId);
    }

    /**
     * Genera una entrega parcial o final vinculando un lote de archivos.
     *
     * @param int[] $archivoIds Lista de identificadores de archivos incluidos en esta entrega.
     */
    public function crearEntrega(
        int $organizacionId,
        int $proyectoId,
        string $titulo,
        string $tipoEntrega = 'PARCIAL',
        array $archivoIds = [],
        ?string $notas = null,
        ?int $usuarioId = null,
        int $diasCaducidad = 30
    ): array {
        $proyecto = $this->obtenerProyecto($organizacionId, $proyectoId);
        if (!$proyecto) {
            throw new InvalidArgumentException("El proyecto #{$proyectoId} no existe en su organización.");
        }

        // Obtener siguiente número de entrega
        $stmtNum = $this->pdo->prepare("
            SELECT COALESCE(MAX(`numero_entrega`), 0) + 1
            FROM `postproduccion_entregas`
            WHERE `organizacion_id` = :org_id AND `proyecto_id` = :pry_id
        ");
        $stmtNum->execute(['org_id' => $organizacionId, 'pry_id' => $proyectoId]);
        $numeroEntrega = (int) $stmtNum->fetchColumn();

        $fechaCaducidad = date('Y-m-d H:i:s', strtotime("+{$diasCaducidad} days"));

        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_entregas` (
                `organizacion_id`, `proyecto_id`, `numero_entrega`, `titulo`,
                `tipo_entrega`, `estado_entrega`, `notas_entrega`,
                `fecha_caducidad_enlace`, `creado_por_usuario_id`
            ) VALUES (
                :org_id, :pry_id, :num, :titulo,
                :tipo, 'PUBLICADA', :notas,
                :caducidad, :usr_id
            )
        ");
        $stmtIns->execute([
            'org_id'    => $organizacionId,
            'pry_id'    => $proyectoId,
            'num'       => $numeroEntrega,
            'titulo'    => trim($titulo),
            'tipo'      => in_array($tipoEntrega, ['PARCIAL', 'FINAL', 'REVISION'], true) ? $tipoEntrega : 'PARCIAL',
            'notas'     => $notas,
            'caducidad' => $fechaCaducidad,
            'usr_id'    => $usuarioId,
        ]);

        $entregaId = (int) $this->pdo->lastInsertId();

        // Vincular archivos a la entrega
        if (!empty($archivoIds)) {
            $stmtVinculo = $this->pdo->prepare("
                INSERT IGNORE INTO `postproduccion_entrega_archivos` (`entrega_id`, `archivo_id`)
                VALUES (:ent_id, :arch_id)
            ");
            foreach ($archivoIds as $aId) {
                // Verificar que el archivo pertenezca al proyecto
                $stmtCheck = $this->pdo->prepare("SELECT id FROM `postproduccion_archivos` WHERE id = :id AND proyecto_id = :pry_id");
                $stmtCheck->execute(['id' => $aId, 'pry_id' => $proyectoId]);
                if ($stmtCheck->fetchColumn()) {
                    $stmtVinculo->execute(['ent_id' => $entregaId, 'arch_id' => $aId]);
                }
            }
        }

        // Si es entrega final, actualizar estado del proyecto
        if ($tipoEntrega === 'FINAL') {
            $this->pdo->prepare("UPDATE `postproduccion_proyectos` SET `estado` = 'LISTO_ENTREGA' WHERE `id` = :id")
                ->execute(['id' => $proyectoId]);
        }

        return $this->obtenerEntrega($organizacionId, $entregaId);
    }

    /**
     * Registra la aprobación o las observaciones del cliente titular sobre una entrega.
     */
    public function responderEntregaCliente(
        int $organizacionId,
        int $entregaId,
        int $clienteId,
        bool $aprobada,
        ?string $observaciones = null
    ): array {
        $entrega = $this->obtenerEntrega($organizacionId, $entregaId);
        if (!$entrega) {
            throw new InvalidArgumentException("La entrega #{$entregaId} no existe en su organización.");
        }

        $proyecto = $this->obtenerProyecto($organizacionId, (int) $entrega['proyecto_id']);
        if ((int) $proyecto['cliente_id'] !== $clienteId) {
            throw new InvalidArgumentException("Acceso denegado: El cliente no es titular del proyecto audiovisual.");
        }

        $nuevoEstado = $aprobada ? 'APROBADA_CLIENTE' : 'OBSERVADA_CLIENTE';
        $stmtUpd = $this->pdo->prepare("
            UPDATE `postproduccion_entregas`
            SET `estado_entrega` = :estado,
                `aprobado_por_cliente_en` = IF(:aprobada = 1, NOW(), NULL),
                `observaciones_cliente` = :obs
            WHERE `id` = :id
        ");
        $stmtUpd->execute([
            'estado'   => $nuevoEstado,
            'aprobada' => $aprobada ? 1 : 0,
            'obs'      => $observaciones,
            'id'       => $entregaId,
        ]);

        return $this->obtenerEntrega($organizacionId, $entregaId);
    }

    /**
     * Retorna el stream de descarga para un archivo asegurando validación anti-IDOR
     * y garantizando que el cierre de una edición NO invalide el acceso a recuerdos históricos legítimos.
     *
     * @return array{stream: resource, archivo: array}
     */
    public function obtenerStreamDescarga(
        int $organizacionId,
        int $archivoId,
        ?int $clienteId = null,
        ?int $usuarioId = null,
        string $ip = '127.0.0.1',
        ?string $userAgent = null
    ): array {
        $archivo = $this->obtenerArchivo($organizacionId, $archivoId);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo solicitado no existe en su organización.");
        }

        $proyecto = $this->obtenerProyecto($organizacionId, (int) $archivo['proyecto_id']);

        // Protección Anti-IDOR: Si es un cliente, validar titularidad
        if ($clienteId !== null && $clienteId > 0) {
            if ((int) $proyecto['cliente_id'] !== $clienteId) {
                throw new InvalidArgumentException("Acceso denegado: No tiene autorización para descargar este contenido multimedia.");
            }
        }

        // Regla Crítica de Edición: El cambio a CERRADA no revoca el acceso histórico del cliente
        // (Se permite la descarga de entregables legítimos sin lanzar excepción por estado CERRADA)

        // Abrir flujo de lectura
        $stream = $this->almacenamiento->obtenerStream($archivo['ruta_privada']);

        // Registrar en bitácora de descargas
        $stmtLog = $this->pdo->prepare("
            INSERT INTO `postproduccion_descargas_log` (
                `organizacion_id`, `archivo_id`, `cliente_id`, `usuario_id`,
                `ip`, `user_agent`, `bytes_transferidos`
            ) VALUES (
                :org_id, :arch_id, :cli_id, :usr_id,
                :ip, :ua, :bytes
            )
        ");
        $stmtLog->execute([
            'org_id'  => $organizacionId,
            'arch_id' => $archivoId,
            'cli_id'  => $clienteId,
            'usr_id'  => $usuarioId,
            'ip'      => $ip,
            'ua'      => substr((string) $userAgent, 0, 255),
            'bytes'   => (int) $archivo['peso_bytes'],
        ]);

        return [
            'stream'  => $stream,
            'archivo' => $archivo,
        ];
    }

    /**
     * Genera un token firmado temporal para descarga segura por URL.
     */
    public function generarTokenTemporalCliente(int $organizacionId, int $archivoId, int $clienteId, int $segundos = 3600): string
    {
        $archivo = $this->obtenerArchivo($organizacionId, $archivoId);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo #{$archivoId} no existe en su organización.");
        }

        $proyecto = $this->obtenerProyecto($organizacionId, (int) $archivo['proyecto_id']);
        if ((int) $proyecto['cliente_id'] !== $clienteId) {
            throw new InvalidArgumentException("Acceso denegado: El cliente no es titular de este archivo.");
        }

        return $this->almacenamiento->generarTokenDescargaTemporal($archivoId, $clienteId, $segundos);
    }

    /**
     * Valida un token temporal y entrega el flujo del archivo.
     */
    public function procesarDescargaPorToken(int $organizacionId, string $token, string $ip, ?string $userAgent): array
    {
        $valido = $this->almacenamiento->validarTokenDescargaTemporal($token);
        if (!$valido) {
            throw new InvalidArgumentException("El enlace de descarga temporal ha caducado o su firma es inválida.");
        }

        return $this->obtenerStreamDescarga(
            $organizacionId,
            $valido['archivo_id'],
            $valido['cliente_id'],
            null,
            $ip,
            $userAgent
        );
    }

    /**
     * Lista los proyectos multimedia de un cliente para su espacio público.
     */
    public function listarProyectosCliente(int $organizacionId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   e.nombre as edicion_nombre, e.anio as edicion_anio, e.estado as edicion_estado,
                   r.correlativo as reserva_correlativo
            FROM `postproduccion_proyectos` p
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            LEFT JOIN `reservas` r ON p.reserva_id = r.id
            WHERE p.organizacion_id = :org_id AND p.cliente_id = :cli_id
            ORDER BY p.id DESC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'cli_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna el detalle completo de un proyecto, sus galerías y entregas para el espacio del cliente.
     */
    public function obtenerDetalleProyectoCliente(int $organizacionId, int $proyectoId, int $clienteId): ?array
    {
        $proyecto = $this->obtenerProyecto($organizacionId, $proyectoId);
        if (!$proyecto || (int) $proyecto['cliente_id'] !== $clienteId) {
            return null;
        }

        // Obtener galerías públicas
        $stmtGal = $this->pdo->prepare("
            SELECT * FROM `postproduccion_galerias`
            WHERE `organizacion_id` = :org_id AND `proyecto_id` = :pry_id AND `es_publica_cliente` = 1
            ORDER BY `orden` ASC, `id` ASC
        ");
        $stmtGal->execute(['org_id' => $organizacionId, 'pry_id' => $proyectoId]);
        $galerias = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

        // Obtener archivos de cada galería
        foreach ($galerias as &$g) {
            $stmtArch = $this->pdo->prepare("
                SELECT `id`, `galeria_id`, `nombre_original`, `tipo_archivo`, `mime_type`,
                       `peso_bytes`, `ancho_px`, `alto_px`, `duracion_segundos`, `es_destacado`, `creado_en`
                FROM `postproduccion_archivos`
                WHERE `galeria_id` = :gal_id AND `estado` = 'LISTO'
                ORDER BY `es_destacado` DESC, `id` ASC
            ");
            $stmtArch->execute(['gal_id' => $g['id']]);
            $g['archivos'] = $stmtArch->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($g);

        // Obtener entregas publicadas
        $stmtEnt = $this->pdo->prepare("
            SELECT * FROM `postproduccion_entregas`
            WHERE `organizacion_id` = :org_id AND `proyecto_id` = :pry_id AND `estado_entrega` != 'PREPARADA'
            ORDER BY `numero_entrega` DESC
        ");
        $stmtEnt->execute(['org_id' => $organizacionId, 'pry_id' => $proyectoId]);
        $entregas = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);

        $proyecto['galerias'] = $galerias;
        $proyecto['entregas'] = $entregas;

        return $proyecto;
    }

    public function obtenerProyecto(int $organizacionId, int $proyectoId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   per.nombres as cliente_nombres, per.apellidos as cliente_apellidos,
                   e.nombre as edicion_nombre, e.anio as edicion_anio, e.estado as edicion_estado
            FROM `postproduccion_proyectos` p
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            JOIN `clientes` cl ON p.cliente_id = cl.id
            JOIN `personas` per ON cl.persona_id = per.id
            WHERE p.id = :id AND p.organizacion_id = :org_id
        ");
        $stmt->execute(['id' => $proyectoId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerGaleria(int $organizacionId, int $galeriaId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `postproduccion_galerias` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $galeriaId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerArchivo(int $organizacionId, int $archivoId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `postproduccion_archivos` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $archivoId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerEntrega(int $organizacionId, int $entregaId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `postproduccion_entregas` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $entregaId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}

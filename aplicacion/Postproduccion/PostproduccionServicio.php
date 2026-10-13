<?php

declare(strict_types=1);

namespace Aplicacion\Postproduccion;

use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoLocalDriver;
use Aplicacion\Postproduccion\Almacenamiento\AlmacenamientoMultimediaInterface;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Servicio Central de Dominio de Postproducción y Galería Multimedia.
 * Gobierna el flujo audiovisual: Proyectos -> Galerías -> Archivos (Fotos/Videos) -> Entregas Parciales -> Descargas.
 * Garantiza aislamiento multitenant estricto, protección anti-IDOR, cuotas de almacenamiento,
 * políticas de retención, supresión autorizada conforme a la Ley 29733 y preservación de acceso histórico en ediciones cerradas.
 */
class PostproduccionServicio
{
    public const CUOTA_DEFECTO_ORG_BYTES = 10737418240; // 10 GB
    public const RETENCION_DEFECTO_DIAS = 730; // 2 años tras la edición

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
        if ($edicion['estado'] === 'CERRADA') {
            throw new InvalidArgumentException("No se pueden aperturar nuevos proyectos audiovisuales en una festividad formalmente CERRADA.");
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
     * Crea una nueva galería o álbum temático dentro de un proyecto.
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
     * Verifica cuota de almacenamiento, tamaño máximo y fecha de retención legal.
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

        // 2. Control de Cuota de Almacenamiento por Organización
        $stmtUso = $this->pdo->prepare("SELECT COALESCE(SUM(`peso_total_bytes`), 0) FROM `postproduccion_proyectos` WHERE `organizacion_id` = :org_id");
        $stmtUso->execute(['org_id' => $organizacionId]);
        $usoActual = (int) $stmtUso->fetchColumn();

        $cuotaMax = self::CUOTA_DEFECTO_ORG_BYTES;
        $stmtCfg = $this->pdo->prepare("SELECT `valor` FROM `parametros_configuracion` WHERE `organizacion_id` = :org_id AND `codigo` = 'postproduccion.cuota_almacenamiento_mb' AND `estado` = 'ACTIVO'");
        $stmtCfg->execute(['org_id' => $organizacionId]);
        $cfgMb = $stmtCfg->fetchColumn();
        if ($cfgMb !== false && is_numeric($cfgMb)) {
            $cuotaMax = (int) $cfgMb * 1024 * 1024;
        }

        if ($usoActual >= $cuotaMax) {
            throw new InvalidArgumentException("Cuota de almacenamiento excedida para la organización (límite: " . round($cuotaMax / (1024 * 1024)) . " MB).");
        }

        // 3. Validación estricta de Whitelist MIME
        $mimeNormalizado = strtolower(trim($mimeType));
        if (!isset(self::TIPOS_MIME_PERMITIDOS[$mimeNormalizado])) {
            throw new InvalidArgumentException("Tipo MIME no permitido para material multimedia: {$mimeNormalizado}. Solo se admiten formatos JPG, PNG, WEBP, TIFF, MP4, MOV, MKV o PDF.");
        }
        $tipoArchivoEnum = self::TIPOS_MIME_PERMITIDOS[$mimeNormalizado];

        // 4. Calcular política de retención
        $stmtDias = $this->pdo->prepare("SELECT `valor` FROM `parametros_configuracion` WHERE `organizacion_id` = :org_id AND `codigo` = 'postproduccion.periodo_retencion_dias' AND `estado` = 'ACTIVO'");
        $stmtDias->execute(['org_id' => $organizacionId]);
        $diasRetencion = (int) ($stmtDias->fetchColumn() ?: self::RETENCION_DEFECTO_DIAS);
        $fechaRetencion = date('Y-m-d', strtotime("+{$diasRetencion} days"));

        // 5. Generar ruta privada unívoca por tenant
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION) ?: ($tipoArchivoEnum === 'FOTO' ? 'jpg' : 'mp4');
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
        $nombreAlmacenamiento = sprintf('%s_%s_%s.%s', strtolower($tipoArchivoEnum), uniqid(), $nombreLimpio, $extension);
        $rutaRelativa = sprintf('%d/proyectos/%d/galerias/%d/%s', $organizacionId, $proyectoId, $galeriaId, $nombreAlmacenamiento);

        // 6. Procesar y almacenar mediante stream en el driver desacoplado (con verificación Magic Bytes interna)
        $guardado = $this->almacenamiento->guardarStream($streamOrigen, $rutaRelativa);

        // 7. Registrar en postproduccion_archivos
        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_archivos` (
                `organizacion_id`, `proyecto_id`, `galeria_id`, `nombre_original`,
                `nombre_almacenamiento`, `driver_almacenamiento`, `ruta_privada`,
                `tipo_archivo`, `mime_type`, `peso_bytes`, `checksum_sha256`,
                `ancho_px`, `alto_px`, `duracion_segundos`, `fecha_retencion_hasta`,
                `version`, `estado`, `subido_por_usuario_id`
            ) VALUES (
                :org_id, :pry_id, :gal_id, :nom_orig,
                :nom_alm, 'LOCAL', :ruta,
                :tipo_arch, :mime, :peso, :hash,
                :ancho, :alto, :duracion, :retencion,
                1, 'ACTIVO', :usr_id
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
            'retencion'  => $fechaRetencion,
            'usr_id'     => $subidoPorUsuarioId,
        ]);

        $archivoId = (int) $this->pdo->lastInsertId();

        // 8. Actualizar contadores del proyecto
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
                `fecha_caducidad_enlace`, `creado_por_usuario_id`, `version_bloqueo`
            ) VALUES (
                :org_id, :pry_id, :num, :titulo,
                :tipo, 'PUBLICADA', :notas,
                :caducidad, :usr_id, 1
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
     * Registra la aprobación o las observaciones del cliente titular sobre una entrega con control de concurrencia optimista.
     */
    public function responderEntregaCliente(
        int $organizacionId,
        int $entregaId,
        int $clienteId,
        bool $aprobada,
        ?string $observaciones = null,
        ?int $versionBloqueoEsperada = null
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

        if ($versionBloqueoEsperada !== null) {
            $stmtUpd = $this->pdo->prepare("
                UPDATE `postproduccion_entregas`
                SET `estado_entrega` = :estado,
                    `aprobado_por_cliente_en` = IF(:aprobada = 1, NOW(), NULL),
                    `observaciones_cliente` = :obs,
                    `version_bloqueo` = `version_bloqueo` + 1
                WHERE `id` = :id AND `organizacion_id` = :org_id AND `version_bloqueo` = :version
            ");
            $stmtUpd->execute([
                'estado'   => $nuevoEstado,
                'aprobada' => $aprobada ? 1 : 0,
                'obs'      => $observaciones,
                'id'       => $entregaId,
                'org_id'   => $organizacionId,
                'version'  => $versionBloqueoEsperada,
            ]);

            if ($stmtUpd->rowCount() === 0) {
                throw new ConflictoConcurrenciaExcepcion("Conflicto de concurrencia: La entrega fue modificada concurrentemente por otra sesión.");
            }
        } else {
            $stmtUpd = $this->pdo->prepare("
                UPDATE `postproduccion_entregas`
                SET `estado_entrega` = :estado,
                    `aprobado_por_cliente_en` = IF(:aprobada = 1, NOW(), NULL),
                    `observaciones_cliente` = :obs,
                    `version_bloqueo` = `version_bloqueo` + 1
                WHERE `id` = :id AND `organizacion_id` = :org_id
            ");
            $stmtUpd->execute([
                'estado'   => $nuevoEstado,
                'aprobada' => $aprobada ? 1 : 0,
                'obs'      => $observaciones,
                'id'       => $entregaId,
                'org_id'   => $organizacionId,
            ]);
        }

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

        // Validación de Estado del Archivo (Ciclo de Vida y Seguridad)
        if ($archivo['estado'] === 'REVOCADO') {
            throw new InvalidArgumentException("Acceso denegado: El archivo multimedia ha sido revocado.");
        }
        if ($archivo['estado'] === 'ELIMINADO_SOLICITUD') {
            throw new InvalidArgumentException("Acceso denegado: El archivo fue eliminado conforme a la solicitud de supresión de datos.");
        }

        $proyecto = $this->obtenerProyecto($organizacionId, (int) $archivo['proyecto_id']);

        // Protección Anti-IDOR: Si es un cliente, validar titularidad
        if ($clienteId !== null && $clienteId > 0) {
            if ((int) $proyecto['cliente_id'] !== $clienteId) {
                throw new InvalidArgumentException("Acceso denegado: No tiene autorización para descargar este contenido multimedia.");
            }
        }

        // Regla Soberana de Edición: El cambio a CERRADA no revoca el acceso histórico del cliente
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
     * Marca un archivo como ARCHIVADO (conservación histórica pasiva).
     */
    public function archivarArchivo(int $organizacionId, int $archivoId, int $usuarioId): array
    {
        $archivo = $this->obtenerArchivo($organizacionId, $archivoId);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo #{$archivoId} no existe en su organización.");
        }

        $this->pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'ARCHIVADO' WHERE `id` = :id AND `organizacion_id` = :org_id")
            ->execute(['id' => $archivoId, 'org_id' => $organizacionId]);

        return $this->obtenerArchivo($organizacionId, $archivoId);
    }

    /**
     * Revoca el acceso a un archivo por motivos contractuales o de seguridad.
     */
    public function revocarArchivo(int $organizacionId, int $archivoId, int $usuarioId, string $motivo): array
    {
        $archivo = $this->obtenerArchivo($organizacionId, $archivoId);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo #{$archivoId} no existe en su organización.");
        }

        $this->pdo->prepare("UPDATE `postproduccion_archivos` SET `estado` = 'REVOCADO' WHERE `id` = :id AND `organizacion_id` = :org_id")
            ->execute(['id' => $archivoId, 'org_id' => $organizacionId]);

        return $this->obtenerArchivo($organizacionId, $archivoId);
    }

    /**
     * Registra una solicitud de supresión de datos personales / eliminación autorizada (Ley 29733).
     */
    public function solicitarSupresionArchivo(
        int $organizacionId,
        int $archivoId,
        string $solicitanteTipo,
        int $solicitanteId,
        string $motivo,
        string $ip
    ): array {
        $archivo = $this->obtenerArchivo($organizacionId, $archivoId);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo #{$archivoId} no existe en su organización.");
        }

        if (!in_array($solicitanteTipo, ['CLIENTE', 'USUARIO'], true)) {
            throw new InvalidArgumentException("Tipo de solicitante inválido: {$solicitanteTipo}.");
        }

        $stmtIns = $this->pdo->prepare("
            INSERT INTO `postproduccion_solicitudes_supresion` (
                `organizacion_id`, `archivo_id`, `solicitante_tipo`, `solicitante_id`,
                `motivo`, `estado`, `ip_solicitud`
            ) VALUES (
                :org_id, :arch_id, :tipo, :sol_id,
                :motivo, 'PENDIENTE', :ip
            )
        ");
        $stmtIns->execute([
            'org_id'  => $organizacionId,
            'arch_id' => $archivoId,
            'tipo'    => $solicitanteTipo,
            'sol_id'  => $solicitanteId,
            'motivo'  => trim($motivo),
            'ip'      => $ip,
        ]);

        $solicitudId = (int) $this->pdo->lastInsertId();
        return $this->obtenerSolicitudSupresion($organizacionId, $solicitudId);
    }

    /**
     * Ejecuta la eliminación física definitiva de un archivo bajo autorización legal (Ley 29733).
     */
    public function ejecutarSupresionAutorizada(int $organizacionId, int $solicitudId, int $usuarioAutorizadorId): array
    {
        $solicitud = $this->obtenerSolicitudSupresion($organizacionId, $solicitudId);
        if (!$solicitud) {
            throw new InvalidArgumentException("La solicitud #{$solicitudId} no existe en su organización.");
        }
        if ($solicitud['estado'] !== 'PENDIENTE') {
            throw new InvalidArgumentException("La solicitud #{$solicitudId} ya fue procesada (estado: {$solicitud['estado']}).");
        }

        $archivo = $this->obtenerArchivo($organizacionId, (int) $solicitud['archivo_id']);
        if (!$archivo) {
            throw new InvalidArgumentException("El archivo asociado no existe.");
        }

        // 1. Eliminar físicamente del disco
        if (!empty($archivo['ruta_privada'])) {
            $this->almacenamiento->eliminar($archivo['ruta_privada']);
        }

        // 2. Marcar archivo como eliminado con ruta limpia y preservar checksum para evidencia
        $this->pdo->prepare("
            UPDATE `postproduccion_archivos`
            SET `estado` = 'ELIMINADO_SOLICITUD',
                `ruta_privada` = ''
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ")->execute(['id' => $archivo['id'], 'org_id' => $organizacionId]);

        // 3. Descontar del proyecto
        $this->pdo->prepare("
            UPDATE `postproduccion_proyectos`
            SET `total_archivos` = GREATEST(0, `total_archivos` - 1),
                `peso_total_bytes` = GREATEST(0, `peso_total_bytes` - :peso)
            WHERE `id` = :id
        ")->execute(['peso' => $archivo['peso_bytes'], 'id' => $archivo['proyecto_id']]);

        // 4. Actualizar estado de la solicitud de supresión
        $this->pdo->prepare("
            UPDATE `postproduccion_solicitudes_supresion`
            SET `estado` = 'APROBADA_EJECUTADA',
                `fecha_ejecucion` = NOW(),
                `checksum_sha256_purgado` = :hash,
                `autorizado_por_usuario_id` = :usr_id
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ")->execute([
            'hash'   => $archivo['checksum_sha256'],
            'usr_id' => $usuarioAutorizadorId,
            'id'     => $solicitudId,
            'org_id' => $organizacionId,
        ]);

        return $this->obtenerSolicitudSupresion($organizacionId, $solicitudId);
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
     * Lista proyectos visibles para el portal de clientes.
     */
    public function listarProyectosCliente(int $organizacionId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   e.nombre as edicion_nombre, e.anio as edicion_anio, e.estado as edicion_estado
            FROM `postproduccion_proyectos` p
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            WHERE p.organizacion_id = :org_id AND p.cliente_id = :cli_id
            ORDER BY p.id DESC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'cli_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna el detalle completo de un proyecto audiovisual para el cliente.
     */
    public function obtenerDetalleProyectoCliente(int $organizacionId, int $proyectoId, int $clienteId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   e.nombre as edicion_nombre, e.anio as edicion_anio, e.estado as edicion_estado
            FROM `postproduccion_proyectos` p
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            WHERE p.id = :id AND p.organizacion_id = :org_id AND p.cliente_id = :cli_id
        ");
        $stmt->execute(['id' => $proyectoId, 'org_id' => $organizacionId, 'cli_id' => $clienteId]);
        $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proyecto) {
            return null;
        }

        // Cargar galerías públicas
        $stmtGal = $this->pdo->prepare("
            SELECT * FROM `postproduccion_galerias`
            WHERE `organizacion_id` = :org_id AND `proyecto_id` = :pry_id AND `es_publica_cliente` = 1
            ORDER BY `orden` ASC
        ");
        $stmtGal->execute(['org_id' => $organizacionId, 'pry_id' => $proyectoId]);
        $galerias = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

        // Obtener archivos de cada galería
        foreach ($galerias as &$g) {
            $stmtArch = $this->pdo->prepare("
                SELECT `id`, `galeria_id`, `nombre_original`, `tipo_archivo`, `mime_type`,
                       `peso_bytes`, `ancho_px`, `alto_px`, `duracion_segundos`, `es_destacado`, `creado_en`
                FROM `postproduccion_archivos`
                WHERE `galeria_id` = :gal_id AND `estado` IN ('ACTIVO', 'ARCHIVADO')
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

    public function obtenerSolicitudSupresion(int $organizacionId, int $solicitudId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `postproduccion_solicitudes_supresion` WHERE id = :id AND organizacion_id = :org_id");
        $stmt->execute(['id' => $solicitudId, 'org_id' => $organizacionId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Retorna las fotos de una galería verificando pertenencia estricta al cliente titular (Anti-IDOR)
     * e incluyendo el estado de selección individual persistido.
     */
    public function obtenerFotosGaleriaCliente(int $organizacionId, int $galeriaId, int $clienteId): array
    {
        // 1. Validar que la galería pertenezca a un proyecto del cliente en el tenant
        $stmtGal = $this->pdo->prepare("
            SELECT g.*, p.id as proyecto_id, p.codigo_proyecto, p.titulo as proyecto_titulo,
                   p.estado as proyecto_estado, p.cliente_id,
                   e.nombre as edicion_nombre, e.anio as edicion_anio
            FROM `postproduccion_galerias` g
            JOIN `postproduccion_proyectos` p ON g.proyecto_id = p.id
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            WHERE g.id = :gal_id AND g.organizacion_id = :org_id AND p.cliente_id = :cli_id AND g.es_publica_cliente = 1
        ");
        $stmtGal->execute([
            'gal_id' => $galeriaId,
            'org_id' => $organizacionId,
            'cli_id' => $clienteId,
        ]);
        $galeria = $stmtGal->fetch(PDO::FETCH_ASSOC);

        if (!$galeria) {
            throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion("Galería no encontrada o no pertenece a su cuenta.");
        }

        // 2. Cargar fotos con su estado de selección persistente para este cliente
        $stmtArch = $this->pdo->prepare("
            SELECT a.id, a.galeria_id, a.proyecto_id, a.nombre_original, a.tipo_archivo,
                   a.mime_type, a.peso_bytes, a.ancho_px, a.alto_px, a.duracion_segundos,
                   a.es_destacado, a.creado_en,
                   s.estado as seleccion_estado, s.notas_cliente as seleccion_notas, s.seleccionado_en
            FROM `postproduccion_archivos` a
            LEFT JOIN `postproduccion_selecciones` s
                   ON s.archivo_id = a.id AND s.cliente_id = :cli_id
            WHERE a.galeria_id = :gal_id AND a.estado IN ('ACTIVO', 'ARCHIVADO')
            ORDER BY a.es_destacado DESC, a.id ASC
        ");
        $stmtArch->execute([
            'gal_id' => $galeriaId,
            'cli_id' => $clienteId,
        ]);
        $archivos = $stmtArch->fetchAll(PDO::FETCH_ASSOC);

        $descargaPermitida = in_array($galeria['proyecto_estado'], ['LISTO_ENTREGA', 'ENTREGADO'], true);
        $totalSeleccionadas = 0;

        $fotos = [];
        foreach ($archivos as $arch) {
            $esSeleccionada = ($arch['seleccion_estado'] === 'SELECCIONADA');
            if ($esSeleccionada) {
                $totalSeleccionadas++;
            }

            $fotos[] = [
                'id'                 => (int) $arch['id'],
                'galeria_id'         => (int) $arch['galeria_id'],
                'proyecto_id'        => (int) $arch['proyecto_id'],
                'nombre_original'    => $arch['nombre_original'],
                'tipo_archivo'       => $arch['tipo_archivo'],
                'mime_type'          => $arch['mime_type'],
                'peso_bytes'         => (int) $arch['peso_bytes'],
                'ancho_px'           => $arch['ancho_px'] !== null ? (int) $arch['ancho_px'] : null,
                'alto_px'            => $arch['alto_px'] !== null ? (int) $arch['alto_px'] : null,
                'duracion_segundos'  => $arch['duracion_segundos'] !== null ? (int) $arch['duracion_segundos'] : null,
                'es_destacado'       => (bool) $arch['es_destacado'],
                'es_seleccionada'    => $esSeleccionada,
                'seleccion_notas'    => $arch['seleccion_notas'],
                'seleccionado_en'    => $arch['seleccionado_en'],
                'descarga_permitida' => $descargaPermitida,
                'url_vista_previa'   => '/api/v1/publico/portal/descargar-multimedia/' . $arch['id'] . '?preview=1',
                'url_descarga'       => '/api/v1/publico/portal/descargar-multimedia/' . $arch['id'],
                'creado_en'          => $arch['creado_en'],
            ];
        }

        return [
            'galeria' => [
                'id'                 => (int) $galeria['id'],
                'proyecto_id'        => (int) $galeria['proyecto_id'],
                'codigo_proyecto'    => $galeria['codigo_proyecto'],
                'proyecto_titulo'    => $galeria['proyecto_titulo'],
                'proyecto_estado'    => $galeria['proyecto_estado'],
                'titulo'             => $galeria['titulo'],
                'descripcion'        => $galeria['descripcion'],
                'total_fotos'        => count($fotos),
                'total_seleccionadas'=> $totalSeleccionadas,
                'descarga_permitida' => $descargaPermitida,
                'edicion_nombre'     => $galeria['edicion_nombre'],
                'edicion_anio'       => $galeria['edicion_anio'],
            ],
            'fotos'               => $fotos,
            'total_fotos'         => count($fotos),
            'total_seleccionadas' => $totalSeleccionadas,
        ];
    }

    /**
     * Registra o actualiza la selección persistente de una fotografía por el cliente titular (Anti-IDOR).
     */
    public function guardarSeleccionFotoCliente(
        int $organizacionId,
        int $galeriaId,
        int $archivoId,
        int $clienteId,
        bool $seleccionada,
        ?string $notas = null
    ): array {
        // Validar titularidad estricta del archivo
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.galeria_id, a.proyecto_id, p.cliente_id
            FROM `postproduccion_archivos` a
            JOIN `postproduccion_galerias` g ON a.galeria_id = g.id
            JOIN `postproduccion_proyectos` p ON a.proyecto_id = p.id
            WHERE a.id = :arch_id AND a.galeria_id = :gal_id AND a.organizacion_id = :org_id
        ");
        $stmt->execute([
            'arch_id' => $archivoId,
            'gal_id'  => $galeriaId,
            'org_id'  => $organizacionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['cliente_id'] !== $clienteId) {
            throw new \Aplicacion\Excepciones\AccesoDenegadoExcepcion("Acceso denegado: El archivo no pertenece a un proyecto autorizado de su cuenta.");
        }

        $proyectoId = (int) $row['proyecto_id'];
        $estado = $seleccionada ? 'SELECCIONADA' : 'DESCARTADA';
        $notasLimpio = ($notas !== null && trim($notas) !== '') ? trim($notas) : null;

        $stmtUpsert = $this->pdo->prepare("
            INSERT INTO `postproduccion_selecciones` (
                `organizacion_id`, `proyecto_id`, `galeria_id`, `archivo_id`, `cliente_id`,
                `estado`, `notas_cliente`, `seleccionado_en`
            ) VALUES (
                :org_id, :pry_id, :gal_id, :arch_id, :cli_id,
                :estado, :notas, NOW()
            )
            ON DUPLICATE KEY UPDATE
                `estado` = VALUES(`estado`),
                `notas_cliente` = VALUES(`notas_cliente`),
                `actualizado_en` = NOW()
        ");
        $stmtUpsert->execute([
            'org_id'  => $organizacionId,
            'pry_id'  => $proyectoId,
            'gal_id'  => $galeriaId,
            'arch_id' => $archivoId,
            'cli_id'  => $clienteId,
            'estado'  => $estado,
            'notas'   => $notasLimpio,
        ]);

        return [
            'archivo_id'      => $archivoId,
            'galeria_id'      => $galeriaId,
            'proyecto_id'     => $proyectoId,
            'cliente_id'      => $clienteId,
            'es_seleccionada' => $seleccionada,
            'estado'          => $estado,
            'notas_cliente'   => $notasLimpio,
            'actualizado_en'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Retorna el consolidado global de fotos marcadas como SELECCIONADAS por el cliente en todos sus proyectos.
     */
    public function listarMisSeleccionesCliente(int $organizacionId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id as seleccion_id, s.estado, s.notas_cliente, s.seleccionado_en, s.actualizado_en,
                   a.id as archivo_id, a.nombre_original, a.tipo_archivo, a.mime_type, a.peso_bytes,
                   a.ancho_px, a.alto_px, a.es_destacado,
                   g.id as galeria_id, g.titulo as galeria_titulo,
                   p.id as proyecto_id, p.codigo_proyecto, p.titulo as proyecto_titulo, p.estado as proyecto_estado,
                   e.nombre as edicion_nombre, e.anio as edicion_anio
            FROM `postproduccion_selecciones` s
            JOIN `postproduccion_archivos` a ON s.archivo_id = a.id
            JOIN `postproduccion_galerias` g ON s.galeria_id = g.id
            JOIN `postproduccion_proyectos` p ON s.proyecto_id = p.id
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            WHERE s.organizacion_id = :org_id
              AND s.cliente_id = :cli_id
              AND s.estado = 'SELECCIONADA'
            ORDER BY s.actualizado_en DESC
        ");
        $stmt->execute([
            'org_id' => $organizacionId,
            'cli_id' => $clienteId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $r) {
            $descargaPermitida = in_array($r['proyecto_estado'], ['LISTO_ENTREGA', 'ENTREGADO'], true);
            $resultado[] = [
                'seleccion_id'       => (int) $r['seleccion_id'],
                'archivo_id'         => (int) $r['archivo_id'],
                'nombre_original'    => $r['nombre_original'],
                'tipo_archivo'       => $r['tipo_archivo'],
                'mime_type'          => $r['mime_type'],
                'peso_bytes'         => (int) $r['peso_bytes'],
                'ancho_px'           => $r['ancho_px'] !== null ? (int) $r['ancho_px'] : null,
                'alto_px'            => $r['alto_px'] !== null ? (int) $r['alto_px'] : null,
                'es_destacado'       => (bool) $r['es_destacado'],
                'galeria_id'         => (int) $r['galeria_id'],
                'galeria_titulo'     => $r['galeria_titulo'],
                'proyecto_id'        => (int) $r['proyecto_id'],
                'codigo_proyecto'    => $r['codigo_proyecto'],
                'proyecto_titulo'    => $r['proyecto_titulo'],
                'proyecto_estado'    => $r['proyecto_estado'],
                'edicion_nombre'     => $r['edicion_nombre'],
                'edicion_anio'       => $r['edicion_anio'],
                'notas_cliente'      => $r['notas_cliente'],
                'seleccionado_en'    => $r['seleccionado_en'],
                'actualizado_en'     => $r['actualizado_en'],
                'descarga_permitida' => $descargaPermitida,
                'url_vista_previa'   => '/api/v1/publico/portal/descargar-multimedia/' . $r['archivo_id'] . '?preview=1',
                'url_descarga'       => '/api/v1/publico/portal/descargar-multimedia/' . $r['archivo_id'],
            ];
        }

        return $resultado;
    }
}


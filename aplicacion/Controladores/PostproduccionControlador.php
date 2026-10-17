<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Postproduccion\PostproduccionServicio;
use Exception;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use PDO;

/**
 * Controlador administrativo de Postproducción y Multimedia (Backoffice Alina).
 * Protegido estrictamente por RBAC (Módulo 24: postproduccion).
 */
class PostproduccionControlador
{
    private PDO $pdo;
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private PostproduccionServicio $servicio;

    public function __construct(
        ?PDO $pdo = null,
        ?AutorizacionServicio $authzServicio = null,
        ?PostproduccionServicio $servicio = null,
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->servicio = $servicio ?? new PostproduccionServicio($this->pdo);
    }

    /**
     * GET /postproduccion
     * Vista principal en Backoffice Alina.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('postproduccion.proyectos.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Postproducción y Galerías',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (postproduccion.proyectos.ver) para consultar el módulo de postproducción.'
            ], 'principal');
        }

        $orgId = (int) $contexto->obtenerOrganizacionId();

        // Obtener ediciones
        $stmtEd = $this->pdo->prepare("SELECT id, nombre, anio, estado FROM `ediciones_candelaria` WHERE organizacion_id = :org_id ORDER BY id DESC");
        $stmtEd->execute(['org_id' => $orgId]);
        $ediciones = $stmtEd->fetchAll(PDO::FETCH_ASSOC);

        // Obtener clientes
        $stmtCli = $this->pdo->prepare("
            SELECT c.id, per.nombres, per.apellidos, per.numero_documento, per.correo_electronico
            FROM `clientes` c
            JOIN `personas` per ON c.persona_id = per.id
            WHERE c.organizacion_id = :org_id
            ORDER BY per.apellidos ASC, per.nombres ASC
        ");
        $stmtCli->execute(['org_id' => $orgId]);
        $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

        // Obtener usuarios operativos (fotógrafos, editores)
        $stmtUsr = $this->pdo->prepare("
            SELECT u.id, u.nombre_completo, u.correo_electronico
            FROM `usuarios` u
            JOIN `usuario_roles` ur ON u.id = ur.usuario_id
            WHERE u.organizacion_id = :org_id
            GROUP BY u.id
            ORDER BY u.nombre_completo ASC
        ");
        $stmtUsr->execute(['org_id' => $orgId]);
        $usuarios = $stmtUsr->fetchAll(PDO::FETCH_ASSOC);

        $permisos = [
            'ver'                => true,
            'crear_proyecto'     => $this->authzMiddleware->verificarPermiso('postproduccion.proyectos.crear', $contexto, false),
            'editar_proyecto'    => $this->authzMiddleware->verificarPermiso('postproduccion.proyectos.editar', $contexto, false),
            'subir_archivos'     => $this->authzMiddleware->verificarPermiso('postproduccion.archivos.subir', $contexto, false),
            'descargar_archivos' => $this->authzMiddleware->verificarPermiso('postproduccion.archivos.descargar', $contexto, false),
            'gestionar_entregas' => $this->authzMiddleware->verificarPermiso('postproduccion.entregas.gestionar', $contexto, false),
        ];

        return Vista::renderizar('postproduccion/index', [
            'titulo'          => 'Postproducción y Galerías | CandelariaAPP',
            'subtitulo'       => 'Gestión de Proyectos Audiovisuales, Álbumes y Entregas',
            'tituloSeccion'   => 'Postproducción',
            'seccionActiva'   => 'postproduccion',
            'permisos'        => $permisos,
            'ediciones'       => $ediciones,
            'clientes'        => $clientes,
            'usuarios'        => $usuarios,
            'scriptAdicional' => url_base('publico/js/postproduccion.js')
        ], 'principal');
    }

    /**
     * GET /api/v1/postproduccion/recursos-formulario
     */
    public function recursosFormulario(?ContextoOperacion $ctx = null): void
    {
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.proyectos.ver');
        $orgId = (int) $ctx->obtenerOrganizacionId();

        $stmtEd = $this->pdo->prepare("SELECT id, nombre, anio, estado FROM `ediciones_candelaria` WHERE organizacion_id = :org_id ORDER BY id DESC");
        $stmtEd->execute(['org_id' => $orgId]);
        $ediciones = $stmtEd->fetchAll(PDO::FETCH_ASSOC);

        $stmtCli = $this->pdo->prepare("
            SELECT c.id, per.nombres, per.apellidos, per.numero_documento, per.correo_electronico
            FROM `clientes` c
            JOIN `personas` per ON c.persona_id = per.id
            WHERE c.organizacion_id = :org_id
            ORDER BY per.apellidos ASC, per.nombres ASC
        ");
        $stmtCli->execute(['org_id' => $orgId]);
        $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

        $stmtUsr = $this->pdo->prepare("
            SELECT u.id, u.nombre_completo, u.correo_electronico
            FROM `usuarios` u
            JOIN `usuario_roles` ur ON u.id = ur.usuario_id
            WHERE u.organizacion_id = :org_id
            GROUP BY u.id
            ORDER BY u.nombre_completo ASC
        ");
        $stmtUsr->execute(['org_id' => $orgId]);
        $usuarios = $stmtUsr->fetchAll(PDO::FETCH_ASSOC);

        $this->responderJson(200, [
            'exito' => true,
            'datos' => [
                'ediciones' => $ediciones,
                'clientes'  => $clientes,
                'usuarios'  => $usuarios,
            ]
        ]);
    }

    /**
     * GET /api/v1/postproduccion/proyectos
     */
    public function listarProyectos(?ContextoOperacion $ctx = null): void
    {
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.proyectos.ver');
        $orgId = (int) $ctx->obtenerOrganizacionId();

        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   per.nombres as cliente_nombres, per.apellidos as cliente_apellidos,
                   e.nombre as edicion_nombre, e.anio as edicion_anio, e.estado as edicion_estado,
                   u_fot.nombre_completo as fotografo_nombre,
                   u_edt.nombre_completo as editor_nombre
            FROM `postproduccion_proyectos` p
            JOIN `ediciones_candelaria` e ON p.edicion_id = e.id
            JOIN `clientes` cl ON p.cliente_id = cl.id
            JOIN `personas` per ON cl.persona_id = per.id
            LEFT JOIN `usuarios` u_fot ON p.fotografo_usuario_id = u_fot.id
            LEFT JOIN `usuarios` u_edt ON p.editor_usuario_id = u_edt.id
            WHERE p.organizacion_id = :org_id
            ORDER BY p.id DESC
        ");
        $stmt->execute(['org_id' => $orgId]);
        $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->responderJson(200, ['exito' => true, 'datos' => $proyectos]);
    }

    /**
     * GET /api/v1/postproduccion/proyectos/{id}
     */
    public function verProyecto(mixed $id = null, ?ContextoOperacion $ctx = null): void
    {
        if ($id instanceof ContextoOperacion) {
            $temp = $ctx;
            $ctx = $id;
            $id = $temp;
        }
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.proyectos.ver');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $proyectoId = (int) ($id ?? ($_GET['id'] ?? 0));

        $proyecto = $this->servicio->obtenerProyecto($orgId, $proyectoId);
        if (!$proyecto) {
            $this->responderJson(404, ['exito' => false, 'mensaje' => 'Proyecto no encontrado.']);
            return;
        }

        // Cargar galerías y entregas
        $stmtGal = $this->pdo->prepare("SELECT * FROM `postproduccion_galerias` WHERE organizacion_id = :org_id AND proyecto_id = :pry_id ORDER BY orden ASC");
        $stmtGal->execute(['org_id' => $orgId, 'pry_id' => $proyectoId]);
        $galerias = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

        foreach ($galerias as &$g) {
            $stmtArch = $this->pdo->prepare("SELECT * FROM `postproduccion_archivos` WHERE galeria_id = :gal_id ORDER BY id ASC");
            $stmtArch->execute(['gal_id' => $g['id']]);
            $g['archivos'] = $stmtArch->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($g);

        $stmtEnt = $this->pdo->prepare("SELECT * FROM `postproduccion_entregas` WHERE organizacion_id = :org_id AND proyecto_id = :pry_id ORDER BY numero_entrega DESC");
        $stmtEnt->execute(['org_id' => $orgId, 'pry_id' => $proyectoId]);
        $entregas = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);

        $proyecto['galerias'] = $galerias;
        $proyecto['entregas'] = $entregas;

        $this->responderJson(200, ['exito' => true, 'datos' => $proyecto]);
    }

    /**
     * POST /api/v1/postproduccion/proyectos
     */
    public function crearProyecto(?ContextoOperacion $ctx = null): void
    {
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.proyectos.crear');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $body = $this->leerJsonBody();

        try {
            $edicionId = (int) ($body['edicion_id'] ?? 0);
            $clienteId = (int) ($body['cliente_id'] ?? 0);
            $titulo = (string) ($body['titulo'] ?? '');

            if ($edicionId <= 0 || $clienteId <= 0 || trim($titulo) === '') {
                throw new InvalidArgumentException("Los campos edicion_id, cliente_id y titulo son requeridos.");
            }

            $proyecto = $this->servicio->crearProyecto(
                $orgId,
                $edicionId,
                $clienteId,
                $titulo,
                $body['descripcion'] ?? null,
                !empty($body['reserva_id']) ? (int) $body['reserva_id'] : null,
                !empty($body['prestacion_id']) ? (int) $body['prestacion_id'] : null,
                !empty($body['fotografo_id']) ? (int) $body['fotografo_id'] : null,
                !empty($body['editor_id']) ? (int) $body['editor_id'] : null,
                $body['fecha_limite_entrega'] ?? null
            );

            $this->responderJson(201, ['exito' => true, 'mensaje' => 'Proyecto creado exitosamente.', 'datos' => $proyecto]);
        } catch (Exception $e) {
            $this->responderJson(400, ['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/postproduccion/proyectos/{id}/galerias
     */
    public function crearGaleria(mixed $id = null, ?ContextoOperacion $ctx = null): void
    {
        if ($id instanceof ContextoOperacion) {
            $temp = $ctx;
            $ctx = $id;
            $id = $temp;
        }
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.proyectos.editar');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $proyectoId = (int) ($id ?? ($_GET['id'] ?? 0));
        $body = $this->leerJsonBody();

        try {
            $titulo = (string) ($body['titulo'] ?? '');
            if (trim($titulo) === '') {
                throw new InvalidArgumentException("El título de la galería es obligatorio.");
            }

            $galeria = $this->servicio->crearGaleria(
                $orgId,
                $proyectoId,
                $titulo,
                (string) ($body['tipo_galeria'] ?? 'FOTOGRAFIA'),
                $body['descripcion'] ?? null,
                (bool) ($body['es_publica_cliente'] ?? true),
                (int) ($body['orden'] ?? 1)
            );

            $this->responderJson(201, ['exito' => true, 'mensaje' => 'Galería creada exitosamente.', 'datos' => $galeria]);
        } catch (Exception $e) {
            $this->responderJson(400, ['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/postproduccion/proyectos/{id}/galerias/{galeriaId}/archivos
     */
    public function subirArchivo(mixed $id = null, mixed $galeriaId = null, ?ContextoOperacion $ctx = null): void
    {
        if ($id instanceof ContextoOperacion) {
            $ctx = $id;
            $id = $galeriaId;
            $galeriaId = null;
        }
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.archivos.subir');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $usuarioId = (int) $ctx->obtenerUsuarioId();
        $proyectoId = (int) ($id ?? ($_GET['id'] ?? 0));
        $galeriaId = (int) ($galeriaId ?? ($_GET['galeriaId'] ?? 0));

        try {
            if (empty($_FILES['archivo']) || (!is_uploaded_file($_FILES['archivo']['tmp_name']) && !file_exists($_FILES['archivo']['tmp_name']))) {
                throw new InvalidArgumentException("No se ha enviado un archivo válido en la petición multipart.");
            }

            $archivoEnviado = $_FILES['archivo'];
            $nombreOriginal = (string) ($archivoEnviado['name'] ?? 'archivo_multimedia');
            $mimeType = (string) ($archivoEnviado['type'] ?? 'application/octet-stream');
            $stream = fopen($archivoEnviado['tmp_name'], 'rb');

            if ($stream === false) {
                throw new InvalidArgumentException("No se pudo leer el archivo subido en el servidor.");
            }

            $archivoGuardado = $this->servicio->subirArchivoStream(
                $orgId,
                $proyectoId,
                $galeriaId,
                $nombreOriginal,
                $stream,
                $mimeType,
                $usuarioId
            );

            $this->responderJson(201, ['exito' => true, 'mensaje' => 'Archivo multimedia procesado y almacenado correctamente.', 'datos' => $archivoGuardado]);
        } catch (Exception $e) {
            $this->responderJson(400, ['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/postproduccion/proyectos/{id}/entregas
     */
    public function crearEntrega(mixed $id = null, ?ContextoOperacion $ctx = null): void
    {
        if ($id instanceof ContextoOperacion) {
            $temp = $ctx;
            $ctx = $id;
            $id = $temp;
        }
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.entregas.gestionar');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $usuarioId = (int) $ctx->obtenerUsuarioId();
        $proyectoId = (int) ($id ?? ($_GET['id'] ?? 0));
        $body = $this->leerJsonBody();

        try {
            $titulo = (string) ($body['titulo'] ?? '');
            if (trim($titulo) === '') {
                throw new InvalidArgumentException("El título del paquete de entrega es obligatorio.");
            }

            $entrega = $this->servicio->crearEntrega(
                $orgId,
                $proyectoId,
                $titulo,
                (string) ($body['tipo_entrega'] ?? 'PARCIAL'),
                (array) ($body['archivo_ids'] ?? []),
                $body['notas'] ?? null,
                $usuarioId,
                (int) ($body['dias_caducidad'] ?? 30)
            );

            $this->responderJson(201, ['exito' => true, 'mensaje' => 'Entrega publicada correctamente para el cliente.', 'datos' => $entrega]);
        } catch (Exception $e) {
            $this->responderJson(400, ['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/postproduccion/archivos/{id}/descargar
     */
    public function descargarArchivo(mixed $id = null, ?ContextoOperacion $ctx = null): void
    {
        if ($id instanceof ContextoOperacion) {
            $temp = $ctx;
            $ctx = $id;
            $id = $temp;
        }
        $ctx = $this->resolverContexto($ctx);
        $this->exigirPermiso($ctx, 'postproduccion.archivos.descargar');
        $orgId = (int) $ctx->obtenerOrganizacionId();
        $usuarioId = (int) $ctx->obtenerUsuarioId();
        $archivoId = (int) ($id ?? ($_GET['id'] ?? 0));

        try {
            $res = $this->servicio->obtenerStreamDescarga(
                $orgId,
                $archivoId,
                null,
                $usuarioId,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $archivo = $res['archivo'];
            $stream = $res['stream'];

            if (ob_get_level() > 0) {
                ob_clean();
            }

            header('Content-Type: ' . $archivo['mime_type']);
            header('Content-Disposition: attachment; filename="' . basename($archivo['nombre_original']) . '"');
            header('Content-Length: ' . $archivo['peso_bytes']);
            header('Cache-Control: private, no-transform, no-store, must-revalidate');

            $GLOBALS['__ultimo_codigo_http_api'] = 200;
            if (php_sapi_name() === 'cli') {
                fclose($stream);
                return;
            }
            fpassthru($stream);
            fclose($stream);
            exit(0);
        } catch (Exception $e) {
            $this->responderJson(403, ['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function resolverContexto(?ContextoOperacion $ctx = null): ContextoOperacion
    {
        if ($ctx !== null) {
            return $ctx;
        }
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            $this->responderJson(401, ['exito' => false, 'mensaje' => 'Sesión expirada o no autenticada.']);
            exit(0);
        }
        return $contexto;
    }

    private function exigirPermiso(ContextoOperacion $ctx, string $permiso): void
    {
        if (!$this->authzMiddleware->verificarPermiso($permiso, $ctx, true)) {
            $this->responderJson(403, ['exito' => false, 'mensaje' => "Acceso denegado: Requiere el permiso '{$permiso}'."]);
        }
    }

    private function leerJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return $_POST ?: [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function responderJson(int $statusCode, array $payload): void
    {
        $payload['codigo'] ??= $statusCode;
        $payload['mensaje'] ??= ($payload['exito'] ?? false) ? 'Operación completada con éxito.' : 'Error en la operación.';
        $GLOBALS['__ultimo_codigo_http_api'] = $statusCode;
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Configuracion\BrandingServicio;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\OrganizacionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlador API oficial para la gestión institucional y branding de la Organización (F1.2B).
 * Gobernanza:
 * - Aislamiento Anti-IDOR absoluto: el tenant se resuelve del contexto autenticado.
 * - Validación y normalización estricta en servidor (textos a MAYÚSCULAS, email a minúsculas).
 * - Protección CSRF en mutaciones HTTP.
 * - Carga segura de branding: detección MIME server-side, 2MB max, SVG restringido, cero BLOBs.
 * - Pista de auditoría inmutable con datos previos y nuevos.
 */
class OrganizacionControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private OrganizacionRepositorio $orgRepo;
    private AuditoriaRepositorio $auditoriaRepo;
    private PDO $pdo;
    private string $raizProyecto;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?OrganizacionRepositorio $orgRepo = null,
        ?AuditoriaRepositorio $auditoriaRepo = null,
        ?PDO $pdo = null,
        ?string $raizProyecto = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->orgRepo = $orgRepo ?? new OrganizacionRepositorio($this->pdo);
        $this->auditoriaRepo = $auditoriaRepo ?? new AuditoriaRepositorio($this->pdo);
        $this->raizProyecto = $raizProyecto ?? dirname(__DIR__, 2);
    }

    /**
     * GET /api/v1/organizacion
     * Retorna la ficha técnica y de branding de la organización operativa actual.
     */
    public function detalle(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('organizacion.ver');
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, $e->getCode() ?: 403, $e->getMessage());
        }

        $orgId = $this->resolverOrganizacionId($contexto);

        $org = $this->orgRepo->buscarPorId($orgId);
        if ($org === null) {
            return $this->responderJson(false, 404, 'Organización no encontrada.');
        }

        $datos = $org->aArreglo();
        $datos['logo_url_completa'] = !empty($org->logoUrl) ? url_subida($org->logoUrl) : null;
        $datos['isotipo_url_completa'] = !empty($org->isotipoUrl) ? url_subida($org->isotipoUrl) : null;

        return $this->responderJson(true, 200, 'Datos de la organización recuperados exitosamente.', [
            'organizacion' => $datos,
        ]);
    }

    /**
     * PUT /api/v1/organizacion
     * Actualiza la información institucional, fiscal, de ubicación y contacto de la organización.
     */
    public function actualizar(): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('organizacion.editar');
            $this->validarCsrf($contexto);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, $e->getCode() ?: 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        }

        $orgId = $this->resolverOrganizacionId($contexto);
        $orgExistente = $this->orgRepo->buscarPorId($orgId);
        if ($orgExistente === null) {
            return $this->responderJson(false, 404, 'Organización no encontrada.');
        }

        $cuerpo = $this->obtenerCuerpoPeticion();

        // 1. Validaciones y normalizaciones estrictas
        $nombreComercial = trim((string) ($cuerpo['nombre_comercial'] ?? ''));
        if (empty($nombreComercial)) {
            return $this->responderJson(false, 422, 'El nombre comercial de la organización es obligatorio.');
        }

        $codigoPais = strtoupper(trim((string) ($cuerpo['codigo_pais'] ?? 'PE')));
        if (strlen($codigoPais) !== 2) {
            return $this->responderJson(false, 422, 'El código de país debe constar de 2 caracteres ISO 3166-1.');
        }

        $correoContacto = !empty($cuerpo['correo_contacto']) ? trim((string) $cuerpo['correo_contacto']) : null;
        if ($correoContacto !== null && !filter_var($correoContacto, FILTER_VALIDATE_EMAIL)) {
            return $this->responderJson(false, 422, 'El formato del correo electrónico de contacto es inválido.');
        }

        $sitioWeb = !empty($cuerpo['sitio_web']) ? trim((string) $cuerpo['sitio_web']) : null;
        if ($sitioWeb !== null && !preg_match('/^https?:\/\/.+/i', $sitioWeb)) {
            return $this->responderJson(false, 422, 'El sitio web debe comenzar con http:// o https://.');
        }

        $tipoDocId = isset($cuerpo['tipo_documento_id']) && $cuerpo['tipo_documento_id'] !== ''
            ? (int) $cuerpo['tipo_documento_id']
            : $orgExistente->tipoDocumentoId;

        // 2. Armar arreglo de actualización con normalización oficial
        $datosNuevos = [
            'nombre_comercial'   => normalizar_mayusculas($nombreComercial),
            'razon_social'       => !empty($cuerpo['razon_social']) ? normalizar_mayusculas(trim((string) $cuerpo['razon_social'])) : null,
            'tipo_documento_id'  => $tipoDocId,
            'numero_documento'   => !empty($cuerpo['numero_documento']) ? trim((string) $cuerpo['numero_documento']) : null,
            'direccion'          => !empty($cuerpo['direccion']) ? normalizar_mayusculas(trim((string) $cuerpo['direccion'])) : null,
            'codigo_pais'        => $codigoPais,
            'departamento'       => !empty($cuerpo['departamento']) ? normalizar_mayusculas(trim((string) $cuerpo['departamento'])) : null,
            'provincia'          => !empty($cuerpo['provincia']) ? normalizar_mayusculas(trim((string) $cuerpo['provincia'])) : null,
            'distrito'           => !empty($cuerpo['distrito']) ? normalizar_mayusculas(trim((string) $cuerpo['distrito'])) : null,
            'correo_contacto'    => $correoContacto !== null ? normalizar_minusculas($correoContacto) : null,
            'sitio_web'          => $sitioWeb !== null ? normalizar_minusculas($sitioWeb) : null,
            'telefono_contacto'  => !empty($cuerpo['telefono_contacto']) ? trim((string) $cuerpo['telefono_contacto']) : null,
            'telefono_whatsapp'  => !empty($cuerpo['telefono_whatsapp']) ? trim((string) $cuerpo['telefono_whatsapp']) : null,
            'contacto_nombre'    => !empty($cuerpo['contacto_nombre']) ? normalizar_mayusculas(trim((string) $cuerpo['contacto_nombre'])) : null,
            'contacto_cargo'     => !empty($cuerpo['contacto_cargo']) ? normalizar_mayusculas(trim((string) $cuerpo['contacto_cargo'])) : null,
        ];

        $datosPrevios = [
            'nombre_comercial'   => $orgExistente->nombreComercial,
            'razon_social'       => $orgExistente->razonSocial,
            'tipo_documento_id'  => $orgExistente->tipoDocumentoId,
            'numero_documento'   => $orgExistente->numeroDocumento,
            'direccion'          => $orgExistente->direccion,
            'codigo_pais'        => $orgExistente->codigoPais,
            'departamento'       => $orgExistente->departamento,
            'provincia'          => $orgExistente->provincia,
            'distrito'           => $orgExistente->distrito,
            'correo_contacto'    => $orgExistente->correoContacto,
            'sitio_web'          => $orgExistente->sitioWeb,
            'telefono_contacto'  => $orgExistente->telefonoContacto,
            'telefono_whatsapp'  => $orgExistente->telefonoWhatsapp,
            'contacto_nombre'    => $orgExistente->contactoNombre,
            'contacto_cargo'     => $orgExistente->contactoCargo,
        ];

        // 3. Transacción atómica y auditoría
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $this->orgRepo->actualizar($orgId, $datosNuevos);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'organizacion',
                accion: 'ACTUALIZAR_PERFIL_ORGANIZACION',
                entidadTipo: 'ORGANIZACION',
                entidadId: (string) $orgId,
                datosPrevios: $datosPrevios,
                datosNuevos: $datosNuevos
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            $orgActualizada = $this->orgRepo->buscarPorId($orgId);

            return $this->responderJson(true, 200, 'Información institucional actualizada exitosamente.', [
                'organizacion' => $orgActualizada?->aArreglo(),
            ]);
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->responderJson(false, 500, 'Error interno al actualizar la información de la organización.');
        }
    }

    /**
     * POST /api/v1/organizacion/branding/logo
     * Sube y sustituye de forma atómica y segura el logotipo institucional.
     */
    public function actualizarLogo(): string
    {
        return $this->procesarCargaBranding('logo');
    }

    /**
     * POST /api/v1/organizacion/branding/isotipo
     * Sube y sustituye de forma atómica y segura el isotipo institucional.
     */
    public function actualizarIsotipo(): string
    {
        return $this->procesarCargaBranding('isotipo');
    }

    /**
     * Procesa la validación, almacenamiento físico, actualización en BD y purga del archivo anterior.
     */
    private function procesarCargaBranding(string $tipo): string
    {
        try {
            $contexto = $this->verificarSesionYPermiso('branding.editar');
            $this->validarCsrf($contexto);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, $e->getCode() ?: 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        }

        $orgId = $this->resolverOrganizacionId($contexto);
        $orgExistente = $this->orgRepo->buscarPorId($orgId);
        if ($orgExistente === null) {
            return $this->responderJson(false, 404, 'Organización no encontrada.');
        }

        // 1. Verificar presencia de archivo en $_FILES
        $campoArchivo = isset($_FILES[$tipo]) ? $tipo : (isset($_FILES['archivo']) ? 'archivo' : null);
        if ($campoArchivo === null || empty($_FILES[$campoArchivo]['tmp_name'])) {
            return $this->responderJson(false, 422, "Debe seleccionar un archivo para el {$tipo}.");
        }

        $archivo = $_FILES[$campoArchivo];
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return $this->responderJson(false, 422, 'Error durante la transferencia del archivo (código ' . $archivo['error'] . ').');
        }

        // 2. Validación física exhaustiva (servidor MIME, SVG deshabilitado, límites, integridad)
        try {
            $metadatos = BrandingServicio::validarSubidaBranding(
                rutaTemporal: (string) $archivo['tmp_name'],
                nombreOriginal: (string) $archivo['name'],
                tamanoBytes: (int) $archivo['size']
            );
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage());
        }

        $archivoAnteriorRelativo = $tipo === 'logo' ? $orgExistente->logoUrl : $orgExistente->isotipoUrl;

        // 3. Almacenar físicamente el nuevo archivo
        try {
            $almacenado = BrandingServicio::almacenarArchivo(
                rutaTemporal: (string) $archivo['tmp_name'],
                organizacionId: $orgId,
                tipo: $tipo,
                extension: $metadatos['extension'],
                raizProyecto: $this->raizProyecto
            );
        } catch (Throwable $e) {
            return $this->responderJson(false, 500, 'Error al almacenar el archivo en disco: ' . $e->getMessage());
        }

        $nuevaRutaRelativa = $almacenado['ruta_relativa'];

        // 4. Actualizar referencia en base de datos y auditar
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $nuevoLogo = $tipo === 'logo' ? $nuevaRutaRelativa : $orgExistente->logoUrl;
            $nuevoIsotipo = $tipo === 'isotipo' ? $nuevaRutaRelativa : $orgExistente->isotipoUrl;

            $this->orgRepo->actualizarBranding($orgId, $nuevoLogo, $nuevoIsotipo, $orgExistente->marcaConfiguracion);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'organizacion',
                accion: $tipo === 'logo' ? 'ACTUALIZAR_BRANDING_LOGO' : 'ACTUALIZAR_BRANDING_ISOTIPO',
                entidadTipo: 'ORGANIZACION',
                entidadId: (string) $orgId,
                datosPrevios: [$tipo . '_url' => $archivoAnteriorRelativo],
                datosNuevos: [
                    $tipo . '_url' => $nuevaRutaRelativa,
                    'mime'        => $metadatos['mime'],
                    'dimensiones' => "{$metadatos['ancho']}x{$metadatos['alto']}",
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            // 5. Retirar archivo anterior de forma segura una vez confirmado en BD
            if (!empty($archivoAnteriorRelativo) && $archivoAnteriorRelativo !== $nuevaRutaRelativa) {
                BrandingServicio::eliminarArchivoAnterior($this->raizProyecto, $orgId, $archivoAnteriorRelativo);
            }

            return $this->responderJson(true, 200, ucfirst($tipo) . ' actualizado correctamente.', [
                'tipo'             => $tipo,
                'ruta_relativa'    => $nuevaRutaRelativa,
                'url_completa'     => url_subida($nuevaRutaRelativa),
                'nombre_archivo'   => $almacenado['nombre_archivo'],
                'dimensiones'      => "{$metadatos['ancho']}x{$metadatos['alto']}",
            ]);
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Si falló la BD, limpiar el archivo recién creado para evitar huérfanos
            @unlink($almacenado['ruta_fisica']);

            return $this->responderJson(false, 500, 'Error interno al registrar el branding en la base de datos.');
        }
    }

    /**
     * Resuelve el ID de la organización a partir del contexto autenticado (Anti-IDOR).
     */
    private function resolverOrganizacionId(ContextoOperacion $contexto): int
    {
        // En V1 single-tenant operativo, el ID siempre proviene de la sesión activa
        return $contexto->organizacionId ?? 10000;
    }

    /**
     * Verifica la sesión activa del operador y valida el permiso RBAC en backend.
     */
    private function verificarSesionYPermiso(string $permiso): ContextoOperacion
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            throw new AccesoDenegadoExcepcion('No autenticado: requiere una sesión activa.', null, 401);
        }

        if (!$this->authzMiddleware->verificarPermiso($permiso, $contexto, false)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new AccesoDenegadoExcepcion("Acceso denegado: requiere el permiso '{$permiso}'.", $permiso, 403);
        }

        return $contexto;
    }

    /**
     * Valida el token CSRF para operaciones mutables (PUT, POST).
     */
    private function validarCsrf(ContextoOperacion $contexto): void
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        $cuerpo = $this->obtenerCuerpoPeticion();
        $post = !empty($cuerpo) ? array_merge($_POST, $cuerpo) : $_POST;

        $tokenRecibido = ProtectorCsrf::extraerTokenDePeticion($post, $_SERVER);

        if ($tokenEsperado === null || $tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new InvalidArgumentException('Token CSRF inválido o ausente. Actualice la página e intente nuevamente.');
        }
    }

    /**
     * Obtiene y decodifica el cuerpo de la petición (JSON o form-data).
     */
    private function obtenerCuerpoPeticion(): array
    {
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $decodificado = json_decode($json, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return $_POST;
    }

    /**
     * Genera respuestas JSON consistentes con encabezados apropiados.
     */
    private function responderJson(bool $exito, int $codigoHttp, string $mensaje, mixed $datos = null): string
    {
        if (!headers_sent()) {
            http_response_code($codigoHttp);
            header('Content-Type: application/json; charset=utf-8');
        }

        return json_encode([
            'exito'   => $exito,
            'codigo'  => $codigoHttp,
            'mensaje' => $mensaje,
            'datos'   => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

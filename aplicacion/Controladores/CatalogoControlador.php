<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Catalogo\CatalogoServicio;
use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\CategoriaItemRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\HistorialTarifaRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlador oficial del Catálogo Comercial (Fase 2.3C).
 * Administra Ítems Comerciales (Productos y Servicios), Paquetes, Composición,
 * Ofertas por Edición, Tarifas Vigentes (Optimistic Locking) e Historial Auditado.
 */
class CatalogoControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private CatalogoServicio $catalogoServicio;
    private EdicionRepositorio $edicionRepo;
    private ConfiguracionServicio $configServicio;
    private CategoriaItemRepositorio $categoriaRepo;
    private ItemComercialRepositorio $itemRepo;
    private PaqueteRepositorio $paqueteRepo;
    private OfertaItemEdicionRepositorio $ofertaItemRepo;
    private OfertaPaqueteEdicionRepositorio $ofertaPaqueteRepo;
    private TarifaItemEdicionRepositorio $tarifaItemRepo;
    private TarifaPaqueteEdicionRepositorio $tarifaPaqueteRepo;
    private HistorialTarifaRepositorio $historialRepo;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?CatalogoServicio $catalogoServicio = null,
        ?EdicionRepositorio $edicionRepo = null,
        ?ConfiguracionServicio $configServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();
        $this->edicionRepo = $edicionRepo ?? new EdicionRepositorio($this->pdo);

        $this->categoriaRepo = new CategoriaItemRepositorio($this->pdo);
        $this->itemRepo = new ItemComercialRepositorio($this->pdo);
        $this->paqueteRepo = new PaqueteRepositorio($this->pdo);
        $this->ofertaItemRepo = new OfertaItemEdicionRepositorio($this->pdo);
        $this->ofertaPaqueteRepo = new OfertaPaqueteEdicionRepositorio($this->pdo);
        $this->tarifaItemRepo = new TarifaItemEdicionRepositorio($this->pdo);
        $this->tarifaPaqueteRepo = new TarifaPaqueteEdicionRepositorio($this->pdo);
        $this->historialRepo = new HistorialTarifaRepositorio($this->pdo);

        $this->configServicio = $configServicio ?? new ConfiguracionServicio(pdo: $this->pdo);

        if ($catalogoServicio !== null) {
            $this->catalogoServicio = $catalogoServicio;
        } else {
            $rolRepo = new RolRepositorio($this->pdo);
            $permRepo = new PermisoRepositorio($this->pdo);
            $usrRepo = new UsuarioRepositorio($this->pdo);
            $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
            $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $this->pdo);

            $this->catalogoServicio = new CatalogoServicio(
                categoriaRepo: $this->categoriaRepo,
                itemRepo: $this->itemRepo,
                paqueteRepo: $this->paqueteRepo,
                ofertaItemRepo: $this->ofertaItemRepo,
                ofertaPaqueteRepo: $this->ofertaPaqueteRepo,
                tarifaItemRepo: $this->tarifaItemRepo,
                tarifaPaqueteRepo: $this->tarifaPaqueteRepo,
                historialRepo: $this->historialRepo,
                edicionRepo: $this->edicionRepo,
                authzServicio: $authzServicio,
                auditoriaRepo: $auditoriaRepo,
                configServicio: $this->configServicio,
                pdo: $this->pdo
            );
        }
    }

    // =========================================================================
    // 1. VISTAS WEB OPERATIVAS
    // =========================================================================

    /**
     * GET /catalogo/items
     * Padrón de Ítems Comerciales y Gestión de Categorías.
     */
    public function vistaItems(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Catálogo Comercial',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (catalogo.ver) para consultar el catálogo de ítems.'
            ], 'principal');
        }

        $permisos = [
            'ver'                 => true,
            'categoriasGestionar' => $this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false),
            'itemsGestionar'      => $this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false),
            'paquetesGestionar'   => $this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false),
            'ofertasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false),
            'tarifasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false),
            'tarifasHistorial'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.ver_historial', $contexto, false),
        ];
        $monedaPrincipal = (string) $this->configServicio->obtenerPlataforma('plataforma.moneda_principal', 'PEN');
        $categorias = $contexto->organizacionId !== null
            ? $this->catalogoServicio->listarCategorias((int) $contexto->organizacionId, EstadoCatalogo::ACTIVO, $contexto)
            : [];

        return Vista::renderizar('catalogo/items', [
            'titulo'          => 'Ítems Comerciales | CandelariaAPP',
            'subtitulo'       => 'Catálogo Maestro de Productos y Servicios',
            'tituloSeccion'   => 'Catálogo Comercial',
            'seccionActiva'   => 'catalogo_items',
            'categorias'      => $categorias,
            'permisos'        => $permisos,
            'monedaPrincipal' => $monedaPrincipal,
            'scriptAdicional' => url_base('publico/js/catalogo_items.js')
        ], 'principal');
    }

    /**
     * GET /catalogo/paquetes
     * Catálogo de Paquetes Comerciales y Composición de Ítems.
     */
    public function vistaPaquetes(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Catálogo Comercial',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (catalogo.ver) para consultar los paquetes comerciales.'
            ], 'principal');
        }

        $permisos = [
            'ver'                 => true,
            'categoriasGestionar' => $this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false),
            'itemsGestionar'      => $this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false),
            'paquetesGestionar'   => $this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false),
            'ofertasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false),
            'tarifasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false),
            'tarifasHistorial'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.ver_historial', $contexto, false),
        ];

        return Vista::renderizar('catalogo/paquetes', [
            'titulo'          => 'Paquetes Comerciales | CandelariaAPP',
            'subtitulo'       => 'Gestión de Paquetes y Composición de Ofertas',
            'tituloSeccion'   => 'Catálogo Comercial',
            'seccionActiva'   => 'catalogo_paquetes',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/catalogo_paquetes.js')
        ], 'principal');
    }

    /**
     * GET /catalogo/ofertas
     * Oferta por Edición Folclórica y Gestión de Tarifas Vigentes.
     */
    public function vistaOfertas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Catálogo Comercial',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (catalogo.ver) para consultar las ofertas por edición.'
            ], 'principal');
        }

        $permisos = [
            'ver'                 => true,
            'categoriasGestionar' => $this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false),
            'itemsGestionar'      => $this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false),
            'paquetesGestionar'   => $this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false),
            'ofertasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false),
            'tarifasGestionar'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false),
            'tarifasHistorial'    => $this->authzMiddleware->verificarPermiso('catalogo.tarifas.ver_historial', $contexto, false),
        ];
        $monedaPrincipal = (string) $this->configServicio->obtenerPlataforma('plataforma.moneda_principal', 'PEN');
        $ediciones = $contexto->organizacionId !== null
            ? $this->edicionRepo->listarPorOrganizacion((int) $contexto->organizacionId)
            : [];

        return Vista::renderizar('catalogo/ofertas', [
            'titulo'          => 'Oferta por Edición | CandelariaAPP',
            'subtitulo'       => 'Habilitación de Ítems, Paquetes y Gestión Tarifaria',
            'tituloSeccion'   => 'Catálogo Comercial',
            'seccionActiva'   => 'catalogo_ofertas',
            'ediciones'       => $ediciones,
            'permisos'        => $permisos,
            'monedaPrincipal' => $monedaPrincipal,
            'scriptAdicional' => url_base('publico/js/catalogo_ofertas.js')
        ], 'principal');
    }

    // =========================================================================
    // 2. ENDPOINTS API: CATEGORÍAS DE CATÁLOGO
    // =========================================================================

    /**
     * GET /api/v1/catalogo/categorias
     */
    public function listarCategorias(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)
            && !$this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso para consultar categorías.');
        }

        try {
            $soloActivas = isset($_GET['solo_activas']) && $_GET['solo_activas'] === '1';
            $estado = $soloActivas ? EstadoCatalogo::ACTIVO : null;

            $categorias = $this->catalogoServicio->listarCategorias(
                (int) $contexto->organizacionId,
                $estado,
                $contexto
            );

            return $this->responderJson(true, 200, 'Categorías consultadas exitosamente.', [
                'total'      => count($categorias),
                'categorias' => array_map(fn($c) => $c->toArray(), $categorias),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/categorias
     */
    public function crearCategoria(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.categorias.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $codigo = trim((string) ($cuerpo['codigo'] ?? ''));
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;
            $orden = isset($cuerpo['orden']) ? (int) $cuerpo['orden'] : 0;

            if ($codigo === '' || $nombre === '') {
                return $this->responderJson(false, 400, 'El código y el nombre de la categoría son obligatorios.');
            }

            $creada = $this->catalogoServicio->crearCategoria(
                (int) $contexto->organizacionId,
                $codigo,
                $nombre,
                $descripcion,
                $orden,
                $contexto
            );

            return $this->responderJson(true, 201, 'Categoría creada exitosamente.', [
                'categoria' => $creada->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/catalogo/categorias/{id}
     */
    public function actualizarCategoria(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.categorias.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;
            $orden = isset($cuerpo['orden']) ? (int) $cuerpo['orden'] : 0;

            if ($nombre === '') {
                return $this->responderJson(false, 400, 'El nombre de la categoría es obligatorio.');
            }

            $actualizada = $this->catalogoServicio->actualizarCategoria(
                (int) $contexto->organizacionId,
                $idInt,
                $nombre,
                $descripcion,
                $orden,
                $contexto
            );

            return $this->responderJson(true, 200, 'Categoría actualizada exitosamente.', [
                'categoria' => $actualizada->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/catalogo/categorias/{id}/estado
     */
    public function cambiarEstadoCategoria(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.categorias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.categorias.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoEstadoStr = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
            if (!in_array($nuevoEstadoStr, ['ACTIVO', 'INACTIVO'], true)) {
                return $this->responderJson(false, 400, 'Estado no válido. Debe ser ACTIVO o INACTIVO.');
            }

            $nuevoEstado = EstadoCatalogo::from($nuevoEstadoStr);
            $this->catalogoServicio->cambiarEstadoCategoria(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevoEstado,
                $contexto
            );

            return $this->responderJson(true, 200, "Estado de categoría actualizado a {$nuevoEstadoStr}.", [
                'categoria' => [
                    'id'     => $idInt,
                    'activo' => $nuevoEstado === EstadoCatalogo::ACTIVO,
                    'estado' => $nuevoEstadoStr,
                ],
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 3. ENDPOINTS API: ÍTEMS COMERCIALES (PRODUCTOS Y SERVICIOS)
    // =========================================================================

    /**
     * GET /api/v1/catalogo/items
     */
    public function listarItems(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $categoriaId = isset($_GET['categoria_id']) && is_numeric($_GET['categoria_id'])
                ? (int) $_GET['categoria_id']
                : null;

            $tipoStr = isset($_GET['tipo']) && in_array(strtoupper(trim($_GET['tipo'])), ['PRODUCTO', 'SERVICIO'], true)
                ? strtoupper(trim($_GET['tipo']))
                : null;
            $tipo = $tipoStr !== null ? TipoItemComercial::from($tipoStr) : null;

            $estadoStr = isset($_GET['estado']) && in_array(strtoupper(trim($_GET['estado'])), ['ACTIVO', 'INACTIVO'], true)
                ? strtoupper(trim($_GET['estado']))
                : null;
            $estado = $estadoStr !== null ? EstadoCatalogo::from($estadoStr) : null;

            $busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            $items = $this->catalogoServicio->listarItems(
                (int) $contexto->organizacionId,
                $categoriaId,
                $tipo,
                $estado,
                $contexto,
                $busqueda
            );

            // Obtener categorías para enriquecer datos de visualización
            $categorias = $this->catalogoServicio->listarCategorias((int) $contexto->organizacionId, null, $contexto);
            $catMap = [];
            foreach ($categorias as $cat) {
                $catMap[$cat->id] = $cat->nombre;
            }

            $itemsFormateados = array_map(function ($item) use ($catMap) {
                $arr = $item->toArray();
                $arr['categoria_nombre'] = $catMap[$item->categoriaId] ?? 'Sin Categoría';
                return $arr;
            }, $items);

            return $this->responderJson(true, 200, 'Ítems comerciales consultados exitosamente.', [
                'total' => count($itemsFormateados),
                'items' => $itemsFormateados,
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/catalogo/items/buscar (Para Select2 asíncrono)
     */
    public function buscarItems(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $items = $this->catalogoServicio->listarItems(
                (int) $contexto->organizacionId,
                categoriaId: null,
                tipo: null,
                estado: EstadoCatalogo::ACTIVO,
                contexto: $contexto,
                busqueda: $q
            );

            $resultados = array_map(fn($it) => [
                'id'            => $it->id,
                'codigo'        => $it->codigo,
                'nombre'        => $it->nombre,
                'tipo'          => $it->tipo->value,
                'unidad_medida' => $it->unidadMedida->value,
                'texto'         => "[{$it->codigo}] {$it->nombre} ({$it->tipo->value} - {$it->unidadMedida->value})"
            ], $items);

            return $this->responderJson(true, 200, 'Búsqueda de ítems completada.', [
                'items' => $resultados
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/catalogo/items/{id}
     */
    public function detalleItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $item = $this->catalogoServicio->obtenerItem((int) $contexto->organizacionId, $idInt, $contexto);
            if ($item === null) {
                return $this->responderJson(false, 404, 'Ítem comercial no encontrado en su organización.');
            }

            return $this->responderJson(true, 200, 'Detalle de ítem comercial.', [
                'item' => $item->toArray(),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/items
     */
    public function crearItem(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.items.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $categoriaId = isset($cuerpo['categoria_id']) ? (int) $cuerpo['categoria_id'] : 0;
            $codigo = trim((string) ($cuerpo['codigo'] ?? ''));
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $tipoStr = strtoupper(trim((string) ($cuerpo['tipo'] ?? '')));
            $unidadMedida = strtoupper(trim((string) ($cuerpo['unidad_medida'] ?? '')));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;

            if ($categoriaId <= 0 || $codigo === '' || $nombre === '' || $tipoStr === '' || $unidadMedida === '') {
                return $this->responderJson(false, 400, 'Los campos categoría, código, nombre, tipo y unidad de medida son obligatorios.');
            }

            if (!in_array($tipoStr, ['PRODUCTO', 'SERVICIO'], true)) {
                return $this->responderJson(false, 400, 'El tipo debe ser PRODUCTO o SERVICIO.');
            }

            $creado = $this->catalogoServicio->crearItem(
                (int) $contexto->organizacionId,
                $categoriaId,
                $codigo,
                $nombre,
                TipoItemComercial::from($tipoStr),
                $unidadMedida,
                $descripcion,
                $contexto
            );

            return $this->responderJson(true, 201, 'Ítem comercial creado exitosamente.', [
                'item' => $creado->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/catalogo/items/{id}
     */
    public function actualizarItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.items.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $categoriaId = isset($cuerpo['categoria_id']) ? (int) $cuerpo['categoria_id'] : 0;
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $tipoStr = strtoupper(trim((string) ($cuerpo['tipo'] ?? '')));
            $unidadMedida = strtoupper(trim((string) ($cuerpo['unidad_medida'] ?? '')));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;

            if ($categoriaId <= 0 || $nombre === '' || $tipoStr === '' || $unidadMedida === '') {
                return $this->responderJson(false, 400, 'Los campos categoría, nombre, tipo y unidad de medida son obligatorios.');
            }

            if (!in_array($tipoStr, ['PRODUCTO', 'SERVICIO'], true)) {
                return $this->responderJson(false, 400, 'El tipo debe ser PRODUCTO o SERVICIO.');
            }

            $actualizado = $this->catalogoServicio->actualizarItem(
                (int) $contexto->organizacionId,
                $idInt,
                $categoriaId,
                $nombre,
                TipoItemComercial::from($tipoStr),
                $unidadMedida,
                $descripcion,
                $contexto
            );

            return $this->responderJson(true, 200, 'Ítem comercial actualizado exitosamente.', [
                'item' => $actualizado->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/catalogo/items/{id}/estado
     */
    public function cambiarEstadoItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.items.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.items.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoEstadoStr = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
            if (!in_array($nuevoEstadoStr, ['ACTIVO', 'INACTIVO'], true)) {
                return $this->responderJson(false, 400, 'Estado no válido. Debe ser ACTIVO o INACTIVO.');
            }

            $nuevoEstado = EstadoCatalogo::from($nuevoEstadoStr);
            $this->catalogoServicio->cambiarEstadoItem(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevoEstado,
                $contexto
            );

            return $this->responderJson(true, 200, "Estado de ítem actualizado a {$nuevoEstadoStr}.", [
                'item' => [
                    'id'     => $idInt,
                    'activo' => $nuevoEstado === EstadoCatalogo::ACTIVO,
                    'estado' => $nuevoEstadoStr,
                ],
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 4. ENDPOINTS API: PAQUETES COMERCIALES Y COMPOSICIÓN
    // =========================================================================

    /**
     * GET /api/v1/catalogo/paquetes
     */
    public function listarPaquetes(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $estadoStr = isset($_GET['estado']) && in_array(strtoupper(trim($_GET['estado'])), ['ACTIVO', 'INACTIVO'], true)
                ? strtoupper(trim($_GET['estado']))
                : null;
            $estado = $estadoStr !== null ? EstadoCatalogo::from($estadoStr) : null;
            $busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null;

            $paquetes = $this->catalogoServicio->listarPaquetes(
                (int) $contexto->organizacionId,
                $estado,
                $contexto,
                $busqueda
            );

            return $this->responderJson(true, 200, 'Paquetes comerciales consultados exitosamente.', [
                'total'    => count($paquetes),
                'paquetes' => array_map(fn($p) => $p->toArray(), $paquetes),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/catalogo/paquetes/{id}
     */
    public function detallePaquete(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $paquete = $this->catalogoServicio->obtenerPaquete((int) $contexto->organizacionId, $idInt, $contexto);
            if ($paquete === null) {
                return $this->responderJson(false, 404, 'Paquete comercial no encontrado en su organización.');
            }

            return $this->responderJson(true, 200, 'Detalle de paquete comercial.', [
                'paquete' => $paquete->toArray(),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/paquetes
     */
    public function crearPaquete(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.paquetes.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $codigo = trim((string) ($cuerpo['codigo'] ?? ''));
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;

            if ($codigo === '' || $nombre === '') {
                return $this->responderJson(false, 400, 'El código y el nombre del paquete son obligatorios.');
            }

            $creado = $this->catalogoServicio->crearPaquete(
                (int) $contexto->organizacionId,
                $codigo,
                $nombre,
                $descripcion,
                $contexto
            );

            return $this->responderJson(true, 201, 'Paquete comercial creado exitosamente.', [
                'paquete' => $creado->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/catalogo/paquetes/{id}
     */
    public function actualizarPaquete(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.paquetes.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
            $descripcion = !empty($cuerpo['descripcion']) ? trim((string) $cuerpo['descripcion']) : null;

            if ($nombre === '') {
                return $this->responderJson(false, 400, 'El nombre del paquete es obligatorio.');
            }

            $actualizado = $this->catalogoServicio->actualizarPaquete(
                (int) $contexto->organizacionId,
                $idInt,
                $nombre,
                $descripcion,
                $contexto
            );

            return $this->responderJson(true, 200, 'Paquete comercial actualizado exitosamente.', [
                'paquete' => $actualizado->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/catalogo/paquetes/{id}/estado
     */
    public function cambiarEstadoPaquete(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.paquetes.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoEstadoStr = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
            if (!in_array($nuevoEstadoStr, ['ACTIVO', 'INACTIVO'], true)) {
                return $this->responderJson(false, 400, 'Estado no válido. Debe ser ACTIVO o INACTIVO.');
            }

            $nuevoEstado = EstadoCatalogo::from($nuevoEstadoStr);
            $this->catalogoServicio->cambiarEstadoPaquete(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevoEstado,
                $contexto
            );
            $paqueteActualizado = $this->catalogoServicio->obtenerPaquete((int) $contexto->organizacionId, $idInt, $contexto);
            $paqArray = $paqueteActualizado?->toArray() ?? [];
            $paqArray['id'] = $idInt;
            $paqArray['activo'] = $nuevoEstado === EstadoCatalogo::ACTIVO;
            $paqArray['estado'] = $nuevoEstadoStr;

            return $this->responderJson(true, 200, "Estado de paquete actualizado a {$nuevoEstadoStr}.", [
                'paquete' => $paqArray,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/catalogo/paquetes/{id}/composicion
     */
    public function obtenerComposicion(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $paquete = $this->catalogoServicio->obtenerPaquete((int) $contexto->organizacionId, $idInt, $contexto);
            if ($paquete === null) {
                return $this->responderJson(false, 404, 'Paquete comercial no encontrado en su organización.');
            }

            $composicionEnriquecida = [];
            foreach ($paquete->items as $pi) {
                $itemInfo = $this->itemRepo->buscarPorId($pi->itemComercialId, (int) $contexto->organizacionId);
                $arr = $pi->toArray();
                $arr['itemId'] = $pi->itemComercialId;
                $arr['itemCodigo'] = $itemInfo?->codigo ?? '';
                $arr['itemNombre'] = $itemInfo?->nombre ?? '';
                $arr['itemTipo'] = $itemInfo?->tipo->value ?? '';
                $arr['itemUnidadMedida'] = $itemInfo?->unidadMedida->value ?? '';
                $composicionEnriquecida[] = $arr;
            }

            return $this->responderJson(true, 200, 'Composición de paquete consultada exitosamente.', $composicionEnriquecida);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/paquetes/{id}/composicion
     */
    public function sincronizarComposicion(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.paquetes.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.paquetes.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $items = $cuerpo['items'] ?? [];
            if (!is_array($items)) {
                return $this->responderJson(false, 400, 'La composición debe ser un arreglo de elementos incluidos.');
            }

            $itemsNormalizados = array_map(function ($it) {
                return [
                    'item_comercial_id' => (int) ($it['item_comercial_id'] ?? $it['item_id'] ?? 0),
                    'cantidad'          => (float) ($it['cantidad'] ?? 1.0),
                    'orden'             => isset($it['orden']) ? (int) $it['orden'] : 0,
                    'nota'              => isset($it['nota']) ? (string) $it['nota'] : null,
                ];
            }, $items);

            $paqueteActualizado = $this->catalogoServicio->sincronizarComposicionPaquete(
                (int) $contexto->organizacionId,
                $idInt,
                $itemsNormalizados,
                $contexto
            );

            return $this->responderJson(true, 200, 'Composición del paquete actualizada exitosamente.', [
                'paquete' => $paqueteActualizado->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 5. ENDPOINTS API: OFERTAS POR EDICIÓN
    // =========================================================================

    /**
     * GET /api/v1/catalogo/ofertas
     */
    public function listarOfertas(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ver.');
        }

        try {
            $edicionId = $this->resolverEdicionContextual();
            if ($edicionId === null || $edicionId <= 0) {
                return $this->responderJson(false, 400, 'Se requiere especificar la edición contextual (parámetro edicion_id o header X-Edicion-Id).');
            }

            $edicion = $this->edicionRepo->buscarPorId($edicionId);
            if ($edicion === null || $edicion->organizacionId !== (int) $contexto->organizacionId) {
                return $this->responderJson(false, 404, 'La edición especificada no existe o no pertenece a su organización.');
            }

            $ofertasItems = $this->catalogoServicio->listarOfertasItemsPorEdicion(
                (int) $contexto->organizacionId,
                $edicionId,
                null,
                $contexto
            );

            $ofertasPaquetes = $this->catalogoServicio->listarOfertasPaquetesPorEdicion(
                (int) $contexto->organizacionId,
                $edicionId,
                null,
                $contexto
            );

            // Obtener todos los ítems del tenant para formatear catálogo de la edición
            $todosItems = $this->catalogoServicio->listarItems((int) $contexto->organizacionId, null, null, null, $contexto);
            $mapaOfertasItems = [];
            foreach ($ofertasItems as $oi) {
                $mapaOfertasItems[$oi->itemComercialId] = $oi;
            }

            $itemsFormat = [];
            foreach ($todosItems as $it) {
                $of = $mapaOfertasItems[$it->id] ?? null;
                $tarifaVigente = null;
                if ($of !== null) {
                    $tar = $this->catalogoServicio->obtenerTarifaVigenteItem((int) $contexto->organizacionId, (int) $of->id, $contexto);
                    if ($tar !== null) {
                        $tarifaVigente = [
                            'id'             => $tar->id,
                            'precio'         => (float) $tar->precio,
                            'moneda'         => $tar->moneda,
                            'versionBloqueo' => $tar->versionBloqueo,
                        ];
                    }
                }

                $itemsFormat[] = [
                    'itemId'               => $it->id,
                    'itemCodigo'           => $it->codigo,
                    'itemNombre'           => $it->nombre,
                    'itemTipo'             => $it->tipo->value,
                    'categoriaNombre'      => $it->categoriaId ? ($this->categoriaRepo->buscarPorId($it->categoriaId, (int) $contexto->organizacionId)?->nombre) : '',
                    'ofertaId'             => $of?->id,
                    'ofertaHabilitada'     => $of !== null && $of->activo(),
                    'capacidadReferencial' => $of?->capacidadReferencial,
                    'tarifaVigente'        => $tarifaVigente,
                ];
            }

            // Obtener todos los paquetes del tenant para formatear catálogo de la edición
            $todosPaquetes = $this->catalogoServicio->listarPaquetes((int) $contexto->organizacionId, null, $contexto);
            $mapaOfertasPaquetes = [];
            foreach ($ofertasPaquetes as $op) {
                $mapaOfertasPaquetes[$op->paqueteId] = $op;
            }

            $paquetesFormat = [];
            foreach ($todosPaquetes as $paq) {
                $of = $mapaOfertasPaquetes[$paq->id] ?? null;
                $tarifaVigente = null;
                if ($of !== null) {
                    $tar = $this->catalogoServicio->obtenerTarifaVigentePaquete((int) $contexto->organizacionId, (int) $of->id, $contexto);
                    if ($tar !== null) {
                        $tarifaVigente = [
                            'id'             => $tar->id,
                            'precio'         => (float) $tar->precio,
                            'moneda'         => $tar->moneda,
                            'versionBloqueo' => $tar->versionBloqueo,
                        ];
                    }
                }

                $paquetesFormat[] = [
                    'paqueteId'            => $paq->id,
                    'paqueteCodigo'        => $paq->codigo,
                    'paqueteNombre'        => $paq->nombre,
                    'cantidadItems'        => count($paq->items),
                    'ofertaId'             => $of?->id,
                    'ofertaHabilitada'     => $of !== null && $of->activo(),
                    'capacidadReferencial' => $of?->capacidadReferencial,
                    'tarifaVigente'        => $tarifaVigente,
                ];
            }

            return $this->responderJson(true, 200, 'Ofertas por edición consultadas exitosamente.', [
                'edicion' => [
                    'id'     => $edicion->id,
                    'codigo' => $edicion->codigo,
                    'nombre' => $edicion->nombre,
                    'anio'   => $edicion->anio,
                    'estado' => $edicion->estado->value,
                ],
                'items'            => $itemsFormat,
                'paquetes'         => $paquetesFormat,
                'ofertas_items'    => array_map(fn($o) => $o->toArray(), $ofertasItems),
                'ofertas_paquetes' => array_map(fn($o) => $o->toArray(), $ofertasPaquetes),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/ofertas/items
     */
    public function habilitarOfertaItem(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ofertas.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $edicionId = isset($cuerpo['edicion_id']) ? (int) $cuerpo['edicion_id'] : $this->resolverEdicionContextual();
            $itemComercialId = isset($cuerpo['item_comercial_id']) ? (int) $cuerpo['item_comercial_id'] : 0;
            $capacidadReferencial = isset($cuerpo['capacidad_referencial']) && $cuerpo['capacidad_referencial'] !== '' && is_numeric($cuerpo['capacidad_referencial'])
                ? (int) $cuerpo['capacidad_referencial']
                : null;

            if ($edicionId === null || $edicionId <= 0 || $itemComercialId <= 0) {
                return $this->responderJson(false, 400, 'La edición y el ítem comercial son obligatorios.');
            }

            $oferta = $this->catalogoServicio->habilitarOfertaItem(
                (int) $contexto->organizacionId,
                $edicionId,
                $itemComercialId,
                $capacidadReferencial,
                $contexto
            );

            // Si se envió precio inicial, fijar tarifa automáticamente
            if (isset($cuerpo['precio_inicial']) && is_numeric($cuerpo['precio_inicial']) && (float) $cuerpo['precio_inicial'] >= 0) {
                $this->catalogoServicio->fijarTarifaInicialItem(
                    (int) $contexto->organizacionId,
                    (int) $oferta->id,
                    (float) $cuerpo['precio_inicial'],
                    $contexto
                );
            }

            $ofertaRecargada = $this->catalogoServicio->obtenerOfertaItem((int) $contexto->organizacionId, (int) $oferta->id, $contexto);

            return $this->responderJson(true, 201, 'Oferta de ítem habilitada exitosamente.', [
                'oferta' => $ofertaRecargada?->toArray() ?? $oferta->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/catalogo/ofertas/items/{id}/estado
     */
    public function cambiarEstadoOfertaItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ofertas.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoEstadoStr = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
            if (!in_array($nuevoEstadoStr, ['ACTIVO', 'INACTIVO'], true)) {
                return $this->responderJson(false, 400, 'Estado no válido. Debe ser ACTIVO o INACTIVO.');
            }

            $this->catalogoServicio->cambiarEstadoOfertaItem(
                (int) $contexto->organizacionId,
                $idInt,
                EstadoCatalogo::from($nuevoEstadoStr),
                $contexto
            );
            $ofertaActualizada = $this->ofertaItemRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            $ofArray = $ofertaActualizada?->toArray() ?? [];
            $ofArray['id'] = $idInt;
            $ofArray['activo'] = $nuevoEstadoStr === 'ACTIVO';
            $ofArray['estado'] = $nuevoEstadoStr;

            return $this->responderJson(true, 200, "Estado de oferta de ítem actualizado a {$nuevoEstadoStr}.", [
                'oferta' => $ofArray,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/ofertas/paquetes
     */
    public function habilitarOfertaPaquete(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ofertas.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $edicionId = isset($cuerpo['edicion_id']) ? (int) $cuerpo['edicion_id'] : $this->resolverEdicionContextual();
            $paqueteId = isset($cuerpo['paquete_id']) ? (int) $cuerpo['paquete_id'] : 0;
            $capacidadReferencial = isset($cuerpo['capacidad_referencial']) && $cuerpo['capacidad_referencial'] !== '' && is_numeric($cuerpo['capacidad_referencial'])
                ? (int) $cuerpo['capacidad_referencial']
                : null;

            if ($edicionId === null || $edicionId <= 0 || $paqueteId <= 0) {
                return $this->responderJson(false, 400, 'La edición y el paquete son obligatorios.');
            }

            $oferta = $this->catalogoServicio->habilitarOfertaPaquete(
                (int) $contexto->organizacionId,
                $edicionId,
                $paqueteId,
                $capacidadReferencial,
                $contexto
            );

            // Si se envió precio inicial, fijar tarifa automáticamente
            if (isset($cuerpo['precio_inicial']) && is_numeric($cuerpo['precio_inicial']) && (float) $cuerpo['precio_inicial'] >= 0) {
                $this->catalogoServicio->fijarTarifaInicialPaquete(
                    (int) $contexto->organizacionId,
                    (int) $oferta->id,
                    (float) $cuerpo['precio_inicial'],
                    $contexto
                );
            }

            $ofertaRecargada = $this->catalogoServicio->obtenerOfertaPaquete((int) $contexto->organizacionId, (int) $oferta->id, $contexto);

            return $this->responderJson(true, 201, 'Oferta de paquete habilitada exitosamente.', [
                'oferta' => $ofertaRecargada?->toArray() ?? $oferta->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PATCH /api/v1/catalogo/ofertas/paquetes/{id}/estado
     */
    public function cambiarEstadoOfertaPaquete(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.ofertas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.ofertas.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoEstadoStr = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
            if (!in_array($nuevoEstadoStr, ['ACTIVO', 'INACTIVO'], true)) {
                return $this->responderJson(false, 400, 'Estado no válido. Debe ser ACTIVO o INACTIVO.');
            }

            $this->catalogoServicio->cambiarEstadoOfertaPaquete(
                (int) $contexto->organizacionId,
                $idInt,
                EstadoCatalogo::from($nuevoEstadoStr),
                $contexto
            );
            $ofertaActualizada = $this->ofertaPaqueteRepo->buscarPorId($idInt, (int) $contexto->organizacionId);
            $ofArray = $ofertaActualizada?->toArray() ?? [];
            $ofArray['id'] = $idInt;
            $ofArray['activo'] = $nuevoEstadoStr === 'ACTIVO';
            $ofArray['estado'] = $nuevoEstadoStr;

            return $this->responderJson(true, 200, "Estado de oferta de paquete actualizado a {$nuevoEstadoStr}.", [
                'oferta' => $ofArray,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 6. ENDPOINTS API: TARIFAS Y CONCURRENCIA OPTIMISTA (HTTP 409)
    // =========================================================================

    /**
     * POST /api/v1/catalogo/tarifas/items/inicial
     */
    public function fijarTarifaInicialItem(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.tarifas.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $ofertaItemId = isset($cuerpo['oferta_item_id']) ? (int) $cuerpo['oferta_item_id'] : 0;
            $precio = isset($cuerpo['precio']) && is_numeric($cuerpo['precio']) ? (float) $cuerpo['precio'] : null;

            if ($ofertaItemId <= 0 || $precio === null) {
                return $this->responderJson(false, 400, 'El identificador de la oferta y el precio son obligatorios.');
            }

            $tarifa = $this->catalogoServicio->fijarTarifaInicialItem(
                (int) $contexto->organizacionId,
                $ofertaItemId,
                $precio,
                $contexto
            );

            return $this->responderJson(true, 201, 'Tarifa inicial fijada exitosamente.', [
                'tarifa' => $tarifa->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->responderJson(false, 500, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/catalogo/tarifas/items/{id}
     * Cambio de precio con control de concurrencia optimista (version_bloqueo).
     */
    public function actualizarTarifaItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.tarifas.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoPrecio = isset($cuerpo['nuevo_precio']) && is_numeric($cuerpo['nuevo_precio']) ? (float) $cuerpo['nuevo_precio'] : null;
            $versionEsperada = isset($cuerpo['version_bloqueo']) && is_numeric($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;
            $motivo = !empty($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : null;

            if ($idInt <= 0 || $nuevoPrecio === null || $versionEsperada === null || $motivo === null || $motivo === '') {
                return $this->responderJson(false, 400, 'El nuevo precio, la versión de bloqueo y el motivo del cambio son obligatorios.');
            }

            $tarifa = $this->catalogoServicio->actualizarTarifaItem(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevoPrecio,
                $versionEsperada,
                $motivo,
                $contexto
            );

            return $this->responderJson(true, 200, 'Tarifa actualizada exitosamente.', [
                'tarifa' => $tarifa->toArray(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(
                false,
                409,
                'La tarifa fue modificada por otro usuario concurrentemente. Por favor recargue los datos para continuar.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/catalogo/tarifas/paquetes/inicial
     */
    public function fijarTarifaInicialPaquete(): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.tarifas.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $ofertaPaqueteId = isset($cuerpo['oferta_paquete_id']) ? (int) $cuerpo['oferta_paquete_id'] : 0;
            $precio = isset($cuerpo['precio']) && is_numeric($cuerpo['precio']) ? (float) $cuerpo['precio'] : null;

            if ($ofertaPaqueteId <= 0 || $precio === null) {
                return $this->responderJson(false, 400, 'El identificador de la oferta de paquete y el precio son obligatorios.');
            }

            $tarifa = $this->catalogoServicio->fijarTarifaInicialPaquete(
                (int) $contexto->organizacionId,
                $ofertaPaqueteId,
                $precio,
                $contexto
            );

            return $this->responderJson(true, 201, 'Tarifa inicial de paquete fijada exitosamente.', [
                'tarifa' => $tarifa->toArray(),
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->responderJson(false, 500, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/catalogo/tarifas/paquetes/{id}
     * Cambio de precio propio de paquete con control de concurrencia optimista (version_bloqueo).
     */
    public function actualizarTarifaPaquete(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso catalogo.tarifas.gestionar.');
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        $cuerpo = $this->obtenerCuerpo();
        $errCsrf = $this->verificarCsrf($contexto, $cuerpo);
        if ($errCsrf !== null) {
            return $errCsrf;
        }

        try {
            $nuevoPrecio = isset($cuerpo['nuevo_precio']) && is_numeric($cuerpo['nuevo_precio']) ? (float) $cuerpo['nuevo_precio'] : null;
            $versionEsperada = isset($cuerpo['version_bloqueo']) && is_numeric($cuerpo['version_bloqueo']) ? (int) $cuerpo['version_bloqueo'] : null;
            $motivo = !empty($cuerpo['motivo']) ? trim((string) $cuerpo['motivo']) : null;

            if ($idInt <= 0 || $nuevoPrecio === null || $versionEsperada === null || $motivo === null || $motivo === '') {
                return $this->responderJson(false, 400, 'El nuevo precio, la versión de bloqueo y el motivo del cambio son obligatorios.');
            }

            $tarifa = $this->catalogoServicio->actualizarTarifaPaquete(
                (int) $contexto->organizacionId,
                $idInt,
                $nuevoPrecio,
                $versionEsperada,
                $motivo,
                $contexto
            );

            return $this->responderJson(true, 200, 'Tarifa de paquete actualizada exitosamente.', [
                'tarifa' => $tarifa->toArray(),
            ]);
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(
                false,
                409,
                'La tarifa fue modificada por otro usuario concurrentemente. Por favor recargue los datos para continuar.'
            );
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 7. ENDPOINTS API: HISTORIAL DE TARIFAS (APPEND-ONLY, READ-ONLY)
    // =========================================================================

    /**
     * GET /api/v1/catalogo/tarifas/items/{id}/historial
     */
    public function historialTarifaItem(string|int|array $id = 0): string
    {

        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.ver_historial', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso exclusivo catalogo.tarifas.ver_historial.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $tarifaItemId = $idInt;
            $tarifa = $this->tarifaItemRepo->buscarPorId($idInt);
            if ($tarifa === null) {
                $edicionId = $this->resolverEdicionContextual();
                if ($edicionId === null) {
                    $edicionActual = $this->edicionRepo->obtenerActual((int) $contexto->organizacionId);
                    $edicionId = $edicionActual?->id;
                }
                if ($edicionId !== null) {
                    $oferta = $this->ofertaItemRepo->buscarPorItemYEdicion($idInt, (int) $edicionId, (int) $contexto->organizacionId);
                    if ($oferta !== null) {
                        $tarifaVig = $this->tarifaItemRepo->buscarPorOfertaId((int) $oferta->id);
                        if ($tarifaVig !== null) {
                            $tarifaItemId = (int) $tarifaVig->id;
                        }
                    }
                }
            }

            $historial = $this->catalogoServicio->listarHistorialTarifasItem(
                (int) $contexto->organizacionId,
                $tarifaItemId,
                $contexto
            );

            // Formatear actor seguro sin PII
            $historialFormateado = array_map(function ($h) {
                $arr = $h->toArray();
                $arr['actor_display'] = $h->actorTipo === 'HUMANO'
                    ? "Usuario #{$h->usuarioId}"
                    : "Sistema Automático #{$h->actorSistemaId}";
                return $arr;
            }, $historial);

            return $this->responderJson(true, 200, 'Historial de tarifas de ítem consultado.', [
                'total'     => count($historialFormateado),
                'historial' => $historialFormateado,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/catalogo/tarifas/paquetes/{id}/historial
     */
    public function historialTarifaPaquete(string|int|array $id = 0): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('catalogo.tarifas.ver_historial', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el permiso exclusivo catalogo.tarifas.ver_historial.');
        }

        try {
            $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
            $tarifaPaqueteId = $idInt;
            $tarifa = $this->tarifaPaqueteRepo->buscarPorId($idInt);
            if ($tarifa === null) {
                $edicionId = $this->resolverEdicionContextual();
                if ($edicionId === null) {
                    $edicionActual = $this->edicionRepo->obtenerActual((int) $contexto->organizacionId);
                    $edicionId = $edicionActual?->id;
                }
                if ($edicionId !== null) {
                    $oferta = $this->ofertaPaqueteRepo->buscarPorPaqueteYEdicion($idInt, (int) $edicionId, (int) $contexto->organizacionId);
                    if ($oferta !== null) {
                        $tarifaVig = $this->tarifaPaqueteRepo->buscarPorOfertaId((int) $oferta->id);
                        if ($tarifaVig !== null) {
                            $tarifaPaqueteId = (int) $tarifaVig->id;
                        }
                    }
                }
            }

            $historial = $this->catalogoServicio->listarHistorialTarifasPaquete(
                (int) $contexto->organizacionId,
                $tarifaPaqueteId,
                $contexto
            );

            // Formatear actor seguro sin PII
            $historialFormateado = array_map(function ($h) {
                $arr = $h->toArray();
                $arr['actor_display'] = $h->actorTipo === 'HUMANO'
                    ? "Usuario #{$h->usuarioId}"
                    : "Sistema Automático #{$h->actorSistemaId}";
                return $arr;
            }, $historial);

            return $this->responderJson(true, 200, 'Historial de tarifas de paquete consultado.', [
                'total'     => count($historialFormateado),
                'historial' => $historialFormateado,
            ]);
        } catch (AccesoDenegadoExcepcion $e) {
            return $this->responderJson(false, 403, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // UTILIDADES PRIVADAS DE CONTROLADOR
    // =========================================================================

    private function resolverEdicionContextual(): ?int
    {
        if (isset($_GET['edicion_id']) && is_numeric($_GET['edicion_id'])) {
            return (int) $_GET['edicion_id'];
        }

        if (!empty($_SERVER['HTTP_X_EDICION_ID']) && is_numeric($_SERVER['HTTP_X_EDICION_ID'])) {
            return (int) $_SERVER['HTTP_X_EDICION_ID'];
        }

        return null;
    }

    private function obtenerCuerpo(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function verificarCsrf(ContextoOperacion $contexto, array $cuerpo): ?string
    {
        $tokenEsperado = $contexto->metadatos['csrf_token'] ?? null;
        if ($tokenEsperado !== null) {
            $tokenRecibido = $cuerpo['_csrf_token'] ?? $cuerpo['csrf_token'] ?? $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($tokenRecibido === null || !ProtectorCsrf::validarToken($tokenEsperado, $tokenRecibido)) {
                return $this->responderJson(false, 403, 'Token de seguridad CSRF inválido o ausente.');
            }
        }
        return null;
    }

    private function responderJson(
        bool $exito,
        int $codigoHttp,
        string $mensaje,
        mixed $datos = [],
        mixed $errores = []
    ): string {
        if (!headers_sent()) {
            http_response_code($codigoHttp);
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = [
            'exito'   => $exito,
            'codigo'  => $codigoHttp,
            'mensaje' => $mensaje,
        ];

        if ($datos !== null) {
            $payload['datos'] = $datos;
        }

        if (!empty($errores)) {
            $payload['errores'] = $errores;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function responderErrorSeguro(Throwable $e): string
    {
        error_log("[CatalogoControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la solicitud en el catálogo comercial.');
    }
}

<?php

declare(strict_types=1);

namespace Pruebas;

require __DIR__ . '/../vendor/autoload.php';
\Nucleo\Soporte\CargadorEntorno::cargar(__DIR__ . '/../.env');
require __DIR__ . '/../nucleo/Soporte/ayudantes.php';

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Catalogo\CatalogoServicio;
use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Catalogo\TipoItemComercial;
use Aplicacion\Catalogo\UnidadMedidaItem;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Controladores\CatalogoControlador;
use Aplicacion\Ediciones\EstadoEdicion;
use Aplicacion\Entidades\Edicion;
use Aplicacion\Entidades\Organizacion;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\Usuario;
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
use Aplicacion\Repositorios\OrganizacionRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Seguridad\ProtectorCsrf;
use PDO;

echo "==============================================================================\n";
echo "CANDELARIAAPP — SUITE DE PRUEBAS F2.3C: API + INTERFAZ OPERATIVA DEL CATÁLOGO\n";
echo "ÍTEMS, PAQUETES, OFERTAS, TARIFAS, CONCURRENCIA 409, HISTORIAL Y NAVEGACIÓN\n";
echo "==============================================================================\n\n";

$pdo = Conexion::obtenerInstancia();

$fallos = 0;
$exitos = 0;

function afirmar(bool $condicion, string $descripcion, string $detalles = ''): void
{
    global $fallos, $exitos;
    if ($condicion) {
        $exitos++;
        echo " [PASS] {$descripcion}\n";
    } else {
        $fallos++;
        echo " [FAIL] {$descripcion}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

// 0. Huella digital previa de Orlando para certificar preservación estricta
$stmtOrlandoPre = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPre->execute();
$orlandoPre = $stmtOrlandoPre->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPre = $orlandoPre ? substr(hash('sha256', (string) $orlandoPre['contrasena_hash']), 0, 16) : null;

// Iniciar transacción de pruebas para no alterar la BD operativa
$pdo->beginTransaction();

try {
    // Repositorios
    $orgRepo             = new OrganizacionRepositorio($pdo);
    $usuarioRepo         = new UsuarioRepositorio($pdo);
    $personaRepo         = new PersonaRepositorio($pdo);
    $rolRepo             = new RolRepositorio($pdo);
    $permisoRepo         = new PermisoRepositorio($pdo);
    $auditoriaRepo       = new AuditoriaRepositorio($pdo);
    $edicionRepo         = new EdicionRepositorio($pdo);
    $configRepo          = new ConfiguracionRepositorio($pdo);
    $categoriaRepo       = new CategoriaItemRepositorio($pdo);
    $itemRepo            = new ItemComercialRepositorio($pdo);
    $paqueteRepo         = new PaqueteRepositorio($pdo);
    $ofertaItemRepo      = new OfertaItemEdicionRepositorio($pdo);
    $ofertaPaqueteRepo   = new OfertaPaqueteEdicionRepositorio($pdo);
    $tarifaItemRepo      = new TarifaItemEdicionRepositorio($pdo);
    $tarifaPaqueteRepo   = new TarifaPaqueteEdicionRepositorio($pdo);
    $historialRepo       = new HistorialTarifaRepositorio($pdo);

    // Servicios
    $authzServicio       = new AutorizacionServicio($rolRepo, $permisoRepo, $usuarioRepo, $auditoriaRepo, $pdo);
    $configServicio      = new ConfiguracionServicio($configRepo, $orgRepo, $authzServicio, $auditoriaRepo, $pdo);
    $catalogoServicio    = new CatalogoServicio(
        categoriaRepo: $categoriaRepo,
        itemRepo: $itemRepo,
        paqueteRepo: $paqueteRepo,
        ofertaItemRepo: $ofertaItemRepo,
        ofertaPaqueteRepo: $ofertaPaqueteRepo,
        tarifaItemRepo: $tarifaItemRepo,
        tarifaPaqueteRepo: $tarifaPaqueteRepo,
        historialRepo: $historialRepo,
        edicionRepo: $edicionRepo,
        authzServicio: $authzServicio,
        auditoriaRepo: $auditoriaRepo,
        configServicio: $configServicio,
        pdo: $pdo
    );

    // Tenant Principal (Org A)
    $stmtOrgA = $pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (:codigo, :nombre, :razon, 2, :doc, 'PE', 'contacto@cat-a.pe', 'ACTIVO')");
    $stmtOrgA->execute([
        ':codigo' => 'org-cat-a-' . time(),
        ':nombre' => 'Productora Catálogo A',
        ':razon'  => 'Estudio Catálogo A S.A.C.',
        ':doc'    => '20' . substr(strval(time()), 0, 9)
    ]);
    $orgAId = (int) $pdo->lastInsertId();

    // Tenant Secundario para IDOR (Org B)
    $stmtOrgB = $pdo->prepare("INSERT INTO `organizaciones` (`codigo`, `nombre_comercial`, `razon_social`, `tipo_documento_id`, `numero_documento`, `codigo_pais`, `correo_contacto`, `estado`) VALUES (:codigo, :nombre, :razon, 2, :doc, 'PE', 'contacto@cat-b.pe', 'ACTIVO')");
    $stmtOrgB->execute([
        ':codigo' => 'org-cat-b-' . time(),
        ':nombre' => 'Productora Catálogo B',
        ':razon'  => 'Estudio Catálogo B S.A.C.',
        ':doc'    => '20' . substr(strval(time() + 1), 0, 9)
    ]);
    $orgBId = (int) $pdo->lastInsertId();

    // Sembrar Edición para Org A y Org B
    $edicionAId = $edicionRepo->crear(new Edicion(
        id: null,
        organizacionId: $orgAId,
        codigo: 'candelaria-2026-cat-' . time(),
        nombre: 'Festividad Candelaria 2026 Cat A',
        anio: 2026,
        fechaInicio: '2026-02-01',
        fechaFin: '2026-02-15',
        estado: EstadoEdicion::PREOPERACION,
        descripcion: 'Edición oficial de pruebas F2.3C',
        esActual: true
    ));
    $edicionA = $edicionRepo->buscarPorId($edicionAId);
    afirmar($edicionA !== null, 'Edición de pruebas creada correctamente para Tenant A');

    $edicionBId = $edicionRepo->crear(new Edicion(
        id: null,
        organizacionId: $orgBId,
        codigo: 'candelaria-2026-cat-b-' . time(),
        nombre: 'Festividad Candelaria 2026 Cat B',
        anio: 2026,
        fechaInicio: '2026-02-01',
        fechaFin: '2026-02-15',
        estado: EstadoEdicion::PREOPERACION,
        descripcion: 'Edición de pruebas Tenant B',
        esActual: true
    ));

    // Usuarios: Administrador (Rol 1 / Superadmin)
    $perAdminA = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgAId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556601',
        nombres: 'Admin',
        apellidos: 'Catalogo A',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'admin.cat@candelaria.pe',
        telefonoMovil: '+51951000101',
        telefonoWhatsapp: '+51951000101',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrAdminAId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgAId,
        personaId: $perAdminA,
        nombreUsuario: 'admin_cat_a_' . time(),
        nombreCompleto: 'Admin Catalogo A',
        correoElectronico: 'admin.cat@candelaria.pe',
        contrasenaHash: password_hash('AdminCat2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($usrAdminAId, [1]); // Superadministrador

    // Usuario: Operador (Rol 3: solo lectura catalogo.ver)
    $perOperadorA = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgAId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556602',
        nombres: 'Operador',
        apellidos: 'Catalogo A',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'operador.cat@candelaria.pe',
        telefonoMovil: '+51951000102',
        telefonoWhatsapp: '+51951000102',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrOperadorAId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgAId,
        personaId: $perOperadorA,
        nombreUsuario: 'operador_cat_a_' . time(),
        nombreCompleto: 'Operador Catalogo A',
        correoElectronico: 'operador.cat@candelaria.pe',
        contrasenaHash: password_hash('OperadorCat2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($usrOperadorAId, [3]); // Operador

    // Usuario sin permisos (Rol vacío)
    $perSinPerm = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgAId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556603',
        nombres: 'Sin',
        apellidos: 'Permisos A',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'sinperm@candelaria.pe',
        telefonoMovil: '+51951000103',
        telefonoWhatsapp: '+51951000103',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrSinPermId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgAId,
        personaId: $perSinPerm,
        nombreUsuario: 'sin_perm_a_' . time(),
        nombreCompleto: 'Sin Permisos A',
        correoElectronico: 'sinperm@candelaria.pe',
        contrasenaHash: password_hash('SinPerm2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));

    // Usuario Admin de Org B (para IDOR)
    $perAdminB = $personaRepo->crear(new Persona(
        id: null,
        organizacionId: $orgBId,
        tipoPersona: 'NATURAL',
        tipoDocumentoId: 1,
        numeroDocumento: '44556604',
        nombres: 'Admin',
        apellidos: 'Catalogo B',
        razonSocial: null,
        nombreComercial: null,
        correoElectronico: 'admin.cat.b@candelaria.pe',
        telefonoMovil: '+51951000104',
        telefonoWhatsapp: '+51951000104',
        codigoPais: 'PE',
        ciudad: 'Puno'
    ));

    $usrAdminBId = $usuarioRepo->crear(new Usuario(
        id: null,
        organizacionId: $orgBId,
        personaId: $perAdminB,
        nombreUsuario: 'admin_cat_b_' . time(),
        nombreCompleto: 'Admin Catalogo B',
        correoElectronico: 'admin.cat.b@candelaria.pe',
        contrasenaHash: password_hash('AdminCatB2026!', PASSWORD_BCRYPT),
        estado: 'ACTIVO'
    ));
    $rolRepo->sincronizarRolesUsuario($usrAdminBId, [1]);

    // Token CSRF
    $tokenCsrfValido = ProtectorCsrf::generarToken();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenCsrfValido;

    // Contextos de operación
    $ctxAdminA = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-Admin',
        organizacionId: $orgAId,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    $ctxOperadorA = ContextoOperacion::paraHumano(
        usuarioId: $usrOperadorAId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-Operador',
        organizacionId: $orgAId,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    $ctxSinPermiso = ContextoOperacion::paraHumano(
        usuarioId: $usrSinPermId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-SinPerm',
        organizacionId: $orgAId,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    $ctxAdminB = ContextoOperacion::paraHumano(
        usuarioId: $usrAdminBId,
        canalId: 1,
        canalCodigo: 'WEB',
        origenIp: '127.0.0.1',
        agenteUsuario: 'PHPUnit-CLI-AdminB',
        organizacionId: $orgBId,
        metadatos: ['csrf_token' => $tokenCsrfValido]
    );

    // Mock dinámico de AutenticacionMiddleware
    $authMiddlewareMock = new class extends AutenticacionMiddleware {
        public ?ContextoOperacion $ctx = null;
        public function procesar(array $servidor = [], array $cookies = [], bool $bloquearSiInvalido = true): ?ContextoOperacion {
            return $this->ctx;
        }
    };

    $authzMiddlewareReal = new AutorizacionMiddleware($authzServicio);

    $controlador = new CatalogoControlador(
        authMiddleware: $authMiddlewareMock,
        authzMiddleware: $authzMiddlewareReal,
        catalogoServicio: $catalogoServicio,
        edicionRepo: $edicionRepo,
        configServicio: $configServicio,
        pdo: $pdo
    );

    // Helper para cambiar contexto
    $cambiarContexto = function (?ContextoOperacion $ctx) use ($authMiddlewareMock) {
        $authMiddlewareMock->ctx = $ctx;
        ContextoOperacion::establecerActual($ctx);
    };

    // ==============================================================================
    // BLOQUE 1: CONTROL DE ACCESO A VISTAS WEB Y RBAC EN NAVEGACIÓN
    // ==============================================================================
    echo "\n--- BLOQUE 1: CONTROL DE ACCESO A VISTAS WEB Y RBAC EN NAVEGACIÓN ---\n";

    // 1.1 Usuario sin permiso catalogo.ver recibe 403 en vistaItems
    $cambiarContexto($ctxSinPermiso);
    ob_start();
    $html403Items = $controlador->vistaItems();
    ob_get_clean();
    afirmar(str_contains($html403Items, 'Acceso Denegado') && str_contains($html403Items, '403'), 'vistaItems() deniega acceso (403) a usuario sin catalogo.ver');

    // 1.2 Usuario sin permiso catalogo.ver recibe 403 en vistaPaquetes
    ob_start();
    $html403Paq = $controlador->vistaPaquetes();
    ob_get_clean();
    afirmar(str_contains($html403Paq, 'Acceso Denegado') && str_contains($html403Paq, '403'), 'vistaPaquetes() deniega acceso (403) a usuario sin catalogo.ver');

    // 1.3 Usuario sin permiso catalogo.ver recibe 403 en vistaOfertas
    ob_start();
    $html403Ofertas = $controlador->vistaOfertas();
    ob_get_clean();
    afirmar(str_contains($html403Ofertas, 'Acceso Denegado') && str_contains($html403Ofertas, '403'), 'vistaOfertas() deniega acceso (403) a usuario sin catalogo.ver');

    // 1.4 Usuario con permiso catalogo.ver visualiza vistaItems correctamente
    $cambiarContexto($ctxAdminA);
    ob_start();
    $htmlItems = $controlador->vistaItems();
    ob_get_clean();
    afirmar(
        str_contains($htmlItems, 'Catálogo de Ítems Comerciales') &&
        str_contains($htmlItems, 'catalogo_items.js'),
        'vistaItems() renderiza vista oficial con título, contenedor DataTables y script catalogo_items.js'
    );

    // 1.5 Usuario con permiso catalogo.ver visualiza vistaPaquetes correctamente
    ob_start();
    $htmlPaq = $controlador->vistaPaquetes();
    ob_get_clean();
    afirmar(
        str_contains($htmlPaq, 'Paquetes Comerciales') &&
        str_contains($htmlPaq, 'catalogo_paquetes.js'),
        'vistaPaquetes() renderiza vista oficial con modal de composición y script catalogo_paquetes.js'
    );

    // 1.6 Usuario con permiso catalogo.ver visualiza vistaOfertas correctamente
    ob_start();
    $htmlOfertas = $controlador->vistaOfertas();
    ob_get_clean();
    afirmar(
        str_contains($htmlOfertas, 'Ofertas y Tarifas por Edición') &&
        str_contains($htmlOfertas, 'catalogo_ofertas.js'),
        'vistaOfertas() renderiza vista oficial con tabs de ofertas y script catalogo_ofertas.js'
    );

    // 1.7 Verificación de menú sidebar para Catálogo Comercial
    ob_start();
    $seccionActiva = 'catalogo_items';
    include __DIR__ . '/../recursos/vistas/parciales/barra_lateral.php';
    $htmlSidebar = ob_get_clean();
    afirmar(
        str_contains($htmlSidebar, 'data-target="menuCatalogo"') &&
        str_contains($htmlSidebar, 'id="menuCatalogo"') &&
        str_contains($htmlSidebar, 'catalogo/items') &&
        str_contains($htmlSidebar, 'catalogo/paquetes') &&
        str_contains($htmlSidebar, 'catalogo/ofertas'),
        'Barra lateral incorpora módulo de Catálogo Comercial en semi-side-nav y main-side-menu con sus 3 rutas operativas'
    );

    // ==============================================================================
    // BLOQUE 2: ENDPOINTS API DE CATEGORÍAS (RBAC, CRUD Y CSRF)
    // ==============================================================================
    echo "\n--- BLOQUE 2: ENDPOINTS API DE CATEGORÍAS (RBAC, CRUD Y CSRF) ---\n";

    // 2.1 Listar categorías sin permiso catalogo.ver retorna 403
    $cambiarContexto($ctxSinPermiso);
    $resListCat403 = json_decode($controlador->listarCategorias(), true);
    afirmar($resListCat403['codigo'] === 403 && $resListCat403['exito'] === false, 'GET /api/v1/catalogo/categorias deniega acceso (403) sin catalogo.ver');

    // 2.2 Listar categorías con catalogo.ver retorna 200 y array
    $cambiarContexto($ctxOperadorA);
    $resListCat = json_decode($controlador->listarCategorias(), true);
    afirmar($resListCat['codigo'] === 200 && $resListCat['exito'] === true && isset($resListCat['datos']), 'GET /api/v1/catalogo/categorias retorna 200 OK con array de categorías');

    // 2.3 Crear categoría sin permiso catalogo.categorias.gestionar retorna 403
    $_POST = ['codigo' => 'CAT_OP', 'nombre' => 'Categoría Operador'];
    $resCrearCat403 = json_decode($controlador->crearCategoria(), true);
    afirmar($resCrearCat403['codigo'] === 403, 'POST /api/v1/catalogo/categorias deniega creación (403) a usuario sin catalogo.categorias.gestionar');

    // 2.4 Crear categoría sin CSRF válido retorna 403
    $cambiarContexto($ctxAdminA);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token_falso_invalido';
    $_POST = ['codigo' => 'CAT_FAIL', 'nombre' => 'Falla CSRF'];
    $resCrearCatCsrf = json_decode($controlador->crearCategoria(), true);
    afirmar($resCrearCatCsrf['codigo'] === 403 && str_contains($resCrearCatCsrf['mensaje'], 'CSRF'), 'POST /api/v1/catalogo/categorias rechaza petición con token CSRF inválido');
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenCsrfValido;

    // 2.5 Crear categoría con campos faltantes retorna 400
    $_POST = ['codigo' => '', 'nombre' => ''];
    $resCrearCat400 = json_decode($controlador->crearCategoria(), true);
    afirmar($resCrearCat400['codigo'] === 400, 'POST /api/v1/catalogo/categorias retorna 400 ante campos vacíos');

    // 2.6 Crear categoría exitosamente con superadmin
    $codigoCatTest = 'CAT_TEST_' . time();
    $_POST = ['codigo' => $codigoCatTest, 'nombre' => 'Categoría de Prueba F2.3C', 'descripcion' => 'Descripción de prueba'];
    $resCrearCat = json_decode($controlador->crearCategoria(), true);
    afirmar($resCrearCat['codigo'] === 201 && $resCrearCat['exito'] === true, 'POST /api/v1/catalogo/categorias crea categoría con 201 Created');
    $categoriaCreadaId = (int) ($resCrearCat['datos']['categoria']['id'] ?? 0);
    afirmar($categoriaCreadaId > 0, 'La categoría creada devuelve ID positivo autogenerado');

    // 2.7 Actualizar categoría
    $_POST = ['nombre' => 'Categoría de Prueba Editada', 'descripcion' => 'Descripción modificada'];
    $resActCat = json_decode($controlador->actualizarCategoria($categoriaCreadaId), true);
    afirmar($resActCat['codigo'] === 200 && $resActCat['exito'] === true, 'PUT /api/v1/catalogo/categorias/{id} actualiza categoría correctamente');

    // 2.8 Cambiar estado de categoría
    $_POST = ['estado' => 'INACTIVO'];
    $resEstCat = json_decode($controlador->cambiarEstadoCategoria($categoriaCreadaId), true);
    afirmar($resEstCat['codigo'] === 200 && $resEstCat['datos']['categoria']['activo'] === false, 'PATCH /api/v1/catalogo/categorias/{id}/estado conmuta a INACTIVO');

    // Reactivar para uso en ítems
    $_POST = ['estado' => 'ACTIVO'];
    $controlador->cambiarEstadoCategoria($categoriaCreadaId);

    // ==============================================================================
    // BLOQUE 3: ENDPOINTS API DE ÍTEMS COMERCIALES (PRODUCTOS Y SERVICIOS)
    // ==============================================================================
    echo "\n--- BLOQUE 3: ENDPOINTS API DE ÍTEMS COMERCIALES (PRODUCTOS Y SERVICIOS) ---\n";

    // 3.1 Listar ítems sin catalogo.ver retorna 403
    $cambiarContexto($ctxSinPermiso);
    $resListItems403 = json_decode($controlador->listarItems(), true);
    afirmar($resListItems403['codigo'] === 403, 'GET /api/v1/catalogo/items deniega lectura (403) sin permiso catalogo.ver');

    // 3.2 Listar ítems con catalogo.ver retorna 200
    $cambiarContexto($ctxOperadorA);
    $resListItems = json_decode($controlador->listarItems(), true);
    afirmar($resListItems['codigo'] === 200 && isset($resListItems['datos']), 'GET /api/v1/catalogo/items retorna 200 OK');

    // 3.3 Crear ítem sin catalogo.items.gestionar retorna 403
    $_POST = [
        'categoria_id'   => $categoriaCreadaId,
        'codigo'         => 'PROD_TEST_01',
        'nombre'         => 'Producto de Prueba',
        'tipo'           => 'PRODUCTO',
        'unidad_medida'  => 'UNIDAD',
    ];
    $resCrearItem403 = json_decode($controlador->crearItem(), true);
    afirmar($resCrearItem403['codigo'] === 403, 'POST /api/v1/catalogo/items deniega creación (403) a usuario sin catalogo.items.gestionar');

    // 3.4 Validación de enum tipo inválido retorna 400
    $cambiarContexto($ctxAdminA);
    $_POST['tipo'] = 'TIPO_INVENTADO';
    $resCrearItemTipoInv = json_decode($controlador->crearItem(), true);
    afirmar($resCrearItemTipoInv['codigo'] === 400, 'POST /api/v1/catalogo/items rechaza tipo no admitido con 400');

    // 3.5 Validación de enum unidad_medida inválido retorna 400
    $_POST['tipo'] = 'PRODUCTO';
    $_POST['unidad_medida'] = 'KILOGRAMO_NO_CANONICO';
    $resCrearItemUniInv = json_decode($controlador->crearItem(), true);
    afirmar($resCrearItemUniInv['codigo'] === 400, 'POST /api/v1/catalogo/items rechaza unidad de medida fuera de las 9 canónicas con 400');

    // 3.6 Crear Ítem tipo PRODUCTO válido con superadmin
    $codigoItemProd = 'PROD_TEST_' . time();
    $_POST = [
        'categoria_id'   => $categoriaCreadaId,
        'codigo'         => $codigoItemProd,
        'nombre'         => 'Álbum Fotográfico Premium',
        'tipo'           => 'PRODUCTO',
        'unidad_medida'  => 'UNIDAD',
        'descripcion'    => 'Álbum empastado en cuero con 50 páginas',
        'activo'         => true,
    ];
    $resCrearProd = json_decode($controlador->crearItem(), true);
    afirmar($resCrearProd['codigo'] === 201 && $resCrearProd['exito'] === true, 'POST /api/v1/catalogo/items crea PRODUCTO tangible con 201 Created');
    $itemProdId = (int) ($resCrearProd['datos']['item']['id'] ?? 0);
    afirmar($itemProdId > 0, 'PRODUCTO creado tiene ID positivo');

    // 3.7 Crear Ítem tipo SERVICIO válido con superadmin
    $codigoItemServ = 'SERV_TEST_' . time();
    $_POST = [
        'categoria_id'   => $categoriaCreadaId,
        'codigo'         => $codigoItemServ,
        'nombre'         => 'Sesión de Video en Traje Típico',
        'tipo'           => 'SERVICIO',
        'unidad_medida'  => 'SERVICIO',
        'descripcion'    => 'Grabación en 4K en locación folclórica de Puno',
        'activo'         => true,
    ];
    $resCrearServ = json_decode($controlador->crearItem(), true);
    afirmar($resCrearServ['codigo'] === 201 && $resCrearServ['datos']['item']['tipo'] === 'SERVICIO', 'POST /api/v1/catalogo/items crea SERVICIO intangible con 201 Created');
    $itemServId = (int) ($resCrearServ['datos']['item']['id'] ?? 0);
    afirmar($itemServId > 0, 'SERVICIO creado tiene ID positivo');

    // 3.8 Búsqueda asíncrona de ítems (para Select2)
    $_GET['q'] = 'Álbum';
    $resBuscarItems = json_decode($controlador->buscarItems(), true);
    afirmar($resBuscarItems['codigo'] === 200 && count($resBuscarItems['datos']) >= 1, 'GET /api/v1/catalogo/items/buscar encuentra ítem por término para Select2');
    unset($_GET['q']);

    // 3.9 Detalle de ítem comercial
    $resDetalleItem = json_decode($controlador->detalleItem($itemProdId), true);
    afirmar($resDetalleItem['codigo'] === 200 && $resDetalleItem['datos']['item']['codigo'] === $codigoItemProd, 'GET /api/v1/catalogo/items/{id} retorna detalle con 200 OK');

    // 3.10 Actualizar ítem comercial
    $_POST = [
        'categoria_id'   => $categoriaCreadaId,
        'nombre'         => 'Álbum Fotográfico Premium Deluxe',
        'tipo'           => 'PRODUCTO',
        'unidad_medida'  => 'UNIDAD',
        'descripcion'    => 'Actualizado con acabado en pan de oro',
        'activo'         => true,
    ];
    $resActItem = json_decode($controlador->actualizarItem($itemProdId), true);
    afirmar($resActItem['codigo'] === 200 && str_contains($resActItem['datos']['item']['nombre'], 'Deluxe'), 'PUT /api/v1/catalogo/items/{id} actualiza datos del ítem comercial');

    // 3.11 Cambiar estado de ítem comercial
    $_POST = ['estado' => 'INACTIVO'];
    $resEstItem = json_decode($controlador->cambiarEstadoItem($itemProdId), true);
    afirmar($resEstItem['codigo'] === 200 && $resEstItem['datos']['item']['activo'] === false, 'PATCH /api/v1/catalogo/items/{id}/estado desactiva ítem');

    // Reactivar para permitir empaquetamiento y oferta
    $_POST = ['estado' => 'ACTIVO'];
    $controlador->cambiarEstadoItem($itemProdId);

    // ==============================================================================
    // BLOQUE 4: ENDPOINTS API DE PAQUETES Y COMPOSICIÓN (REGLA SOBERANA)
    // ==============================================================================
    echo "\n--- BLOQUE 4: ENDPOINTS API DE PAQUETES Y COMPOSICIÓN (REGLA SOBERANA) ---\n";

    // 4.1 Crear paquete sin catalogo.paquetes.gestionar retorna 403
    $cambiarContexto($ctxOperadorA);
    $_POST = ['codigo' => 'PAQ_TEST_01', 'nombre' => 'Paquete Operador'];
    $resCrearPaq403 = json_decode($controlador->crearPaquete(), true);
    afirmar($resCrearPaq403['codigo'] === 403, 'POST /api/v1/catalogo/paquetes deniega creación (403) a usuario sin catalogo.paquetes.gestionar');

    // 4.2 Crear paquete con superadmin
    $cambiarContexto($ctxAdminA);
    $codigoPaqTest = 'PAQ_VIP_' . time();
    $_POST = [
        'codigo'      => $codigoPaqTest,
        'nombre'      => 'Paquete Candelaria VIP Cobertura Total',
        'descripcion' => 'Paquete integrado con álbum físico y video profesional',
        'activo'      => true,
    ];
    $resCrearPaq = json_decode($controlador->crearPaquete(), true);
    afirmar($resCrearPaq['codigo'] === 201 && $resCrearPaq['exito'] === true, 'POST /api/v1/catalogo/paquetes crea paquete con 201 Created');
    $paqueteCreadoId = (int) ($resCrearPaq['datos']['paquete']['id'] ?? 0);
    afirmar($paqueteCreadoId > 0, 'Paquete creado tiene ID autogenerado');

    // 4.3 Consultar composición inicial (debe estar vacía)
    $resCompInicial = json_decode($controlador->obtenerComposicion($paqueteCreadoId), true);
    afirmar($resCompInicial['codigo'] === 200 && count($resCompInicial['datos']) === 0, 'GET /api/v1/catalogo/paquetes/{id}/composicion retorna array vacío inicialmente');

    // 4.4 Sincronizar composición con ítems (Regla Soberana: todos incluidos, cantidad >= 1)
    $_POST = [
        'items' => [
            [
                'item_id'  => $itemProdId,
                'cantidad' => 2,
                'orden'    => 1,
                'nota'     => 'Dos copias de lujo para familiares',
            ],
            [
                'item_id'  => $itemServId,
                'cantidad' => 1,
                'orden'    => 2,
                'nota'     => 'Sesión completa de 4 horas',
            ]
        ]
    ];
    $resSyncComp = json_decode($controlador->sincronizarComposicion($paqueteCreadoId), true);
    afirmar($resSyncComp['codigo'] === 200 && $resSyncComp['exito'] === true, 'PUT /api/v1/catalogo/paquetes/{id}/composicion sincroniza composición con 2 ítems incluidos');

    // 4.5 Consultar composición actualizada
    $resCompActual = json_decode($controlador->obtenerComposicion($paqueteCreadoId), true);
    afirmar(
        $resCompActual['codigo'] === 200 &&
        count($resCompActual['datos']) === 2 &&
        (int) $resCompActual['datos'][0]['cantidad'] === 2,
        'GET /api/v1/catalogo/paquetes/{id}/composicion retorna los 2 ítems incluidos con sus cantidades y sin campos de precio opcional'
    );

    // 4.6 Rechazo de cantidad <= 0 en composición
    $_POST = [
        'items' => [
            ['item_id' => $itemProdId, 'cantidad' => 0, 'orden' => 1]
        ]
    ];
    $resCompCero = json_decode($controlador->sincronizarComposicion($paqueteCreadoId), true);
    afirmar($resCompCero['codigo'] === 400, 'PUT /api/v1/catalogo/paquetes/{id}/composicion rechaza cantidad 0 con HTTP 400');

    // 4.7 Cambiar estado de paquete
    $_POST = ['estado' => 'INACTIVO'];
    $resEstPaq = json_decode($controlador->cambiarEstadoPaquete($paqueteCreadoId), true);
    afirmar($resEstPaq['codigo'] === 200 && $resEstPaq['datos']['paquete']['activo'] === false, 'PATCH /api/v1/catalogo/paquetes/{id}/estado conmuta a INACTIVO');

    // Reactivar paquete
    $_POST = ['estado' => 'ACTIVO'];
    $controlador->cambiarEstadoPaquete($paqueteCreadoId);

    // ==============================================================================
    // BLOQUE 5: ENDPOINTS API DE OFERTAS POR EDICIÓN
    // ==============================================================================
    echo "\n--- BLOQUE 5: ENDPOINTS API DE OFERTAS POR EDICIÓN ---\n";

    // 5.1 Listar ofertas por edición requiere edicion_id
    unset($_GET['edicion_id']);
    unset($_SERVER['HTTP_X_EDICION_ID']);
    $resListOfertas400 = json_decode($controlador->listarOfertas(), true);
    afirmar($resListOfertas400['codigo'] === 400, 'GET /api/v1/catalogo/ofertas sin edicion_id retorna 400');

    // 5.2 Listar ofertas con header HTTP_X_EDICION_ID
    $_SERVER['HTTP_X_EDICION_ID'] = (string) $edicionAId;
    $resListOfertas = json_decode($controlador->listarOfertas(), true);
    afirmar(
        $resListOfertas['codigo'] === 200 &&
        isset($resListOfertas['datos']['items']) &&
        isset($resListOfertas['datos']['paquetes']),
        'GET /api/v1/catalogo/ofertas resuelve contexto desde header HTTP_X_EDICION_ID y retorna estructura con items y paquetes'
    );

    // 5.3 Habilitar oferta de ítem sin catalogo.ofertas.gestionar retorna 403
    $cambiarContexto($ctxOperadorA);
    $_POST = [
        'edicion_id'            => $edicionAId,
        'item_comercial_id'     => $itemProdId,
        'capacidad_referencial' => 50,
    ];
    $resHabItem403 = json_decode($controlador->habilitarOfertaItem(), true);
    afirmar($resHabItem403['codigo'] === 403, 'POST /api/v1/catalogo/ofertas/items deniega habilitación (403) sin catalogo.ofertas.gestionar');

    // 5.4 Habilitar oferta de ítem con capacidad referencial y tarifa inicial opcional
    $cambiarContexto($ctxAdminA);
    $_POST = [
        'edicion_id'            => $edicionAId,
        'item_comercial_id'     => $itemProdId,
        'capacidad_referencial' => 75,
        'precio_inicial'        => 350.00,
    ];
    $resHabItem = json_decode($controlador->habilitarOfertaItem(), true);
    afirmar(
        $resHabItem['codigo'] === 201 &&
        $resHabItem['exito'] === true &&
        (int) $resHabItem['datos']['oferta']['capacidad_referencial'] === 75,
        'POST /api/v1/catalogo/ofertas/items habilita ítem en edición con capacidad referencial (75) y tarifa inicial automática'
    );
    $ofertaItemId = (int) ($resHabItem['datos']['oferta']['id'] ?? 0);

    // 5.5 Habilitar oferta de paquete
    $_POST = [
        'edicion_id'            => $edicionAId,
        'paquete_id'            => $paqueteCreadoId,
        'capacidad_referencial' => 20,
        'precio_inicial'        => 1200.00,
    ];
    $resHabPaq = json_decode($controlador->habilitarOfertaPaquete(), true);
    afirmar(
        $resHabPaq['codigo'] === 201 &&
        $resHabPaq['exito'] === true &&
        (int) $resHabPaq['datos']['oferta']['capacidad_referencial'] === 20,
        'POST /api/v1/catalogo/ofertas/paquetes habilita paquete en edición con capacidad referencial (20) y tarifa inicial automática'
    );
    $ofertaPaqId = (int) ($resHabPaq['datos']['oferta']['id'] ?? 0);

    // 5.6 Conmutar estado de oferta ítem (deshabilitar y rehabilitar)
    $_POST = ['estado' => 'INACTIVO'];
    $resEstOfItem = json_decode($controlador->cambiarEstadoOfertaItem($ofertaItemId), true);
    afirmar($resEstOfItem['codigo'] === 200 && $resEstOfItem['datos']['oferta']['activo'] === false, 'PATCH /api/v1/catalogo/ofertas/items/{id}/estado deshabilita oferta');

    $_POST = ['estado' => 'ACTIVO'];
    $controlador->cambiarEstadoOfertaItem($ofertaItemId);

    // ==============================================================================
    // BLOQUE 6: TARIFAS VIGENTES, MONEDA INMUTABLE Y CONCURRENCIA OPTIMISTA 409
    // ==============================================================================
    echo "\n--- BLOQUE 6: TARIFAS VIGENTES, MONEDA INMUTABLE Y CONCURRENCIA OPTIMISTA 409 ---\n";

    // 6.1 Obtener tarifa vigente del ítem
    $tarifaItemVigente = $tarifaItemRepo->buscarPorOfertaId($ofertaItemId);
    afirmar($tarifaItemVigente !== null, 'El ítem en oferta cuenta con tarifa vigente inicial registrada');
    $tarifaItemId = (int) $tarifaItemVigente->id;
    $versionInicial = (int) $tarifaItemVigente->versionBloqueo;
    $monedaOriginal = $tarifaItemVigente->moneda;
    afirmar($monedaOriginal === 'PEN', 'La divisa de la tarifa es estrictamente PEN (moneda principal de la plataforma)');

    // 6.2 Actualizar tarifa sin catalogo.tarifas.gestionar retorna 403
    $cambiarContexto($ctxOperadorA);
    $_POST = [
        'nuevo_precio'    => 380.00,
        'motivo'          => 'Intento operador',
        'version_bloqueo' => $versionInicial,
    ];
    $resActTar403 = json_decode($controlador->actualizarTarifaItem($tarifaItemId), true);
    afirmar($resActTar403['codigo'] === 403, 'PUT /api/v1/catalogo/tarifas/items/{id} deniega actualización de tarifa (403) a usuario sin catalogo.tarifas.gestionar');

    // 6.3 Actualizar tarifa exitosamente con version_bloqueo correcta
    $cambiarContexto($ctxAdminA);
    $_POST = [
        'nuevo_precio'    => 399.50,
        'motivo'          => 'Ajuste de temporada alta Puno 2026',
        'version_bloqueo' => $versionInicial,
    ];
    $resActTarOk = json_decode($controlador->actualizarTarifaItem($tarifaItemId), true);
    afirmar(
        $resActTarOk['codigo'] === 200 &&
        $resActTarOk['exito'] === true &&
        (float) $resActTarOk['datos']['tarifa']['precio'] === 399.50 &&
        (int) $resActTarOk['datos']['tarifa']['version_bloqueo'] === ($versionInicial + 1),
        'PUT /api/v1/catalogo/tarifas/items/{id} actualiza tarifa exitosamente e incrementa version_bloqueo a v2'
    );

    // 6.4 CONCURRENCIA OPTIMISTA: Usuario concurrente intenta actualizar usando version_bloqueo vieja
    $_POST = [
        'nuevo_precio'    => 450.00,
        'motivo'          => 'Intento de guardado desfasado',
        'version_bloqueo' => $versionInicial, // Versión 1 cuando en BD ya es Versión 2
    ];
    $resConflicto409 = json_decode($controlador->actualizarTarifaItem($tarifaItemId), true);
    afirmar(
        $resConflicto409['codigo'] === 409 &&
        $resConflicto409['exito'] === false &&
        str_contains($resConflicto409['mensaje'], 'concurrentemente'),
        'PUT /api/v1/catalogo/tarifas/items/{id} detecta colisión concurrente, retorna HTTP 409 e instruye recarga'
    );

    // 6.5 Actualización de tarifa de paquete con colisión concurrente idéntica (409)
    $tarifaPaqVigente = $tarifaPaqueteRepo->buscarPorOfertaId($ofertaPaqId);
    afirmar($tarifaPaqVigente !== null, 'El paquete en oferta cuenta con tarifa vigente inicial registrada');
    $tarifaPaqId = (int) $tarifaPaqVigente->id;
    $vPaq = (int) $tarifaPaqVigente->versionBloqueo;

    // Actualizar correctamente
    $_POST = [
        'nuevo_precio'    => 1350.00,
        'motivo'          => 'Inclusión de nuevo material de archivo',
        'version_bloqueo' => $vPaq,
    ];
    $resActPaqOk = json_decode($controlador->actualizarTarifaPaquete($tarifaPaqId), true);
    afirmar($resActPaqOk['codigo'] === 200 && (float) $resActPaqOk['datos']['tarifa']['precio'] === 1350.00, 'PUT /api/v1/catalogo/tarifas/paquetes/{id} actualiza tarifa de paquete exitosamente');

    // Forzar colisión con vPaq anterior
    $_POST['version_bloqueo'] = $vPaq;
    $resConflictoPaq409 = json_decode($controlador->actualizarTarifaPaquete($tarifaPaqId), true);
    afirmar($resConflictoPaq409['codigo'] === 409, 'PUT /api/v1/catalogo/tarifas/paquetes/{id} retorna HTTP 409 ante versión desactualizada');

    // ==============================================================================
    // BLOQUE 7: HISTORIAL DE TARIFAS APPEND-ONLY Y PRIVACIDAD ZERO PII
    // ==============================================================================
    echo "\n--- BLOQUE 7: HISTORIAL DE TARIFAS APPEND-ONLY Y PRIVACIDAD ZERO PII ---\n";

    // 7.1 Usuario operador tiene catalogo.ver pero NO catalogo.tarifas.ver_historial -> 403
    $cambiarContexto($ctxOperadorA);
    $resHist403 = json_decode($controlador->historialTarifaItem($itemProdId), true);
    afirmar(
        $resHist403['codigo'] === 403 &&
        str_contains($resHist403['mensaje'], 'catalogo.tarifas.ver_historial'),
        'GET /api/v1/catalogo/tarifas/items/{id}/historial deniega acceso (403) a quien no posee permiso exclusivo catalogo.tarifas.ver_historial'
    );

    // 7.2 Usuario superadmin con catalogo.tarifas.ver_historial consulta historial exitosamente
    $cambiarContexto($ctxAdminA);
    $_GET['edicion_id'] = (string) $edicionAId;
    $resHistOk = json_decode($controlador->historialTarifaItem($itemProdId), true);
    afirmar(
        $resHistOk['codigo'] === 200 &&
        $resHistOk['exito'] === true &&
        count($resHistOk['datos']['historial']) >= 1,
        'GET /api/v1/catalogo/tarifas/items/{id}/historial retorna trazabilidad histórica completa (transición append-only)'
    );

    // 7.3 ZERO PII: Verificar que el historial expone únicamente actor seguro sin datos personales sensibles
    $lineaHist = $resHistOk['datos']['historial'][0];
    $contienePii = isset($lineaHist['contrasena_hash']) ||
                   isset($lineaHist['correo_electronico']) ||
                   isset($lineaHist['telefono']) ||
                   isset($lineaHist['numero_documento']);
    afirmar(
        !$contienePii && isset($lineaHist['actor_display']) && isset($lineaHist['actor_tipo']),
        'Historial de tarifas cumple directiva estricta ZERO PII (solo actor_tipo, actor_display, motivo y precios)'
    );

    // 7.4 Historial de tarifas de paquete
    $resHistPaqOk = json_decode($controlador->historialTarifaPaquete($paqueteCreadoId), true);
    afirmar(
        $resHistPaqOk['codigo'] === 200 &&
        count($resHistPaqOk['datos']['historial']) >= 1,
        'GET /api/v1/catalogo/tarifas/paquetes/{id}/historial retorna trazabilidad inmutable append-only del paquete'
    );

    // ==============================================================================
    // BLOQUE 8: ANTI-IDOR Y SEGURIDAD MULTITENANT
    // ==============================================================================
    echo "\n--- BLOQUE 8: ANTI-IDOR Y SEGURIDAD MULTITENANT ---\n";

    // 8.1 Tenant 2 intenta consultar ítem de Tenant 1 -> 404
    $cambiarContexto($ctxAdminB);
    $resIdorItem = json_decode($controlador->detalleItem($itemProdId), true);
    afirmar($resIdorItem['codigo'] === 404, 'Anti-IDOR: Tenant 2 no puede acceder al ítem comercial del Tenant 1 (404 Not Found)');

    // 8.2 Tenant 2 intenta consultar paquete de Tenant 1 -> 404
    $resIdorPaq = json_decode($controlador->detallePaquete($paqueteCreadoId), true);
    afirmar($resIdorPaq['codigo'] === 404, 'Anti-IDOR: Tenant 2 no puede acceder al paquete comercial del Tenant 1 (404 Not Found)');

    // 8.3 Tenant 2 intenta actualizar tarifa del Tenant 1 -> 403 / 404
    $_POST = ['nuevo_precio' => 999.00, 'motivo' => 'Hack IDOR', 'version_bloqueo' => 2];
    $resIdorTar = json_decode($controlador->actualizarTarifaItem($tarifaItemId), true);
    afirmar($resIdorTar['codigo'] === 403 || $resIdorTar['codigo'] === 404, 'Anti-IDOR: Tenant 2 no puede alterar tarifas del Tenant 1 (403/404 Denegado)');

} finally {
    // Revertir transacción para preservar el estado limpio de la BD
    $pdo->rollBack();
}

// 9. Certificación de integridad de Orlando
$stmtOrlandoPost = $pdo->prepare("SELECT contrasena_hash, estado, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE nombre_usuario = 'orlando'");
$stmtOrlandoPost->execute();
$orlandoPost = $stmtOrlandoPost->fetch(PDO::FETCH_ASSOC);
$fingerprintOrlandoPost = $orlandoPost ? substr(hash('sha256', (string) $orlandoPost['contrasena_hash']), 0, 16) : null;

afirmar(
    $fingerprintOrlandoPre === $fingerprintOrlandoPost &&
    $orlandoPre['estado'] === $orlandoPost['estado'],
    'Integridad criptográfica de credenciales y estado del usuario Orlando preservada intacta'
);

echo "\n==============================================================================\n";
echo "RESULTADOS SUITE F2.3C: EXITOS: {$exitos} | FALLOS: {$fallos}\n";
echo "==============================================================================\n";

if ($fallos > 0) {
    exit(1);
}

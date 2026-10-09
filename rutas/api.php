<?php

declare(strict_types=1);

use Nucleo\Enrutamiento\Enrutador;

/**
 * Rutas de la API REST (API-First) de CandelariaAPP.
 */

Enrutador::get('/api/v1/estado', function () {
    header('Content-Type: application/json; charset=utf-8');
    return json_encode([
        'exito' => true,
        'codigo' => 200,
        'mensaje' => 'API CandelariaAPP V1 en funcionamiento.',
        'datos' => [
            'plataforma' => 'CandelariaAPP',
            'version' => '1.0.0',
            'fase' => 'F1.1C RBAC y Autorización Backend',
            'entorno' => entorno('APP_ENV', 'desarrollo'),
            'php' => PHP_VERSION,
            'frontend' => 'Alina Bootstrap 5 + JS Moderno + Fetch API',
            'iconografia' => 'Font Awesome Free 6.3.0',
            'tipografia' => 'Fira Sans Extra Condensed',
            'hora_servidor' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Autenticación (Login)
Enrutador::post('/api/v1/auth/login', function () {
    header('Content-Type: application/json; charset=utf-8');
    $cuerpo = json_decode(file_get_contents('php://input'), true);
    if (!is_array($cuerpo)) {
        $cuerpo = $_POST;
    }

    $login = (string) ($cuerpo['login'] ?? $cuerpo['usuario'] ?? $cuerpo['correo'] ?? '');
    $password = (string) ($cuerpo['password'] ?? $cuerpo['contrasena'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agente = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $correlacion = \Nucleo\Http\ContextoOperacion::extraerDeEncabezados($_SERVER);

    if (empty($login) || empty($password)) {
        http_response_code(400);
        return json_encode([
            'exito'   => false,
            'codigo'  => 400,
            'mensaje' => 'Debe ingresar su usuario/correo y contraseña.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    $servicio = new \Aplicacion\Seguridad\AutenticacionServicio();
    $resultado = $servicio->autenticar($login, $password, $ip, $agente, $correlacion);

    if (!$resultado->exitoso) {
        http_response_code(401);
        return json_encode([
            'exito'   => false,
            'codigo'  => 401,
            'mensaje' => $resultado->mensajeError,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    $usr = $resultado->usuario;
    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Autenticación exitosa.',
        'datos'   => [
            'usuario' => [
                'id'              => $usr->id,
                'nombre_usuario'  => $usr->nombreUsuario,
                'nombre_completo' => $usr->nombreCompleto,
                'correo'          => $usr->correoElectronico,
                'estado'          => $usr->estado,
            ],
            'redireccion'    => url_base(),
            'correlacion_id' => $resultado->contexto?->correlacionId,
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Cierre de Sesión (Logout)
Enrutador::post('/api/v1/auth/logout', function () {
    header('Content-Type: application/json; charset=utf-8');
    $token = \Nucleo\Seguridad\ManejadorCookie::extraerDePeticion();
    if ($token === null && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    }

    if ($token !== null) {
        $servicio = new \Aplicacion\Seguridad\AutenticacionServicio();
        $servicio->cerrarSesion($token);
    }
    \Nucleo\Seguridad\ManejadorCookie::destruir();

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Sesión cerrada correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico de Consulta de Sesión Activa
Enrutador::get('/api/v1/auth/sesion', function () {
    header('Content-Type: application/json; charset=utf-8');
    $middleware = new \Nucleo\Http\Middleware\AutenticacionMiddleware();
    $contexto = $middleware->procesar($_SERVER, $_COOKIE, false);

    if ($contexto === null) {
        http_response_code(401);
        return json_encode([
            'exito'   => false,
            'codigo'  => 401,
            'mensaje' => 'No hay una sesión activa o ha expirado.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Sesión activa verificada.',
        'datos'   => [
            'actor_tipo'     => $contexto->actorTipo,
            'usuario_id'     => $contexto->usuarioId,
            'canal'          => $contexto->canalCodigo,
            'correlacion_id' => $contexto->correlacionId,
            'ip'             => $contexto->origenIp,
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// Endpoint técnico protegido por Autorización RBAC (Requiere permiso usuarios.ver)
Enrutador::get('/api/v1/usuarios/lista', function () {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Verificar Autenticación (401 si falla)
    $authMiddleware = new \Nucleo\Http\Middleware\AutenticacionMiddleware();
    $contexto = $authMiddleware->procesar($_SERVER, $_COOKIE, true);

    // 2. Verificar Permiso RBAC (403 si carece de usuarios.ver)
    $rbacMiddleware = new \Nucleo\Http\Middleware\AutorizacionMiddleware();
    $rbacMiddleware->verificarPermiso('usuarios.ver', $contexto, true);

    // 3. Operación permitida en Backend (Fail-closed estricto si no hay tenant)
    if ($contexto->organizacionId === null || $contexto->organizacionId <= 0) {
        http_response_code(403);
        return json_encode([
            'exito'   => false,
            'codigo'  => 403,
            'mensaje' => 'Contexto organizacional ausente o inválido para la sesión activa.'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    $repo = new \Aplicacion\Repositorios\UsuarioRepositorio();
    $usuarios = $repo->buscarPorOrganizacion($contexto->organizacionId, 10, 0);

    return json_encode([
        'exito'   => true,
        'codigo'  => 200,
        'mensaje' => 'Acceso autorizado al padrón de usuarios.',
        'datos'   => [
            'total'    => count($usuarios),
            'usuarios' => array_map(fn($u) => [
                'id'              => $u->id,
                'nombre_usuario'  => $u->nombreUsuario,
                'nombre_completo' => $u->nombreCompleto,
                'estado'          => $u->estado,
            ], $usuarios)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// ==============================================================================
// GESTIÓN INTEGRAL DE USUARIOS Y CONTROL DE ACCESO (MICROLOTE F1.1D)
// ==============================================================================
Enrutador::get('/api/v1/usuarios', [\Aplicacion\Controladores\UsuarioControlador::class, 'listar']);
Enrutador::get('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'detalle']);
Enrutador::get('/api/v1/personas/disponibles', [\Aplicacion\Controladores\UsuarioControlador::class, 'personasDisponibles']);
Enrutador::get('/api/v1/tipos-documento', [\Aplicacion\Controladores\UsuarioControlador::class, 'tiposDocumento']);
Enrutador::get('/api/v1/roles', [\Aplicacion\Controladores\UsuarioControlador::class, 'roles']);
Enrutador::post('/api/v1/usuarios', [\Aplicacion\Controladores\UsuarioControlador::class, 'crear']);
Enrutador::put('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'actualizar']);
Enrutador::patch('/api/v1/usuarios/{id}/estado', [\Aplicacion\Controladores\UsuarioControlador::class, 'cambiarEstado']);
Enrutador::put('/api/v1/usuarios/{id}/roles', [\Aplicacion\Controladores\UsuarioControlador::class, 'sincronizarRoles']);
Enrutador::post('/api/v1/usuarios/{id}/restablecer-clave', [\Aplicacion\Controladores\UsuarioControlador::class, 'restablecerClave']);
Enrutador::delete('/api/v1/usuarios/{id}', [\Aplicacion\Controladores\UsuarioControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN DE ORGANIZACIÓN Y BRANDING (MICROLOTE F1.2B)
// ==============================================================================
Enrutador::get('/api/v1/organizacion', [\Aplicacion\Controladores\OrganizacionControlador::class, 'detalle']);
Enrutador::put('/api/v1/organizacion', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizar']);
Enrutador::post('/api/v1/organizacion/branding/logo', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizarLogo']);
Enrutador::post('/api/v1/organizacion/branding/isotipo', [\Aplicacion\Controladores\OrganizacionControlador::class, 'actualizarIsotipo']);

// ==============================================================================
// GESTIÓN DE CONFIGURACIÓN GENERAL Y PARÁMETROS OPERATIVOS (MICROLOTE F1.2C)
// ==============================================================================
Enrutador::get('/api/v1/configuracion', [\Aplicacion\Controladores\ConfiguracionControlador::class, 'listar']);
Enrutador::put('/api/v1/configuracion', [\Aplicacion\Controladores\ConfiguracionControlador::class, 'actualizar']);

// ==============================================================================
// GESTIÓN DE EDICIONES CANDELARIA Y CONTEXTO ACTIVO (MICROLOTE F2.1B)
// ==============================================================================
Enrutador::get('/api/v1/ediciones', [\Aplicacion\Controladores\EdicionControlador::class, 'listar']);
Enrutador::get('/api/v1/ediciones/{id}', [\Aplicacion\Controladores\EdicionControlador::class, 'detalle']);
Enrutador::post('/api/v1/ediciones', [\Aplicacion\Controladores\EdicionControlador::class, 'crear']);
Enrutador::put('/api/v1/ediciones/{id}', [\Aplicacion\Controladores\EdicionControlador::class, 'actualizar']);
Enrutador::post('/api/v1/ediciones/{id}/estado', [\Aplicacion\Controladores\EdicionControlador::class, 'cambiarEstado']);
Enrutador::post('/api/v1/ediciones/{id}/seleccionar-actual', [\Aplicacion\Controladores\EdicionControlador::class, 'seleccionarActual']);
Enrutador::get('/api/v1/contexto/edicion', [\Aplicacion\Controladores\EdicionControlador::class, 'contextoActual']);

// ==============================================================================
// GESTIÓN COMERCIAL: CLIENTES Y PERSONAS (FASE 2.2C)
// ==============================================================================
Enrutador::get('/api/v1/clientes', [\Aplicacion\Controladores\ClienteControlador::class, 'listar']);
Enrutador::get('/api/v1/personas/buscar', [\Aplicacion\Controladores\ClienteControlador::class, 'buscarPersonas']);
Enrutador::get('/api/v1/clientes/{id}', [\Aplicacion\Controladores\ClienteControlador::class, 'detalle']);
Enrutador::post('/api/v1/clientes', [\Aplicacion\Controladores\ClienteControlador::class, 'crear']);
Enrutador::patch('/api/v1/clientes/{id}/estado', [\Aplicacion\Controladores\ClienteControlador::class, 'cambiarEstado']);
Enrutador::post('/api/v1/clientes/{id}/consentimientos', [\Aplicacion\Controladores\ClienteControlador::class, 'actualizarConsentimientos']);
Enrutador::delete('/api/v1/clientes/{id}', [\Aplicacion\Controladores\ClienteControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN COMERCIAL: OPORTUNIDADES (FASE 2.2C)
// ==============================================================================
Enrutador::get('/api/v1/crm/oportunidades', [\Aplicacion\Controladores\OportunidadControlador::class, 'listar']);
Enrutador::get('/api/v1/crm/oportunidades/{id}', [\Aplicacion\Controladores\OportunidadControlador::class, 'detalle']);
Enrutador::post('/api/v1/crm/oportunidades', [\Aplicacion\Controladores\OportunidadControlador::class, 'crear']);
Enrutador::put('/api/v1/crm/oportunidades/{id}', [\Aplicacion\Controladores\OportunidadControlador::class, 'actualizar']);
Enrutador::post('/api/v1/crm/oportunidades/{id}/etapa', [\Aplicacion\Controladores\OportunidadControlador::class, 'cambiarEtapa']);
Enrutador::post('/api/v1/crm/oportunidades/{id}/asignar', [\Aplicacion\Controladores\OportunidadControlador::class, 'asignar']);
Enrutador::delete('/api/v1/crm/oportunidades/{id}', [\Aplicacion\Controladores\OportunidadControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN COMERCIAL: INTERACCIONES Y TIMELINE (FASE 2.2C)
// ==============================================================================
Enrutador::get('/api/v1/crm/interacciones', [\Aplicacion\Controladores\InteraccionControlador::class, 'listar']);
Enrutador::post('/api/v1/crm/interacciones', [\Aplicacion\Controladores\InteraccionControlador::class, 'crear']);
Enrutador::get('/api/v1/crm/clientes/{id}/timeline', [\Aplicacion\Controladores\InteraccionControlador::class, 'timeline']);
Enrutador::delete('/api/v1/crm/interacciones/{id}', [\Aplicacion\Controladores\InteraccionControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN COMERCIAL: ORÍGENES COMERCIALES (FASE 2.2C)
// ==============================================================================
Enrutador::get('/api/v1/crm/origenes', [\Aplicacion\Controladores\OrigenComercialControlador::class, 'listar']);
Enrutador::post('/api/v1/crm/origenes', [\Aplicacion\Controladores\OrigenComercialControlador::class, 'crear']);
Enrutador::put('/api/v1/crm/origenes/{id}', [\Aplicacion\Controladores\OrigenComercialControlador::class, 'actualizar']);
Enrutador::patch('/api/v1/crm/origenes/{id}/estado', [\Aplicacion\Controladores\OrigenComercialControlador::class, 'cambiarEstado']);
Enrutador::delete('/api/v1/crm/origenes/{id}', [\Aplicacion\Controladores\OrigenComercialControlador::class, 'eliminar']);

// ==============================================================================
// GESTIÓN COMERCIAL: CATÁLOGO, PAQUETES, OFERTAS Y TARIFAS (FASE 2.3C)
// ==============================================================================
// Categorías
Enrutador::get('/api/v1/catalogo/categorias', [\Aplicacion\Controladores\CatalogoControlador::class, 'listarCategorias']);
Enrutador::post('/api/v1/catalogo/categorias', [\Aplicacion\Controladores\CatalogoControlador::class, 'crearCategoria']);
Enrutador::put('/api/v1/catalogo/categorias/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'actualizarCategoria']);
Enrutador::patch('/api/v1/catalogo/categorias/{id}/estado', [\Aplicacion\Controladores\CatalogoControlador::class, 'cambiarEstadoCategoria']);

// Ítems Comerciales
Enrutador::get('/api/v1/catalogo/unidades-medida', [\Aplicacion\Controladores\CatalogoControlador::class, 'listarUnidadesMedida']);
Enrutador::get('/api/v1/catalogo/items', [\Aplicacion\Controladores\CatalogoControlador::class, 'listarItems']);
Enrutador::get('/api/v1/catalogo/items/buscar', [\Aplicacion\Controladores\CatalogoControlador::class, 'buscarItems']);
Enrutador::get('/api/v1/catalogo/items/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'detalleItem']);
Enrutador::post('/api/v1/catalogo/items', [\Aplicacion\Controladores\CatalogoControlador::class, 'crearItem']);
Enrutador::put('/api/v1/catalogo/items/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'actualizarItem']);
Enrutador::patch('/api/v1/catalogo/items/{id}/estado', [\Aplicacion\Controladores\CatalogoControlador::class, 'cambiarEstadoItem']);

// Paquetes Comerciales y Composición
Enrutador::get('/api/v1/catalogo/paquetes', [\Aplicacion\Controladores\CatalogoControlador::class, 'listarPaquetes']);
Enrutador::get('/api/v1/catalogo/paquetes/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'detallePaquete']);
Enrutador::post('/api/v1/catalogo/paquetes', [\Aplicacion\Controladores\CatalogoControlador::class, 'crearPaquete']);
Enrutador::put('/api/v1/catalogo/paquetes/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'actualizarPaquete']);
Enrutador::patch('/api/v1/catalogo/paquetes/{id}/estado', [\Aplicacion\Controladores\CatalogoControlador::class, 'cambiarEstadoPaquete']);
Enrutador::get('/api/v1/catalogo/paquetes/{id}/composicion', [\Aplicacion\Controladores\CatalogoControlador::class, 'obtenerComposicion']);
Enrutador::post('/api/v1/catalogo/paquetes/{id}/composicion', [\Aplicacion\Controladores\CatalogoControlador::class, 'sincronizarComposicion']);
Enrutador::put('/api/v1/catalogo/paquetes/{id}/composicion', [\Aplicacion\Controladores\CatalogoControlador::class, 'sincronizarComposicion']);

// Ofertas por Edición
Enrutador::get('/api/v1/catalogo/ofertas', [\Aplicacion\Controladores\CatalogoControlador::class, 'listarOfertas']);
Enrutador::post('/api/v1/catalogo/ofertas/items', [\Aplicacion\Controladores\CatalogoControlador::class, 'habilitarOfertaItem']);
Enrutador::patch('/api/v1/catalogo/ofertas/items/{id}/estado', [\Aplicacion\Controladores\CatalogoControlador::class, 'cambiarEstadoOfertaItem']);
Enrutador::post('/api/v1/catalogo/ofertas/paquetes', [\Aplicacion\Controladores\CatalogoControlador::class, 'habilitarOfertaPaquete']);
Enrutador::patch('/api/v1/catalogo/ofertas/paquetes/{id}/estado', [\Aplicacion\Controladores\CatalogoControlador::class, 'cambiarEstadoOfertaPaquete']);

// Tarifas Vigentes y Optimistic Locking
Enrutador::post('/api/v1/catalogo/tarifas/items/inicial', [\Aplicacion\Controladores\CatalogoControlador::class, 'fijarTarifaInicialItem']);
Enrutador::put('/api/v1/catalogo/tarifas/items/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'actualizarTarifaItem']);
Enrutador::post('/api/v1/catalogo/tarifas/paquetes/inicial', [\Aplicacion\Controladores\CatalogoControlador::class, 'fijarTarifaInicialPaquete']);
Enrutador::put('/api/v1/catalogo/tarifas/paquetes/{id}', [\Aplicacion\Controladores\CatalogoControlador::class, 'actualizarTarifaPaquete']);

// Historial de Tarifas (Append-Only)
Enrutador::get('/api/v1/catalogo/tarifas/items/{id}/historial', [\Aplicacion\Controladores\CatalogoControlador::class, 'historialTarifaItem']);
Enrutador::get('/api/v1/catalogo/tarifas/paquetes/{id}/historial', [\Aplicacion\Controladores\CatalogoControlador::class, 'historialTarifaPaquete']);

// ==============================================================================
// GESTIÓN COMERCIAL: COTIZACIONES (FASE 2.4C)
// ==============================================================================
Enrutador::get('/api/v1/cotizaciones', [\Aplicacion\Controladores\CotizacionControlador::class, 'listar']);
Enrutador::get('/api/v1/cotizaciones/{id}', [\Aplicacion\Controladores\CotizacionControlador::class, 'detalle']);
Enrutador::post('/api/v1/cotizaciones', [\Aplicacion\Controladores\CotizacionControlador::class, 'crearBorrador']);
Enrutador::put('/api/v1/cotizaciones/{id}', [\Aplicacion\Controladores\CotizacionControlador::class, 'actualizarBorrador']);
Enrutador::post('/api/v1/cotizaciones/{id}/lineas', [\Aplicacion\Controladores\CotizacionControlador::class, 'agregarLinea']);
Enrutador::patch('/api/v1/cotizaciones/{id}/lineas/{lineaId}/descuento', [\Aplicacion\Controladores\CotizacionControlador::class, 'aplicarDescuentoLinea']);
Enrutador::delete('/api/v1/cotizaciones/{id}/lineas/{lineaId}', [\Aplicacion\Controladores\CotizacionControlador::class, 'eliminarLinea']);
Enrutador::post('/api/v1/cotizaciones/{id}/descuento-global', [\Aplicacion\Controladores\CotizacionControlador::class, 'aplicarDescuentoGlobal']);
Enrutador::post('/api/v1/cotizaciones/{id}/emitir', [\Aplicacion\Controladores\CotizacionControlador::class, 'emitir']);
Enrutador::post('/api/v1/cotizaciones/{id}/revision', [\Aplicacion\Controladores\CotizacionControlador::class, 'crearRevision']);
Enrutador::post('/api/v1/cotizaciones/{id}/aceptar', [\Aplicacion\Controladores\CotizacionControlador::class, 'aceptar']);
Enrutador::post('/api/v1/cotizaciones/{id}/rechazar', [\Aplicacion\Controladores\CotizacionControlador::class, 'rechazar']);
Enrutador::post('/api/v1/cotizaciones/{id}/anular', [\Aplicacion\Controladores\CotizacionControlador::class, 'anular']);

// Lookups Auxiliares para Select2 y Catálogo en Cotizaciones
Enrutador::get('/api/v1/cotizaciones/aux/clientes', [\Aplicacion\Controladores\CotizacionControlador::class, 'auxClientes']);
Enrutador::get('/api/v1/cotizaciones/aux/oportunidades', [\Aplicacion\Controladores\CotizacionControlador::class, 'auxOportunidades']);
Enrutador::get('/api/v1/cotizaciones/aux/ofertas', [\Aplicacion\Controladores\CotizacionControlador::class, 'auxOfertas']);

// ==============================================================================
// GESTIÓN COMERCIAL: VENTAS (FASE 2.5C)
// ==============================================================================
Enrutador::get('/api/v1/ventas', [\Aplicacion\Controladores\VentaControlador::class, 'listar']);
Enrutador::get('/api/v1/ventas/aux/cotizaciones-aceptadas', [\Aplicacion\Controladores\VentaControlador::class, 'auxCotizacionesAceptadas']);
Enrutador::get('/api/v1/ventas/{id}', [\Aplicacion\Controladores\VentaControlador::class, 'detalle']);
Enrutador::post('/api/v1/ventas/desde-cotizacion', [\Aplicacion\Controladores\VentaControlador::class, 'crearDesdeCotizacion']);
Enrutador::post('/api/v1/ventas/{id}/cancelar', [\Aplicacion\Controladores\VentaControlador::class, 'cancelar']);
Enrutador::post('/api/v1/ventas/{id}/anular', [\Aplicacion\Controladores\VentaControlador::class, 'anular']);

// ==============================================================================
// GESTIÓN DE RESERVAS Y AGENDAMIENTO (FASE 2.6E)
// ==============================================================================
Enrutador::get('/api/v1/reservas', [\Aplicacion\Controladores\ReservaControlador::class, 'listar']);
Enrutador::get('/api/v1/reservas/aux/ventas-confirmadas', [\Aplicacion\Controladores\ReservaControlador::class, 'auxVentasConfirmadas']);
Enrutador::get('/api/v1/reservas/aux/tipos-documento', [\Aplicacion\Controladores\ReservaControlador::class, 'auxTiposDocumento']);
Enrutador::get('/api/v1/reservas/{id}', [\Aplicacion\Controladores\ReservaControlador::class, 'detalle']);
Enrutador::post('/api/v1/reservas/desde-venta/{ventaId}', [\Aplicacion\Controladores\ReservaControlador::class, 'formalizarDesdeVenta']);
Enrutador::post('/api/v1/reservas/{id}/cancelar', [\Aplicacion\Controladores\ReservaControlador::class, 'cancelar']);
Enrutador::post('/api/v1/reservas/{id}/participantes', [\Aplicacion\Controladores\ReservaControlador::class, 'registrarParticipante']);
Enrutador::delete('/api/v1/reservas/{id}/participantes/{participanteId}', [\Aplicacion\Controladores\ReservaControlador::class, 'eliminarParticipante']);
Enrutador::post('/api/v1/reservas/prestaciones/{prestacionId}/programar', [\Aplicacion\Controladores\ReservaControlador::class, 'programarPrestacion']);
Enrutador::post('/api/v1/reservas/prestaciones/{prestacionId}/reprogramar', [\Aplicacion\Controladores\ReservaControlador::class, 'reprogramarPrestacion']);
Enrutador::get('/api/v1/reservas/prestaciones/{prestacionId}/reprogramaciones', [\Aplicacion\Controladores\ReservaControlador::class, 'obtenerReprogramaciones']);

// ==============================================================================
// OPERACIONES DE CAMPO, SALIDAS Y RECURSOS (FASE 2.6E)
// ==============================================================================
Enrutador::get('/api/v1/operaciones/salidas', [\Aplicacion\Controladores\OperacionControlador::class, 'listarSalidas']);
Enrutador::get('/api/v1/operaciones/salidas/{id}', [\Aplicacion\Controladores\OperacionControlador::class, 'detalleSalida']);
Enrutador::post('/api/v1/operaciones/salidas', [\Aplicacion\Controladores\OperacionControlador::class, 'crearSalida']);
Enrutador::put('/api/v1/operaciones/salidas/{id}', [\Aplicacion\Controladores\OperacionControlador::class, 'actualizarSalida']);
Enrutador::get('/api/v1/operaciones/salidas/{id}/prestaciones-compatibles', [\Aplicacion\Controladores\OperacionControlador::class, 'prestacionesCompatibles']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/prestaciones', [\Aplicacion\Controladores\OperacionControlador::class, 'asignarPrestacion']);
Enrutador::delete('/api/v1/operaciones/salidas/{id}/prestaciones/{prestacionId}', [\Aplicacion\Controladores\OperacionControlador::class, 'desasignarPrestacion']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/recursos', [\Aplicacion\Controladores\OperacionControlador::class, 'asignarRecurso']);
Enrutador::delete('/api/v1/operaciones/salidas/{id}/recursos/{asignacionId}', [\Aplicacion\Controladores\OperacionControlador::class, 'desasignarRecurso']);
Enrutador::get('/api/v1/operaciones/salidas/{id}/manifiesto', [\Aplicacion\Controladores\OperacionControlador::class, 'manifiesto']);
Enrutador::get('/api/v1/operaciones/salidas/{id}/checkin', [\Aplicacion\Controladores\OperacionControlador::class, 'listadoCheckin']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/checkin', [\Aplicacion\Controladores\OperacionControlador::class, 'marcarCheckin']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/despachar', [\Aplicacion\Controladores\OperacionControlador::class, 'despachar']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/finalizar', [\Aplicacion\Controladores\OperacionControlador::class, 'finalizar']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/interrumpir', [\Aplicacion\Controladores\OperacionControlador::class, 'interrumpir']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/cancelar', [\Aplicacion\Controladores\OperacionControlador::class, 'cancelar']);
Enrutador::get('/api/v1/operaciones/salidas/{id}/incidencias', [\Aplicacion\Controladores\OperacionControlador::class, 'listarIncidencias']);
Enrutador::post('/api/v1/operaciones/salidas/{id}/incidencias', [\Aplicacion\Controladores\OperacionControlador::class, 'registrarIncidencia']);

// Proveedores y Recursos
Enrutador::get('/api/v1/operaciones/proveedores', [\Aplicacion\Controladores\OperacionControlador::class, 'listarProveedores']);
Enrutador::post('/api/v1/operaciones/proveedores', [\Aplicacion\Controladores\OperacionControlador::class, 'registrarProveedor']);
Enrutador::patch('/api/v1/operaciones/proveedores/{id}/suspender', [\Aplicacion\Controladores\OperacionControlador::class, 'suspenderProveedor']);
Enrutador::get('/api/v1/operaciones/recursos', [\Aplicacion\Controladores\OperacionControlador::class, 'listarRecursos']);
Enrutador::post('/api/v1/operaciones/recursos', [\Aplicacion\Controladores\OperacionControlador::class, 'registrarRecurso']);
Enrutador::patch('/api/v1/operaciones/recursos/{id}/estado', [\Aplicacion\Controladores\OperacionControlador::class, 'cambiarEstadoRecurso']);
Enrutador::get('/api/v1/operaciones/aux/personas', [\Aplicacion\Controladores\OperacionControlador::class, 'auxPersonas']);
Enrutador::get('/api/v1/operaciones/aux/servicios', [\Aplicacion\Controladores\OperacionControlador::class, 'auxServicios']);
Enrutador::get('/api/v1/operaciones/aux/recursos-activos', [\Aplicacion\Controladores\OperacionControlador::class, 'auxRecursosActivos']);

// Despacho de Entregas
Enrutador::get('/api/v1/operaciones/entregas', [\Aplicacion\Controladores\OperacionControlador::class, 'listarEntregas']);
Enrutador::get('/api/v1/operaciones/entregas/{id}', [\Aplicacion\Controladores\OperacionControlador::class, 'detalleEntrega']);
Enrutador::post('/api/v1/operaciones/entregas/{id}/despachar', [\Aplicacion\Controladores\OperacionControlador::class, 'despacharEntrega']);

// ==============================================================================
// GESTIÓN FINANCIERA, PAGOS Y PASARELAS (FASE 2.7D)
// ==============================================================================
Enrutador::get('/api/v1/pagos', [\Aplicacion\Controladores\PagoControlador::class, 'listar']);
Enrutador::get('/api/v1/pagos/{id}', [\Aplicacion\Controladores\PagoControlador::class, 'detalle']);
Enrutador::post('/api/v1/pagos/manual', [\Aplicacion\Controladores\PagoControlador::class, 'registrarManual']);
Enrutador::post('/api/v1/pagos/{id}/boucher', [\Aplicacion\Controladores\PagoControlador::class, 'subirBoucher']);
Enrutador::post('/api/v1/pagos/{id}/verificar', [\Aplicacion\Controladores\PagoControlador::class, 'verificar']);
Enrutador::post('/api/v1/pagos/{id}/reembolsar', [\Aplicacion\Controladores\PagoControlador::class, 'reembolsar']);

Enrutador::get('/api/v1/ventas/{id}/estado-cuenta', [\Aplicacion\Controladores\PagoControlador::class, 'estadoCuentaVenta']);
Enrutador::post('/api/v1/ventas/{id}/liquidar', [\Aplicacion\Controladores\PagoControlador::class, 'liquidarVenta']);

Enrutador::get('/api/v1/cuentas-bancarias', [\Aplicacion\Controladores\PagoControlador::class, 'listarCuentasBancarias']);
Enrutador::post('/api/v1/cuentas-bancarias', [\Aplicacion\Controladores\PagoControlador::class, 'crearCuentaBancaria']);
Enrutador::put('/api/v1/cuentas-bancarias/{id}', [\Aplicacion\Controladores\PagoControlador::class, 'actualizarCuentaBancaria']);

Enrutador::get('/api/v1/pasarelas', [\Aplicacion\Controladores\PagoControlador::class, 'listarPasarelas']);
Enrutador::put('/api/v1/pasarelas/{codigo}', [\Aplicacion\Controladores\PagoControlador::class, 'configurarPasarela']);

Enrutador::post('/api/v1/webhooks/pasarelas/{codigo}', [\Aplicacion\Controladores\PagoControlador::class, 'recibirWebhook']);

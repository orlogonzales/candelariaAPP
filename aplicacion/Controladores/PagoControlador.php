<?php

declare(strict_types=1);

namespace Aplicacion\Controladores;

use Aplicacion\Ediciones\ContextoEdicionResolver;
use Aplicacion\Entidades\CuentaBancariaOrganizacion;
use Aplicacion\Entidades\OrganizacionPasarela;
use Aplicacion\Entidades\Pago;
use Aplicacion\Finanzas\AsumeComisionPasarela;
use Aplicacion\Finanzas\CifradorFinanciero;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\MotivoReembolso;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Finanzas\WebhookPasarelaServicio;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\PermisoRepositorio;
use Aplicacion\Repositorios\RolRepositorio;
use Aplicacion\Repositorios\UsuarioRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Seguridad\ProtectorCsrf;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Excepciones\AccesoDenegadoExcepcion;
use Nucleo\Excepciones\ConflictoConcurrenciaExcepcion;
use Nucleo\Http\ContextoOperacion;
use Nucleo\Http\Middleware\AutenticacionMiddleware;
use Nucleo\Http\Middleware\AutorizacionMiddleware;
use Nucleo\Http\Vista;
use PDO;
use Throwable;

/**
 * Controlador oficial del Módulo de Finanzas, Pagos, Conciliación y Pasarelas Web (F2.7D).
 * Implementa los 14 endpoints RESTful para administración de pagos y recepción segura de webhooks.
 */
class PagoControlador
{
    private AutenticacionMiddleware $authMiddleware;
    private AutorizacionMiddleware $authzMiddleware;
    private PagoServicio $pagoServicio;
    private WebhookPasarelaServicio $webhookServicio;
    private PagoRepositorio $pagoRepo;
    private PagoReembolsoRepositorio $reembolsoRepo;
    private CuentaBancariaRepositorio $cuentaRepo;
    private PasarelaRepositorio $pasarelaRepo;
    private VentaRepositorio $ventaRepo;
    private EdicionRepositorio $edicionRepo;
    private ContextoEdicionResolver $contextoEdicionResolver;
    private CifradorFinanciero $cifrador;
    private PDO $pdo;

    public function __construct(
        ?AutenticacionMiddleware $authMiddleware = null,
        ?AutorizacionMiddleware $authzMiddleware = null,
        ?PagoServicio $pagoServicio = null,
        ?WebhookPasarelaServicio $webhookServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
        $this->authMiddleware = $authMiddleware ?? new AutenticacionMiddleware();
        $this->authzMiddleware = $authzMiddleware ?? new AutorizacionMiddleware();

        $this->pagoRepo = new PagoRepositorio($this->pdo);
        $this->reembolsoRepo = new PagoReembolsoRepositorio($this->pdo);
        $this->cuentaRepo = new CuentaBancariaRepositorio($this->pdo);
        $this->pasarelaRepo = new PasarelaRepositorio($this->pdo);
        $this->ventaRepo = new VentaRepositorio($this->pdo);
        $this->edicionRepo = new EdicionRepositorio($this->pdo);
        $this->contextoEdicionResolver = new ContextoEdicionResolver($this->edicionRepo);
        $this->cifrador = new CifradorFinanciero();

        $auditoriaRepo = new AuditoriaRepositorio($this->pdo);
        $configRepo = new ConfiguracionRepositorio($this->pdo);
        $rolRepo = new RolRepositorio($this->pdo);
        $permRepo = new PermisoRepositorio($this->pdo);
        $usrRepo = new UsuarioRepositorio($this->pdo);
        $authzServicio = new AutorizacionServicio($rolRepo, $permRepo, $usrRepo, $auditoriaRepo, $this->pdo);

        $this->pagoServicio = $pagoServicio ?? new PagoServicio(
            pagoRepo: $this->pagoRepo,
            reembolsoRepo: $this->reembolsoRepo,
            cuentaRepo: $this->cuentaRepo,
            pasarelaRepo: $this->pasarelaRepo,
            ventaRepo: $this->ventaRepo,
            edicionRepo: $this->edicionRepo,
            configRepo: $configRepo,
            authzServicio: $authzServicio,
            auditoriaRepo: $auditoriaRepo,
            pdo: $this->pdo
        );

        $this->webhookServicio = $webhookServicio ?? new WebhookPasarelaServicio(
            pagoRepo: $this->pagoRepo,
            pasarelaRepo: $this->pasarelaRepo,
            ventaRepo: $this->ventaRepo,
            pagoServicio: $this->pagoServicio,
            cifrador: $this->cifrador,
            auditoriaRepo: $auditoriaRepo,
            pdo: $this->pdo
        );
    }

    // =========================================================================
    // 0. VISTA WEB OFICIAL (ALINA UI - F2.7E)
    // =========================================================================

    /**
     * GET /pagos
     * Renderiza la interfaz administrativa oficial de Finanzas, Pagos, Cuentas Bancarias y Pasarelas.
     */
    public function index(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, false);
        if ($contexto === null) {
            header('Location: ' . url_base('login'));
            exit;
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false)) {
            http_response_code(403);
            return Vista::renderizar('errores/403', [
                'titulo'        => 'Acceso Denegado | CandelariaAPP',
                'subtitulo'     => 'Finanzas y Pagos',
                'tituloSeccion' => 'Error 403',
                'mensaje'       => 'No cuenta con los privilegios necesarios (pagos.ver) para consultar el libro de pagos y tesorería.'
            ], 'principal');
        }

        $permisos = [
            'ver'                => true,
            'registrarManual'    => $this->authzMiddleware->verificarPermiso('pagos.registrar_manual', $contexto, false),
            'verificar'          => $this->authzMiddleware->verificarPermiso('pagos.verificar', $contexto, false),
            'reembolsar'         => $this->authzMiddleware->verificarPermiso('pagos.reembolsar', $contexto, false),
            'gestionarCuentas'   => $this->authzMiddleware->verificarPermiso('cuentas_bancarias.gestionar', $contexto, false),
            'gestionarPasarelas' => $this->authzMiddleware->verificarPermiso('pasarelas.gestionar', $contexto, false),
        ];

        return Vista::renderizar('pagos/index', [
            'titulo'          => 'Finanzas y Pagos | CandelariaAPP',
            'subtitulo'       => 'Libro Mayor de Cobros, Cuentas Bancarias, Conciliación y Pasarelas',
            'tituloSeccion'   => 'Finanzas y Pagos',
            'seccionActiva'   => 'pagos',
            'permisos'        => $permisos,
            'scriptAdicional' => url_base('publico/js/pagos.js')
        ], 'principal');
    }

    // =========================================================================
    // 1. ENDPOINTS DE PAGOS Y TRANSACCIONES
    // =========================================================================

    /**
     * GET /api/v1/pagos
     * Listado paginado de pagos con filtros múltiples.
     */
    public function listar(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $edicionId = $this->resolverEdicionContextual($contexto);

            $pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
            $porPagina = isset($_GET['por_pagina']) ? max(1, min(100, (int) $_GET['por_pagina'])) : 20;

            $filtros = [
                'venta_id'                 => !empty($_GET['venta_id']) ? (int) $_GET['venta_id'] : null,
                'estado'                   => !empty($_GET['estado']) ? trim((string) $_GET['estado']) : null,
                'metodo_pago'              => !empty($_GET['metodo_pago']) ? trim((string) $_GET['metodo_pago']) : null,
                'cuenta_bancaria_id'       => !empty($_GET['cuenta_bancaria_id']) ? (int) $_GET['cuenta_bancaria_id'] : null,
                'organizacion_pasarela_id' => !empty($_GET['organizacion_pasarela_id']) ? (int) $_GET['organizacion_pasarela_id'] : null,
                'fecha_desde'              => !empty($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : null,
                'fecha_hasta'              => !empty($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : null,
                'busqueda'                 => !empty($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : null,
            ];

            $resultado = $this->pagoRepo->listarPaginado(
                organizacionId: $orgId,
                edicionId: $edicionId,
                filtros: array_filter($filtros, fn($v) => $v !== null && $v !== ''),
                pagina: $pagina,
                porPagina: $porPagina
            );

            return $this->responderJson(true, 200, 'Pagos recuperados exitosamente.', [
                'items'         => array_map(fn(Pago $p) => $p->aArreglo(), $resultado['items']),
                'pagos'         => array_map(fn(Pago $p) => $p->aArreglo(), $resultado['items']),
                'total'         => $resultado['total'],
                'pagina'        => $resultado['pagina'],
                'por_pagina'    => $resultado['por_pagina'],
                'total_paginas' => $resultado['total_paginas'],
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/pagos/{id}
     * Detalle 360 de un pago incluyendo reembolsos e imputación a la venta.
     */
    public function detalle(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $pago = $this->pagoRepo->buscarPorId($id, $orgId);
            if ($pago === null) {
                return $this->responderJson(false, 404, "El pago #{$id} no existe en su organización.");
            }

            $reembolsos = $this->reembolsoRepo->listarPorPago($id, $orgId);

            return $this->responderJson(true, 200, 'Detalle de pago obtenido.', [
                'pago'       => $pago->aArreglo(),
                'reembolsos' => array_map(fn($r) => $r->aArreglo(), $reembolsos),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/pagos/manual
     * Registro de cobro manual (efectivo, transferencia, POS, billetera móvil).
     */
    public function registrarManual(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.registrar_manual', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.registrar_manual.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $csrfError = $this->verificarCsrf($contexto, $cuerpo);
        if ($csrfError !== null) {
            return $csrfError;
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $edicionId = !empty($cuerpo['edicion_id']) ? (int) $cuerpo['edicion_id'] : $this->resolverEdicionContextual($contexto);
            if ($edicionId === null || $edicionId <= 0) {
                return $this->responderJson(false, 422, 'Se requiere especificar la edición de trabajo contextual.', (object) [], ['edicion_id' => 'CAMPO_REQUERIDO']);
            }

            $ventaId = isset($cuerpo['venta_id']) ? (int) $cuerpo['venta_id'] : null;
            if ($ventaId === null || $ventaId <= 0) {
                return $this->responderJson(false, 422, 'Debe especificar el identificador de venta (venta_id).', (object) [], ['venta_id' => 'CAMPO_REQUERIDO']);
            }

            $pago = $this->pagoServicio->registrarPagoManual(
                organizacionId: $orgId,
                edicionId: $edicionId,
                ventaId: $ventaId,
                datos: $cuerpo,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Cobro manual registrado exitosamente.', $pago->aArreglo());
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => 'VALIDACION']);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/pagos/{id}/boucher
     * Adjunta o actualiza la URL del comprobante o boucher bancario.
     */
    public function subirBoucher(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.registrar_manual', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.registrar_manual.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $boucherUrl = trim((string) ($cuerpo['boucher_comprobante_url'] ?? $cuerpo['boucher_url'] ?? ''));
        if ($boucherUrl === '') {
            return $this->responderJson(false, 422, 'Debe especificar la URL del boucher de pago.', (object) [], ['boucher_url' => 'CAMPO_REQUERIDO']);
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $pago = $this->pagoServicio->registrarBoucher($orgId, $id, $boucherUrl, $contexto);

            return $this->responderJson(true, 200, 'Boucher comprobante actualizado exitosamente.', $pago->aArreglo());
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 404, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/pagos/{id}/verificar
     * Valida y aprueba o rechaza un depósito/transferencia bancaria con control de concurrencia.
     */
    public function verificar(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.verificar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.verificar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $csrfError = $this->verificarCsrf($contexto, $cuerpo);
        if ($csrfError !== null) {
            return $csrfError;
        }

        if (!isset($cuerpo['aprobar']) || !isset($cuerpo['version_bloqueo'])) {
            return $this->responderJson(false, 422, 'Se requieren los campos aprobar (booleano) y version_bloqueo (entero).', (object) [], ['campos' => 'CAMPOS_REQUERIDOS']);
        }

        $aprobar = (bool) $cuerpo['aprobar'];
        $versionBloqueo = (int) $cuerpo['version_bloqueo'];
        $notas = !empty($cuerpo['notas']) ? trim((string) $cuerpo['notas']) : null;

        try {
            $orgId = (int) $contexto->organizacionId;
            $pago = $this->pagoServicio->verificarPagoManual(
                organizacionId: $orgId,
                pagoId: $id,
                aprobar: $aprobar,
                versionBloqueoEsperada: $versionBloqueo,
                notas: $notas,
                contexto: $contexto
            );

            return $this->responderJson(true, 200, 'Pago verificado correctamente.', $pago->aArreglo());
        } catch (ConflictoConcurrenciaExcepcion $e) {
            return $this->responderJson(false, 409, $e->getMessage(), (object) [], ['concurrencia' => 'CONFLICTO_VERSION']);
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => 'VALIDACION']);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/pagos/{id}/reembolsar
     * Emite un asiento compensatorio inmutable de reembolso.
     */
    public function reembolsar(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.reembolsar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.reembolsar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $csrfError = $this->verificarCsrf($contexto, $cuerpo);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $monto = isset($cuerpo['monto']) ? (float) $cuerpo['monto'] : 0.0;
        $motivoRaw = (string) ($cuerpo['motivo'] ?? '');
        $motivoDetalle = trim((string) ($cuerpo['motivo_detalle'] ?? ''));
        $transaccionExternaId = !empty($cuerpo['transaccion_externa_id']) ? trim((string) $cuerpo['transaccion_externa_id']) : null;

        $motivoEnum = MotivoReembolso::tryFrom($motivoRaw);
        if ($motivoEnum === null) {
            return $this->responderJson(false, 422, "Motivo de reembolso inválido: '{$motivoRaw}'.", (object) [], ['motivo' => 'VALOR_INVALIDO']);
        }

        if ($motivoDetalle === '') {
            return $this->responderJson(false, 422, 'Debe especificar el detalle explicativo del motivo de reembolso.', (object) [], ['motivo_detalle' => 'CAMPO_REQUERIDO']);
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $reembolso = $this->pagoServicio->registrarReembolso(
                organizacionId: $orgId,
                pagoId: $id,
                montoReembolso: $monto,
                motivo: $motivoEnum,
                motivoDetalle: $motivoDetalle,
                transaccionExternaId: $transaccionExternaId,
                contexto: $contexto
            );

            return $this->responderJson(true, 201, 'Reembolso registrado y ejecutado exitosamente.', $reembolso->aArreglo());
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => 'VALIDACION']);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 2. ESTADO DE CUENTA Y LIQUIDACIÓN DE VENTAS
    // =========================================================================

    /**
     * GET /api/v1/ventas/{id}/estado-cuenta
     * Retorna el balance de pagos, saldo pendiente y estado financiero de la venta.
     */
    public function estadoCuentaVenta(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pagos.ver.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $estadoCuenta = $this->pagoServicio->obtenerEstadoCuentaVenta($id, $orgId, $contexto);

            return $this->responderJson(true, 200, 'Estado de cuenta de venta recuperado.', $estadoCuenta);
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 404, $e->getMessage());
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/ventas/{id}/liquidar
     * Transiciona comercialmente la venta a LIQUIDADA si el saldo adeudado es 0.00.
     */
    public function liquidarVenta(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.verificar', $contexto, false) &&
            !$this->authzMiddleware->verificarPermiso('ventas.cancelar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con los privilegios requeridos para liquidar la venta.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $csrfError = $this->verificarCsrf($contexto, $cuerpo);
        if ($csrfError !== null) {
            return $csrfError;
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $this->pagoServicio->liquidarVenta($id, $orgId, $contexto);

            return $this->responderJson(true, 200, 'Venta liquidada comercialmente con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 422, $e->getMessage(), (object) [], ['error' => 'VALIDACION']);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 3. GESTIÓN DE CUENTAS BANCARIAS INSTITUCIONALES
    // =========================================================================

    /**
     * GET /api/v1/cuentas-bancarias
     */
    public function listarCuentasBancarias(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cuentas_bancarias.gestionar', $contexto, false) &&
            !$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con permisos para listar cuentas bancarias.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $soloActivas = isset($_GET['solo_activas']) ? (bool) $_GET['solo_activas'] : false;
            $cuentas = $this->cuentaRepo->listarPorOrganizacion($orgId, $soloActivas);

            return $this->responderJson(true, 200, 'Cuentas bancarias recuperadas.', array_map(fn($c) => $c->aArreglo(), $cuentas));
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * POST /api/v1/cuentas-bancarias
     */
    public function crearCuentaBancaria(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cuentas_bancarias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: cuentas_bancarias.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $banco = trim((string) ($cuerpo['banco_nombre'] ?? ''));
        $tipo = trim((string) ($cuerpo['tipo_cuenta'] ?? ''));
        $moneda = strtoupper(trim((string) ($cuerpo['moneda'] ?? 'PEN')));
        $titular = trim((string) ($cuerpo['titular_nombre'] ?? ''));
        $numero = trim((string) ($cuerpo['numero_cuenta'] ?? ''));

        if ($banco === '' || $tipo === '' || $titular === '' || $numero === '') {
            return $this->responderJson(false, 422, 'Los campos banco_nombre, tipo_cuenta, titular_nombre y numero_cuenta son obligatorios.', (object) [], ['campos' => 'CAMPOS_REQUERIDOS']);
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $cuenta = new CuentaBancariaOrganizacion(
                id: null,
                organizacionId: $orgId,
                bancoNombre: $banco,
                tipoCuenta: $tipo,
                moneda: $moneda,
                titularNombre: $titular,
                numeroCuenta: $numero,
                codigoInterbancario: !empty($cuerpo['codigo_interbancario']) ? trim((string) $cuerpo['codigo_interbancario']) : null,
                aliasIdentificador: !empty($cuerpo['alias_identificador']) ? trim((string) $cuerpo['alias_identificador']) : null,
                qrImagenUrl: !empty($cuerpo['qr_imagen_url']) ? trim((string) $cuerpo['qr_imagen_url']) : null,
                instruccionesPago: !empty($cuerpo['instrucciones_pago']) ? trim((string) $cuerpo['instrucciones_pago']) : null,
                activo: isset($cuerpo['activo']) ? (bool) $cuerpo['activo'] : true
            );

            $id = $this->cuentaRepo->crear($cuenta);
            $creada = $this->cuentaRepo->buscarPorId($id, $orgId);

            return $this->responderJson(true, 201, 'Cuenta bancaria creada exitosamente.', $creada ? $creada->aArreglo() : (object) []);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/cuentas-bancarias/{id}
     */
    public function actualizarCuentaBancaria(int $id): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('cuentas_bancarias.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: cuentas_bancarias.gestionar.');
        }

        $cuerpo = $this->obtenerCuerpo();
        $orgId = (int) $contexto->organizacionId;

        $existente = $this->cuentaRepo->buscarPorId($id, $orgId);
        if ($existente === null) {
            return $this->responderJson(false, 404, "La cuenta bancaria #{$id} no existe en su organización.");
        }

        try {
            $cuentaModificada = new CuentaBancariaOrganizacion(
                id: $id,
                organizacionId: $orgId,
                bancoNombre: !empty($cuerpo['banco_nombre']) ? trim((string) $cuerpo['banco_nombre']) : $existente->bancoNombre,
                tipoCuenta: !empty($cuerpo['tipo_cuenta']) ? trim((string) $cuerpo['tipo_cuenta']) : $existente->tipoCuenta,
                moneda: !empty($cuerpo['moneda']) ? strtoupper(trim((string) $cuerpo['moneda'])) : $existente->moneda,
                titularNombre: !empty($cuerpo['titular_nombre']) ? trim((string) $cuerpo['titular_nombre']) : $existente->titularNombre,
                numeroCuenta: !empty($cuerpo['numero_cuenta']) ? trim((string) $cuerpo['numero_cuenta']) : $existente->numeroCuenta,
                codigoInterbancario: array_key_exists('codigo_interbancario', $cuerpo) ? (!empty($cuerpo['codigo_interbancario']) ? trim((string) $cuerpo['codigo_interbancario']) : null) : $existente->codigoInterbancario,
                aliasIdentificador: array_key_exists('alias_identificador', $cuerpo) ? (!empty($cuerpo['alias_identificador']) ? trim((string) $cuerpo['alias_identificador']) : null) : $existente->aliasIdentificador,
                qrImagenUrl: array_key_exists('qr_imagen_url', $cuerpo) ? (!empty($cuerpo['qr_imagen_url']) ? trim((string) $cuerpo['qr_imagen_url']) : null) : $existente->qrImagenUrl,
                instruccionesPago: array_key_exists('instrucciones_pago', $cuerpo) ? (!empty($cuerpo['instrucciones_pago']) ? trim((string) $cuerpo['instrucciones_pago']) : null) : $existente->instruccionesPago,
                activo: isset($cuerpo['activo']) ? (bool) $cuerpo['activo'] : $existente->activo
            );

            $this->cuentaRepo->actualizar($cuentaModificada);
            $actualizada = $this->cuentaRepo->buscarPorId($id, $orgId);

            return $this->responderJson(true, 200, 'Cuenta bancaria actualizada exitosamente.', $actualizada ? $actualizada->aArreglo() : (object) []);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 4. GESTIÓN Y CONFIGURACIÓN DE PASARELAS DE PAGO
    // =========================================================================

    /**
     * GET /api/v1/pasarelas
     * Retorna el catálogo institucional y las pasarelas activas para el tenant (sin secretos).
     */
    public function listarPasarelas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pasarelas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pasarelas.gestionar.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $catalogo = $this->pasarelaRepo->listarCatalogo();
            $configuraciones = $this->pasarelaRepo->listarPorOrganizacion($orgId);

            return $this->responderJson(true, 200, 'Pasarelas recuperadas.', [
                'catalogo'        => array_map(fn($p) => [
                    'id'                 => $p->id,
                    'codigo'             => $p->codigo,
                    'nombre'             => $p->nombre,
                    'descripcion'        => $p->descripcion,
                    'tipo_integracion'   => $p->tipoIntegracion,
                    'protocolo_webhook'  => $p->protocoloWebhook,
                    'soporta_reembolsos' => $p->soportaReembolsos,
                ], $catalogo),
                'configuraciones' => array_map(fn(OrganizacionPasarela $op) => $op->aArreglo(false), $configuraciones),
            ]);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * PUT /api/v1/pasarelas/{codigo}
     * Configura credenciales y comisiones de una pasarela para el tenant (cifrado AES-256-GCM).
     */
    public function configurarPasarela(string $codigo): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pasarelas.gestionar', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con el privilegio requerido: pasarelas.gestionar.');
        }

        $pasarela = $this->pasarelaRepo->buscarPasarelaPorCodigo($codigo);
        if ($pasarela === null) {
            return $this->responderJson(false, 404, "Pasarela con código '{$codigo}' no encontrada en el catálogo.");
        }

        $cuerpo = $this->obtenerCuerpo();
        $orgId = (int) $contexto->organizacionId;

        try {
            $existente = $this->pasarelaRepo->buscarOrganizacionPasarelaPorCodigo($orgId, $codigo);

            // Cifrar credenciales si fueron proporcionadas en texto plano
            $credencialEnc = $existente?->credencialSecretaEnc;
            if (!empty($cuerpo['credencial_secreta'])) {
                $credencialEnc = $this->cifrador->cifrar(trim((string) $cuerpo['credencial_secreta']));
            }

            $webhookEnc = $existente?->webhookSecretoEnc;
            if (!empty($cuerpo['webhook_secreto'])) {
                $webhookEnc = $this->cifrador->cifrar(trim((string) $cuerpo['webhook_secreto']));
            }

            $asumeComision = isset($cuerpo['asume_comision'])
                ? AsumeComisionPasarela::from((string) $cuerpo['asume_comision'])
                : ($existente?->asumeComision ?? AsumeComisionPasarela::ORGANIZACION);

            $config = new OrganizacionPasarela(
                id: $existente?->id,
                organizacionId: $orgId,
                pasarelaId: (int) $pasarela->id,
                modo: !empty($cuerpo['modo']) ? strtoupper(trim((string) $cuerpo['modo'])) : ($existente?->modo ?? 'TEST'),
                identificadorComercio: array_key_exists('identificador_comercio', $cuerpo) ? trim((string) $cuerpo['identificador_comercio']) : $existente?->identificadorComercio,
                credencialSecretaEnc: $credencialEnc,
                webhookSecretoEnc: $webhookEnc,
                porcentajeComision: isset($cuerpo['porcentaje_comision']) ? (float) $cuerpo['porcentaje_comision'] : ($existente?->porcentajeComision ?? 0.0),
                comisionFija: isset($cuerpo['comision_fija']) ? (float) $cuerpo['comision_fija'] : ($existente?->comisionFija ?? 0.0),
                asumeComision: $asumeComision,
                activo: isset($cuerpo['activo']) ? (bool) $cuerpo['activo'] : ($existente?->activo ?? true),
                pasarelaCodigo: $pasarela->codigo,
                pasarelaNombre: $pasarela->nombre
            );

            $id = $this->pasarelaRepo->guardarConfiguracionOrganizacion($config);
            $guardada = $this->pasarelaRepo->buscarOrganizacionPasarelaPorId($id, $orgId);

            return $this->responderJson(true, 200, 'Configuración de pasarela guardada de forma segura (cifrada).', $guardada ? $guardada->aArreglo(false) : (object) []);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    // =========================================================================
    // 5. RECEPCIÓN SEGURA DE WEBHOOKS DE PASARELAS (PÚBLICO)
    // =========================================================================

    /**
     * POST /api/v1/webhooks/pasarelas/{codigo}
     * Endpoint público para webhooks de pasarelas (Culqi, Stripe, Niubiz, MercadoPago).
     * Autenticación criptográfica por firma/HMAC y rate-limiting/idempotencia.
     */
    public function recibirWebhook(string $codigo): string
    {
        $cuerpoRaw = (string) file_get_contents('php://input');

        // Normalizar cabeceras a minúsculas
        $cabeceras = [];
        if (function_exists('getallheaders')) {
            $todas = getallheaders();
            if (is_array($todas)) {
                foreach ($todas as $k => $v) {
                    $cabeceras[strtolower((string) $k)] = (string) $v;
                }
            }
        }
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $nombre = strtolower(str_replace('_', '-', substr($k, 5)));
                $cabeceras[$nombre] = (string) $v;
            }
        }

        $ipOrigen = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $resultado = $this->webhookServicio->procesarWebhook(
                codigoPasarela: $codigo,
                cuerpoRaw: $cuerpoRaw,
                cabeceras: $cabeceras,
                parametrosQuery: $_GET,
                ipOrigen: $ipOrigen
            );

            return $this->responderJson(true, 200, $resultado['mensaje'], $resultado);
        } catch (\InvalidArgumentException $e) {
            return $this->responderJson(false, 400, $e->getMessage(), (object) [], ['webhook' => 'SOLICITUD_INVALIDA']);
        } catch (Throwable $e) {
            error_log("[Webhook Error: {$codigo}] " . $e->getMessage());
            return $this->responderJson(false, 400, 'Falla en verificación o procesamiento del webhook de pasarela.', (object) [], ['webhook' => 'ERROR_VERIFICACION']);
        }
    }

    // =========================================================================
    // MÉTODOS DE APOYO
    // =========================================================================

    private function resolverEdicionContextual(ContextoOperacion $contexto): ?int
    {
        $candidato = null;
        if (!empty($_GET['edicion_id']) && is_numeric($_GET['edicion_id'])) {
            $candidato = (int) $_GET['edicion_id'];
        } elseif (!empty($_SERVER['HTTP_X_EDICION_ID']) && is_numeric($_SERVER['HTTP_X_EDICION_ID'])) {
            $candidato = (int) $_SERVER['HTTP_X_EDICION_ID'];
        }

        if ($candidato !== null) {
            $ctxResuelto = $this->contextoEdicionResolver->resolver($contexto, $candidato);
            return $ctxResuelto->edicionTrabajoId;
        }

        return $contexto->edicionTrabajoId;
    }

    // =========================================================================
    // 6. ENDPOINTS AUXILIARES DE LOOKUP (F2.7E)
    // =========================================================================

    /**
     * GET /api/v1/pagos/aux/ventas
     * Lista ventas de la organización para el selector de cobro manual.
     */
    public function auxVentas(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        if (!$this->authzMiddleware->verificarPermiso('pagos.ver', $contexto, false) &&
            !$this->authzMiddleware->verificarPermiso('pagos.registrar_manual', $contexto, false)) {
            return $this->responderJson(false, 403, 'No cuenta con permisos para consultar ventas.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $edicionId = !empty($_GET['edicion_id']) ? (int) $_GET['edicion_id'] : $this->resolverEdicionContextual($contexto);

            $sql = "SELECT v.id, v.correlativo, v.cliente_nombre_completo, v.moneda, v.total,
                           v.monto_pagado, v.saldo_pendiente, v.estado, v.estado_financiero
                    FROM ventas v
                    WHERE v.organizacion_id = :org_id";
            $params = ['org_id' => $orgId];

            if ($edicionId !== null && $edicionId > 0) {
                $sql .= " AND v.edicion_id = :edicion_id";
                $params['edicion_id'] = $edicionId;
            }

            $sql .= " AND v.estado NOT IN ('CANCELADA', 'ANULADA') ORDER BY v.id DESC LIMIT 100";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Ventas recuperadas.', $ventas);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
    }

    /**
     * GET /api/v1/pagos/aux/ediciones
     * Retorna las ediciones disponibles para filtros.
     */
    public function auxEdiciones(): string
    {
        $contexto = $this->authMiddleware->procesar($_SERVER, $_COOKIE, true);
        if ($contexto === null || $contexto->organizacionId === null) {
            return $this->responderJson(false, 401, 'Sesión no válida o expirada.');
        }

        try {
            $orgId = (int) $contexto->organizacionId;
            $stmt = $this->pdo->prepare("SELECT id, anio, nombre, estado, es_actual FROM ediciones_candelaria WHERE organizacion_id = :org_id ORDER BY anio DESC");
            $stmt->execute(['org_id' => $orgId]);
            $ediciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->responderJson(true, 200, 'Ediciones recuperadas.', $ediciones);
        } catch (Throwable $e) {
            return $this->responderErrorSeguro($e);
        }
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
                return $this->responderJson(false, 403, 'Token de seguridad CSRF inválido o ausente.', (object) [], ['csrf' => 'TOKEN_INVALIDO']);
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
            'mensaje' => $mensaje,
            'datos'   => is_array($datos) && empty($datos) ? (object) [] : ($datos ?? (object) []),
            'errores' => is_array($errores) && empty($errores) ? (object) [] : ($errores ?? (object) []),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function responderErrorSeguro(Throwable $e): string
    {
        error_log("[PagoControlador Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return $this->responderJson(false, 500, 'Ha ocurrido un error inesperado al procesar la operación financiera.', (object) [], ['error' => 'ERROR_INTERNO']);
    }
}

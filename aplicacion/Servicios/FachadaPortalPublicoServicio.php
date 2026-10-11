<?php

declare(strict_types=1);

namespace Aplicacion\Servicios;

use Aplicacion\Clientes\EstadoCliente;
use Aplicacion\Cotizaciones\CotizacionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Entidades\Cliente;
use Aplicacion\Entidades\Persona;
use Aplicacion\Entidades\ReservaBloqueoTemporal;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Finanzas\EstadoFinancieroVenta;
use Aplicacion\Finanzas\EstadoPago;
use Aplicacion\Finanzas\MetodoPago;
use Aplicacion\Finanzas\PagoServicio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio;
use Aplicacion\Repositorios\ReservaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Reservas\EstadoReserva;
use Aplicacion\Reservas\ReservaServicio;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\VentaServicio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Fachada Soberana de la Capa Pública (Portal, Catálogo, Cotizador, Checkout y Portal Cliente).
 * Garantiza:
 * 1. Cero duplicación de cálculos financieros (backend soberano con IGV 18%).
 * 2. Estados comerciales y financieros canónicos (sin inventar PENDIENTE_PAGO).
 * 3. Máquina de estados de bloqueos temporales (TTL 15 min y retención voucher 24h).
 * 4. Aislamiento multitenant estricto y protección anti-IDOR.
 * 5. Notificaciones condicionadas estrictamente a la confirmación contractual real.
 */
class FachadaPortalPublicoServicio
{
    private PDO $pdo;

    public function __construct(
        private MotorDisponibilidadServicio $motorDisp,
        private AutenticacionClienteServicio $authClienteSvc,
        private CotizacionServicio $cotizacionServicio,
        private VentaServicio $ventaServicio,
        private ReservaServicio $reservaServicio,
        private PagoServicio $pagoServicio,
        private EdicionRepositorio $edicionRepo,
        private PaqueteRepositorio $paqueteRepo,
        private ItemComercialRepositorio $itemRepo,
        private ClienteRepositorio $clienteRepo,
        private PersonaRepositorio $personaRepo,
        private ReservaRepositorio $reservaRepo,
        private VentaRepositorio $ventaRepo,
        private PagoRepositorio $pagoRepo,
        private ReservaBloqueoTemporalRepositorio $bloqueoRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Factoría estática para instanciar la fachada con todas sus dependencias predeterminadas.
     */
    public static function crearPorDefecto(?PDO $pdo = null): self
    {
        $pdo = $pdo ?? Conexion::obtenerInstancia();

        $bloqueoRepo = new \Aplicacion\Repositorios\ReservaBloqueoTemporalRepositorio($pdo);
        $sesionRepo = new \Aplicacion\Repositorios\ClienteSesionRepositorio($pdo);
        $codigoRepo = new \Aplicacion\Repositorios\ClienteCodigoAccesoRepositorio($pdo);
        $clienteRepo = new \Aplicacion\Repositorios\ClienteRepositorio($pdo);
        $personaRepo = new \Aplicacion\Repositorios\PersonaRepositorio($pdo);
        $edicionRepo = new \Aplicacion\Repositorios\EdicionRepositorio($pdo);
        $paqueteRepo = new \Aplicacion\Repositorios\PaqueteRepositorio($pdo);
        $itemRepo = new \Aplicacion\Repositorios\ItemComercialRepositorio($pdo);
        $reservaRepo = new \Aplicacion\Repositorios\ReservaRepositorio($pdo);
        $ventaRepo = new \Aplicacion\Repositorios\VentaRepositorio($pdo);
        $pagoRepo = new \Aplicacion\Repositorios\PagoRepositorio($pdo);
        $reembolsoRepo = new \Aplicacion\Repositorios\PagoReembolsoRepositorio($pdo);
        $cuentaRepo = new \Aplicacion\Repositorios\CuentaBancariaRepositorio($pdo);
        $pasarelaRepo = new \Aplicacion\Repositorios\PasarelaRepositorio($pdo);
        $configRepo = new \Aplicacion\Repositorios\ConfiguracionRepositorio($pdo);
        $cotizacionRepo = new \Aplicacion\Repositorios\CotizacionRepositorio($pdo);
        $oportunidadRepo = new \Aplicacion\Repositorios\OportunidadRepositorio($pdo);
        $ofertaItemRepo = new \Aplicacion\Repositorios\OfertaItemEdicionRepositorio($pdo);
        $ofertaPaqueteRepo = new \Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio($pdo);
        $tarifaItemRepo = new \Aplicacion\Repositorios\TarifaItemEdicionRepositorio($pdo);
        $tarifaPaqueteRepo = new \Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio($pdo);
        $entregaRepo = new \Aplicacion\Repositorios\EntregaProductoRepositorio($pdo);
        $itemCfgRepo = new \Aplicacion\Repositorios\ItemConfiguracionOperativaRepositorio($pdo);
        $auditoriaRepo = new \Aplicacion\Repositorios\AuditoriaRepositorio($pdo);

        $configServicio = new \Aplicacion\Configuracion\ConfiguracionServicio(configRepo: $configRepo, pdo: $pdo);
        $authzServicio = new \Aplicacion\Autorizacion\AutorizacionServicio(null, null, null, null, $pdo);

        $cotizacionServicio = new \Aplicacion\Cotizaciones\CotizacionServicio(
            $cotizacionRepo, $clienteRepo, $edicionRepo, $oportunidadRepo,
            $itemRepo, $paqueteRepo, $ofertaItemRepo, $ofertaPaqueteRepo,
            $tarifaItemRepo, $tarifaPaqueteRepo, $configServicio,
            $authzServicio, $auditoriaRepo, null, $pdo
        );

        $ventaServicio = new \Aplicacion\Ventas\VentaServicio(
            $ventaRepo, $cotizacionRepo, $clienteRepo, $personaRepo,
            $edicionRepo, $configServicio, $authzServicio, $auditoriaRepo,
            null, $pdo
        );

        $reservaServicio = new \Aplicacion\Reservas\ReservaServicio(
            $reservaRepo, $entregaRepo, $itemCfgRepo, $ventaRepo,
            $edicionRepo, $itemRepo, $authzServicio, $auditoriaRepo, $pdo
        );

        $pagoServicio = new \Aplicacion\Finanzas\PagoServicio(
            $pagoRepo, $reembolsoRepo, $cuentaRepo, $pasarelaRepo,
            $ventaRepo, $edicionRepo, $configRepo, $authzServicio, $auditoriaRepo, $pdo
        );

        $motorDisp = new MotorDisponibilidadServicio($bloqueoRepo, $pdo);
        $authClienteSvc = new AutenticacionClienteServicio($sesionRepo, $codigoRepo, $clienteRepo, $personaRepo);

        return new self(
            $motorDisp, $authClienteSvc, $cotizacionServicio, $ventaServicio,
            $reservaServicio, $pagoServicio, $edicionRepo, $paqueteRepo,
            $itemRepo, $clienteRepo, $personaRepo, $reservaRepo,
            $ventaRepo, $pagoRepo, $bloqueoRepo, $pdo
        );
    }


    /**
     * Retorna la información de la edición actual y metadatos públicos de la organización.
     */
    public function obtenerEdicionActual(int $organizacionId): ?array
    {
        $edicion = $this->edicionRepo->buscarActual($organizacionId);
        if ($edicion === null) {
            return null;
        }

        $stmtOrg = $this->pdo->prepare("
            SELECT `nombre_comercial`, `razon_social`, `logo_url`, `isotipo_url`, `codigo`, `sitio_web`
            FROM `organizaciones`
            WHERE `id` = :id AND `estado` = 'ACTIVO'
        ");
        $stmtOrg->execute(['id' => $organizacionId]);
        $org = $stmtOrg->fetch(PDO::FETCH_ASSOC);

        if (!$org) {
            return null;
        }

        return [
            'organizacion' => [
                'id'               => $organizacionId,
                'codigo'           => $org['codigo'],
                'nombre_comercial' => $org['nombre_comercial'],
                'razon_social'     => $org['razon_social'],
                'logo_url'         => $org['logo_url'],
                'isotipo_url'      => $org['isotipo_url'],
            ],
            'edicion' => [
                'id'               => $edicion->id,
                'nombre'           => $edicion->nombre,
                'anio'             => $edicion->anio,
                'fecha_inicio'     => $edicion->fechaInicio,
                'estado_operativo' => $edicion->estado->value,
                'descripcion'      => $edicion->descripcion,
            ],
        ];
    }

    /**
     * Retorna el catálogo público de paquetes e ítems disponibles con sus tarifas y disponibilidad real.
     */
    public function obtenerCatalogoPublico(int $organizacionId, int $edicionId, ?int $categoriaId = null): array
    {
        // 1. Paquetes activos de la edición
        $sqlPaq = "
            SELECT p.id, p.codigo, p.nombre, p.descripcion,
                   tpe.precio, tpe.moneda, ope.id as oferta_id
            FROM `paquetes` p
            JOIN `ofertas_paquetes_edicion` ope ON ope.paquete_id = p.id
            JOIN `tarifas_paquetes_edicion` tpe ON tpe.oferta_paquete_id = ope.id
            WHERE ope.organizacion_id = :org_id
              AND ope.edicion_id = :edicion_id
              AND ope.estado = 'ACTIVO'
              AND p.estado = 'ACTIVO'
        ";
        $stmtPaq = $this->pdo->prepare($sqlPaq);
        $stmtPaq->execute(['org_id' => $organizacionId, 'edicion_id' => $edicionId]);
        $paquetesRaw = $stmtPaq->fetchAll(PDO::FETCH_ASSOC);

        $paquetes = [];
        foreach ($paquetesRaw as $p) {
            $pId = (int) $p['id'];
            $cuposDisp = $this->motorDisp->calcularDisponibilidadPaquete($organizacionId, $edicionId, $pId);

            // Obtener componentes del paquete
            $stmtComp = $this->pdo->prepare("
                SELECT ic.nombre, ic.tipo, pi.cantidad
                FROM `paquete_items` pi
                JOIN `items_comerciales` ic ON pi.item_comercial_id = ic.id
                WHERE pi.paquete_id = :paquete_id
            ");
            $stmtComp->execute(['paquete_id' => $pId]);
            $componentes = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

            $paquetes[] = [
                'id'                => $pId,
                'codigo'            => $p['codigo'],
                'nombre'            => $p['nombre'],
                'descripcion'       => $p['descripcion'],
                'precio'            => (float) $p['precio'],
                'moneda'            => $p['moneda'],
                'cupos_disponibles' => $cuposDisp,
                'componentes'       => $componentes,
            ];
        }

        // 2. Ítems individuales vendibles
        $sqlItems = "
            SELECT ic.id, ic.codigo, ic.nombre, ic.tipo, ic.unidad_medida, ic.descripcion,
                   tie.precio, tie.moneda
            FROM `items_comerciales` ic
            JOIN `ofertas_items_edicion` oie ON oie.item_comercial_id = ic.id
            JOIN `tarifas_items_edicion` tie ON tie.oferta_item_id = oie.id
            WHERE oie.organizacion_id = :org_id
              AND oie.edicion_id = :edicion_id
              AND oie.estado = 'ACTIVO'
              AND ic.estado = 'ACTIVO'
        ";
        if ($categoriaId !== null) {
            $sqlItems .= " AND ic.categoria_id = :cat_id";
        }
        $stmtItems = $this->pdo->prepare($sqlItems);
        $params = ['org_id' => $organizacionId, 'edicion_id' => $edicionId];
        if ($categoriaId !== null) {
            $params['cat_id'] = $categoriaId;
        }
        $stmtItems->execute($params);
        $itemsRaw = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($itemsRaw as $i) {
            $iId = (int) $i['id'];
            $cuposDisp = $this->motorDisp->calcularDisponibilidadItem($organizacionId, $edicionId, $iId);

            $items[] = [
                'id'                => $iId,
                'codigo'            => $i['codigo'],
                'nombre'            => $i['nombre'],
                'tipo'              => $i['tipo'],
                'unidad_medida'     => $i['unidad_medida'],
                'descripcion'       => $i['descripcion'],
                'precio'            => (float) $i['precio'],
                'moneda'            => $i['moneda'],
                'cupos_disponibles' => $cuposDisp,
            ];
        }

        return [
            'paquetes' => $paquetes,
            'items'    => $items,
        ];
    }

    /**
     * Retorna la información completa de un paquete para la ficha pública con disponibilidad real.
     */
    public function obtenerDetallePaquete(int $organizacionId, int $edicionId, int $paqueteId): ?array
    {
        $stmtPaq = $this->pdo->prepare("
            SELECT p.id, p.codigo, p.nombre, p.descripcion,
                   tpe.precio, tpe.moneda, ope.id as oferta_id
            FROM `paquetes` p
            JOIN `ofertas_paquetes_edicion` ope ON ope.paquete_id = p.id
            JOIN `tarifas_paquetes_edicion` tpe ON tpe.oferta_paquete_id = ope.id
            WHERE ope.organizacion_id = :org_id
              AND ope.edicion_id = :edicion_id
              AND p.id = :paquete_id
              AND ope.estado = 'ACTIVO'
              AND p.estado = 'ACTIVO'
            LIMIT 1
        ");
        $stmtPaq->execute([
            'org_id'     => $organizacionId,
            'edicion_id' => $edicionId,
            'paquete_id' => $paqueteId,
        ]);
        $p = $stmtPaq->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            return null;
        }

        $cuposDisp = $this->motorDisp->calcularDisponibilidadPaquete($organizacionId, $edicionId, $paqueteId);

        $stmtComp = $this->pdo->prepare("
            SELECT pi.cantidad, ic.id as item_id, ic.codigo, ic.nombre, ic.tipo, ic.unidad_medida
            FROM `paquete_items` pi
            JOIN `items_comerciales` ic ON pi.item_comercial_id = ic.id
            WHERE pi.paquete_id = :paquete_id AND ic.estado = 'ACTIVO'
        ");
        $stmtComp->execute(['paquete_id' => $paqueteId]);
        $componentes = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id'                => $paqueteId,
            'codigo'            => $p['codigo'],
            'nombre'            => $p['nombre'],
            'descripcion'       => $p['descripcion'],
            'precio'            => (float) $p['precio'],
            'moneda'            => $p['moneda'],
            'cupos_disponibles' => $cuposDisp,
            'componentes'       => $componentes,
        ];
    }

    /**
     * Recalcula forzosamente en el backend una solicitud de cotización.
     * Cero cálculos matemáticos permitidos en el cliente.
     *
     * @param array<array{tipo: string, id: int, cantidad: int}> $lineasSolicitadas
     */
    public function cotizar(int $organizacionId, int $edicionId, array $lineasSolicitadas): array
    {
        if (empty($lineasSolicitadas)) {
            throw new InvalidArgumentException("Debe seleccionar al menos un ítem o paquete para cotizar.");
        }

        $subtotal = 0.00;
        $descuentoTotal = 0.00;
        $lineasDesglose = [];

        foreach ($lineasSolicitadas as $solicitud) {
            $tipo = strtoupper(trim((string) ($solicitud['tipo'] ?? 'PAQUETE')));
            $id = (int) ($solicitud['id'] ?? 0);
            $cantidad = max(1, (int) ($solicitud['cantidad'] ?? 1));

            if ($tipo === 'PAQUETE') {
                $stmtTarifa = $this->pdo->prepare("
                    SELECT tpe.precio, tpe.moneda, p.nombre, p.codigo
                    FROM `paquetes` p
                    JOIN `ofertas_paquetes_edicion` ope ON ope.paquete_id = p.id
                    JOIN `tarifas_paquetes_edicion` tpe ON tpe.oferta_paquete_id = ope.id
                    WHERE ope.organizacion_id = :org_id
                      AND ope.edicion_id = :edicion_id
                      AND p.id = :id
                      AND ope.estado = 'ACTIVO'
                ");
                $stmtTarifa->execute(['org_id' => $organizacionId, 'edicion_id' => $edicionId, 'id' => $id]);
                $tarifa = $stmtTarifa->fetch(PDO::FETCH_ASSOC);

                if (!$tarifa) {
                    throw new InvalidArgumentException("El paquete #{$id} no está disponible en la edición actual.");
                }

                $precioUnitario = (float) $tarifa['precio'];
                $totalLinea = round($precioUnitario * $cantidad, 2);
                $subtotal += $totalLinea;

                $lineasDesglose[] = [
                    'tipo'            => 'PAQUETE',
                    'referencia_id'   => $id,
                    'codigo'          => $tarifa['codigo'],
                    'nombre'          => $tarifa['nombre'],
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'total_linea'     => $totalLinea,
                ];
            } else {
                $stmtTarifa = $this->pdo->prepare("
                    SELECT tie.precio, tie.moneda, ic.nombre, ic.codigo
                    FROM `items_comerciales` ic
                    JOIN `ofertas_items_edicion` oie ON oie.item_comercial_id = ic.id
                    JOIN `tarifas_items_edicion` tie ON tie.oferta_item_id = oie.id
                    WHERE oie.organizacion_id = :org_id
                      AND oie.edicion_id = :edicion_id
                      AND ic.id = :id
                      AND oie.estado = 'ACTIVO'
                ");
                $stmtTarifa->execute(['org_id' => $organizacionId, 'edicion_id' => $edicionId, 'id' => $id]);
                $tarifa = $stmtTarifa->fetch(PDO::FETCH_ASSOC);

                if (!$tarifa) {
                    throw new InvalidArgumentException("El ítem #{$id} no está disponible en la edición actual.");
                }

                $precioUnitario = (float) $tarifa['precio'];
                $totalLinea = round($precioUnitario * $cantidad, 2);
                $subtotal += $totalLinea;

                $lineasDesglose[] = [
                    'tipo'            => 'ITEM',
                    'referencia_id'   => $id,
                    'codigo'          => $tarifa['codigo'],
                    'nombre'          => $tarifa['nombre'],
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'total_linea'     => $totalLinea,
                ];
            }
        }

        $total = round($subtotal - $descuentoTotal, 2);
        // Base imponible e IGV (18% incluido según régimen peruano)
        $baseImponible = round($total / 1.18, 2);
        $igv = round($total - $baseImponible, 2);

        return [
            'moneda'          => 'PEN',
            'subtotal'        => $subtotal,
            'descuento_total' => $descuentoTotal,
            'base_imponible'  => $baseImponible,
            'igv'             => $igv,
            'total'           => $total,
            'lineas'          => $lineasDesglose,
        ];
    }

    /**
     * Bloquea cupos temporalmente para el checkout (TTL 15 min).
     */
    public function bloquearCupos(
        int $organizacionId,
        int $edicionId,
        int $itemId,
        int $cantidad,
        string $identificadorSesion,
        ?string $fecha = null,
        ?string $hora = null,
        ?int $salidaId = null
    ): array {
        return $this->motorDisp->bloquearCupos(
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            itemComercialId: $itemId,
            cuposSolicitados: $cantidad,
            identificadorSesion: $identificadorSesion,
            fechaServicio: $fecha,
            horaServicio: $hora,
            salidaId: $salidaId,
            ttlMinutos: 15
        );
    }

    /**
     * Procesa el checkout de un invitado o cliente autenticado de forma atómica e idempotente.
     */
    public function procesarCheckout(
        int $organizacionId,
        int $edicionId,
        array $payload,
        string $ipOrigen,
        ?string $userAgent = null
    ): array {
        // 1. Validar bloqueo temporal de cupos
        $tokenBloqueo = trim((string) ($payload['token_bloqueo'] ?? ''));
        if ($tokenBloqueo === '') {
            throw new InvalidArgumentException("Debe proporcionar un token_bloqueo activo para proceder al checkout.");
        }

        $bloqueo = $this->bloqueoRepo->buscarPorTokenParaActualizar($tokenBloqueo);
        if ($bloqueo === null || !$bloqueo->estaVigente()) {
            throw new RuntimeException("El tiempo límite para completar su reserva ha expirado. Por favor seleccione sus cupos nuevamente.");
        }

        // 2. Datos del cliente / titular
        $clienteData = $payload['cliente'] ?? [];
        $tipoDocId = (int) ($clienteData['tipo_documento_id'] ?? 1);
        $numDoc = trim((string) ($clienteData['numero_documento'] ?? ''));
        $nombres = trim((string) ($clienteData['nombres'] ?? ''));
        $apellidos = trim((string) ($clienteData['apellidos'] ?? ''));
        $correo = strtolower(trim((string) ($clienteData['correo'] ?? '')));
        $telefono = trim((string) ($clienteData['telefono'] ?? ''));

        if ($numDoc === '' || $nombres === '' || $apellidos === '' || $correo === '') {
            throw new InvalidArgumentException("Los datos personales del titular (documento, nombres, apellidos y correo) son obligatorios.");
        }

        // 3. Validar consentimientos legales (Ley 29733)
        $consentimientos = $payload['consentimientos'] ?? [];
        if (empty($consentimientos['terminos_condiciones']) || empty($consentimientos['politica_privacidad'])) {
            throw new InvalidArgumentException("Debe aceptar expresamente los Términos y Condiciones y la Política de Privacidad para continuar.");
        }

        // 4. Localizar o registrar de forma idempotente Persona y Cliente
        $persona = $this->personaRepo->buscarPorDocumento($organizacionId, $tipoDocId, $numDoc);
        if ($persona === null) {
            $persona = new Persona(
                id: null,
                organizacionId: $organizacionId,
                tipoPersona: 'NATURAL',
                tipoDocumentoId: $tipoDocId,
                numeroDocumento: $numDoc,
                nombres: $nombres,
                apellidos: $apellidos,
                razonSocial: null,
                nombreComercial: null,
                correoElectronico: $correo,
                telefonoMovil: $telefono,
                telefonoWhatsapp: $telefono,
                direccion: $payload['facturacion']['direccion'] ?? null,
                ciudad: 'PUNO',
                codigoPais: 'PE',
                estado: 'ACTIVO'
            );
            $personaId = $this->personaRepo->crear($persona);
            $persona = $this->personaRepo->buscarPorId($personaId);
        }

        $cliente = $this->clienteRepo->buscarPorPersona($organizacionId, (int) $persona->id);
        if ($cliente === null) {
            $clienteObj = new Cliente(
                id: null,
                organizacionId: $organizacionId,
                personaId: (int) $persona->id,
                estadoComercial: EstadoCliente::CONTACTO,
                consentimientoOperativo: true,
                consentimientoOperativoEn: date('Y-m-d H:i:s'),
                consentimientoPromocional: !empty($consentimientos['promocional']),
                consentimientoPromocionalEn: !empty($consentimientos['promocional']) ? date('Y-m-d H:i:s') : null,
                notasComerciales: 'Cliente registrado desde Checkout Portal Público'
            );
            $clienteId = $this->clienteRepo->crear($clienteObj);
            $cliente = $this->clienteRepo->buscarPorId($clienteId);
        }

        // 5. Registrar evidencia de consentimiento por canal (Ley 29733)
        if (!empty($consentimientos['whatsapp_transaccional']) && $telefono !== '') {
            $stmtCons = $this->pdo->prepare("
                INSERT INTO `comunicacion_consentimientos_canal` (
                    `organizacion_id`, `cliente_id`, `canal`, `telefono_destino`,
                    `finalidad`, `estado`, `origen_evidencia`, `texto_clausula_aceptada`,
                    `direccion_ip_registro`, `actor_tipo`, `usuario_id`, `correlacion_id`
                ) VALUES (
                    :org_id, :cliente_id, 'WHATSAPP', :telefono,
                    'TRANSACCIONAL_OPERATIVO', 'CONCEDIDO', 'WEB_CHECKBOX',
                    'Acepto recibir notificaciones transaccionales y de confirmación por WhatsApp.',
                    :ip, 'SISTEMA', NULL, :correlacion
                )
            ");
            $stmtCons->execute([
                'org_id'      => $organizacionId,
                'cliente_id'  => (int) $cliente->id,
                'telefono'    => $telefono,
                'ip'          => $ipOrigen,
                'correlacion' => bin2hex(random_bytes(16)),
            ]);
        }

        // 6. Calcular cotización formal en backend
        $lineas = $payload['lineas'] ?? [];
        $desglose = $this->cotizar($organizacionId, $edicionId, $lineas);

        $metodoPago = strtoupper(trim((string) ($payload['metodo_pago'] ?? 'PASARELA_NIUBIZ')));

        // 7. Bifurcación según Método de Pago:
        // CASO A: Pago Manual (Voucher Bancario / Yape)
        if ($metodoPago === 'TRANSFERENCIA_BANCARIA' || $metodoPago === 'PAGO_MANUAL_VOUCHER') {
            return $this->procesarCheckoutVoucherManual(
                $organizacionId,
                $edicionId,
                (int) $cliente->id,
                $tokenBloqueo,
                $desglose,
                $payload,
                $ipOrigen
            );
        }

        // CASO B: Pasarela de Pago Online Sandbox
        return $this->procesarCheckoutPasarelaSandbox(
            $organizacionId,
            $edicionId,
            (int) $cliente->id,
            $tokenBloqueo,
            $desglose,
            $metodoPago,
            $payload
        );
    }

    /**
     * Procesa checkout con voucher bancario manual (Pago PENDIENTE_VERIFICACION, Reserva REGISTRADA, Retención 24h).
     */
    private function procesarCheckoutVoucherManual(
        int $organizacionId,
        int $edicionId,
        int $clienteId,
        string $tokenBloqueo,
        array $desglose,
        array $payload,
        string $ipOrigen
    ): array {
        $ctxSistema = ContextoOperacion::paraSistema(
            actorSistemaId: 1,
            actorSistemaCodigo: 'PORTAL_PUBLICO_CHECKOUT',
            canalId: 2,
            canalCodigo: 'WEB',
            origenIp: $ipOrigen,
            organizacionId: $organizacionId
        );

        // 1. Crear cotización formal y aceptarla
        $cotizacion = $this->cotizacionServicio->crearBorrador(
            $organizacionId,
            $edicionId,
            $clienteId,
            'Cotización Checkout Público (Voucher)',
            contexto: $ctxSistema
        );

        foreach ($desglose['lineas'] as $l) {
            if ($l['tipo'] === 'PAQUETE') {
                $this->cotizacionServicio->agregarLineaPaquete(
                    $organizacionId,
                    (int) $cotizacion->id,
                    (int) $l['referencia_id'],
                    (float) $l['cantidad'],
                    contexto: $ctxSistema
                );
            } else {
                $this->cotizacionServicio->agregarLineaItem(
                    $organizacionId,
                    (int) $cotizacion->id,
                    (int) $l['referencia_id'],
                    (float) $l['cantidad'],
                    contexto: $ctxSistema
                );
            }
        }

        $cotizacionEmitida = $this->cotizacionServicio->emitir(
            $organizacionId,
            (int) $cotizacion->id,
            validoHastaManual: date('Y-m-d', strtotime('+3 days')),
            contexto: $ctxSistema
        );
        $this->cotizacionServicio->aceptar($organizacionId, (int) $cotizacionEmitida->id, contexto: $ctxSistema);

        // 2. Crear Venta CONFIRMADA con estado_financiero = NO_PAGADA
        $venta = $this->ventaServicio->crearDesdeCotizacion($organizacionId, (int) $cotizacionEmitida->id, contexto: $ctxSistema);

        // 3. Formalizar Reserva en estado REGISTRADA / PENDIENTE_DATOS
        $resultadoReserva = $this->reservaServicio->formalizarDesdeVenta($organizacionId, (int) $venta->id, contexto: $ctxSistema);
        $reserva = $resultadoReserva['reserva'];

        // 4. Extender bloqueo temporal a RETENCION_VOUCHER (24h de retención garantizada)
        $this->motorDisp->extenderParaVoucher($tokenBloqueo, 24);

        // 5. Registrar el Pago en estado PENDIENTE_VERIFICACION
        $stmtSec = $this->pdo->prepare("
            INSERT INTO `pagos_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 1)
            ON DUPLICATE KEY UPDATE `ultimo_numero` = `ultimo_numero` + 1
        ");
        $anioActual = (int) date('Y');
        $stmtSec->execute(['org_id' => $organizacionId, 'anio' => $anioActual]);
        $stmtNum = $this->pdo->prepare("SELECT `ultimo_numero` FROM `pagos_secuencias` WHERE `organizacion_id` = :org_id AND `anio` = :anio");
        $stmtNum->execute(['org_id' => $organizacionId, 'anio' => $anioActual]);
        $numCorrelativo = (int) $stmtNum->fetchColumn();
        $correlativoPago = sprintf('PAG-%04d-%06d', $anioActual, $numCorrelativo);

        $usuarioCreador = $this->resolverUsuarioSistemaId($organizacionId);
        $totalCobrado = (float) $desglose['total'];

        $stmtPago = $this->pdo->prepare("
            INSERT INTO `pagos` (
                `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
                `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`,
                `comision_porcentaje_aplicada`, `comision_fija_aplicada`, `comision_pasarela`,
                `monto_neto_recibido`, `monto_aplicado_venta`, `monto_excedente`,
                `comision_asumida_por`, `monto_reembolsado_acumulado`, `fecha_pago`,
                `cuenta_bancaria_id`, `organizacion_pasarela_id`, `numero_operacion_bancaria`,
                `boucher_comprobante_url`, `notas_operativas`, `clave_idempotencia`,
                `version_bloqueo`, `verificado_por`, `verificado_en`, `creado_por`
            ) VALUES (
                :org_id, :edicion_id, :venta_id, :correlativo,
                :metodo, :estado, :moneda, :monto_cobrado,
                0.00, 0.00, 0.00,
                :monto_neto, 0.00, 0.00,
                'ORGANIZACION', 0.00, NOW(),
                NULL, NULL, :num_op,
                :voucher_url, 'Voucher cargado por cliente en checkout público', :idempotencia,
                1, NULL, NULL, :creado_por
            )
        ");
        $stmtPago->execute([
            'org_id'        => $organizacionId,
            'edicion_id'    => $edicionId,
            'venta_id'      => (int) $venta->id,
            'correlativo'   => $correlativoPago,
            'metodo'        => MetodoPago::TRANSFERENCIA_BANCARIA->value,
            'estado'        => EstadoPago::PENDIENTE_VERIFICACION->value,
            'moneda'        => $desglose['moneda'],
            'monto_cobrado' => $totalCobrado,
            'monto_neto'    => $totalCobrado,
            'num_op'        => $payload['comprobante']['numero_operacion'] ?? 'OP-PENDIENTE',
            'voucher_url'   => $payload['comprobante']['voucher_url'] ?? null,
            'idempotencia'  => 'vch_' . bin2hex(random_bytes(16)),
            'creado_por'    => $usuarioCreador,
        ]);
        $pagoId = (int) $this->pdo->lastInsertId();

        // 6. Encolar notificación en Outbox: VOUCHER_EN_REVISION
        $this->encolarNotificacionOutbox(
            $organizacionId,
            $clienteId,
            'voucher_recibido_revision',
            [
                'correlativo_venta' => $venta->correlativo,
                'monto'             => number_format($desglose['total'], 2),
            ],
            'VOUCHER_EN_REVISION'
        );

        return [
            'exito'             => true,
            'modo'              => 'VOUCHER_MANUAL',
            'estado_pago'       => EstadoPago::PENDIENTE_VERIFICACION->value,
            'estado_reserva'    => $reserva ? $reserva->estado->value : 'REGISTRADA',
            'venta_id'          => (int) $venta->id,
            'reserva_id'        => $reserva ? (int) $reserva->id : null,
            'pago_id'           => $pagoId,
            'correlativo_venta' => $venta->correlativo,
            'mensaje'           => 'Su voucher ha sido registrado. Sus cupos quedan retenidos provisionalmente por 24 horas mientras nuestro equipo administrativo verifica el abono.',
        ];
    }

    /**
     * Procesa checkout con pasarela sandbox (genera intención y token sin confirmar la reserva).
     */
    private function procesarCheckoutPasarelaSandbox(
        int $organizacionId,
        int $edicionId,
        int $clienteId,
        string $tokenBloqueo,
        array $desglose,
        string $metodoPago,
        array $payload
    ): array {
        $ctxSistema = ContextoOperacion::paraSistema(
            actorSistemaId: 1,
            actorSistemaCodigo: 'PORTAL_PUBLICO_CHECKOUT',
            canalId: 2,
            canalCodigo: 'WEB',
            organizacionId: $organizacionId
        );

        // Crear cotización formal y aceptarla
        $cotizacion = $this->cotizacionServicio->crearBorrador(
            $organizacionId,
            $edicionId,
            $clienteId,
            'Cotización Checkout Pasarela Sandbox',
            contexto: $ctxSistema
        );

        foreach ($desglose['lineas'] as $l) {
            if ($l['tipo'] === 'PAQUETE') {
                $this->cotizacionServicio->agregarLineaPaquete(
                    $organizacionId,
                    (int) $cotizacion->id,
                    (int) $l['referencia_id'],
                    (float) $l['cantidad'],
                    contexto: $ctxSistema
                );
            } else {
                $this->cotizacionServicio->agregarLineaItem(
                    $organizacionId,
                    (int) $cotizacion->id,
                    (int) $l['referencia_id'],
                    (float) $l['cantidad'],
                    contexto: $ctxSistema
                );
            }
        }

        $cotizacionEmitida = $this->cotizacionServicio->emitir(
            $organizacionId,
            (int) $cotizacion->id,
            validoHastaManual: date('Y-m-d', strtotime('+3 days')),
            contexto: $ctxSistema
        );
        $this->cotizacionServicio->aceptar($organizacionId, (int) $cotizacionEmitida->id, contexto: $ctxSistema);

        // Token efímero de pasarela sandbox
        $sessionTokenSandbox = 'tok_sbx_' . bin2hex(random_bytes(16));

        return [
            'exito'         => true,
            'modo'          => 'PASARELA_ONLINE_SANDBOX',
            'cotizacion_id' => (int) $cotizacionEmitida->id,
            'token_bloqueo' => $tokenBloqueo,
            'total'         => $desglose['total'],
            'moneda'        => $desglose['moneda'],
            'pasarela'      => [
                'codigo'        => str_replace('PASARELA_', '', $metodoPago),
                'modo'          => 'SANDBOX',
                'session_token' => $sessionTokenSandbox,
                'url_checkout'  => "/p/checkout/sandbox-frame?token={$sessionTokenSandbox}",
            ],
            'mensaje'       => 'Sesión de pago sandbox generada. Proceda al pago seguro para confirmar su reserva.',
        ];
    }

    /**
     * Confirma atómicamente un pago sandbox tras la recepción exitosa del webhook o simulación.
     */
    public function confirmarPagoOnlineSandbox(
        int $organizacionId,
        int $cotizacionId,
        string $tokenBloqueo,
        string $codigoPasarela,
        string $referenciaTransaccion
    ): array {
        $ctxSistema = ContextoOperacion::paraSistema(
            actorSistemaId: 1,
            actorSistemaCodigo: 'PORTAL_PUBLICO_CHECKOUT',
            canalId: 2,
            canalCodigo: 'WEB',
            organizacionId: $organizacionId
        );

        $transaccionIniciada = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            // 1. Convertir Cotización ACEPTADA a Venta CONFIRMADA
            $venta = $this->ventaServicio->crearDesdeCotizacion($organizacionId, $cotizacionId, contexto: $ctxSistema);

            // 2. Formalizar Reserva
            $resultadoReserva = $this->reservaServicio->formalizarDesdeVenta($organizacionId, (int) $venta->id, contexto: $ctxSistema);
            $reserva = $resultadoReserva['reserva'];

            // 3. Registrar el Pago en estado APROBADO
            $stmtSec = $this->pdo->prepare("
                INSERT INTO `pagos_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
                VALUES (:org_id, :anio, 1)
                ON DUPLICATE KEY UPDATE `ultimo_numero` = `ultimo_numero` + 1
            ");
            $anioActual = (int) date('Y');
            $stmtSec->execute(['org_id' => $organizacionId, 'anio' => $anioActual]);
            $stmtNum = $this->pdo->prepare("SELECT `ultimo_numero` FROM `pagos_secuencias` WHERE `organizacion_id` = :org_id AND `anio` = :anio");
            $stmtNum->execute(['org_id' => $organizacionId, 'anio' => $anioActual]);
            $numCorrelativo = (int) $stmtNum->fetchColumn();
            $correlativoPago = sprintf('PAG-%04d-%06d', $anioActual, $numCorrelativo);

            $usuarioCreador = $this->resolverUsuarioSistemaId($organizacionId);
            $totalCobrado = (float) $venta->total;

            $stmtPago = $this->pdo->prepare("
                INSERT INTO `pagos` (
                    `organizacion_id`, `edicion_id`, `venta_id`, `correlativo`,
                    `metodo_pago`, `estado`, `moneda`, `monto_cobrado_cliente`,
                    `comision_porcentaje_aplicada`, `comision_fija_aplicada`, `comision_pasarela`,
                    `monto_neto_recibido`, `monto_aplicado_venta`, `monto_excedente`,
                    `comision_asumida_por`, `monto_reembolsado_acumulado`, `fecha_pago`,
                    `cuenta_bancaria_id`, `organizacion_pasarela_id`, `numero_operacion_bancaria`,
                    `boucher_comprobante_url`, `notas_operativas`, `clave_idempotencia`,
                    `version_bloqueo`, `verificado_por`, `verificado_en`, `creado_por`
                ) VALUES (
                    :org_id, :edicion_id, :venta_id, :correlativo,
                    :metodo, :estado, :moneda, :monto_cobrado,
                    0.00, 0.00, 0.00,
                    :monto_neto, :monto_aplicado, 0.00,
                    'ORGANIZACION', 0.00, NOW(),
                    NULL, NULL, :num_op,
                    NULL, 'Pago sandbox confirmado desde portal público', :idempotencia,
                    1, :verificado_por, NOW(), :creado_por
                )
            ");
            $stmtPago->execute([
                'org_id'         => $organizacionId,
                'edicion_id'     => $venta->edicionId,
                'venta_id'       => (int) $venta->id,
                'correlativo'    => $correlativoPago,
                'metodo'         => MetodoPago::TARJETA_PASARELA->value,
                'estado'         => EstadoPago::APROBADO->value,
                'moneda'         => $venta->moneda,
                'monto_cobrado'  => $totalCobrado,
                'monto_neto'     => $totalCobrado,
                'monto_aplicado' => $totalCobrado,
                'num_op'         => $referenciaTransaccion,
                'idempotencia'   => 'sbx_' . bin2hex(random_bytes(16)),
                'verificado_por' => $usuarioCreador,
                'creado_por'     => $usuarioCreador,
            ]);
            $pagoId = (int) $this->pdo->lastInsertId();

            // 4. Actualizar estado financiero de la Venta a PAGADA_TOTAL
            $stmtVtaFin = $this->pdo->prepare("
                UPDATE `ventas`
                SET `estado_financiero` = :est_fin
                WHERE `id` = :id
            ");
            $stmtVtaFin->execute([
                'est_fin' => EstadoFinancieroVenta::PAGADA_TOTAL->value,
                'id'      => (int) $venta->id,
            ]);

            // 5. Transicionar Reserva a CONFIRMADA
            if ($reserva !== null) {
                $stmtResConf = $this->pdo->prepare("
                    UPDATE `reservas`
                    SET `estado` = :est_res
                    WHERE `id` = :id
                ");
                $stmtResConf->execute([
                    'est_res' => EstadoReserva::CONFIRMADA->value,
                    'id'      => (int) $reserva->id,
                ]);
            }

            // 6. Marcar bloqueo temporal como CONSUMIDO
            $this->motorDisp->consumirBloqueo($tokenBloqueo);

            // 7. Encolar notificación Outbox: RESERVA_CONFIRMADA
            $this->encolarNotificacionOutbox(
                $organizacionId,
                $venta->clienteId,
                'confirmacion_reserva_candelaria',
                [
                    'correlativo_reserva' => $reserva ? $reserva->correlativo : $venta->correlativo,
                    'monto_total'         => number_format($venta->total, 2),
                ],
                'RESERVA_CONFIRMADA'
            );

            if ($transaccionIniciada) {
                $this->pdo->commit();
            }

            return [
                'exito'             => true,
                'venta_id'          => (int) $venta->id,
                'reserva_id'        => $reserva ? (int) $reserva->id : null,
                'pago_id'           => $pagoId,
                'correlativo_venta' => $venta->correlativo,
                'estado'            => 'CONFIRMADA',
                'mensaje'           => '¡Pago confirmado exitosamente! Su reserva ha sido formalizada.',
            ];
        } catch (\Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException("Error al confirmar pago online: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Aprobación de un voucher por un operador administrativo autorizado (pagos.verificar).
     */
    public function aprobarVoucherPorOperador(int $organizacionId, int $pagoId, ContextoOperacion $contexto): array
    {
        $stmtPago = $this->pdo->prepare("
            SELECT p.*, v.cliente_id, v.total as venta_total, v.id as venta_id, r.id as reserva_id, r.correlativo as reserva_correlativo
            FROM `pagos` p
            JOIN `ventas` v ON p.venta_id = v.id
            LEFT JOIN `reservas` r ON r.venta_id = v.id
            WHERE p.id = :id AND p.organizacion_id = :org_id
            FOR UPDATE
        ");
        $stmtPago->execute(['id' => $pagoId, 'org_id' => $organizacionId]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }

        if ($pago['estado'] !== EstadoPago::PENDIENTE_VERIFICACION->value) {
            throw new InvalidArgumentException("Solo se pueden aprobar pagos en estado PENDIENTE_VERIFICACION.");
        }

        // 1. Aprobar pago
        $stmtUpPago = $this->pdo->prepare("
            UPDATE `pagos`
            SET `estado` = :estado, `verificado_por` = :usr_id, `verificado_en` = NOW()
            WHERE `id` = :id
        ");
        $stmtUpPago->execute([
            'estado' => EstadoPago::APROBADO->value,
            'usr_id' => $contexto->usuarioId ?? $this->resolverUsuarioSistemaId($organizacionId),
            'id'     => $pagoId,
        ]);

        // 2. Actualizar estado financiero de la venta preservando el estado comercial canónico CONFIRMADA
        $stmtUpVta = $this->pdo->prepare("
            UPDATE `ventas`
            SET `estado` = :est_com,
                `estado_financiero` = :est_fin,
                `monto_pagado` = `total`,
                `saldo_pendiente` = 0.00
            WHERE `id` = :id
        ");
        $stmtUpVta->execute([
            'est_com' => EstadoVenta::CONFIRMADA->value,
            'est_fin' => EstadoFinancieroVenta::PAGADA_TOTAL->value,
            'id'      => (int) $pago['venta_id'],
        ]);

        // 3. Transicionar Reserva a CONFIRMADA y consumir bloqueos
        if (!empty($pago['reserva_id'])) {
            $stmtUpRes = $this->pdo->prepare("
                UPDATE `reservas`
                SET `estado` = :est_res
                WHERE `id` = :id
            ");
            $stmtUpRes->execute([
                'est_res' => EstadoReserva::CONFIRMADA->value,
                'id'      => (int) $pago['reserva_id'],
            ]);

            // Consumir formalmente bloqueos en retención para los items de la reserva
            $this->pdo->prepare("
                UPDATE `reserva_bloqueos_temporales`
                SET `estado` = 'CONSUMIDO'
                WHERE `organizacion_id` = :org_id
                  AND `estado` = 'RETENCION_VOUCHER'
                  AND `item_comercial_id` IN (
                      SELECT rp.item_comercial_id FROM `reserva_prestaciones` rp
                      WHERE rp.reserva_id = :reserva_id
                  )
            ")->execute(['org_id' => $organizacionId, 'reserva_id' => (int) $pago['reserva_id']]);
        }

        // 4. Encolar notificación: RESERVA_CONFIRMADA
        $this->encolarNotificacionOutbox(
            $organizacionId,
            (int) $pago['cliente_id'],
            'confirmacion_reserva_candelaria',
            [
                'correlativo_reserva' => $pago['reserva_correlativo'] ?? ('VTA-' . $pago['venta_id']),
                'monto_total'         => number_format((float) $pago['venta_total'], 2),
            ],
            'RESERVA_CONFIRMADA'
        );

        return [
            'exito'      => true,
            'pago_id'    => $pagoId,
            'estado'     => 'APROBADO',
            'mensaje'    => 'Voucher verificado y aprobado. Reserva formalmente confirmada.',
        ];
    }

    /**
     * Rechazo de un voucher por un operador administrativo autorizado.
     */
    public function rechazarVoucherPorOperador(
        int $organizacionId,
        int $pagoId,
        string $motivo,
        ContextoOperacion $contexto
    ): array {
        $stmtPago = $this->pdo->prepare("
            SELECT p.*, v.id as venta_id, r.id as reserva_id
            FROM `pagos` p
            JOIN `ventas` v ON p.venta_id = v.id
            LEFT JOIN `reservas` r ON r.venta_id = v.id
            WHERE p.id = :id AND p.organizacion_id = :org_id
            FOR UPDATE
        ");
        $stmtPago->execute(['id' => $pagoId, 'org_id' => $organizacionId]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }

        // 1. Rechazar pago
        $stmtUpPago = $this->pdo->prepare("
            UPDATE `pagos`
            SET `estado` = :estado, `notas_operativas` = :motivo, `verificado_por` = :usr_id, `verificado_en` = NOW()
            WHERE `id` = :id
        ");
        $stmtUpPago->execute([
            'estado' => EstadoPago::RECHAZADO->value,
            'motivo' => trim($motivo),
            'usr_id' => $contexto->usuarioId ?? $this->resolverUsuarioSistemaId($organizacionId),
            'id'     => $pagoId,
        ]);

        // 2. Cancelar reserva y liberar bloqueos temporales
        if (!empty($pago['reserva_id'])) {
            $stmtUpRes = $this->pdo->prepare("
                UPDATE `reservas`
                SET `estado` = :est_res
                WHERE `id` = :id
            ");
            $stmtUpRes->execute([
                'est_res' => EstadoReserva::CANCELADA->value,
                'id'      => (int) $pago['reserva_id'],
            ]);

            // Liberar bloqueos en RETENCION_VOUCHER para restituir disponibilidad
            $this->pdo->prepare("
                UPDATE `reserva_bloqueos_temporales`
                SET `estado` = 'LIBERADO_MANUAL'
                WHERE `organizacion_id` = :org_id
                  AND `estado` = 'RETENCION_VOUCHER'
                  AND `item_comercial_id` IN (
                      SELECT rp.item_comercial_id FROM `reserva_prestaciones` rp
                      WHERE rp.reserva_id = :reserva_id
                  )
            ")->execute(['org_id' => $organizacionId, 'reserva_id' => (int) $pago['reserva_id']]);
        }

        // 3. Cancelar venta
        $stmtUpVta = $this->pdo->prepare("
            UPDATE `ventas`
            SET `estado` = :est_vta, `motivo_cancelacion` = 'INCUMPLIMIENTO_ORGANIZACION', `motivo_cancelacion_detalle` = :motivo
            WHERE `id` = :id
        ");
        $stmtUpVta->execute([
            'est_vta' => EstadoVenta::CANCELADA->value,
            'motivo'  => 'Voucher rechazado: ' . trim($motivo),
            'id'      => (int) $pago['venta_id'],
        ]);

        return [
            'exito'   => true,
            'pago_id' => $pagoId,
            'estado'  => 'RECHAZADO',
            'mensaje' => 'Voucher observado y rechazado. Capacidad liberada.',
        ];
    }

    /**
     * Genera el token criptográfico del boleto QR.
     * Condicionado estrictamente a que la reserva esté CONFIRMADA y pertenezca al cliente.
     */
    public function generarTokenBoletoQr(int $organizacionId, int $reservaId, int $clienteId): array
    {
        $stmtRes = $this->pdo->prepare("
            SELECT r.*, e.nombre as edicion_nombre
            FROM `reservas` r
            JOIN `ediciones_candelaria` e ON r.edicion_id = e.id
            WHERE r.id = :id AND r.organizacion_id = :org_id
        ");
        $stmtRes->execute(['id' => $reservaId, 'org_id' => $organizacionId]);
        $reserva = $stmtRes->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            throw new InvalidArgumentException("La reserva no existe.");
        }

        // Anti-IDOR: verificar titularidad
        if ((int) $reserva['cliente_id'] !== $clienteId) {
            throw new AccesoDenegadoExcepcion("Acceso denegado: No tiene permisos para acceder a esta reserva.");
        }

        // Condición estricta 1: Solo reservas formalmente CONFIRMADAS pueden emitir boletos QR
        if ($reserva['estado'] !== EstadoReserva::CONFIRMADA->value) {
            return [
                'exito'        => false,
                'codigo_error' => 'RESERVA_NO_CONFIRMADA',
                'mensaje'      => 'El boleto QR solo se emite para reservas formalmente confirmadas con datos completos.',
            ];
        }

        // Condición estricta 2: Derecho de acceso condicionado a venta CONFIRMADA con pago total verificado
        $stmtVta = $this->pdo->prepare("
            SELECT `estado`, `estado_financiero` FROM `ventas`
            WHERE `id` = :id AND `organizacion_id` = :org_id
        ");
        $stmtVta->execute(['id' => (int) $reserva['venta_id'], 'org_id' => $organizacionId]);
        $venta = $stmtVta->fetch(PDO::FETCH_ASSOC);

        if (!$venta || $venta['estado'] !== EstadoVenta::CONFIRMADA->value || !in_array($venta['estado_financiero'], [EstadoFinancieroVenta::PAGADA_TOTAL->value, EstadoFinancieroVenta::SOBREPAGADA->value], true)) {
            return [
                'exito'        => false,
                'codigo_error' => 'PAGO_NO_VERIFICADO',
                'mensaje'      => 'El derecho de acceso no está activo: la venta asociada aún no cuenta con pago total verificado.',
            ];
        }

        // Obtener prestaciones de la reserva
        $stmtPrest = $this->pdo->prepare("
            SELECT id, concepto_nombre, fecha_servicio, hora_servicio
            FROM `reserva_prestaciones`
            WHERE `reserva_id` = :reserva_id
        ");
        $stmtPrest->execute(['reserva_id' => $reservaId]);
        $prestaciones = $stmtPrest->fetchAll(PDO::FETCH_ASSOC);

        $secretoQr = hash('sha256', 'candelaria_qr_secret_org_' . $organizacionId);
        $payloadBoleto = [
            'organizacion_id' => $organizacionId,
            'reserva_id'      => $reservaId,
            'venta_id'        => (int) $reserva['venta_id'],
            'correlativo'     => $reserva['correlativo'],
            'titular'         => $reserva['contacto_nombre'],
            'edicion'         => $reserva['edicion_nombre'],
            'prestaciones'    => $prestaciones,
            'emitido_en'      => date('Y-m-d H:i:s'),
        ];

        $payloadJson = json_encode($payloadBoleto, JSON_UNESCAPED_UNICODE);
        $firma = hash_hmac('sha256', $payloadJson, $secretoQr);
        $tokenQr = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=') . '.' . $firma;

        return [
            'exito'        => true,
            'token_qr'     => $tokenQr,
            'correlativo'  => $reserva['correlativo'],
            'datos_boleto' => $payloadBoleto,
        ];
    }

    /**
     * Valida la firma criptográfica, la vigencia y el estado de ingreso de un boleto QR.
     * Previene reutilización, capturas de pantalla y accesos posteriores a cancelaciones o reembolsos.
     */
    public function validarTokenBoletoQr(int $organizacionId, string $tokenQr, bool $marcarUso = false): array
    {
        $partes = explode('.', $tokenQr);
        if (count($partes) !== 2) {
            return ['valido' => false, 'motivo' => 'Formato de token QR inválido.'];
        }

        [$payloadB64, $firmaRecibida] = $partes;
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
        if ($payloadJson === false) {
            return ['valido' => false, 'motivo' => 'Payload de boleto no decodificable.'];
        }

        $secretoQr = hash('sha256', 'candelaria_qr_secret_org_' . $organizacionId);
        $firmaCalculada = hash_hmac('sha256', $payloadJson, $secretoQr);

        if (!hash_equals($firmaCalculada, $firmaRecibida)) {
            return ['valido' => false, 'motivo' => 'Firma digital inválida o adulterada.'];
        }

        $datos = json_decode($payloadJson, true);
        if (!is_array($datos) || empty($datos['reserva_id'])) {
            return ['valido' => false, 'motivo' => 'Estructura interna del boleto inválida.'];
        }

        $reservaId = (int) $datos['reserva_id'];

        // CASO A: Check-in / Ingreso Operativo en Puerta ($marcarUso = true)
        // Requiere atomicidad estricta y bloqueo pesimista FOR UPDATE para impedir ingresos simultáneos
        if ($marcarUso) {
            $transaccionIniciada = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transaccionIniciada = true;
            }

            try {
                $stmt = $this->pdo->prepare("
                    SELECT r.id, r.edicion_id, r.estado as reserva_estado, r.notas_operativas,
                           v.estado as venta_estado, v.estado_financiero
                    FROM `reservas` r
                    JOIN `ventas` v ON r.venta_id = v.id
                    WHERE r.id = :r_id AND r.organizacion_id = :org_id
                    FOR UPDATE
                ");
                $stmt->execute(['r_id' => $reservaId, 'org_id' => $organizacionId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    if ($transaccionIniciada) { $this->pdo->rollBack(); }
                    return ['valido' => false, 'motivo' => 'La reserva asociada al boleto no existe en esta organización.'];
                }

                if ($row['reserva_estado'] !== EstadoReserva::CONFIRMADA->value) {
                    if ($transaccionIniciada) { $this->pdo->rollBack(); }
                    return ['valido' => false, 'motivo' => "El derecho de acceso está inactivo o cancelado (estado: {$row['reserva_estado']})."];
                }

                if ($row['venta_estado'] !== EstadoVenta::CONFIRMADA->value || !in_array($row['estado_financiero'], [EstadoFinancieroVenta::PAGADA_TOTAL->value, EstadoFinancieroVenta::SOBREPAGADA->value], true)) {
                    if ($transaccionIniciada) { $this->pdo->rollBack(); }
                    return ['valido' => false, 'motivo' => "El derecho de acceso ha sido revocado por falta de pago verificado."];
                }

                // Validar ciclo de vida para NUEVOS INGRESOS: Edición CERRADA no admite nuevos accesos en puerta
                $stmtEd = $this->pdo->prepare("SELECT estado FROM `ediciones_candelaria` WHERE id = :ed_id");
                $stmtEd->execute(['ed_id' => (int) $row['edicion_id']]);
                $edEstado = (string) $stmtEd->fetchColumn();
                if ($edEstado === 'CERRADA') {
                    if ($transaccionIniciada) { $this->pdo->rollBack(); }
                    return ['valido' => false, 'motivo' => 'El evento oficial ha concluido (Edición CERRADA). No se admiten nuevos ingresos en puerta.'];
                }

                // Check-in Atómico / Anti-Replay: verificar si ya fue canjeado
                $notasOp = (string) ($row['notas_operativas'] ?? '');
                if (str_contains($notasOp, '[CHECKIN_QR_REGISTRADO')) {
                    if ($transaccionIniciada) { $this->pdo->rollBack(); }
                    return [
                        'valido' => false,
                        'motivo' => 'Boleto QR duplicado: este acceso ya fue canjeado previamente.',
                        'datos'  => $datos,
                    ];
                }

                $timestampUso = date('Y-m-d H:i:s');
                $notaCheckin = " [CHECKIN_QR_REGISTRADO: {$timestampUso}]";
                $this->pdo->prepare("
                    UPDATE `reservas`
                    SET `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), :nota)
                    WHERE `id` = :id
                ")->execute(['nota' => $notaCheckin, 'id' => (int) $row['id']]);

                if ($transaccionIniciada) {
                    $this->pdo->commit();
                }

                return [
                    'valido' => true,
                    'motivo' => 'Ingreso registrado exitosamente en puerta (Check-in concedido).',
                    'datos'  => array_merge($datos, ['checkin_en' => $timestampUso]),
                ];
            } catch (\Throwable $e) {
                if ($transaccionIniciada && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }

        // CASO B: Consulta Histórica / Visualización de Boleto ($marcarUso = false)
        // El cliente y el sistema pueden auditar boletos históricos legítimos aun cuando la edición esté CERRADA
        // o el boleto ya haya sido canjeado previamente.
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.edicion_id, r.estado as reserva_estado, r.notas_operativas,
                   v.estado as venta_estado, v.estado_financiero
            FROM `reservas` r
            JOIN `ventas` v ON r.venta_id = v.id
            WHERE r.id = :r_id AND r.organizacion_id = :org_id
        ");
        $stmt->execute(['r_id' => $reservaId, 'org_id' => $organizacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['valido' => false, 'motivo' => 'La reserva asociada al boleto no existe en esta organización.'];
        }

        if ($row['reserva_estado'] !== EstadoReserva::CONFIRMADA->value) {
            return ['valido' => false, 'motivo' => "El derecho de acceso está inactivo o cancelado (estado: {$row['reserva_estado']})."];
        }

        if ($row['venta_estado'] !== EstadoVenta::CONFIRMADA->value || !in_array($row['estado_financiero'], [EstadoFinancieroVenta::PAGADA_TOTAL->value, EstadoFinancieroVenta::SOBREPAGADA->value], true)) {
            return ['valido' => false, 'motivo' => "El derecho de acceso no cuenta con pago confirmado."];
        }

        $notasOp = (string) ($row['notas_operativas'] ?? '');
        $yaIngresado = str_contains($notasOp, '[CHECKIN_QR_REGISTRADO');

        return [
            'valido'       => true,
            'motivo'       => $yaIngresado ? 'Boleto QR auténtico (Canjeado previamente).' : 'Boleto QR legítimo y vigente.',
            'datos'        => array_merge($datos, ['ya_ingresado' => $yaIngresado]),
            'ya_ingresado' => $yaIngresado,
        ];
    }

    /**
     * Expira vouchers en revisión tras cumplirse el límite de horas (default 24h),
     * cancelando la reserva y la venta para liberar los cupos de manera determinista sin borrar historial.
     */
    public function expirarVouchersVencidos(int $organizacionId, int $horasLimite = 24): int
    {
        $stmtVencidos = $this->pdo->prepare("
            SELECT p.id as pago_id, p.venta_id, v.cliente_id, r.id as reserva_id, r.correlativo as reserva_correlativo
            FROM `pagos` p
            JOIN `ventas` v ON p.venta_id = v.id
            LEFT JOIN `reservas` r ON r.venta_id = v.id
            WHERE p.organizacion_id = :org_id
              AND p.metodo_pago = 'TRANSFERENCIA_BANCARIA'
              AND p.estado = 'PENDIENTE_VERIFICACION'
              AND p.fecha_pago <= (NOW() - INTERVAL :horas HOUR)
        ");
        $stmtVencidos->bindValue(':org_id', $organizacionId, PDO::PARAM_INT);
        $stmtVencidos->bindValue(':horas', $horasLimite, PDO::PARAM_INT);
        $stmtVencidos->execute();
        $vencidos = $stmtVencidos->fetchAll(PDO::FETCH_ASSOC);

        $expirados = 0;
        foreach ($vencidos as $v) {
            $pagoId = (int) $v['pago_id'];
            $ventaId = (int) $v['venta_id'];
            $reservaId = !empty($v['reserva_id']) ? (int) $v['reserva_id'] : null;

            // 1. Pago -> RECHAZADO con nota de expiración
            $this->pdo->prepare("
                UPDATE `pagos`
                SET `estado` = 'RECHAZADO',
                    `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), ' [EXPIRADO POR PLAZO VENCIDO]'),
                    `verificado_en` = NOW()
                WHERE `id` = :id
            ")->execute(['id' => $pagoId]);

            // 2. Venta -> CANCELADA
            $this->pdo->prepare("
                UPDATE `ventas`
                SET `estado` = 'CANCELADA',
                    `motivo_cancelacion` = 'INCUMPLIMIENTO_ORGANIZACION',
                    `motivo_cancelacion_detalle` = 'Plazo de 24h expirado sin verificación de comprobante'
                WHERE `id` = :id
            ")->execute(['id' => $ventaId]);

            // 3. Reserva -> CANCELADA (libera cupos inmediatamente)
            if ($reservaId !== null) {
                $this->pdo->prepare("
                    UPDATE `reservas`
                    SET `estado` = 'CANCELADA',
                        `notas_operativas` = CONCAT(COALESCE(`notas_operativas`, ''), ' [CANCELADA: Plazo de pago expirado]')
                    WHERE `id` = :id
                ")->execute(['id' => $reservaId]);
            }

            // 4. Encolar notificación en Outbox
            $this->encolarNotificacionOutbox(
                $organizacionId,
                (int) $v['cliente_id'],
                'voucher_expirado_plazo_vencido',
                [
                    'correlativo_reserva' => $v['reserva_correlativo'] ?? ('VTA-' . $ventaId),
                    'motivo'              => "El plazo de {$horasLimite} horas para la validación de su pago ha expirado.",
                ],
                'VOUCHER_EXPIRADO'
            );

            $expirados++;
        }

        return $expirados;
    }

    /**
     * Retorna el histórico de reservas del cliente autenticado (Anti-IDOR).
     */
    public function obtenerMisReservas(int $organizacionId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.correlativo, r.estado, r.creado_en,
                   e.nombre as edicion_nombre, v.total, v.moneda, v.estado_financiero
            FROM `reservas` r
            JOIN `ediciones_candelaria` e ON r.edicion_id = e.id
            JOIN `ventas` v ON r.venta_id = v.id
            WHERE r.organizacion_id = :org_id
              AND r.cliente_id = :cliente_id
            ORDER BY r.id DESC
        ");
        $stmt->execute(['org_id' => $organizacionId, 'cliente_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Encola de forma segura un mensaje en el Outbox tras validar consentimientos.
     */
    private function encolarNotificacionOutbox(
        int $organizacionId,
        int $clienteId,
        string $nombrePlantilla,
        array $parametros,
        string $eventoOrigen
    ): void {
        try {
            $stmtCliente = $this->pdo->prepare("
                SELECT p.telefono_whatsapp, p.nombres, p.apellidos
                FROM `clientes` c
                JOIN `personas` p ON c.persona_id = p.id
                WHERE c.id = :id AND c.organizacion_id = :org_id
            ");
            $stmtCliente->execute(['id' => $clienteId, 'org_id' => $organizacionId]);
            $cli = $stmtCliente->fetch(PDO::FETCH_ASSOC);

            if (!$cli || empty($cli['telefono_whatsapp'])) {
                return;
            }

            $telefono = (string) $cli['telefono_whatsapp'];
            $nombre = trim(($cli['nombres'] ?? '') . ' ' . ($cli['apellidos'] ?? ''));

            // Encolar mensaje en comunicacion_mensajes
            $stmtMsg = $this->pdo->prepare("
                INSERT INTO `comunicacion_mensajes` (
                    `organizacion_id`, `cliente_id`, `canal`, `tipo_mensaje`, `direccion`,
                    `destinatario_telefono`, `destinatario_nombre`,
                    `contenido_texto`, `parametros_enviados_json`,
                    `estado`, `peso_estado`, `idempotency_key`, `correlacion_id`,
                    `costo_estimado_usd`, `creado_en`
                ) VALUES (
                    :org_id, :cliente_id, 'WHATSAPP', 'TRANSACCIONAL', 'SALIENTE',
                    :telefono, :nombre,
                    :cuerpo, :params_json,
                    'ENCOLADO', 10, :idempotency_key, :correlacion,
                    0.005, NOW()
                )
            ");
            $stmtMsg->execute([
                'org_id'          => $organizacionId,
                'cliente_id'      => $clienteId,
                'telefono'        => $telefono,
                'nombre'          => $nombre !== '' ? $nombre : 'Cliente',
                'cuerpo'          => "Plantilla: {$nombrePlantilla} [Evento: {$eventoOrigen}] | " . json_encode($parametros, JSON_UNESCAPED_UNICODE),
                'params_json'     => json_encode($parametros, JSON_UNESCAPED_UNICODE),
                'idempotency_key' => 'out_' . bin2hex(random_bytes(16)),
                'correlacion'     => bin2hex(random_bytes(16)),
            ]);
        } catch (\Throwable) {
            // Silenciar error en outbox para no interrumpir la transacción principal
        }
    }

    /**
     * Resuelve un usuario activo del sistema/tenant para asociar formalmente pagos desatendidos.
     */
    private function resolverUsuarioSistemaId(int $organizacionId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM `usuarios` 
            WHERE `organizacion_id` = :org_id AND `estado` = 'ACTIVO' 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute(['org_id' => $organizacionId]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            return (int) $uid;
        }

        $fallback = $this->pdo->query("SELECT id FROM `usuarios` WHERE `estado` = 'ACTIVO' ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $fallback ? (int) $fallback : 24;
    }
}

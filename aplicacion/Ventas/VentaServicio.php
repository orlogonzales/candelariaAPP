<?php

declare(strict_types=1);

namespace Aplicacion\Ventas;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Entidades\Venta;
use Aplicacion\Entidades\VentaLinea;
use Aplicacion\Entidades\VentaLineaComponente;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PersonaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use BadMethodCallException;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Servicio Central de Dominio de Ventas Comerciales (F2.5B).
 * Implementa la conversión formal de cotizaciones aceptadas en ventas confirmadas,
 * snapshot inmutable completo, inmutabilidad contractual post-confirmación,
 * validación fail-closed de moneda institucional y transición atómica de CRM a GANADA.
 */
class VentaServicio
{
    private PDO $pdo;

    public function __construct(
        private VentaRepositorio $ventaRepo,
        private CotizacionRepositorio $cotizacionRepo,
        private ClienteRepositorio $clienteRepo,
        private PersonaRepositorio $personaRepo,
        private EdicionRepositorio $edicionRepo,
        private ConfiguracionServicio $configServicio,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        private ?OportunidadServicio $oportunidadServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Convierte formalmente una cotización ACEPTADA en una Venta CONFIRMADA.
     * Operación transaccional atómica con snapshot inmutable y avance de CRM a GANADA.
     */
    public function crearDesdeCotizacion(
        int $organizacionId,
        int $cotizacionId,
        ?ContextoOperacion $contexto = null
    ): Venta {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.crear_desde_cotizacion', $contexto);

        // 1. Validación de Moneda Institucional (FAIL-CLOSED estricto, cero fallback)
        try {
            $monedaInst = $this->configServicio->obtenerPlataforma('plataforma.moneda_principal');
        } catch (\Throwable $e) {
            throw new RuntimeException("Configuración institucional de moneda principal inaccesible o inválida. Operación bloqueada (Fail-Closed).", 0, $e);
        }
        if ($monedaInst === null || !is_string($monedaInst) || trim($monedaInst) === '') {
            throw new RuntimeException("Configuración institucional de moneda principal ausente. Operación bloqueada (Fail-Closed).");
        }
        $monedaInst = trim($monedaInst);
        if (strlen($monedaInst) !== 3 || !ctype_upper($monedaInst)) {
            throw new RuntimeException("Configuración institucional de moneda principal inválida ('{$monedaInst}'). Operación bloqueada (Fail-Closed).");
        }

        // 2. Inicio de Transacción Atómica con soporte de Savepoint anidado
        $transaccionIniciada = false;
        $savepoint = null;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionIniciada = true;
        } else {
            $savepoint = 'sp_vta_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT {$savepoint}");
        }

        try {
            // 3. Bloqueo pesimista de fila FOR UPDATE sobre la cotización origen
            $stmtCot = $this->pdo->prepare("
                SELECT * FROM `cotizaciones`
                WHERE `id` = :id AND `organizacion_id` = :org_id
                FOR UPDATE
            ");
            $stmtCot->execute(['id' => $cotizacionId, 'org_id' => $organizacionId]);
            $filaCot = $stmtCot->fetch(PDO::FETCH_ASSOC);

            if (!$filaCot) {
                throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
            }

            // 4. Protección contra doble conversión (Cardinalidad 1 a 1 estricta)
            if ($this->ventaRepo->existePorCotizacionId($cotizacionId, $organizacionId)) {
                throw new RuntimeException("La cotización #{$cotizacionId} ya fue convertida previamente en una venta formal.");
            }

            // 5. Validación de Estado: Solo cotizaciones ACEPTADAS
            $estadoCot = EstadoCotizacion::from($filaCot['estado']);
            if ($estadoCot !== EstadoCotizacion::ACEPTADA) {
                throw new InvalidArgumentException("Solo se pueden convertir en venta cotizaciones en estado ACEPTADA (estado actual: {$estadoCot->value}).");
            }

            // 6. Validación de vigencia efectiva (fecha límite de validez)
            $hoy = date('Y-m-d');
            if (!empty($filaCot['valido_hasta']) && $filaCot['valido_hasta'] < $hoy) {
                throw new InvalidArgumentException("La cotización #{$cotizacionId} ha expirado su fecha límite de validez ({$filaCot['valido_hasta']}). Requiere nueva revisión.");
            }

            // 7. Validación de paridad de moneda (Fail-Closed)
            if ($filaCot['moneda'] !== $monedaInst) {
                throw new RuntimeException("La moneda de la cotización ({$filaCot['moneda']}) no coincide con la moneda institucional ({$monedaInst}). Operación bloqueada (Fail-Closed).");
            }

            // 8. Validación de Edición
            $edicionId = (int) $filaCot['edicion_id'];
            $edicion = $this->edicionRepo->buscarPorId($edicionId);
            if ($edicion === null || (int) $edicion->organizacionId !== $organizacionId) {
                throw new InvalidArgumentException("La edición vinculada a la cotización no existe o pertenece a otra organización.");
            }

            // 9. Validación de Cliente y Snapshot de Identidad
            $clienteId = (int) $filaCot['cliente_id'];
            $cliente = $this->clienteRepo->buscarPorId($clienteId);
            if ($cliente === null || (int) $cliente->organizacionId !== $organizacionId) {
                throw new InvalidArgumentException("El cliente vinculado a la cotización no existe o pertenece a otra organización.");
            }

            $persona = $this->personaRepo->buscarPorId((int) $cliente->personaId);
            if ($persona === null) {
                throw new InvalidArgumentException("No se encontró el registro de persona asociado al cliente #{$clienteId}.");
            }

            $nombreCompleto = trim($persona->nombres . ' ' . $persona->apellidos) ?: 'Cliente #' . $clienteId;
            $tipoDocNombre = $persona->tipoDocumentoId !== null ? (string) $persona->tipoDocumentoId : null;

            // 10. Generación Concurrente y Monotónica de Correlativo formal (VTA-YYYY-NNNNNN)
            $anio = (int) date('Y');
            $correlativo = $this->ventaRepo->generarSiguienteCorrelativo($organizacionId, $anio);

            // 11. Creación de Cabecera Venta (Directamente en CONFIRMADA con snapshot inmutable)
            $venta = new Venta(
                id: null,
                organizacionId: $organizacionId,
                edicionId: $edicionId,
                clienteId: $clienteId,
                cotizacionId: $cotizacionId,
                origenTipo: TipoOrigenVenta::COTIZACION,
                correlativo: $correlativo,
                fechaVenta: $hoy,
                estado: EstadoVenta::CONFIRMADA,
                clienteNombreCompleto: $nombreCompleto,
                clienteTipoDocumento: $tipoDocNombre,
                clienteNumeroDocumento: $persona->numeroDocumento,
                clienteTelefono: $persona->telefonoWhatsapp ?? $persona->telefonoMovil,
                clienteEmail: $persona->correoElectronico,
                moneda: $filaCot['moneda'],
                subtotal: (float) $filaCot['subtotal'],
                descuentoGlobalTipo: TipoDescuentoVenta::from($filaCot['descuento_global_tipo']),
                descuentoGlobalValor: (float) $filaCot['descuento_global_valor'],
                descuentoGlobalMonto: (float) $filaCot['descuento_global_monto'],
                descuentoGlobalMotivo: $filaCot['descuento_global_motivo'],
                descuentoLineasTotal: (float) $filaCot['descuento_lineas_total'],
                total: (float) $filaCot['total'],
                terminosCondiciones: $filaCot['terminos_condiciones'],
                notasComerciales: $filaCot['notas_internas'],
                motivoCancelacion: null,
                motivoCancelacionDetalle: null,
                motivoAnulacion: null,
                motivoAnulacionDetalle: null,
                versionBloqueo: 1,
                creadoPor: $contexto->usuarioId ?? 1,
                creadoEn: date('Y-m-d H:i:s'),
                actualizadoEn: date('Y-m-d H:i:s'),
                lineas: []
            );

            $ventaGuardada = $this->ventaRepo->guardar($venta);

            // 12. Snapshot relacional inmutable de Líneas y Componentes
            $lineasCot = $this->cotizacionRepo->obtenerLineas($cotizacionId);
            $lineasVentaGuardadas = [];

            foreach ($lineasCot as $lc) {
                $compVenta = [];
                foreach ($lc->componentes as $cc) {
                    $compVenta[] = new VentaLineaComponente(
                        id: null,
                        ventaLineaId: 0,
                        itemComercialId: $cc->itemComercialId,
                        itemCodigo: $cc->itemCodigo,
                        itemNombre: $cc->itemNombre,
                        itemTipo: $cc->itemTipo,
                        unidadMedida: $cc->unidadMedida,
                        cantidad: $cc->cantidad,
                        nota: $cc->nota,
                        orden: $cc->orden,
                        creadoEn: date('Y-m-d H:i:s')
                    );
                }

                $lv = new VentaLinea(
                    id: null,
                    ventaId: (int) $ventaGuardada->id,
                    cotizacionLineaId: (int) $lc->id,
                    tipoLinea: TipoLineaVenta::from($lc->tipoLinea->value),
                    itemComercialId: $lc->itemComercialId,
                    paqueteId: $lc->paqueteId,
                    ofertaItemId: $lc->ofertaItemId,
                    ofertaPaqueteId: $lc->ofertaPaqueteId,
                    conceptoCodigo: $lc->conceptoCodigo,
                    conceptoNombre: $lc->conceptoNombre,
                    conceptoDescripcion: $lc->conceptoDescripcion,
                    unidadMedida: $lc->unidadMedida,
                    cantidad: $lc->cantidad,
                    precioUnitario: $lc->precioUnitario,
                    descuentoTipo: TipoDescuentoVenta::from($lc->descuentoTipo->value),
                    descuentoValor: $lc->descuentoValor,
                    descuentoMonto: $lc->descuentoMonto,
                    descuentoMotivo: $lc->descuentoMotivo,
                    subtotal: $lc->subtotal,
                    moneda: $lc->moneda,
                    orden: $lc->orden,
                    notas: $lc->notas,
                    creadoEn: date('Y-m-d H:i:s'),
                    componentes: $compVenta
                );

                $lineasVentaGuardadas[] = $this->ventaRepo->guardarLinea($lv);
            }

            // 13. Transición CRM Atómica: Si la cotización tiene oportunidad_id -> GANADA
            if (!empty($filaCot['oportunidad_id']) && $this->oportunidadServicio !== null) {
                $opId = (int) $filaCot['oportunidad_id'];
                $this->oportunidadServicio->cambiarEtapa(
                    organizacionId: $organizacionId,
                    oportunidadId: $opId,
                    nuevaEtapaStr: 'GANADA',
                    motivoPerdidaStr: null,
                    motivoPerdidaDetalle: null,
                    motivoCambio: 'Conversión comercial formal a Venta ' . $correlativo,
                    versionBloqueoEsperada: null,
                    contexto: $contexto
                );
            }

            // 14. Registro de Auditoría Inmutable
            $this->registrarAuditoria($contexto, 'VENTA_CREADA_DESDE_COTIZACION', 'ventas', (int) $ventaGuardada->id, [
                'correlativo'    => $correlativo,
                'cotizacion_id'  => $cotizacionId,
                'total'          => $ventaGuardada->total,
                'moneda'         => $ventaGuardada->moneda,
                'lineas_count'   => count($lineasVentaGuardadas),
                'oportunidad_id' => $filaCot['oportunidad_id'] ?? null,
            ]);

            // 15. Commit de la Transacción o liberación de Savepoint
            if ($transaccionIniciada) {
                $this->pdo->commit();
            } elseif ($savepoint !== null) {
                $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }

            return $this->ventaRepo->buscarPorId((int) $ventaGuardada->id, $organizacionId);
        } catch (Throwable $e) {
            if ($transaccionIniciada && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            } elseif ($savepoint !== null && $this->pdo->inTransaction()) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            throw $e;
        }
    }

    /**
     * Registra la cancelación comercial de una venta con motivo obligatorio y bloqueo optimista.
     */
    public function cancelar(
        int $organizacionId,
        int $ventaId,
        MotivoCancelacionVenta $motivo,
        ?string $motivoDetalle,
        int $versionBloqueoEsperada,
        ?ContextoOperacion $contexto = null
    ): Venta {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.cancelar', $contexto);

        if ($motivo->requiereDetalle() && ($motivoDetalle === null || trim($motivoDetalle) === '')) {
            throw new InvalidArgumentException("El motivo de cancelación 'OTRO' exige obligatoriamente un detalle explicativo.");
        }

        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe o no pertenece a su organización.");
        }

        if ($venta->estado !== EstadoVenta::CONFIRMADA) {
            throw new InvalidArgumentException("Solo se pueden cancelar ventas en estado CONFIRMADA (estado actual: {$venta->estado->value}).");
        }

        if ($venta->versionBloqueo !== $versionBloqueoEsperada) {
            throw new ConflictoConcurrenciaExcepcion("La venta fue modificada concurrentemente por otro usuario.");
        }

        $ventaCancelada = new Venta(
            id: $venta->id,
            organizacionId: $venta->organizacionId,
            edicionId: $venta->edicionId,
            clienteId: $venta->clienteId,
            cotizacionId: $venta->cotizacionId,
            origenTipo: $venta->origenTipo,
            correlativo: $venta->correlativo,
            fechaVenta: $venta->fechaVenta,
            estado: EstadoVenta::CANCELADA,
            clienteNombreCompleto: $venta->clienteNombreCompleto,
            clienteTipoDocumento: $venta->clienteTipoDocumento,
            clienteNumeroDocumento: $venta->clienteNumeroDocumento,
            clienteTelefono: $venta->clienteTelefono,
            clienteEmail: $venta->clienteEmail,
            moneda: $venta->moneda,
            subtotal: $venta->subtotal,
            descuentoGlobalTipo: $venta->descuentoGlobalTipo,
            descuentoGlobalValor: $venta->descuentoGlobalValor,
            descuentoGlobalMonto: $venta->descuentoGlobalMonto,
            descuentoGlobalMotivo: $venta->descuentoGlobalMotivo,
            descuentoLineasTotal: $venta->descuentoLineasTotal,
            total: $venta->total,
            terminosCondiciones: $venta->terminosCondiciones,
            notasComerciales: $venta->notasComerciales,
            motivoCancelacion: $motivo,
            motivoCancelacionDetalle: $motivoDetalle !== null ? trim($motivoDetalle) : null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: $venta->versionBloqueo,
            creadoPor: $venta->creadoPor,
            creadoEn: $venta->creadoEn,
            actualizadoEn: date('Y-m-d H:i:s'),
            lineas: $venta->lineas
        );

        $ok = $this->ventaRepo->actualizar($ventaCancelada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La venta fue modificada concurrentemente por otro usuario.");
        }

        $this->registrarAuditoria($contexto, 'VENTA_CANCELADA', 'ventas', $ventaId, [
            'correlativo' => $venta->correlativo,
            'motivo'      => $motivo->value,
            'detalle'     => $motivoDetalle,
        ]);

        return $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
    }

    /**
     * Registra la anulación administrativa de una venta con motivo obligatorio y bloqueo optimista.
     */
    public function anular(
        int $organizacionId,
        int $ventaId,
        MotivoAnulacionVenta $motivo,
        ?string $motivoDetalle,
        int $versionBloqueoEsperada,
        ?ContextoOperacion $contexto = null
    ): Venta {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.anular', $contexto);

        if ($motivo->requiereDetalle() && ($motivoDetalle === null || trim($motivoDetalle) === '')) {
            throw new InvalidArgumentException("El motivo de anulación 'OTRO' exige obligatoriamente un detalle explicativo.");
        }

        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe o no pertenece a su organización.");
        }

        if ($venta->estado !== EstadoVenta::CONFIRMADA) {
            throw new InvalidArgumentException("Solo se pueden anular ventas en estado CONFIRMADA (estado actual: {$venta->estado->value}).");
        }

        if ($venta->versionBloqueo !== $versionBloqueoEsperada) {
            throw new ConflictoConcurrenciaExcepcion("La venta fue modificada concurrentemente por otro usuario.");
        }

        $ventaAnulada = new Venta(
            id: $venta->id,
            organizacionId: $venta->organizacionId,
            edicionId: $venta->edicionId,
            clienteId: $venta->clienteId,
            cotizacionId: $venta->cotizacionId,
            origenTipo: $venta->origenTipo,
            correlativo: $venta->correlativo,
            fechaVenta: $venta->fechaVenta,
            estado: EstadoVenta::ANULADA,
            clienteNombreCompleto: $venta->clienteNombreCompleto,
            clienteTipoDocumento: $venta->clienteTipoDocumento,
            clienteNumeroDocumento: $venta->clienteNumeroDocumento,
            clienteTelefono: $venta->clienteTelefono,
            clienteEmail: $venta->clienteEmail,
            moneda: $venta->moneda,
            subtotal: $venta->subtotal,
            descuentoGlobalTipo: $venta->descuentoGlobalTipo,
            descuentoGlobalValor: $venta->descuentoGlobalValor,
            descuentoGlobalMonto: $venta->descuentoGlobalMonto,
            descuentoGlobalMotivo: $venta->descuentoGlobalMotivo,
            descuentoLineasTotal: $venta->descuentoLineasTotal,
            total: $venta->total,
            terminosCondiciones: $venta->terminosCondiciones,
            notasComerciales: $venta->notasComerciales,
            motivoCancelacion: null,
            motivoCancelacionDetalle: null,
            motivoAnulacion: $motivo,
            motivoAnulacionDetalle: $motivoDetalle !== null ? trim($motivoDetalle) : null,
            versionBloqueo: $venta->versionBloqueo,
            creadoPor: $venta->creadoPor,
            creadoEn: $venta->creadoEn,
            actualizadoEn: date('Y-m-d H:i:s'),
            lineas: $venta->lineas
        );

        $ok = $this->ventaRepo->actualizar($ventaAnulada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La venta fue modificada concurrentemente por otro usuario.");
        }

        $this->registrarAuditoria($contexto, 'VENTA_ANULADA', 'ventas', $ventaId, [
            'correlativo' => $venta->correlativo,
            'motivo'      => $motivo->value,
            'detalle'     => $motivoDetalle,
        ]);

        return $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
    }

    /**
     * Consulta una venta por ID con validación de permisos y alcance de tenant.
     */
    public function obtenerPorId(int $organizacionId, int $ventaId, ?ContextoOperacion $contexto = null): ?Venta
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.ver', $contexto);

        return $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
    }

    /**
     * Consulta una venta por ID de cotización con validación de permisos y alcance de tenant.
     */
    public function obtenerPorCotizacionId(int $organizacionId, int $cotizacionId, ?ContextoOperacion $contexto = null): ?Venta
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.ver', $contexto);

        return $this->ventaRepo->buscarPorCotizacionId($cotizacionId, $organizacionId);
    }

    /**
     * Transición a LIQUIDADA bloqueada funcionalmente en F2.5B.
     */
    public function liquidar(int $organizacionId, int $ventaId, ?ContextoOperacion $contexto = null): never
    {
        throw new BadMethodCallException("La transición a LIQUIDADA no está habilitada en F2.5B; queda reservada para fases operativas de Reservas.");
    }

    /**
     * Creación de Venta Directa bloqueada funcionalmente en F2.5B.
     */
    public function crearVentaDirecta(): never
    {
        throw new BadMethodCallException("La creación de venta directa no está autorizada ni implementada en F2.5B.");
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        return $contexto ?? ContextoOperacion::actual() ?? ContextoOperacion::paraHumano(
            usuarioId: 1,
            canalId: 1,
            canalCodigo: 'SISTEMA',
            origenIp: '127.0.0.1',
            agenteUsuario: 'VentaServicio/v1',
            organizacionId: 10000
        );
    }

    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId !== null && (int) $contexto->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion("Acceso denegado: El recurso pertenece a otra organización.");
        }
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId === null) {
            return;
        }

        if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
            throw new AccesoDenegadoExcepcion("Acceso denegado: Se requiere el permiso '{$permiso}'.");
        }
    }

    private function registrarAuditoria(
        ContextoOperacion $contexto,
        string $evento,
        string $entidad,
        int $entidadId,
        array $detalles
    ): void {
        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'ventas',
            accion: $evento,
            entidadTipo: $entidad,
            entidadId: (string) $entidadId,
            datosPrevios: null,
            datosNuevos: $detalles
        );
    }
}

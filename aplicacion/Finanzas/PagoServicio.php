<?php

declare(strict_types=1);

namespace Aplicacion\Finanzas;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Entidades\Pago;
use Aplicacion\Entidades\PagoReembolso;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ConfiguracionRepositorio;
use Aplicacion\Repositorios\CuentaBancariaRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\PagoReembolsoRepositorio;
use Aplicacion\Repositorios\PagoRepositorio;
use Aplicacion\Repositorios\PasarelaRepositorio;
use Aplicacion\Repositorios\VentaRepositorio;
use Aplicacion\Ventas\EstadoVenta;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;

class PagoServicio
{
    private PDO $pdo;

    public function __construct(
        private PagoRepositorio $pagoRepo,
        private PagoReembolsoRepositorio $reembolsoRepo,
        private CuentaBancariaRepositorio $cuentaRepo,
        private PasarelaRepositorio $pasarelaRepo,
        private VentaRepositorio $ventaRepo,
        private EdicionRepositorio $edicionRepo,
        private ConfiguracionRepositorio $configRepo,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Registra un cobro manual (efectivo, transferencia bancaria, billetera móvil o POS físico).
     */
    public function registrarPagoManual(
        int $organizacionId,
        int $edicionId,
        int $ventaId,
        array $datos,
        ?ContextoOperacion $contexto = null
    ): Pago {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('pagos.registrar_manual', $contexto);

        // 1. Idempotencia: si ya existe pago con esta clave para el tenant, devolverlo sin duplicar
        $claveIdempotencia = !empty($datos['clave_idempotencia']) ? trim((string) $datos['clave_idempotencia']) : null;
        if ($claveIdempotencia !== null) {
            $pagoExistente = $this->pagoRepo->buscarPorIdempotencia($organizacionId, $claveIdempotencia);
            if ($pagoExistente !== null) {
                return $pagoExistente;
            }
        }

        // 2. Validar venta y coherencia multitenant
        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null || $venta->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe en su organización.");
        }
        if ($venta->edicionId !== $edicionId) {
            throw new InvalidArgumentException("Inconsistencia: La venta pertenece a una edición distinta a la activa.");
        }
        if ($venta->estado === EstadoVenta::CANCELADA || $venta->estado === EstadoVenta::ANULADA) {
            throw new InvalidArgumentException("No se pueden registrar pagos en una venta en estado {$venta->estado->value}.");
        }
        if ($venta->estado === EstadoVenta::LIQUIDADA) {
            throw new InvalidArgumentException("La venta ya se encuentra formalmente LIQUIDADA. No admite nuevos pagos.");
        }

        // 3. Validar método de pago
        $metodoRaw = strtoupper((string) ($datos['metodo_pago'] ?? ''));
        $metodo = MetodoPago::tryFrom($metodoRaw);
        if ($metodo === null) {
            throw new InvalidArgumentException("Método de pago inválido o no reconocido: '{$metodoRaw}'.");
        }

        // 4. Validar monto numérico y mínimo global
        $monto = (float) ($datos['monto'] ?? $datos['monto_cobrado_cliente'] ?? 0.0);
        if ($monto <= 0.0) {
            throw new InvalidArgumentException('El monto del pago debe ser estrictamente mayor a 0.00.');
        }

        // Parámetro monto mínimo global
        $paramMin = $this->configRepo->buscarParametro('plataforma.monto_minimo_pago_pe');
        $minimoGlobal = $paramMin ? (float) $paramMin->valor : 50.00;

        // Si el saldo pendiente es menor que el mínimo, se permite liquidar el remanente exacto
        $saldoActual = (float) ($venta->saldoPendiente ?? $venta->total);
        if ($monto < $minimoGlobal && $monto < $saldoActual) {
            throw new InvalidArgumentException(
                sprintf('El monto mínimo permitido por operación es S/ %.2f (o el saldo total remanente de S/ %.2f).', $minimoGlobal, $saldoActual)
            );
        }

        // 5. Validar cuenta bancaria si aplica
        $cuentaId = !empty($datos['cuenta_bancaria_id']) ? (int) $datos['cuenta_bancaria_id'] : null;
        if ($cuentaId !== null) {
            $cuenta = $this->cuentaRepo->buscarPorId($cuentaId, $organizacionId);
            if ($cuenta === null || !$cuenta->activo) {
                throw new InvalidArgumentException("La cuenta bancaria seleccionada (#{$cuentaId}) no existe o está inactiva en su organización.");
            }
        }

        // 6. Cálculo financiero de amortización y sobrepago (BCMath)
        $montoAplicado = min($monto, $saldoActual);
        $montoExcedente = max(0.0, round($monto - $saldoActual, 2));

        // 7. Determinar estado inicial del pago
        // Efectivo y POS físico se aprueban de inmediato. Transferencias y billeteras quedan pendientes de verificación
        $inmediato = ($metodo === MetodoPago::EFECTIVO || $metodo === MetodoPago::POS_FISICO || !empty($datos['aprobacion_inmediata']));
        $estadoPago = $inmediato ? EstadoPago::APROBADO : EstadoPago::PENDIENTE_VERIFICACION;

        $fechaPago = !empty($datos['fecha_pago']) ? (string) $datos['fecha_pago'] : date('Y-m-d H:i:s');
        $anio = (int) date('Y', strtotime($fechaPago));
        $correlativo = $this->pagoRepo->generarSiguienteCorrelativo($organizacionId, $anio);

        $usuarioId = $contexto->usuarioId ?? throw new InvalidArgumentException('Se requiere un usuario operador autenticado.');

        $pago = new Pago(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            ventaId: $ventaId,
            correlativo: $correlativo,
            metodoPago: $metodo,
            estado: $estadoPago,
            moneda: 'PEN',
            montoCobradoCliente: $monto,
            comisionPorcentajeAplicada: 0.00,
            comisionFijaAplicada: 0.00,
            comisionPasarela: 0.00,
            montoNetoRecibido: $monto,
            montoAplicadoVenta: $montoAplicado,
            montoExcedente: $montoExcedente,
            comisionAsumidaPor: AsumeComisionPasarela::ORGANIZACION,
            montoReembolsadoAcumulado: 0.00,
            fechaPago: $fechaPago,
            cuentaBancariaId: $cuentaId,
            organizacionPasarelaId: null,
            numeroOperacionBancaria: !empty($datos['numero_operacion_bancaria']) ? trim((string) $datos['numero_operacion_bancaria']) : null,
            boucherComprobanteUrl: !empty($datos['boucher_comprobante_url']) ? trim((string) $datos['boucher_comprobante_url']) : null,
            notasOperativas: !empty($datos['notas_operativas']) ? trim((string) $datos['notas_operativas']) : null,
            claveIdempotencia: $claveIdempotencia,
            versionBloqueo: 1,
            verificadoPor: $inmediato ? $usuarioId : null,
            verificadoEn: $inmediato ? date('Y-m-d H:i:s') : null,
            creadoPor: $usuarioId
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $nuevoId = $this->pagoRepo->registrarPago($pago);

            // Si el pago quedó aprobado de inmediato, amortizar la venta y recalcular proyección
            if ($estadoPago === EstadoPago::APROBADO) {
                $this->recalcularProyeccionVenta($ventaId, $organizacionId);
            }

            // Auditoría inmutable sin secretos
            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'pagos_caja',
                accion: 'REGISTRAR_PAGO_MANUAL',
                entidadTipo: 'PAGO',
                entidadId: (string) $nuevoId,
                datosPrevios: null,
                datosNuevos: [
                    'correlativo'            => $correlativo,
                    'venta_id'               => $ventaId,
                    'monto'                  => $monto,
                    'monto_aplicado'         => $montoAplicado,
                    'monto_excedente'        => $montoExcedente,
                    'metodo'                 => $metodo->value,
                    'estado'                 => $estadoPago->value,
                    'cuenta_id'              => $cuentaId,
                    'num_operacion'          => $pago->numeroOperacionBancaria,
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->pagoRepo->buscarPorId($nuevoId, $organizacionId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra un pago confirmado proveniente de una pasarela web (vía webhook o checkout).
     *
     * @param int $organizacionId
     * @param int $ventaId
     * @param int $organizacionPasarelaId
     * @param float $monto
     * @param float $comisionPasarela
     * @param string $transaccionExternaId
     * @param string $claveIdempotencia
     * @param array $datosMetadatos
     * @param ContextoOperacion|null $contexto
     * @return Pago
     */
    public function registrarPagoPasarelaWeb(
        int $organizacionId,
        int $ventaId,
        int $organizacionPasarelaId,
        float $monto,
        float $comisionPasarela,
        string $transaccionExternaId,
        string $claveIdempotencia,
        array $datosMetadatos = [],
        ?ContextoOperacion $contexto = null
    ): Pago {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        // 1. Idempotencia: si ya existe con esta clave, retornar el pago existente
        $pagoExistente = $this->pagoRepo->buscarPorIdempotencia($organizacionId, $claveIdempotencia);
        if ($pagoExistente !== null) {
            return $pagoExistente;
        }

        // 2. Validar venta
        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null || $venta->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe en su organización.");
        }
        if ($venta->estado === EstadoVenta::CANCELADA || $venta->estado === EstadoVenta::ANULADA) {
            throw new InvalidArgumentException("No se pueden registrar pagos en ventas en estado {$venta->estado->value}.");
        }
        if ($venta->estado === EstadoVenta::LIQUIDADA) {
            throw new InvalidArgumentException("La venta ya se encuentra formalmente LIQUIDADA. No admite nuevos pagos.");
        }

        // 3. Validar pasarela de la organización
        $orgPasarela = $this->pasarelaRepo->buscarOrganizacionPasarelaPorId($organizacionPasarelaId, $organizacionId);
        if ($orgPasarela === null || !$orgPasarela->activo) {
            throw new InvalidArgumentException("La configuración de pasarela (#{$organizacionPasarelaId}) no existe o está inactiva.");
        }

        // 4. Saldo actual de la venta
        $saldoActual = (float) ($venta->saldoPendiente ?? $venta->total);

        $montoAplicado = min($monto, $saldoActual);
        $montoExcedente = max(0.0, round($monto - $saldoActual, 2));

        $montoNeto = max(0.0, round($monto - $comisionPasarela, 2));

        $fechaPago = !empty($datosMetadatos['fecha_pago']) ? (string) $datosMetadatos['fecha_pago'] : date('Y-m-d H:i:s');
        $anio = (int) date('Y', strtotime($fechaPago));
        $correlativo = $this->pagoRepo->generarSiguienteCorrelativo($organizacionId, $anio);

        $usuarioCreador = $contexto->usuarioId ?? $venta->creadoPor;

        $asumeComision = $orgPasarela->asumeComision;

        $pago = new Pago(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $venta->edicionId,
            ventaId: $ventaId,
            correlativo: $correlativo,
            metodoPago: MetodoPago::TARJETA_PASARELA,
            estado: EstadoPago::APROBADO,
            moneda: 'PEN',
            montoCobradoCliente: $monto,
            comisionPorcentajeAplicada: (float) $orgPasarela->porcentajeComision,
            comisionFijaAplicada: (float) $orgPasarela->comisionFija,
            comisionPasarela: $comisionPasarela,
            montoNetoRecibido: $montoNeto,
            montoAplicadoVenta: $montoAplicado,
            montoExcedente: $montoExcedente,
            comisionAsumidaPor: $asumeComision,
            montoReembolsadoAcumulado: 0.00,
            fechaPago: $fechaPago,
            cuentaBancariaId: null,
            organizacionPasarelaId: $organizacionPasarelaId,
            numeroOperacionBancaria: $transaccionExternaId,
            boucherComprobanteUrl: null,
            notasOperativas: $datosMetadatos['notas'] ?? "Pago confirmado vía pasarela {$orgPasarela->pasarelaCodigo} (Ref: {$transaccionExternaId})",
            claveIdempotencia: $claveIdempotencia,
            versionBloqueo: 1,
            verificadoPor: $contexto->usuarioId,
            verificadoEn: date('Y-m-d H:i:s'),
            creadoPor: $usuarioCreador
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $nuevoId = $this->pagoRepo->registrarPago($pago);

            $this->recalcularProyeccionVenta($ventaId, $organizacionId);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'pagos_caja',
                accion: 'REGISTRAR_PAGO_PASARELA',
                entidadTipo: 'PAGO',
                entidadId: (string) $nuevoId,
                datosPrevios: null,
                datosNuevos: [
                    'correlativo'         => $correlativo,
                    'venta_id'            => $ventaId,
                    'monto'               => $monto,
                    'comision_pasarela'   => $comisionPasarela,
                    'monto_neto'          => $montoNeto,
                    'monto_aplicado'      => $montoAplicado,
                    'monto_excedente'     => $montoExcedente,
                    'metodo'              => MetodoPago::TARJETA_PASARELA->value,
                    'estado'              => EstadoPago::APROBADO->value,
                    'pasarela_id'         => $organizacionPasarelaId,
                    'transaccion_externa' => $transaccionExternaId,
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->pagoRepo->buscarPorId($nuevoId, $organizacionId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza el boucher o comprobante bancario de un pago.
     */
    public function registrarBoucher(
        int $organizacionId,
        int $pagoId,
        string $boucherUrl,
        ?ContextoOperacion $contexto = null
    ): Pago {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('pagos.registrar_manual', $contexto);

        $pago = $this->pagoRepo->buscarPorId($pagoId, $organizacionId);
        if ($pago === null) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }

        $this->pagoRepo->actualizarBoucherUrl($pagoId, $organizacionId, $boucherUrl);

        $this->auditoriaRepo->registrar(
            contexto: $contexto,
            modulo: 'pagos_caja',
            accion: 'ACTUALIZAR_BOUCHER_PAGO',
            entidadTipo: 'PAGO',
            entidadId: (string) $pagoId,
            datosPrevios: ['boucher_comprobante_url' => $pago->boucherComprobanteUrl],
            datosNuevos: ['boucher_comprobante_url' => $boucherUrl]
        );

        return $this->pagoRepo->buscarPorId($pagoId, $organizacionId);
    }

    /**
     * Valida y aprueba o rechaza un pago manual en verificación (boucher bancario).
     */
    public function verificarPagoManual(
        int $organizacionId,
        int $pagoId,
        bool $aprobar,
        int $versionBloqueoEsperada,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): Pago {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('pagos.verificar', $contexto);

        $pago = $this->pagoRepo->buscarPorId($pagoId, $organizacionId);
        if ($pago === null) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }
        if (!$pago->estado->permiteVerificacion()) {
            throw new InvalidArgumentException("Solo se pueden verificar pagos en estado PENDIENTE_VERIFICACION (estado actual: {$pago->estado->value}).");
        }

        $nuevoEstado = $aprobar ? EstadoPago::APROBADO : EstadoPago::RECHAZADO;
        $usuarioId = $contexto->usuarioId ?? throw new InvalidArgumentException('Se requiere un usuario operador autenticado.');

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $actualizado = $this->pagoRepo->cambiarEstadoVerificacion(
                pagoId: $pagoId,
                organizacionId: $organizacionId,
                nuevoEstado: $nuevoEstado,
                verificadoPor: $usuarioId,
                versionBloqueoEsperada: $versionBloqueoEsperada,
                notas: $notas
            );

            if (!$actualizado) {
                throw new ConflictoConcurrenciaExcepcion('El pago fue modificado o verificado concurrentemente por otro operador.');
            }

            // Si se aprobó, amortizar la venta y actualizar proyecciones
            if ($nuevoEstado === EstadoPago::APROBADO) {
                $this->recalcularProyeccionVenta($pago->ventaId, $organizacionId);
            }

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'pagos_caja',
                accion: $aprobar ? 'APROBAR_PAGO_MANUAL' : 'RECHAZAR_PAGO_MANUAL',
                entidadTipo: 'PAGO',
                entidadId: (string) $pagoId,
                datosPrevios: ['estado' => $pago->estado->value],
                datosNuevos: [
                    'estado'         => $nuevoEstado->value,
                    'verificado_por' => $usuarioId,
                    'notas'          => $notas,
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->pagoRepo->buscarPorId($pagoId, $organizacionId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra un asiento compensatorio inmutable de reembolso sobre un pago aprobado.
     */
    public function registrarReembolso(
        int $organizacionId,
        int $pagoId,
        float $montoReembolso,
        MotivoReembolso $motivo,
        string $motivoDetalle,
        ?string $transaccionExternaId = null,
        ?ContextoOperacion $contexto = null
    ): PagoReembolso {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('pagos.reembolsar', $contexto);

        if ($montoReembolso <= 0.0) {
            throw new InvalidArgumentException('El monto de reembolso debe ser estrictamente mayor a 0.00.');
        }

        $pago = $this->pagoRepo->buscarPorId($pagoId, $organizacionId);
        if ($pago === null) {
            throw new InvalidArgumentException("El pago #{$pagoId} no existe en su organización.");
        }
        if ($pago->estado !== EstadoPago::APROBADO) {
            throw new InvalidArgumentException("Solo se pueden reembolsar pagos en estado APROBADO (estado actual: {$pago->estado->value}).");
        }

        // Validar que no exceda el monto neto disponible en el pago
        $disponible = round($pago->montoCobradoCliente - $pago->montoReembolsadoAcumulado, 2);
        if ($montoReembolso > $disponible) {
            throw new InvalidArgumentException(
                sprintf('El monto solicitado (S/ %.2f) excede el saldo reembolsable disponible del pago (S/ %.2f).', $montoReembolso, $disponible)
            );
        }

        $anio = (int) date('Y');
        $correlativo = $this->reembolsoRepo->generarSiguienteCorrelativo($organizacionId, $anio);
        $usuarioId = $contexto->usuarioId ?? throw new InvalidArgumentException('Se requiere un usuario operador autenticado.');

        $reembolso = new PagoReembolso(
            id: null,
            organizacionId: $organizacionId,
            pagoId: $pagoId,
            ventaId: $pago->ventaId,
            correlativo: $correlativo,
            montoReembolsado: $montoReembolso,
            estado: EstadoReembolso::EJECUTADO, // Asiento compensatorio inmediato
            motivo: $motivo,
            motivoDetalle: $motivoDetalle,
            transaccionReembolsoExternaId: $transaccionExternaId,
            autorizadoPor: $usuarioId,
            ejecutadoEn: date('Y-m-d H:i:s')
        );

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $nuevoId = $this->reembolsoRepo->registrarReembolso($reembolso);

            // Acumular reembolso en el pago (sin mutar su estado APROBADO)
            $this->pagoRepo->acumularReembolsoEjecutado($pagoId, $organizacionId, $montoReembolso);

            // Recalcular proyecciones financieras de la venta
            $this->recalcularProyeccionVenta($pago->ventaId, $organizacionId);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'pagos_caja',
                accion: 'REGISTRAR_REEMBOLSO',
                entidadTipo: 'REEMBOLSO',
                entidadId: (string) $nuevoId,
                datosPrevios: null,
                datosNuevos: [
                    'correlativo' => $correlativo,
                    'pago_id'     => $pagoId,
                    'venta_id'    => $pago->ventaId,
                    'monto'       => $montoReembolso,
                    'motivo'      => $motivo->value,
                ]
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $this->reembolsoRepo->buscarPorId($nuevoId, $organizacionId);
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retorna el estado de cuenta y resumen financiero completo de una venta.
     */
    public function obtenerEstadoCuentaVenta(int $ventaId, int $organizacionId, ?ContextoOperacion $contexto = null): array
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('pagos.ver', $contexto);

        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null || $venta->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La venta #{$ventaId} no existe en su organización.");
        }

        $pagos = $this->pagoRepo->listarPorVenta($ventaId, $organizacionId);

        $totalPagadoNeto = 0.0;
        $totalExcedente = 0.0;
        $pagosAprobados = 0;
        $pagosPendientes = 0;

        foreach ($pagos as $p) {
            if ($p->estado === EstadoPago::APROBADO) {
                $pagosAprobados++;
                $totalPagadoNeto += $p->montoNetoAporteVenta();
                $totalExcedente += $p->montoExcedente;
            } elseif ($p->estado === EstadoPago::PENDIENTE_VERIFICACION) {
                $pagosPendientes++;
            }
        }

        $totalPagadoNeto = round($totalPagadoNeto, 2);
        $totalVenta = (float) $venta->total;

        $saldoPendiente = ($venta->estado === EstadoVenta::CANCELADA || $venta->estado === EstadoVenta::ANULADA)
            ? 0.00
            : max(0.00, round($totalVenta - $totalPagadoNeto, 2));

        // Porcentaje mínimo de reserva (política informativa de cobranza)
        $paramRes = $this->configRepo->buscarParametro('organizacion.porcentaje_reserva_minimo', $organizacionId);
        $pctReservaMin = $paramRes ? (float) $paramRes->valor : 30.00;
        $montoReservaMinimo = round(($totalVenta * $pctReservaMin) / 100.0, 2);
        $anticipoCubierto = ($totalPagadoNeto >= $montoReservaMinimo);

        $permiteLiquidacion = ($venta->estado === EstadoVenta::CONFIRMADA && $saldoPendiente <= 0.00 && $totalPagadoNeto >= $totalVenta);

        return [
            'venta_id'              => $ventaId,
            'correlativo'           => $venta->correlativo,
            'estado_comercial'      => $venta->estado->value,
            'total_contractual'     => $totalVenta,
            'monto_pagado_neto'     => $totalPagadoNeto,
            'saldo_pendiente'       => $saldoPendiente,
            'saldo_a_favor_cliente' => round($totalExcedente, 2),
            'estado_financiero'     => $this->determinarEstadoFinanciero($totalVenta, $totalPagadoNeto, $totalExcedente, $venta->estado),
            'politica_anticipo'     => [
                'porcentaje_minimo'     => $pctReservaMin,
                'monto_minimo_reserva'  => $montoReservaMinimo,
                'anticipo_cubierto'     => $anticipoCubierto,
            ],
            'resumen_pagos'         => [
                'total_registros'    => count($pagos),
                'aprobados'          => $pagosAprobados,
                'pendientes'         => $pagosPendientes,
            ],
            'permite_liquidacion'   => $permiteLiquidacion,
            'pagos'                 => array_map(fn(Pago $p) => $p->aArreglo(), $pagos),
        ];
    }

    /**
     * Transiciona comercialmente una venta a LIQUIDADA si y solo si su saldo adeudado es 0.00.
     */
    public function liquidarVenta(int $ventaId, int $organizacionId, ?ContextoOperacion $contexto = null): void
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('ventas.cancelar', $contexto); // O permiso equivalente de gestión de venta

        $estadoCuenta = $this->obtenerEstadoCuentaVenta($ventaId, $organizacionId, $contexto);
        if (!$estadoCuenta['permite_liquidacion']) {
            throw new InvalidArgumentException(
                sprintf('La venta #%d posee un saldo pendiente de S/ %.2f y no puede ser liquidada comercialmente.', $ventaId, $estadoCuenta['saldo_pendiente'])
            );
        }

        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE `ventas`
                SET `estado` = 'LIQUIDADA',
                    `version_bloqueo` = `version_bloqueo` + 1,
                    `actualizado_en` = NOW()
                WHERE `id` = :id AND `organizacion_id` = :org_id AND `estado` = 'CONFIRMADA'
            ");
            $stmt->execute(['id' => $ventaId, 'org_id' => $organizacionId]);

            $this->auditoriaRepo->registrar(
                contexto: $contexto,
                modulo: 'ventas',
                accion: 'LIQUIDAR_VENTA',
                entidadTipo: 'VENTA',
                entidadId: (string) $ventaId,
                datosPrevios: ['estado' => 'CONFIRMADA'],
                datosNuevos: ['estado' => 'LIQUIDADA']
            );

            if ($transaccionPropia) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Recalcula y actualiza la proyección transaccional en la tabla ventas.
     */
    private function recalcularProyeccionVenta(int $ventaId, int $organizacionId): void
    {
        $venta = $this->ventaRepo->buscarPorId($ventaId, $organizacionId);
        if ($venta === null) {
            return;
        }

        $pagos = $this->pagoRepo->listarPorVenta($ventaId, $organizacionId);
        $totalPagadoNeto = 0.0;
        $totalExcedente = 0.0;
        foreach ($pagos as $p) {
            if ($p->estado === EstadoPago::APROBADO) {
                $totalPagadoNeto += $p->montoNetoAporteVenta();
                $totalExcedente += $p->montoExcedente;
            }
        }
        $totalPagadoNeto = round($totalPagadoNeto, 2);
        $totalExcedente = round($totalExcedente, 2);
        $totalVenta = (float) $venta->total;

        $saldoPendiente = ($venta->estado === EstadoVenta::CANCELADA || $venta->estado === EstadoVenta::ANULADA)
            ? 0.00
            : max(0.00, round($totalVenta - $totalPagadoNeto, 2));

        $estadoFin = $this->determinarEstadoFinanciero($totalVenta, $totalPagadoNeto, $totalExcedente, $venta->estado);

        $this->pagoRepo->actualizarProyeccionFinancieraVenta(
            ventaId: $ventaId,
            organizacionId: $organizacionId,
            montoPagado: $totalPagadoNeto,
            saldoPendiente: $saldoPendiente,
            estadoFinanciero: $estadoFin
        );
    }

    private function determinarEstadoFinanciero(float $totalVenta, float $totalPagadoNeto, float $totalExcedente, EstadoVenta $estadoComercial): string
    {
        if ($estadoComercial === EstadoVenta::CANCELADA || $estadoComercial === EstadoVenta::ANULADA) {
            return EstadoFinancieroVenta::NO_APLICA->value;
        }
        if ($totalPagadoNeto <= 0.00 && $totalExcedente <= 0.00) {
            return EstadoFinancieroVenta::NO_PAGADA->value;
        }
        if ($totalPagadoNeto < $totalVenta) {
            return EstadoFinancieroVenta::PAGO_PARCIAL->value;
        }
        if ($totalExcedente > 0.00 || $totalPagadoNeto > $totalVenta) {
            return EstadoFinancieroVenta::SOBREPAGADA->value;
        }
        return EstadoFinancieroVenta::PAGADA_TOTAL->value;
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        return $contexto ?? ContextoOperacion::crearParaCLI();
    }

    private function validarAlcanceTenant(int $organizacionId, ContextoOperacion $contexto): void
    {
        if ($contexto->organizacionId !== null && $contexto->organizacionId !== $organizacionId) {
            throw new AccesoDenegadoExcepcion('Anti-IDOR: No cuenta con autorización para operar sobre otra organización.');
        }
    }

    private function validarPermiso(string $permiso, ContextoOperacion $contexto): void
    {
        if ($contexto->usuarioId === null) {
            return;
        }

        if (!$this->authzServicio->tienePermiso($contexto->usuarioId, $permiso)) {
            throw new AccesoDenegadoExcepcion("No posee el privilegio de seguridad requerido: '{$permiso}'.");
        }
    }
}

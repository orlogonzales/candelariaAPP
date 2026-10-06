<?php

declare(strict_types=1);

namespace Aplicacion\Cotizaciones;

use Aplicacion\Autorizacion\AutorizacionServicio;
use Aplicacion\Catalogo\EstadoCatalogo;
use Aplicacion\Configuracion\ConfiguracionServicio;
use Aplicacion\Crm\EtapaOportunidad;
use Aplicacion\Crm\OportunidadServicio;
use Aplicacion\Entidades\Cotizacion;
use Aplicacion\Entidades\CotizacionLinea;
use Aplicacion\Entidades\CotizacionLineaComponente;
use Aplicacion\Excepciones\AccesoDenegadoExcepcion;
use Aplicacion\Excepciones\ConflictoConcurrenciaExcepcion;
use Aplicacion\Repositorios\AuditoriaRepositorio;
use Aplicacion\Repositorios\ClienteRepositorio;
use Aplicacion\Repositorios\CotizacionRepositorio;
use Aplicacion\Repositorios\EdicionRepositorio;
use Aplicacion\Repositorios\ItemComercialRepositorio;
use Aplicacion\Repositorios\OfertaItemEdicionRepositorio;
use Aplicacion\Repositorios\OfertaPaqueteEdicionRepositorio;
use Aplicacion\Repositorios\OportunidadRepositorio;
use Aplicacion\Repositorios\PaqueteRepositorio;
use Aplicacion\Repositorios\TarifaItemEdicionRepositorio;
use Aplicacion\Repositorios\TarifaPaqueteEdicionRepositorio;
use InvalidArgumentException;
use Nucleo\BaseDatos\Conexion;
use Nucleo\Http\ContextoOperacion;
use PDO;
use RuntimeException;

/**
 * Servicio de dominio oficial para la gestión de Cotizaciones y Presupuestos (F2.4B).
 * Implementa:
 * 1. Aislamiento multi-tenant estricto y Anti-IDOR (Cliente, Edición, Oportunidad del mismo tenant).
 * 2. Asignación de correlativo monotónico y único ÚNICAMENTE al emitir (Borrador sin correlativo).
 * 3. Procedencia comercial estricta con FKs reales y snapshot inmutable de catálogo y paquetes.
 * 4. Cálculos matemáticos deterministas centralizados en backend.
 * 5. Descuentos estructurados con justificación obligatoria y permiso dedicado.
 * 6. Política de vigencia calculada o con override validado (cero escrituras en lecturas).
 * 7. Control de concurrencia optimista (version_bloqueo) y excepciones puras de dominio.
 * 8. Bitácora de auditoría transversal con ContextoOperacion.
 */
class CotizacionServicio
{
    private PDO $pdo;

    public function __construct(
        private CotizacionRepositorio $cotizacionRepo,
        private ClienteRepositorio $clienteRepo,
        private EdicionRepositorio $edicionRepo,
        private OportunidadRepositorio $oportunidadRepo,
        private ItemComercialRepositorio $itemRepo,
        private PaqueteRepositorio $paqueteRepo,
        private OfertaItemEdicionRepositorio $ofertaItemRepo,
        private OfertaPaqueteEdicionRepositorio $ofertaPaqueteRepo,
        private TarifaItemEdicionRepositorio $tarifaItemRepo,
        private TarifaPaqueteEdicionRepositorio $tarifaPaqueteRepo,
        private ConfiguracionServicio $configServicio,
        private AutorizacionServicio $authzServicio,
        private AuditoriaRepositorio $auditoriaRepo,
        private ?OportunidadServicio $oportunidadServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Conexion::obtenerInstancia();
    }

    /**
     * Da de alta un nuevo borrador de cotización.
     */
    public function crearBorrador(
        int $organizacionId,
        int $edicionId,
        int $clienteId,
        string $titulo,
        ?int $oportunidadId = null,
        ?string $terminosCondiciones = null,
        ?string $notasInternas = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.crear', $contexto);

        // 1. Validar Anti-IDOR Cliente
        $cliente = $this->clienteRepo->buscarPorId($clienteId);
        if ($cliente === null || (int) $cliente->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("El cliente #{$clienteId} no existe o no pertenece a su organización.");
        }

        // 2. Validar Anti-IDOR Edición
        $edicion = $this->edicionRepo->buscarPorId($edicionId);
        if ($edicion === null || (int) $edicion->organizacionId !== $organizacionId) {
            throw new InvalidArgumentException("La edición #{$edicionId} no existe o no pertenece a su organización.");
        }

        // 3. Validar Anti-IDOR Oportunidad (si se envía)
        if ($oportunidadId !== null) {
            $oportunidad = $this->oportunidadRepo->buscarPorId($oportunidadId);
            if ($oportunidad === null || (int) $oportunidad->organizacionId !== $organizacionId) {
                throw new InvalidArgumentException("La oportunidad #{$oportunidadId} no existe o no pertenece a su organización.");
            }
            if ($oportunidad->edicionId !== $edicionId) {
                throw new InvalidArgumentException("La oportunidad #{$oportunidadId} pertenece a otra edición comercial.");
            }
            if ($oportunidad->clienteId !== $clienteId) {
                throw new InvalidArgumentException("La oportunidad #{$oportunidadId} pertenece a otro cliente comercial.");
            }
        }

        // 4. Resolver divisa soberana institucional
        $moneda = $this->resolverMonedaInstitucional();

        $usuarioCreador = $contexto->usuarioId ?? 1;

        $cotizacion = new Cotizacion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $edicionId,
            clienteId: $clienteId,
            oportunidadId: $oportunidadId,
            correlativo: null,
            correlativoBase: null,
            versionNumero: 1,
            cotizacionOrigenId: null,
            cotizacionRaizId: null,
            titulo: trim($titulo) !== '' ? trim($titulo) : 'Propuesta Comercial',
            estado: EstadoCotizacion::BORRADOR,
            fechaEmision: null,
            validoHasta: null,
            moneda: $moneda,
            subtotal: 0.00,
            descuentoGlobalTipo: TipoDescuentoCotizacion::NINGUNO,
            descuentoGlobalValor: 0.00,
            descuentoGlobalMonto: 0.00,
            descuentoGlobalMotivo: null,
            descuentoLineasTotal: 0.00,
            total: 0.00,
            terminosCondiciones: $terminosCondiciones,
            notasInternas: $notasInternas,
            motivoRechazo: null,
            motivoRechazoDetalle: null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: 1,
            creadoPor: $usuarioCreador
        );

        $guardada = $this->cotizacionRepo->guardar($cotizacion);

        $this->registrarAuditoria(
            $contexto,
            'COTIZACION_CREADA',
            'cotizaciones',
            $guardada->id,
            [
                'organizacion_id' => $organizacionId,
                'edicion_id' => $edicionId,
                'cliente_id' => $clienteId,
                'oportunidad_id' => $oportunidadId,
                'moneda' => $moneda,
            ]
        );

        return $guardada;
    }

    /**
     * Agrega una línea de ítem comercial a una cotización en borrador.
     */
    public function agregarLineaItem(
        int $organizacionId,
        int $cotizacionId,
        int $itemComercialId,
        float $cantidad,
        ?float $precioUnitario = null,
        TipoDescuentoCotizacion $descuentoTipo = TipoDescuentoCotizacion::NINGUNO,
        float $descuentoValor = 0.00,
        ?string $descuentoMotivo = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.editar', $contexto);

        $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);

        // 1. Validar Anti-IDOR Ítem Comercial
        $item = $this->itemRepo->buscarPorId($itemComercialId, $organizacionId);
        if ($item === null) {
            throw new InvalidArgumentException("El ítem comercial #{$itemComercialId} no existe o no pertenece a su organización.");
        }
        if ($item->estado !== EstadoCatalogo::ACTIVO) {
            throw new InvalidArgumentException("El ítem comercial '{$item->nombre}' se encuentra inactivo.");
        }

        // 2. Validar que el ítem cuente con oferta activa en la edición de la cotización
        $oferta = $this->ofertaItemRepo->buscarPorItemYEdicion($itemComercialId, $cotizacion->edicionId, $organizacionId);
        if ($oferta === null || $oferta->estado->value !== 'ACTIVO') {
            throw new InvalidArgumentException("El ítem comercial '{$item->nombre}' no cuenta con una oferta activa en esta edición.");
        }

        // 3. Resolver precio unitario (si no se envía explícito, se toma la tarifa de catálogo)
        if ($precioUnitario === null) {
            $tarifaVigente = $this->tarifaItemRepo->buscarPorOfertaId((int) $oferta->id);
            if ($tarifaVigente === null) {
                throw new InvalidArgumentException("La oferta del ítem '{$item->nombre}' no tiene una tarifa comercial vigente fijada.");
            }
            $precioUnitario = (float) $tarifaVigente->precio;
        }

        if ($cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad cotizada debe ser mayor a cero.");
        }
        if ($precioUnitario < 0) {
            throw new InvalidArgumentException("El precio unitario no puede ser negativo.");
        }

        // 4. Calcular importes y descuentos
        $bruto = round($cantidad * $precioUnitario, 2);
        $descuentoMonto = $this->calcularDescuento($bruto, $descuentoTipo, $descuentoValor);

        if ($descuentoMonto > 0) {
            $this->validarPermiso('cotizaciones.aplicar_descuento', $contexto);
            if ($descuentoMotivo === null || trim($descuentoMotivo) === '') {
                throw new InvalidArgumentException("Todo descuento aplicado a una línea exige un motivo obligatorio justificado.");
            }
        }

        $subtotal = round($bruto - $descuentoMonto, 2);

        // 5. Construir snapshot
        $orden = count($cotizacion->lineas) + 1;
        $linea = new CotizacionLinea(
            id: null,
            cotizacionId: $cotizacionId,
            tipoLinea: TipoLineaCotizacion::ITEM,
            itemComercialId: $itemComercialId,
            paqueteId: null,
            ofertaItemId: (int) $oferta->id,
            ofertaPaqueteId: null,
            conceptoCodigo: $item->codigo,
            conceptoNombre: $item->nombre,
            conceptoDescripcion: $item->descripcion,
            unidadMedida: $item->unidadMedida->value,
            cantidad: $cantidad,
            precioUnitario: $precioUnitario,
            descuentoTipo: $descuentoTipo,
            descuentoValor: $descuentoValor,
            descuentoMonto: $descuentoMonto,
            descuentoMotivo: $descuentoMonto > 0 ? trim($descuentoMotivo) : null,
            subtotal: $subtotal,
            moneda: $cotizacion->moneda,
            orden: $orden,
            notas: $notas,
            componentes: []
        );

        $this->cotizacionRepo->guardarLinea($linea);

        // 6. Recalcular y persistir cabecera
        $actualizada = $this->recalcularTotales($cotizacionId, $organizacionId);

        $this->registrarAuditoria($contexto, 'COTIZACION_LINEA_AGREGADA', 'cotizaciones', $cotizacionId, [
            'tipo_linea' => 'ITEM',
            'item_id' => $itemComercialId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotal,
        ]);

        return $actualizada;
    }

    /**
     * Agrega una línea de paquete comercial con snapshot de sus componentes.
     */
    public function agregarLineaPaquete(
        int $organizacionId,
        int $cotizacionId,
        int $paqueteId,
        float $cantidad,
        ?float $precioUnitario = null,
        TipoDescuentoCotizacion $descuentoTipo = TipoDescuentoCotizacion::NINGUNO,
        float $descuentoValor = 0.00,
        ?string $descuentoMotivo = null,
        ?string $notas = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.editar', $contexto);

        $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);

        // 1. Validar Anti-IDOR Paquete Comercial
        $paquete = $this->paqueteRepo->buscarPorId($paqueteId, $organizacionId);
        if ($paquete === null) {
            throw new InvalidArgumentException("El paquete comercial #{$paqueteId} no existe o no pertenece a su organización.");
        }
        if ($paquete->estado !== EstadoCatalogo::ACTIVO) {
            throw new InvalidArgumentException("El paquete comercial '{$paquete->nombre}' se encuentra inactivo.");
        }

        // 2. Validar que el paquete cuente con oferta activa en la edición de la cotización
        $oferta = $this->ofertaPaqueteRepo->buscarPorPaqueteYEdicion($paqueteId, $cotizacion->edicionId, $organizacionId);
        if ($oferta === null || $oferta->estado->value !== 'ACTIVO') {
            throw new InvalidArgumentException("El paquete comercial '{$paquete->nombre}' no cuenta con una oferta activa en esta edición.");
        }

        // 3. Resolver precio unitario
        if ($precioUnitario === null) {
            $tarifaVigente = $this->tarifaPaqueteRepo->buscarPorOfertaId((int) $oferta->id);
            if ($tarifaVigente === null) {
                throw new InvalidArgumentException("La oferta del paquete '{$paquete->nombre}' no tiene una tarifa comercial vigente fijada.");
            }
            $precioUnitario = (float) $tarifaVigente->precio;
        }

        if ($cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad cotizada debe ser mayor a cero.");
        }
        if ($precioUnitario < 0) {
            throw new InvalidArgumentException("El precio unitario no puede ser negativo.");
        }

        // 4. Calcular importes y descuentos
        $bruto = round($cantidad * $precioUnitario, 2);
        $descuentoMonto = $this->calcularDescuento($bruto, $descuentoTipo, $descuentoValor);

        if ($descuentoMonto > 0) {
            $this->validarPermiso('cotizaciones.aplicar_descuento', $contexto);
            if ($descuentoMotivo === null || trim($descuentoMotivo) === '') {
                throw new InvalidArgumentException("Todo descuento aplicado a una línea exige un motivo obligatorio justificado.");
            }
        }

        $subtotal = round($bruto - $descuentoMonto, 2);

        // 5. Cargar componentes de paquete vivos para snapshot
        $itemsIncluidos = $this->paqueteRepo->obtenerItemsDePaquete($paqueteId);
        $componentesSnapshot = [];
        $ordenComp = 1;
        foreach ($itemsIncluidos as $pi) {
            $itemMaestro = $pi->itemComercial ?? $this->itemRepo->buscarPorId($pi->itemComercialId, $organizacionId);
            $componentesSnapshot[] = new CotizacionLineaComponente(
                id: null,
                cotizacionLineaId: 0,
                itemComercialId: $pi->itemComercialId,
                itemCodigo: $itemMaestro?->codigo ?? 'ITEM',
                itemNombre: $itemMaestro?->nombre ?? 'Componente',
                itemTipo: $itemMaestro?->tipo->value ?? 'PRODUCTO',
                unidadMedida: $itemMaestro?->unidadMedida->value ?? 'UNIDAD',
                cantidad: (float) $pi->cantidad,
                nota: null,
                orden: $ordenComp++
            );
        }

        $orden = count($cotizacion->lineas) + 1;
        $linea = new CotizacionLinea(
            id: null,
            cotizacionId: $cotizacionId,
            tipoLinea: TipoLineaCotizacion::PAQUETE,
            itemComercialId: null,
            paqueteId: $paqueteId,
            ofertaItemId: null,
            ofertaPaqueteId: (int) $oferta->id,
            conceptoCodigo: $paquete->codigo,
            conceptoNombre: $paquete->nombre,
            conceptoDescripcion: $paquete->descripcion,
            unidadMedida: 'PAQUETE',
            cantidad: $cantidad,
            precioUnitario: $precioUnitario,
            descuentoTipo: $descuentoTipo,
            descuentoValor: $descuentoValor,
            descuentoMonto: $descuentoMonto,
            descuentoMotivo: $descuentoMonto > 0 ? trim($descuentoMotivo) : null,
            subtotal: $subtotal,
            moneda: $cotizacion->moneda,
            orden: $orden,
            notas: $notas,
            componentes: $componentesSnapshot
        );

        $this->cotizacionRepo->guardarLinea($linea);

        // 6. Recalcular y persistir cabecera
        $actualizada = $this->recalcularTotales($cotizacionId, $organizacionId);

        $this->registrarAuditoria($contexto, 'COTIZACION_LINEA_AGREGADA', 'cotizaciones', $cotizacionId, [
            'tipo_linea' => 'PAQUETE',
            'paquete_id' => $paqueteId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotal,
            'componentes_congelados' => count($componentesSnapshot),
        ]);

        return $actualizada;
    }

    /**
     * Retira una línea de una cotización en borrador.
     */
    public function eliminarLinea(int $organizacionId, int $cotizacionId, int $lineaId, ?ContextoOperacion $contexto = null): Cotizacion
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.editar', $contexto);

        $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);

        $eliminado = $this->cotizacionRepo->eliminarLinea($lineaId, $cotizacionId);
        if (!$eliminado) {
            throw new InvalidArgumentException("La línea #{$lineaId} no pertenece a la cotización #{$cotizacionId}.");
        }

        $actualizada = $this->recalcularTotales($cotizacionId, $organizacionId);

        $this->registrarAuditoria($contexto, 'COTIZACION_LINEA_ELIMINADA', 'cotizaciones', $cotizacionId, [
            'linea_id' => $lineaId,
        ]);

        return $actualizada;
    }

    /**
     * Actualiza metadatos de un borrador (título, términos y condiciones, notas internas).
     */
    public function actualizarBorrador(
        int $organizacionId,
        int $cotizacionId,
        ?string $titulo = null,
        ?string $terminosCondiciones = null,
        ?string $notasInternas = null,
        ?int $versionBloqueoEsperada = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.editar', $contexto);

        $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);
        $version = $versionBloqueoEsperada ?? $cotizacion->versionBloqueo;

        $actualizada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $titulo !== null && trim($titulo) !== '' ? trim($titulo) : $cotizacion->titulo,
            estado: $cotizacion->estado,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $cotizacion->total,
            terminosCondiciones: $terminosCondiciones !== null ? $terminosCondiciones : $cotizacion->terminosCondiciones,
            notasInternas: $notasInternas !== null ? $notasInternas : $cotizacion->notasInternas,
            motivoRechazo: $cotizacion->motivoRechazo,
            motivoRechazoDetalle: $cotizacion->motivoRechazoDetalle,
            motivoAnulacion: $cotizacion->motivoAnulacion,
            motivoAnulacionDetalle: $cotizacion->motivoAnulacionDetalle,
            versionBloqueo: $version,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($actualizada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_ACTUALIZADA', 'cotizaciones', $cotizacionId, [
            'titulo' => $actualizada->titulo,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * Aplica un descuento global sobre el subtotal de la cotización.
     */
    public function aplicarDescuentoGlobal(
        int $organizacionId,
        int $cotizacionId,
        TipoDescuentoCotizacion $tipo,
        float $valor,
        string $motivo,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.aplicar_descuento', $contexto);

        $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);

        if ($tipo !== TipoDescuentoCotizacion::NINGUNO) {
            if ($valor <= 0) {
                throw new InvalidArgumentException("El valor del descuento debe ser mayor a cero.");
            }
            if (trim($motivo) === '') {
                throw new InvalidArgumentException("Todo descuento global exige un motivo obligatorio justificado.");
            }
        } else {
            $valor = 0.00;
            $motivo = '';
        }

        $descuentoGlobalMonto = $this->calcularDescuento($cotizacion->subtotal, $tipo, $valor);
        $nuevoTotal = round($cotizacion->subtotal - $descuentoGlobalMonto, 2);

        $actualizada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: $cotizacion->estado,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $tipo,
            descuentoGlobalValor: $valor,
            descuentoGlobalMonto: $descuentoGlobalMonto,
            descuentoGlobalMotivo: $tipo !== TipoDescuentoCotizacion::NINGUNO ? trim($motivo) : null,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $nuevoTotal,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: $cotizacion->motivoRechazo,
            motivoRechazoDetalle: $cotizacion->motivoRechazoDetalle,
            motivoAnulacion: $cotizacion->motivoAnulacion,
            motivoAnulacionDetalle: $cotizacion->motivoAnulacionDetalle,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($actualizada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_DESCUENTO_GLOBAL_APLICADO', 'cotizaciones', $cotizacionId, [
            'tipo' => $tipo->value,
            'valor' => $valor,
            'monto' => $descuentoGlobalMonto,
            'motivo' => $motivo,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * Emite formalmente la cotización:
     * - Asigna correlativo monotónico oficial único por organización y año.
     * - Resuelve fecha_emision y valido_hasta.
     * - Congela snapshot inmutable.
     * - Si es revisión, anula atómicamente la versión previa.
     * - Si tiene oportunidad en etapas iniciales, avanza CRM a COTIZACION.
     */
    public function emitir(
        int $organizacionId,
        int $cotizacionId,
        ?string $validoHastaManual = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.emitir', $contexto);

        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $cotizacion = $this->obtenerYValidarBorrador($cotizacionId, $organizacionId);

            if (empty($cotizacion->lineas)) {
                throw new InvalidArgumentException("No se puede emitir una cotización sin líneas comerciales.");
            }

            $edicion = $this->edicionRepo->buscarPorId($cotizacion->edicionId);
            $anio = $edicion ? (int) $edicion->anio : (int) date('Y');

            // 1. Fechas de emisión y validez (validar antes de consumir secuencia)
            $fechaEmision = date('Y-m-d');
            if ($validoHastaManual !== null && trim($validoHastaManual) !== '') {
                $validoHasta = trim($validoHastaManual);
                if ($validoHasta < $fechaEmision) {
                    throw new InvalidArgumentException("La fecha límite de validez ({$validoHasta}) no puede ser anterior a la fecha de emisión ({$fechaEmision}).");
                }
            } else {
                $diasValidez = (int) $this->configServicio->obtenerOrganizacion($organizacionId, 'organizacion.dias_validez_cotizacion');
                if ($diasValidez <= 0) {
                    $diasValidez = 15;
                }
                $validoHasta = date('Y-m-d', strtotime("+{$diasValidez} days", strtotime($fechaEmision)));
            }

            // 2. Resolver y reservar correlativo oficial
            if ($cotizacion->versionNumero === 1) {
                $secuencia = $this->cotizacionRepo->reservarSiguienteNumeroSecuencia($organizacionId, $anio);
                $correlativoBase = sprintf('COT-%04d-%06d', $anio, $secuencia);
                $correlativo = $correlativoBase;
            } else {
                $correlativoBase = $cotizacion->correlativoBase;
                if ($correlativoBase === null || trim($correlativoBase) === '') {
                    $secuencia = $this->cotizacionRepo->reservarSiguienteNumeroSecuencia($organizacionId, $anio);
                    $correlativoBase = sprintf('COT-%04d-%06d', $anio, $secuencia);
                }
                $correlativo = sprintf('%s-R%d', $correlativoBase, $cotizacion->versionNumero);
            }

            // 3. Mutar a EMITIDA
            $emitida = new Cotizacion(
                id: $cotizacion->id,
                organizacionId: $cotizacion->organizacionId,
                edicionId: $cotizacion->edicionId,
                clienteId: $cotizacion->clienteId,
                oportunidadId: $cotizacion->oportunidadId,
                correlativo: $correlativo,
                correlativoBase: $correlativoBase,
                versionNumero: $cotizacion->versionNumero,
                cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
                cotizacionRaizId: $cotizacion->cotizacionRaizId,
                titulo: $cotizacion->titulo,
                estado: EstadoCotizacion::EMITIDA,
                fechaEmision: $fechaEmision,
                validoHasta: $validoHasta,
                moneda: $cotizacion->moneda,
                subtotal: $cotizacion->subtotal,
                descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
                descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
                descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
                descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
                descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
                total: $cotizacion->total,
                terminosCondiciones: $cotizacion->terminosCondiciones,
                notasInternas: $cotizacion->notasInternas,
                motivoRechazo: null,
                motivoRechazoDetalle: null,
                motivoAnulacion: null,
                motivoAnulacionDetalle: null,
                versionBloqueo: $cotizacion->versionBloqueo,
                creadoPor: $cotizacion->creadoPor,
                creadoEn: $cotizacion->creadoEn,
                actualizadoEn: $cotizacion->actualizadoEn,
                lineas: $cotizacion->lineas
            );

            $ok = $this->cotizacionRepo->actualizar($emitida);
            if (!$ok) {
                throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
            }

            // 4. Si es revisión, anular atómicamente la versión previa (R1 o anterior)
            if ($cotizacion->cotizacionOrigenId !== null) {
                $origen = $this->cotizacionRepo->buscarPorId($cotizacion->cotizacionOrigenId, $organizacionId);
                if ($origen !== null && $origen->estado === EstadoCotizacion::EMITIDA) {
                    $origenAnulada = new Cotizacion(
                        id: $origen->id,
                        organizacionId: $origen->organizacionId,
                        edicionId: $origen->edicionId,
                        clienteId: $origen->clienteId,
                        oportunidadId: $origen->oportunidadId,
                        correlativo: $origen->correlativo,
                        correlativoBase: $origen->correlativoBase,
                        versionNumero: $origen->versionNumero,
                        cotizacionOrigenId: $origen->cotizacionOrigenId,
                        cotizacionRaizId: $origen->cotizacionRaizId,
                        titulo: $origen->titulo,
                        estado: EstadoCotizacion::ANULADA,
                        fechaEmision: $origen->fechaEmision,
                        validoHasta: $origen->validoHasta,
                        moneda: $origen->moneda,
                        subtotal: $origen->subtotal,
                        descuentoGlobalTipo: $origen->descuentoGlobalTipo,
                        descuentoGlobalValor: $origen->descuentoGlobalValor,
                        descuentoGlobalMonto: $origen->descuentoGlobalMonto,
                        descuentoGlobalMotivo: $origen->descuentoGlobalMotivo,
                        descuentoLineasTotal: $origen->descuentoLineasTotal,
                        total: $origen->total,
                        terminosCondiciones: $origen->terminosCondiciones,
                        notasInternas: $origen->notasInternas,
                        motivoRechazo: null,
                        motivoRechazoDetalle: null,
                        motivoAnulacion: MotivoAnulacionCotizacion::SUPERADA_POR_REVISION,
                        motivoAnulacionDetalle: "Sustituida por emisión formal de la revisión {$correlativo}.",
                        versionBloqueo: $origen->versionBloqueo,
                        creadoPor: $origen->creadoPor,
                        creadoEn: $origen->creadoEn,
                        actualizadoEn: $origen->actualizadoEn,
                        lineas: $origen->lineas
                    );
                    $this->cotizacionRepo->actualizar($origenAnulada);
                    $this->registrarAuditoria($contexto, 'COTIZACION_ANULADA', 'cotizaciones', $origen->id, [
                        'motivo' => 'SUPERADA_POR_REVISION',
                        'nueva_cotizacion_id' => $cotizacionId,
                        'nuevo_correlativo' => $correlativo,
                    ]);
                }
            }

            // 5. Integración con CRM: Avanzar etapa si la oportunidad está en etapa temprana
            if ($cotizacion->oportunidadId !== null) {
                $op = $this->oportunidadRepo->buscarPorId($cotizacion->oportunidadId);
                if ($op !== null && in_array($op->etapa->value, ['NUEVA', 'CONTACTADA', 'CALIFICADA'], true)) {
                    if ($this->oportunidadServicio !== null) {
                        $this->oportunidadServicio->cambiarEtapa(
                            organizacionId: $organizacionId,
                            oportunidadId: $op->id,
                            nuevaEtapaStr: 'COTIZACION',
                            motivoPerdidaStr: null,
                            motivoPerdidaDetalle: null,
                            motivoCambio: "Avanzado automáticamente por emisión formal de cotización {$correlativo}",
                            versionBloqueoEsperada: $op->versionBloqueo,
                            contexto: $contexto
                        );
                    }
                }
            }

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $resultado = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);

            $this->registrarAuditoria($contexto, 'COTIZACION_EMITIDA', 'cotizaciones', $cotizacionId, [
                'correlativo' => $correlativo,
                'version_numero' => $cotizacion->versionNumero,
                'total' => $cotizacion->total,
                'fecha_emision' => $fechaEmision,
                'valido_hasta' => $validoHasta,
            ]);

            return $resultado;
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Genera una nueva revisión en estado BORRADOR a partir de una cotización emitida o vencida.
     * NO modifica la versión de origen; la sustitución ocurrirá cuando la nueva revisión sea emitida.
     */
    public function crearRevision(int $organizacionId, int $cotizacionId, ?ContextoOperacion $contexto = null): Cotizacion
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.crear_revision', $contexto);

        $origen = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($origen === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if (!in_array($origen->estado, [EstadoCotizacion::EMITIDA, EstadoCotizacion::VENCIDA], true)) {
            throw new InvalidArgumentException("Solo se pueden generar revisiones a partir de cotizaciones en estado EMITIDA o VENCIDA (estado actual: {$origen->estado->value}).");
        }

        $raizId = $origen->cotizacionRaizId ?? $origen->id;
        $correlativoBase = $origen->correlativoBase ?? $origen->correlativo;
        $nuevaVersion = $origen->versionNumero + 1;
        $usuarioCreador = $contexto->usuarioId ?? 1;

        $borradorRevision = new Cotizacion(
            id: null,
            organizacionId: $organizacionId,
            edicionId: $origen->edicionId,
            clienteId: $origen->clienteId,
            oportunidadId: $origen->oportunidadId,
            correlativo: null,
            correlativoBase: $correlativoBase,
            versionNumero: $nuevaVersion,
            cotizacionOrigenId: $origen->id,
            cotizacionRaizId: $raizId,
            titulo: $origen->titulo,
            estado: EstadoCotizacion::BORRADOR,
            fechaEmision: null,
            validoHasta: null,
            moneda: $origen->moneda,
            subtotal: $origen->subtotal,
            descuentoGlobalTipo: $origen->descuentoGlobalTipo,
            descuentoGlobalValor: $origen->descuentoGlobalValor,
            descuentoGlobalMonto: $origen->descuentoGlobalMonto,
            descuentoGlobalMotivo: $origen->descuentoGlobalMotivo,
            descuentoLineasTotal: $origen->descuentoLineasTotal,
            total: $origen->total,
            terminosCondiciones: $origen->terminosCondiciones,
            notasInternas: $origen->notasInternas,
            motivoRechazo: null,
            motivoRechazoDetalle: null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: 1,
            creadoPor: $usuarioCreador
        );

        $guardada = $this->cotizacionRepo->guardar($borradorRevision);

        // Clonar líneas y componentes
        foreach ($origen->lineas as $l) {
            $lineaClonada = new CotizacionLinea(
                id: null,
                cotizacionId: (int) $guardada->id,
                tipoLinea: $l->tipoLinea,
                itemComercialId: $l->itemComercialId,
                paqueteId: $l->paqueteId,
                ofertaItemId: $l->ofertaItemId,
                ofertaPaqueteId: $l->ofertaPaqueteId,
                conceptoCodigo: $l->conceptoCodigo,
                conceptoNombre: $l->conceptoNombre,
                conceptoDescripcion: $l->conceptoDescripcion,
                unidadMedida: $l->unidadMedida,
                cantidad: $l->cantidad,
                precioUnitario: $l->precioUnitario,
                descuentoTipo: $l->descuentoTipo,
                descuentoValor: $l->descuentoValor,
                descuentoMonto: $l->descuentoMonto,
                descuentoMotivo: $l->descuentoMotivo,
                subtotal: $l->subtotal,
                moneda: $l->moneda,
                orden: $l->orden,
                notas: $l->notas,
                componentes: $l->componentes
            );
            $this->cotizacionRepo->guardarLinea($lineaClonada);
        }

        $resultado = $this->recalcularTotales((int) $guardada->id, $organizacionId);

        $this->registrarAuditoria($contexto, 'COTIZACION_REVISION_GENERADA', 'cotizaciones', (int) $guardada->id, [
            'cotizacion_origen_id' => $origen->id,
            'version_numero' => $nuevaVersion,
            'correlativo_base' => $correlativoBase,
        ]);

        return $resultado;
    }

    /**
     * Registra la aceptación comercial de la cotización por parte del cliente.
     * En F2.4, representa únicamente la conformidad comercial (no genera venta, reserva ni caja).
     */
    public function aceptar(int $organizacionId, int $cotizacionId, ?ContextoOperacion $contexto = null): Cotizacion
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.aceptar', $contexto);

        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if ($cotizacion->estado !== EstadoCotizacion::EMITIDA) {
            throw new InvalidArgumentException("Solo se pueden aceptar cotizaciones formalmente EMITIDAS (estado actual: {$cotizacion->estado->value}).");
        }

        if ($cotizacion->estaVencidaEfectiva()) {
            throw new InvalidArgumentException("La cotización ha expirado su fecha límite de validez ({$cotizacion->validoHasta}). Requiere revalidación o nueva revisión.");
        }

        $aceptada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: EstadoCotizacion::ACEPTADA,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $cotizacion->total,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: null,
            motivoRechazoDetalle: null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($aceptada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_ACEPTADA', 'cotizaciones', $cotizacionId, [
            'correlativo' => $cotizacion->correlativo,
            'total' => $cotizacion->total,
            'moneda' => $cotizacion->moneda,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * Registra el rechazo de la cotización por decisión comercial del cliente.
     */
    public function rechazar(
        int $organizacionId,
        int $cotizacionId,
        MotivoRechazoCotizacion $motivo,
        ?string $motivoDetalle = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.rechazar', $contexto);

        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if ($cotizacion->estado !== EstadoCotizacion::EMITIDA) {
            throw new InvalidArgumentException("Solo se pueden rechazar cotizaciones formalmente EMITIDAS.");
        }

        if ($motivo === MotivoRechazoCotizacion::OTRO && ($motivoDetalle === null || trim($motivoDetalle) === '')) {
            throw new InvalidArgumentException("El motivo de rechazo 'OTRO' exige una explicación detallada.");
        }

        $rechazada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: EstadoCotizacion::RECHAZADA,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $cotizacion->total,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: $motivo,
            motivoRechazoDetalle: $motivoDetalle !== null ? trim($motivoDetalle) : null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($rechazada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_RECHAZADA', 'cotizaciones', $cotizacionId, [
            'motivo' => $motivo->value,
            'motivo_detalle' => $motivoDetalle,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * Anula formalmente una cotización (en borrador, emitida o vencida).
     */
    public function anular(
        int $organizacionId,
        int $cotizacionId,
        MotivoAnulacionCotizacion $motivo,
        ?string $motivoDetalle = null,
        ?ContextoOperacion $contexto = null
    ): Cotizacion {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.anular', $contexto);

        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if ($cotizacion->estado->esTerminal()) {
            throw new InvalidArgumentException("No se puede anular una cotización que ya se encuentra en estado terminal ({$cotizacion->estado->value}).");
        }

        if ($motivo === MotivoAnulacionCotizacion::OTRO && ($motivoDetalle === null || trim($motivoDetalle) === '')) {
            throw new InvalidArgumentException("El motivo de anulación 'OTRO' exige una explicación detallada.");
        }

        $anulada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: EstadoCotizacion::ANULADA,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $cotizacion->total,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: null,
            motivoRechazoDetalle: null,
            motivoAnulacion: $motivo,
            motivoAnulacionDetalle: $motivoDetalle !== null ? trim($motivoDetalle) : null,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($anulada);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_ANULADA', 'cotizaciones', $cotizacionId, [
            'motivo' => $motivo->value,
            'motivo_detalle' => $motivoDetalle,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * Servicio explícito y auditable para persistir el estado VENCIDA en cotizaciones cuya vigencia expiró.
     * (Las lecturas no mutan la base de datos).
     */
    public function marcarVencida(int $organizacionId, int $cotizacionId, ?ContextoOperacion $contexto = null): Cotizacion
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);

        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if ($cotizacion->estado !== EstadoCotizacion::EMITIDA) {
            throw new InvalidArgumentException("Solo cotizaciones formalmente EMITIDAS pueden marcarse como vencidas.");
        }

        if (!$cotizacion->estaVencidaEfectiva()) {
            throw new InvalidArgumentException("La cotización aún se encuentra dentro de su período de validez ({$cotizacion->validoHasta}).");
        }

        $vencida = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: EstadoCotizacion::VENCIDA,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $cotizacion->subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $cotizacion->descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $cotizacion->descuentoLineasTotal,
            total: $cotizacion->total,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: null,
            motivoRechazoDetalle: null,
            motivoAnulacion: null,
            motivoAnulacionDetalle: null,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $cotizacion->lineas
        );

        $ok = $this->cotizacionRepo->actualizar($vencida);
        if (!$ok) {
            throw new ConflictoConcurrenciaExcepcion("La cotización fue modificada por otro usuario concurrentemente.");
        }

        $this->registrarAuditoria($contexto, 'COTIZACION_VENCIMIENTO_PERSISTIDO', 'cotizaciones', $cotizacionId, [
            'valido_hasta' => $cotizacion->validoHasta,
            'correlativo' => $cotizacion->correlativo,
        ]);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    public function obtenerCotizacion(int $organizacionId, int $cotizacionId, ?ContextoOperacion $contexto = null): ?Cotizacion
    {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.ver', $contexto);

        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    /**
     * @return Cotizacion[]
     */
    public function listarCotizaciones(
        int $organizacionId,
        ?int $edicionId = null,
        ?int $clienteId = null,
        ?string $estado = null,
        ?ContextoOperacion $contexto = null
    ): array {
        $contexto = $this->resolverContexto($contexto);
        $this->validarAlcanceTenant($organizacionId, $contexto);
        $this->validarPermiso('cotizaciones.ver', $contexto);

        return $this->cotizacionRepo->listarPorOrganizacion($organizacionId, $edicionId, $clienteId, $estado);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES DE CÁLCULO, RECALCULO Y VALIDACIÓN
    // =========================================================================

    private function recalcularTotales(int $cotizacionId, int $organizacionId): Cotizacion
    {
        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("Cotización no encontrada para recalcular.");
        }

        $lineas = $this->cotizacionRepo->obtenerLineas($cotizacionId);

        $subtotal = 0.00;
        $descuentoLineasTotal = 0.00;
        foreach ($lineas as $l) {
            $subtotal = round($subtotal + $l->subtotal, 2);
            $descuentoLineasTotal = round($descuentoLineasTotal + $l->descuentoMonto, 2);
        }

        // Recalcular descuento global
        $descuentoGlobalMonto = $this->calcularDescuento($subtotal, $cotizacion->descuentoGlobalTipo, $cotizacion->descuentoGlobalValor);
        $total = round($subtotal - $descuentoGlobalMonto, 2);

        $actualizada = new Cotizacion(
            id: $cotizacion->id,
            organizacionId: $cotizacion->organizacionId,
            edicionId: $cotizacion->edicionId,
            clienteId: $cotizacion->clienteId,
            oportunidadId: $cotizacion->oportunidadId,
            correlativo: $cotizacion->correlativo,
            correlativoBase: $cotizacion->correlativoBase,
            versionNumero: $cotizacion->versionNumero,
            cotizacionOrigenId: $cotizacion->cotizacionOrigenId,
            cotizacionRaizId: $cotizacion->cotizacionRaizId,
            titulo: $cotizacion->titulo,
            estado: $cotizacion->estado,
            fechaEmision: $cotizacion->fechaEmision,
            validoHasta: $cotizacion->validoHasta,
            moneda: $cotizacion->moneda,
            subtotal: $subtotal,
            descuentoGlobalTipo: $cotizacion->descuentoGlobalTipo,
            descuentoGlobalValor: $cotizacion->descuentoGlobalValor,
            descuentoGlobalMonto: $descuentoGlobalMonto,
            descuentoGlobalMotivo: $cotizacion->descuentoGlobalMotivo,
            descuentoLineasTotal: $descuentoLineasTotal,
            total: $total,
            terminosCondiciones: $cotizacion->terminosCondiciones,
            notasInternas: $cotizacion->notasInternas,
            motivoRechazo: $cotizacion->motivoRechazo,
            motivoRechazoDetalle: $cotizacion->motivoRechazoDetalle,
            motivoAnulacion: $cotizacion->motivoAnulacion,
            motivoAnulacionDetalle: $cotizacion->motivoAnulacionDetalle,
            versionBloqueo: $cotizacion->versionBloqueo,
            creadoPor: $cotizacion->creadoPor,
            creadoEn: $cotizacion->creadoEn,
            actualizadoEn: $cotizacion->actualizadoEn,
            lineas: $lineas
        );

        $this->cotizacionRepo->actualizar($actualizada);
        return $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
    }

    private function calcularDescuento(float $base, TipoDescuentoCotizacion $tipo, float $valor): float
    {
        if ($tipo === TipoDescuentoCotizacion::NINGUNO || $valor <= 0) {
            return 0.00;
        }

        if ($tipo === TipoDescuentoCotizacion::PORCENTAJE) {
            if ($valor > 100.00) {
                throw new InvalidArgumentException("El porcentaje de descuento ({$valor}%) no puede superar el 100%.");
            }
            return round($base * ($valor / 100.00), 2);
        }

        if ($tipo === TipoDescuentoCotizacion::MONTO_FIJO) {
            if ($valor > $base) {
                throw new InvalidArgumentException("El monto de descuento fijo ({$valor}) no puede superar la base imponible ({$base}).");
            }
            return round($valor, 2);
        }

        return 0.00;
    }

    private function obtenerYValidarBorrador(int $cotizacionId, int $organizacionId): Cotizacion
    {
        $cotizacion = $this->cotizacionRepo->buscarPorId($cotizacionId, $organizacionId);
        if ($cotizacion === null) {
            throw new InvalidArgumentException("La cotización #{$cotizacionId} no existe o no pertenece a su organización.");
        }

        if ($cotizacion->estado !== EstadoCotizacion::BORRADOR) {
            throw new InvalidArgumentException("La cotización no se encuentra en estado BORRADOR y no puede ser modificada (estado actual: {$cotizacion->estado->value}).");
        }

        return $cotizacion;
    }

    private function resolverMonedaInstitucional(): string
    {
        $moneda = $this->configServicio->obtenerPlataforma('plataforma.moneda_principal');
        if (!is_string($moneda) || trim($moneda) === '' || strlen(trim($moneda)) !== 3) {
            throw new RuntimeException("Configuración institucional de divisa 'plataforma.moneda_principal' no encontrada o inválida (FAIL CLOSED).");
        }
        return strtoupper(trim($moneda));
    }

    private function resolverContexto(?ContextoOperacion $contexto): ContextoOperacion
    {
        return $contexto ?? ContextoOperacion::actual() ?? ContextoOperacion::paraHumano(
            usuarioId: 1,
            canalId: 1,
            canalCodigo: 'SISTEMA',
            origenIp: '127.0.0.1',
            agenteUsuario: 'CotizacionServicio/v1',
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
            modulo: 'cotizaciones',
            accion: $evento,
            entidadTipo: $entidad,
            entidadId: (string) $entidadId,
            datosPrevios: null,
            datosNuevos: $detalles
        );
    }
}

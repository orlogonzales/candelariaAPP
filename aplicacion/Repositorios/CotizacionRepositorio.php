<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Cotizaciones\EstadoCotizacion;
use Aplicacion\Cotizaciones\MotivoAnulacionCotizacion;
use Aplicacion\Cotizaciones\MotivoRechazoCotizacion;
use Aplicacion\Cotizaciones\TipoDescuentoCotizacion;
use Aplicacion\Cotizaciones\TipoLineaCotizacion;
use Aplicacion\Entidades\Cotizacion;
use Aplicacion\Entidades\CotizacionLinea;
use Aplicacion\Entidades\CotizacionLineaComponente;
use PDO;

class CotizacionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(Cotizacion $c): Cotizacion
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cotizaciones` (
                `organizacion_id`, `edicion_id`, `cliente_id`, `oportunidad_id`,
                `correlativo`, `correlativo_base`, `version_numero`, `cotizacion_origen_id`,
                `cotizacion_raiz_id`, `titulo`, `estado`, `fecha_emision`, `valido_hasta`,
                `moneda`, `subtotal`, `descuento_global_tipo`, `descuento_global_valor`,
                `descuento_global_monto`, `descuento_global_motivo`, `descuento_lineas_total`,
                `total`, `terminos_condiciones`, `notas_internas`, `motivo_rechazo`,
                `motivo_rechazo_detalle`, `motivo_anulacion`, `motivo_anulacion_detalle`,
                `version_bloqueo`, `creado_por`
            ) VALUES (
                :org_id, :edicion_id, :cliente_id, :oportunidad_id,
                :correlativo, :correlativo_base, :version_numero, :cotizacion_origen_id,
                :cotizacion_raiz_id, :titulo, :estado, :fecha_emision, :valido_hasta,
                :moneda, :subtotal, :descuento_global_tipo, :descuento_global_valor,
                :descuento_global_monto, :descuento_global_motivo, :descuento_lineas_total,
                :total, :terminos_condiciones, :notas_internas, :motivo_rechazo,
                :motivo_rechazo_detalle, :motivo_anulacion, :motivo_anulacion_detalle,
                :version_bloqueo, :creado_por
            )
        ");

        $stmt->execute([
            'org_id' => $c->organizacionId,
            'edicion_id' => $c->edicionId,
            'cliente_id' => $c->clienteId,
            'oportunidad_id' => $c->oportunidadId,
            'correlativo' => $c->correlativo,
            'correlativo_base' => $c->correlativoBase,
            'version_numero' => $c->versionNumero,
            'cotizacion_origen_id' => $c->cotizacionOrigenId,
            'cotizacion_raiz_id' => $c->cotizacionRaizId,
            'titulo' => $c->titulo,
            'estado' => $c->estado->value,
            'fecha_emision' => $c->fechaEmision,
            'valido_hasta' => $c->validoHasta,
            'moneda' => $c->moneda,
            'subtotal' => $c->subtotal,
            'descuento_global_tipo' => $c->descuentoGlobalTipo->value,
            'descuento_global_valor' => $c->descuentoGlobalValor,
            'descuento_global_monto' => $c->descuentoGlobalMonto,
            'descuento_global_motivo' => $c->descuentoGlobalMotivo,
            'descuento_lineas_total' => $c->descuentoLineasTotal,
            'total' => $c->total,
            'terminos_condiciones' => $c->terminosCondiciones,
            'notas_internas' => $c->notasInternas,
            'motivo_rechazo' => $c->motivoRechazo?->value,
            'motivo_rechazo_detalle' => $c->motivoRechazoDetalle,
            'motivo_anulacion' => $c->motivoAnulacion?->value,
            'motivo_anulacion_detalle' => $c->motivoAnulacionDetalle,
            'version_bloqueo' => $c->versionBloqueo,
            'creado_por' => $c->creadoPor,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $c->organizacionId);
    }

    public function actualizar(Cotizacion $c): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `cotizaciones`
            SET `titulo` = :titulo,
                `estado` = :estado,
                `correlativo` = :correlativo,
                `correlativo_base` = :correlativo_base,
                `version_numero` = :version_numero,
                `cotizacion_origen_id` = :cotizacion_origen_id,
                `cotizacion_raiz_id` = :cotizacion_raiz_id,
                `fecha_emision` = :fecha_emision,
                `valido_hasta` = :valido_hasta,
                `subtotal` = :subtotal,
                `descuento_global_tipo` = :descuento_global_tipo,
                `descuento_global_valor` = :descuento_global_valor,
                `descuento_global_monto` = :descuento_global_monto,
                `descuento_global_motivo` = :descuento_global_motivo,
                `descuento_lineas_total` = :descuento_lineas_total,
                `total` = :total,
                `terminos_condiciones` = :terminos_condiciones,
                `notas_internas` = :notas_internas,
                `motivo_rechazo` = :motivo_rechazo,
                `motivo_rechazo_detalle` = :motivo_rechazo_detalle,
                `motivo_anulacion` = :motivo_anulacion,
                `motivo_anulacion_detalle` = :motivo_anulacion_detalle,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id
              AND `organizacion_id` = :org_id
              AND `version_bloqueo` = :version_actual
        ");

        $stmt->execute([
            'id' => $c->id,
            'org_id' => $c->organizacionId,
            'titulo' => $c->titulo,
            'estado' => $c->estado->value,
            'correlativo' => $c->correlativo,
            'correlativo_base' => $c->correlativoBase,
            'version_numero' => $c->versionNumero,
            'cotizacion_origen_id' => $c->cotizacionOrigenId,
            'cotizacion_raiz_id' => $c->cotizacionRaizId,
            'fecha_emision' => $c->fechaEmision,
            'valido_hasta' => $c->validoHasta,
            'subtotal' => $c->subtotal,
            'descuento_global_tipo' => $c->descuentoGlobalTipo->value,
            'descuento_global_valor' => $c->descuentoGlobalValor,
            'descuento_global_monto' => $c->descuentoGlobalMonto,
            'descuento_global_motivo' => $c->descuentoGlobalMotivo,
            'descuento_lineas_total' => $c->descuentoLineasTotal,
            'total' => $c->total,
            'terminos_condiciones' => $c->terminosCondiciones,
            'notas_internas' => $c->notasInternas,
            'motivo_rechazo' => $c->motivoRechazo?->value,
            'motivo_rechazo_detalle' => $c->motivoRechazoDetalle,
            'motivo_anulacion' => $c->motivoAnulacion?->value,
            'motivo_anulacion_detalle' => $c->motivoAnulacionDetalle,
            'version_actual' => $c->versionBloqueo,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function buscarPorId(int $id, ?int $organizacionId = null): ?Cotizacion
    {
        $sql = "SELECT * FROM `cotizaciones` WHERE `id` = :id";
        $params = ['id' => $id];
        if ($organizacionId !== null) {
            $sql .= " AND `organizacion_id` = :org_id";
            $params['org_id'] = $organizacionId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $lineas = $this->obtenerLineas($id);
        return $this->mapearCotizacion($row, $lineas);
    }

    public function buscarPorCorrelativo(string $correlativo, int $organizacionId): ?Cotizacion
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cotizaciones` 
            WHERE `correlativo` = :correlativo AND `organizacion_id` = :org_id
        ");
        $stmt->execute([
            'correlativo' => $correlativo,
            'org_id' => $organizacionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $lineas = $this->obtenerLineas((int) $row['id']);
        return $this->mapearCotizacion($row, $lineas);
    }

    /**
     * @return Cotizacion[]
     */
    public function listarPorOrganizacion(
        int $organizacionId,
        ?int $edicionId = null,
        ?int $clienteId = null,
        ?string $estado = null
    ): array {
        $sql = "SELECT * FROM `cotizaciones` WHERE `organizacion_id` = :org_id";
        $params = ['org_id' => $organizacionId];

        if ($edicionId !== null) {
            $sql .= " AND `edicion_id` = :edicion_id";
            $params['edicion_id'] = $edicionId;
        }
        if ($clienteId !== null) {
            $sql .= " AND `cliente_id` = :cliente_id";
            $params['cliente_id'] = $clienteId;
        }
        if ($estado !== null && $estado !== '') {
            $sql .= " AND `estado` = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY `id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($rows as $r) {
            $lineas = $this->obtenerLineas((int) $r['id']);
            $resultado[] = $this->mapearCotizacion($r, $lineas);
        }
        return $resultado;
    }

    /**
     * Reserva el siguiente número de secuencia en forma atómica y monotónica.
     * Particionada por organizacion_id y anio.
     */
    public function reservarSiguienteNumeroSecuencia(int $organizacionId, int $anio): int
    {
        $stmtInit = $this->pdo->prepare("
            INSERT INTO `cotizaciones_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 0)
            ON DUPLICATE KEY UPDATE `organizacion_id` = `organizacion_id`
        ");
        $stmtInit->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        $stmtLock = $this->pdo->prepare("
            SELECT `ultimo_numero`
            FROM `cotizaciones_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
            FOR UPDATE
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $ultimo = (int) $stmtLock->fetchColumn();

        $siguiente = $ultimo + 1;

        $stmtUp = $this->pdo->prepare("
            UPDATE `cotizaciones_secuencias`
            SET `ultimo_numero` = :siguiente
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtUp->execute([
            'siguiente' => $siguiente,
            'org_id' => $organizacionId,
            'anio' => $anio,
        ]);

        return $siguiente;
    }

    public function guardarLinea(CotizacionLinea $l): CotizacionLinea
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cotizacion_lineas` (
                `cotizacion_id`, `tipo_linea`, `item_comercial_id`, `paquete_id`,
                `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`, `concepto_nombre`,
                `concepto_descripcion`, `unidad_medida`, `cantidad`, `precio_unitario`,
                `descuento_tipo`, `descuento_valor`, `descuento_monto`, `descuento_motivo`,
                `subtotal`, `moneda`, `orden`, `notas`
            ) VALUES (
                :cotizacion_id, :tipo_linea, :item_id, :paquete_id,
                :oferta_item_id, :oferta_paquete_id, :codigo, :nombre,
                :descripcion, :unidad, :cantidad, :precio_unitario,
                :descuento_tipo, :descuento_valor, :descuento_monto, :descuento_motivo,
                :subtotal, :moneda, :orden, :notas
            )
        ");

        $stmt->execute([
            'cotizacion_id' => $l->cotizacionId,
            'tipo_linea' => $l->tipoLinea->value,
            'item_id' => $l->itemComercialId,
            'paquete_id' => $l->paqueteId,
            'oferta_item_id' => $l->ofertaItemId,
            'oferta_paquete_id' => $l->ofertaPaqueteId,
            'codigo' => $l->conceptoCodigo,
            'nombre' => $l->conceptoNombre,
            'descripcion' => $l->conceptoDescripcion,
            'unidad' => $l->unidadMedida,
            'cantidad' => $l->cantidad,
            'precio_unitario' => $l->precioUnitario,
            'descuento_tipo' => $l->descuentoTipo->value,
            'descuento_valor' => $l->descuentoValor,
            'descuento_monto' => $l->descuentoMonto,
            'descuento_motivo' => $l->descuentoMotivo,
            'subtotal' => $l->subtotal,
            'moneda' => $l->moneda,
            'orden' => $l->orden,
            'notas' => $l->notas,
        ]);

        $lineaId = (int) $this->pdo->lastInsertId();

        // Guardar componentes si es paquete
        foreach ($l->componentes as $comp) {
            $compEntidad = new CotizacionLineaComponente(
                id: null,
                cotizacionLineaId: $lineaId,
                itemComercialId: $comp->itemComercialId,
                itemCodigo: $comp->itemCodigo,
                itemNombre: $comp->itemNombre,
                itemTipo: $comp->itemTipo,
                unidadMedida: $comp->unidadMedida,
                cantidad: $comp->cantidad,
                nota: $comp->nota,
                orden: $comp->orden
            );
            $this->guardarComponente($compEntidad);
        }

        return $this->buscarLineaPorId($lineaId);
    }

    public function eliminarLinea(int $lineaId, int $cotizacionId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM `cotizacion_lineas`
            WHERE `id` = :id AND `cotizacion_id` = :cotizacion_id
        ");
        $stmt->execute(['id' => $lineaId, 'cotizacion_id' => $cotizacionId]);
        return $stmt->rowCount() > 0;
    }

    public function guardarComponente(CotizacionLineaComponente $comp): CotizacionLineaComponente
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `cotizacion_linea_componentes` (
                `cotizacion_linea_id`, `item_comercial_id`, `item_codigo`, `item_nombre`,
                `item_tipo`, `unidad_medida`, `cantidad`, `nota`, `orden`
            ) VALUES (
                :linea_id, :item_id, :codigo, :nombre,
                :tipo, :unidad, :cantidad, :nota, :orden
            )
        ");

        $stmt->execute([
            'linea_id' => $comp->cotizacionLineaId,
            'item_id' => $comp->itemComercialId,
            'codigo' => $comp->itemCodigo,
            'nombre' => $comp->itemNombre,
            'tipo' => $comp->itemTipo,
            'unidad' => $comp->unidadMedida,
            'cantidad' => $comp->cantidad,
            'nota' => $comp->nota,
            'orden' => $comp->orden,
        ]);

        $compId = (int) $this->pdo->lastInsertId();
        return new CotizacionLineaComponente(
            id: $compId,
            cotizacionLineaId: $comp->cotizacionLineaId,
            itemComercialId: $comp->itemComercialId,
            itemCodigo: $comp->itemCodigo,
            itemNombre: $comp->itemNombre,
            itemTipo: $comp->itemTipo,
            unidadMedida: $comp->unidadMedida,
            cantidad: $comp->cantidad,
            nota: $comp->nota,
            orden: $comp->orden
        );
    }

    public function buscarLineaPorId(int $lineaId): ?CotizacionLinea
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `cotizacion_lineas` WHERE `id` = :id");
        $stmt->execute(['id' => $lineaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $componentes = $this->obtenerComponentes($lineaId);
        return $this->mapearLinea($row, $componentes);
    }

    /**
     * @return CotizacionLinea[]
     */
    public function obtenerLineas(int $cotizacionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cotizacion_lineas` 
            WHERE `cotizacion_id` = :cotizacion_id 
            ORDER BY `orden` ASC, `id` ASC
        ");
        $stmt->execute(['cotizacion_id' => $cotizacionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lineas = [];
        foreach ($rows as $r) {
            $componentes = $this->obtenerComponentes((int) $r['id']);
            $lineas[] = $this->mapearLinea($r, $componentes);
        }
        return $lineas;
    }

    /**
     * @return CotizacionLineaComponente[]
     */
    public function obtenerComponentes(int $cotizacionLineaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `cotizacion_linea_componentes` 
            WHERE `cotizacion_linea_id` = :linea_id 
            ORDER BY `orden` ASC, `id` ASC
        ");
        $stmt->execute(['linea_id' => $cotizacionLineaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $componentes = [];
        foreach ($rows as $r) {
            $componentes[] = new CotizacionLineaComponente(
                id: (int) $r['id'],
                cotizacionLineaId: (int) $r['cotizacion_linea_id'],
                itemComercialId: $r['item_comercial_id'] !== null ? (int) $r['item_comercial_id'] : null,
                itemCodigo: (string) $r['item_codigo'],
                itemNombre: (string) $r['item_nombre'],
                itemTipo: (string) $r['item_tipo'],
                unidadMedida: (string) $r['unidad_medida'],
                cantidad: (float) $r['cantidad'],
                nota: $r['nota'] !== null ? (string) $r['nota'] : null,
                orden: (int) $r['orden'],
                creadoEn: (string) $r['creado_en']
            );
        }
        return $componentes;
    }

    private function mapearCotizacion(array $r, array $lineas): Cotizacion
    {
        return new Cotizacion(
            id: (int) $r['id'],
            organizacionId: (int) $r['organizacion_id'],
            edicionId: (int) $r['edicion_id'],
            clienteId: (int) $r['cliente_id'],
            oportunidadId: $r['oportunidad_id'] !== null ? (int) $r['oportunidad_id'] : null,
            correlativo: $r['correlativo'] !== null ? (string) $r['correlativo'] : null,
            correlativoBase: $r['correlativo_base'] !== null ? (string) $r['correlativo_base'] : null,
            versionNumero: (int) $r['version_numero'],
            cotizacionOrigenId: $r['cotizacion_origen_id'] !== null ? (int) $r['cotizacion_origen_id'] : null,
            cotizacionRaizId: $r['cotizacion_raiz_id'] !== null ? (int) $r['cotizacion_raiz_id'] : null,
            titulo: (string) $r['titulo'],
            estado: EstadoCotizacion::from((string) $r['estado']),
            fechaEmision: $r['fecha_emision'] !== null ? (string) $r['fecha_emision'] : null,
            validoHasta: $r['valido_hasta'] !== null ? (string) $r['valido_hasta'] : null,
            moneda: (string) $r['moneda'],
            subtotal: (float) $r['subtotal'],
            descuentoGlobalTipo: TipoDescuentoCotizacion::from((string) $r['descuento_global_tipo']),
            descuentoGlobalValor: (float) $r['descuento_global_valor'],
            descuentoGlobalMonto: (float) $r['descuento_global_monto'],
            descuentoGlobalMotivo: $r['descuento_global_motivo'] !== null ? (string) $r['descuento_global_motivo'] : null,
            descuentoLineasTotal: (float) $r['descuento_lineas_total'],
            total: (float) $r['total'],
            terminosCondiciones: $r['terminos_condiciones'] !== null ? (string) $r['terminos_condiciones'] : null,
            notasInternas: $r['notas_internas'] !== null ? (string) $r['notas_internas'] : null,
            motivoRechazo: $r['motivo_rechazo'] !== null ? MotivoRechazoCotizacion::from((string) $r['motivo_rechazo']) : null,
            motivoRechazoDetalle: $r['motivo_rechazo_detalle'] !== null ? (string) $r['motivo_rechazo_detalle'] : null,
            motivoAnulacion: $r['motivo_anulacion'] !== null ? MotivoAnulacionCotizacion::from((string) $r['motivo_anulacion']) : null,
            motivoAnulacionDetalle: $r['motivo_anulacion_detalle'] !== null ? (string) $r['motivo_anulacion_detalle'] : null,
            versionBloqueo: (int) $r['version_bloqueo'],
            creadoPor: (int) $r['creado_por'],
            creadoEn: (string) $r['creado_en'],
            actualizadoEn: (string) $r['actualizado_en'],
            lineas: $lineas
        );
    }

    private function mapearLinea(array $r, array $componentes): CotizacionLinea
    {
        return new CotizacionLinea(
            id: (int) $r['id'],
            cotizacionId: (int) $r['cotizacion_id'],
            tipoLinea: TipoLineaCotizacion::from((string) $r['tipo_linea']),
            itemComercialId: $r['item_comercial_id'] !== null ? (int) $r['item_comercial_id'] : null,
            paqueteId: $r['paquete_id'] !== null ? (int) $r['paquete_id'] : null,
            ofertaItemId: $r['oferta_item_id'] !== null ? (int) $r['oferta_item_id'] : null,
            ofertaPaqueteId: $r['oferta_paquete_id'] !== null ? (int) $r['oferta_paquete_id'] : null,
            conceptoCodigo: (string) $r['concepto_codigo'],
            conceptoNombre: (string) $r['concepto_nombre'],
            conceptoDescripcion: $r['concepto_descripcion'] !== null ? (string) $r['concepto_descripcion'] : null,
            unidadMedida: (string) $r['unidad_medida'],
            cantidad: (float) $r['cantidad'],
            precioUnitario: (float) $r['precio_unitario'],
            descuentoTipo: TipoDescuentoCotizacion::from((string) $r['descuento_tipo']),
            descuentoValor: (float) $r['descuento_valor'],
            descuentoMonto: (float) $r['descuento_monto'],
            descuentoMotivo: $r['descuento_motivo'] !== null ? (string) $r['descuento_motivo'] : null,
            subtotal: (float) $r['subtotal'],
            moneda: (string) $r['moneda'],
            orden: (int) $r['orden'],
            notas: $r['notas'] !== null ? (string) $r['notas'] : null,
            creadoEn: (string) $r['creado_en'],
            componentes: $componentes
        );
    }
}

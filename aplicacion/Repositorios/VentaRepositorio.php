<?php

declare(strict_types=1);

namespace Aplicacion\Repositorios;

use Aplicacion\Entidades\Venta;
use Aplicacion\Entidades\VentaLinea;
use Aplicacion\Entidades\VentaLineaComponente;
use Aplicacion\Ventas\EstadoVenta;
use Aplicacion\Ventas\MotivoAnulacionVenta;
use Aplicacion\Ventas\MotivoCancelacionVenta;
use Aplicacion\Ventas\TipoDescuentoVenta;
use Aplicacion\Ventas\TipoLineaVenta;
use Aplicacion\Ventas\TipoOrigenVenta;
use PDO;

/**
 * Repositorio de Persistencia Soberana para el Dominio de Ventas.
 */
class VentaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardar(Venta $v): Venta
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `ventas` (
                `organizacion_id`, `edicion_id`, `cliente_id`, `cotizacion_id`,
                `origen_tipo`, `correlativo`, `fecha_venta`, `estado`,
                `cliente_nombre_completo`, `cliente_tipo_documento`, `cliente_numero_documento`,
                `cliente_telefono`, `cliente_email`, `moneda`, `subtotal`,
                `descuento_global_tipo`, `descuento_global_valor`, `descuento_global_monto`,
                `descuento_global_motivo`, `descuento_lineas_total`, `total`,
                `monto_pagado`, `saldo_pendiente`, `estado_financiero`,
                `terminos_condiciones`, `notas_comerciales`, `motivo_cancelacion`,
                `motivo_cancelacion_detalle`, `motivo_anulacion`, `motivo_anulacion_detalle`,
                `version_bloqueo`, `creado_por`
            ) VALUES (
                :org_id, :edicion_id, :cliente_id, :cotizacion_id,
                :origen_tipo, :correlativo, :fecha_venta, :estado,
                :cliente_nombre, :cliente_tipo_doc, :cliente_num_doc,
                :cliente_tel, :cliente_email, :moneda, :subtotal,
                :desc_global_tipo, :desc_global_val, :desc_global_monto,
                :desc_global_motivo, :desc_lineas_total, :total,
                :monto_pagado, :saldo_pendiente, :estado_financiero,
                :terminos, :notas, :motivo_cancelacion,
                :motivo_cancelacion_det, :motivo_anulacion, :motivo_anulacion_det,
                :version_bloqueo, :creado_por
            )
        ");

        $stmt->execute([
            'org_id'                 => $v->organizacionId,
            'edicion_id'             => $v->edicionId,
            'cliente_id'             => $v->clienteId,
            'cotizacion_id'          => $v->cotizacionId,
            'origen_tipo'            => $v->origenTipo->value,
            'correlativo'            => $v->correlativo,
            'fecha_venta'            => $v->fechaVenta,
            'estado'                 => $v->estado->value,
            'cliente_nombre'         => $v->clienteNombreCompleto,
            'cliente_tipo_doc'       => $v->clienteTipoDocumento,
            'cliente_num_doc'        => $v->clienteNumeroDocumento,
            'cliente_tel'            => $v->clienteTelefono,
            'cliente_email'          => $v->clienteEmail,
            'moneda'                 => $v->moneda,
            'subtotal'               => $v->subtotal,
            'desc_global_tipo'       => $v->descuentoGlobalTipo->value,
            'desc_global_val'        => $v->descuentoGlobalValor,
            'desc_global_monto'      => $v->descuentoGlobalMonto,
            'desc_global_motivo'     => $v->descuentoGlobalMotivo,
            'desc_lineas_total'      => $v->descuentoLineasTotal,
            'total'                  => $v->total,
            'monto_pagado'           => $v->montoPagado,
            'saldo_pendiente'        => $v->saldoPendiente > 0 ? $v->saldoPendiente : ($v->montoPagado > 0 ? max(0, $v->total - $v->montoPagado) : $v->total),
            'estado_financiero'      => $v->estadoFinanciero ?? 'NO_PAGADA',
            'terminos'               => $v->terminosCondiciones,
            'notas'                  => $v->notasComerciales,
            'motivo_cancelacion'     => $v->motivoCancelacion?->value,
            'motivo_cancelacion_det' => $v->motivoCancelacionDetalle,
            'motivo_anulacion'       => $v->motivoAnulacion?->value,
            'motivo_anulacion_det'   => $v->motivoAnulacionDetalle,
            'version_bloqueo'        => $v->versionBloqueo,
            'creado_por'             => $v->creadoPor,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($id, $v->organizacionId);
    }

    public function actualizar(Venta $v): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ventas`
            SET `estado` = :estado,
                `motivo_cancelacion` = :motivo_cancelacion,
                `motivo_cancelacion_detalle` = :motivo_cancelacion_detalle,
                `motivo_anulacion` = :motivo_anulacion,
                `motivo_anulacion_detalle` = :motivo_anulacion_detalle,
                `notas_comerciales` = :notas_comerciales,
                `version_bloqueo` = `version_bloqueo` + 1
            WHERE `id` = :id
              AND `organizacion_id` = :org_id
              AND `version_bloqueo` = :version_bloqueo_esperada
        ");

        $stmt->execute([
            'estado'                     => $v->estado->value,
            'motivo_cancelacion'         => $v->motivoCancelacion?->value,
            'motivo_cancelacion_detalle' => $v->motivoCancelacionDetalle,
            'motivo_anulacion'           => $v->motivoAnulacion?->value,
            'motivo_anulacion_detalle'   => $v->motivoAnulacionDetalle,
            'notas_comerciales'          => $v->notasComerciales,
            'id'                         => $v->id,
            'org_id'                     => $v->organizacionId,
            'version_bloqueo_esperada'   => $v->versionBloqueo,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function guardarLinea(VentaLinea $l): VentaLinea
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `venta_lineas` (
                `venta_id`, `cotizacion_linea_id`, `tipo_linea`, `item_comercial_id`,
                `paquete_id`, `oferta_item_id`, `oferta_paquete_id`, `concepto_codigo`,
                `concepto_nombre`, `concepto_descripcion`, `unidad_medida`, `cantidad`,
                `precio_unitario`, `descuento_tipo`, `descuento_valor`, `descuento_monto`,
                `descuento_motivo`, `subtotal`, `moneda`, `orden`, `notas`
            ) VALUES (
                :venta_id, :cotizacion_linea_id, :tipo_linea, :item_id,
                :paquete_id, :oferta_item_id, :oferta_paquete_id, :codigo,
                :nombre, :descripcion, :unidad, :cantidad,
                :precio_unitario, :desc_tipo, :desc_val, :desc_monto,
                :desc_motivo, :subtotal, :moneda, :orden, :notas
            )
        ");

        $stmt->execute([
            'venta_id'            => $l->ventaId,
            'cotizacion_linea_id' => $l->cotizacionLineaId,
            'tipo_linea'          => $l->tipoLinea->value,
            'item_id'             => $l->itemComercialId,
            'paquete_id'          => $l->paqueteId,
            'oferta_item_id'      => $l->ofertaItemId,
            'oferta_paquete_id'   => $l->ofertaPaqueteId,
            'codigo'              => $l->conceptoCodigo,
            'nombre'              => $l->conceptoNombre,
            'descripcion'         => $l->conceptoDescripcion,
            'unidad'              => $l->unidadMedida,
            'cantidad'            => $l->cantidad,
            'precio_unitario'     => $l->precioUnitario,
            'desc_tipo'           => $l->descuentoTipo->value,
            'desc_val'            => $l->descuentoValor,
            'desc_monto'          => $l->descuentoMonto,
            'desc_motivo'         => $l->descuentoMotivo,
            'subtotal'            => $l->subtotal,
            'moneda'              => $l->moneda,
            'orden'               => $l->orden,
            'notas'               => $l->notas,
        ]);

        $lineaId = (int) $this->pdo->lastInsertId();

        // Guardar componentes si es paquete
        $compGuardados = [];
        if (!empty($l->componentes)) {
            $stmtComp = $this->pdo->prepare("
                INSERT INTO `venta_linea_componentes` (
                    `venta_linea_id`, `item_comercial_id`, `item_codigo`, `item_nombre`,
                    `item_tipo`, `unidad_medida`, `cantidad`, `nota`, `orden`
                ) VALUES (
                    :linea_id, :item_id, :codigo, :nombre,
                    :tipo, :unidad, :cantidad, :nota, :orden
                )
            ");

            foreach ($l->componentes as $c) {
                $stmtComp->execute([
                    'linea_id' => $lineaId,
                    'item_id'  => $c->itemComercialId,
                    'codigo'   => $c->itemCodigo,
                    'nombre'   => $c->itemNombre,
                    'tipo'     => $c->itemTipo,
                    'unidad'   => $c->unidadMedida,
                    'cantidad' => $c->cantidad,
                    'nota'     => $c->nota,
                    'orden'    => $c->orden,
                ]);

                $compId = (int) $this->pdo->lastInsertId();
                $compGuardados[] = new VentaLineaComponente(
                    id: $compId,
                    ventaLineaId: $lineaId,
                    itemComercialId: $c->itemComercialId,
                    itemCodigo: $c->itemCodigo,
                    itemNombre: $c->itemNombre,
                    itemTipo: $c->itemTipo,
                    unidadMedida: $c->unidadMedida,
                    cantidad: $c->cantidad,
                    nota: $c->nota,
                    orden: $c->orden,
                    creadoEn: date('Y-m-d H:i:s')
                );
            }
        }

        return new VentaLinea(
            id: $lineaId,
            ventaId: $l->ventaId,
            cotizacionLineaId: $l->cotizacionLineaId,
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
            creadoEn: date('Y-m-d H:i:s'),
            componentes: $compGuardados
        );
    }

    public function buscarPorId(int $id, int $organizacionId): ?Venta
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ventas`
            WHERE `id` = :id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $lineas = $this->obtenerLineasConComponentes($id);

        return $this->hidratarVenta($fila, $lineas);
    }

    public function buscarPorCotizacionId(int $cotizacionId, int $organizacionId): ?Venta
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ventas`
            WHERE `cotizacion_id` = :cot_id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['cot_id' => $cotizacionId, 'org_id' => $organizacionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $lineas = $this->obtenerLineasConComponentes((int) $fila['id']);
        return $this->hidratarVenta($fila, $lineas);
    }

    public function existePorCotizacionId(int $cotizacionId, int $organizacionId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM `ventas`
            WHERE `cotizacion_id` = :cot_id AND `organizacion_id` = :org_id
            LIMIT 1
        ");
        $stmt->execute(['cot_id' => $cotizacionId, 'org_id' => $organizacionId]);
        return (bool) $stmt->fetchColumn();
    }

    public function generarSiguienteCorrelativo(int $organizacionId, int $anio): string
    {
        // 1. Asegurar registro de secuencia
        $stmtInit = $this->pdo->prepare("
            INSERT IGNORE INTO `ventas_secuencias` (`organizacion_id`, `anio`, `ultimo_numero`)
            VALUES (:org_id, :anio, 0)
        ");
        $stmtInit->execute(['org_id' => $organizacionId, 'anio' => $anio]);

        // 2. Bloqueo de fila exclusivo
        $stmtLock = $this->pdo->prepare("
            SELECT `ultimo_numero`
            FROM `ventas_secuencias`
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
            FOR UPDATE
        ");
        $stmtLock->execute(['org_id' => $organizacionId, 'anio' => $anio]);
        $ultimo = (int) $stmtLock->fetchColumn();

        $siguiente = $ultimo + 1;

        // 3. Incrementar
        $stmtUp = $this->pdo->prepare("
            UPDATE `ventas_secuencias`
            SET `ultimo_numero` = :siguiente
            WHERE `organizacion_id` = :org_id AND `anio` = :anio
        ");
        $stmtUp->execute([
            'siguiente' => $siguiente,
            'org_id'    => $organizacionId,
            'anio'      => $anio,
        ]);

        return sprintf('VTA-%04d-%06d', $anio, $siguiente);
    }

    /**
     * @return VentaLinea[]
     */
    private function obtenerLineasConComponentes(int $ventaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `venta_lineas`
            WHERE `venta_id` = :venta_id
            ORDER BY `orden` ASC, `id` ASC
        ");
        $stmt->execute(['venta_id' => $ventaId]);
        $filasLineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lineas = [];
        foreach ($filasLineas as $fl) {
            $lineaId = (int) $fl['id'];

            $stmtComp = $this->pdo->prepare("
                SELECT * FROM `venta_linea_componentes`
                WHERE `venta_linea_id` = :linea_id
                ORDER BY `orden` ASC, `id` ASC
            ");
            $stmtComp->execute(['linea_id' => $lineaId]);
            $filasComp = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

            $componentes = array_map(function ($fc) {
                return new VentaLineaComponente(
                    id: (int) $fc['id'],
                    ventaLineaId: (int) $fc['venta_linea_id'],
                    itemComercialId: $fc['item_comercial_id'] !== null ? (int) $fc['item_comercial_id'] : null,
                    itemCodigo: (string) $fc['item_codigo'],
                    itemNombre: (string) $fc['item_nombre'],
                    itemTipo: (string) $fc['item_tipo'],
                    unidadMedida: (string) $fc['unidad_medida'],
                    cantidad: (float) $fc['cantidad'],
                    nota: $fc['nota'],
                    orden: (int) $fc['orden'],
                    creadoEn: $fc['creado_en']
                );
            }, $filasComp);

            $lineas[] = new VentaLinea(
                id: $lineaId,
                ventaId: (int) $fl['venta_id'],
                cotizacionLineaId: $fl['cotizacion_linea_id'] !== null ? (int) $fl['cotizacion_linea_id'] : null,
                tipoLinea: TipoLineaVenta::from($fl['tipo_linea']),
                itemComercialId: $fl['item_comercial_id'] !== null ? (int) $fl['item_comercial_id'] : null,
                paqueteId: $fl['paquete_id'] !== null ? (int) $fl['paquete_id'] : null,
                ofertaItemId: $fl['oferta_item_id'] !== null ? (int) $fl['oferta_item_id'] : null,
                ofertaPaqueteId: $fl['oferta_paquete_id'] !== null ? (int) $fl['oferta_paquete_id'] : null,
                conceptoCodigo: (string) $fl['concepto_codigo'],
                conceptoNombre: (string) $fl['concepto_nombre'],
                conceptoDescripcion: $fl['concepto_descripcion'],
                unidadMedida: (string) $fl['unidad_medida'],
                cantidad: (float) $fl['cantidad'],
                precioUnitario: (float) $fl['precio_unitario'],
                descuentoTipo: TipoDescuentoVenta::from($fl['descuento_tipo']),
                descuentoValor: (float) $fl['descuento_valor'],
                descuentoMonto: (float) $fl['descuento_monto'],
                descuentoMotivo: $fl['descuento_motivo'],
                subtotal: (float) $fl['subtotal'],
                moneda: (string) $fl['moneda'],
                orden: (int) $fl['orden'],
                notas: $fl['notas'],
                creadoEn: $fl['creado_en'],
                componentes: $componentes
            );
        }

        return $lineas;
    }

    private function hidratarVenta(array $fila, array $lineas = []): Venta
    {
        return new Venta(
            id: (int) $fila['id'],
            organizacionId: (int) $fila['organizacion_id'],
            edicionId: (int) $fila['edicion_id'],
            clienteId: (int) $fila['cliente_id'],
            cotizacionId: $fila['cotizacion_id'] !== null ? (int) $fila['cotizacion_id'] : null,
            origenTipo: TipoOrigenVenta::from($fila['origen_tipo']),
            correlativo: (string) $fila['correlativo'],
            fechaVenta: (string) $fila['fecha_venta'],
            estado: EstadoVenta::from($fila['estado']),
            clienteNombreCompleto: (string) $fila['cliente_nombre_completo'],
            clienteTipoDocumento: $fila['cliente_tipo_documento'],
            clienteNumeroDocumento: $fila['cliente_numero_documento'],
            clienteTelefono: $fila['cliente_telefono'],
            clienteEmail: $fila['cliente_email'],
            moneda: (string) $fila['moneda'],
            subtotal: (float) $fila['subtotal'],
            descuentoGlobalTipo: TipoDescuentoVenta::from($fila['descuento_global_tipo']),
            descuentoGlobalValor: (float) $fila['descuento_global_valor'],
            descuentoGlobalMonto: (float) $fila['descuento_global_monto'],
            descuentoGlobalMotivo: $fila['descuento_global_motivo'],
            descuentoLineasTotal: (float) $fila['descuento_lineas_total'],
            total: (float) $fila['total'],
            montoPagado: isset($fila['monto_pagado']) ? (float) $fila['monto_pagado'] : 0.00,
            saldoPendiente: isset($fila['saldo_pendiente']) ? (float) $fila['saldo_pendiente'] : (float) $fila['total'],
            estadoFinanciero: $fila['estado_financiero'] ?? 'NO_PAGADA',
            terminosCondiciones: $fila['terminos_condiciones'],
            notasComerciales: $fila['notas_comerciales'],
            motivoCancelacion: $fila['motivo_cancelacion'] !== null ? MotivoCancelacionVenta::from($fila['motivo_cancelacion']) : null,
            motivoCancelacionDetalle: $fila['motivo_cancelacion_detalle'],
            motivoAnulacion: $fila['motivo_anulacion'] !== null ? MotivoAnulacionVenta::from($fila['motivo_anulacion']) : null,
            motivoAnulacionDetalle: $fila['motivo_anulacion_detalle'],
            versionBloqueo: (int) $fila['version_bloqueo'],
            creadoPor: (int) $fila['creado_por'],
            creadoEn: $fila['creado_en'],
            actualizadoEn: $fila['actualizado_en'],
            lineas: $lineas
        );
    }
}

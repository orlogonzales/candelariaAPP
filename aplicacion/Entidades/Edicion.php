<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Ediciones\EstadoEdicion;
use InvalidArgumentException;

/**
 * Entidad de dominio que representa una Edición anual de la Festividad
 * de la Virgen de la Candelaria asociada a una Organización.
 *
 * Invariantes:
 * - Toda edición debe pertenecer obligatoriamente a una organización válida.
 * - El código slug debe ser alfanumérico en minúsculas con guiones.
 * - El nombre de la edición se almacena normalizado en MAYÚSCULAS.
 * - El año calendario debe encontrarse en el rango de gobernanza [2000, 2100].
 * - fecha_inicio debe ser menor o igual a fecha_fin.
 * - El estado debe ser una fase válida de EstadoEdicion.
 */
class Edicion
{
    public readonly int $organizacionId;
    public readonly string $codigo;
    public readonly string $nombre;
    public readonly int $anio;
    public readonly EstadoEdicion $estado;
    public readonly string $fechaInicio;
    public readonly string $fechaFin;
    public readonly ?string $descripcion;
    public readonly bool $esActual;
    public readonly ?string $flyerOficialUrl;
    public readonly ?array $configuracion;
    public readonly ?string $creadoEn;
    public readonly ?string $actualizadoEn;

    public function __construct(
        public readonly ?int $id,
        int $organizacionId,
        string $codigo,
        string $nombre,
        int $anio,
        string $fechaInicio,
        string $fechaFin,
        EstadoEdicion|string $estado = EstadoEdicion::PREOPERACION,
        ?string $descripcion = null,
        bool $esActual = false,
        ?string $flyerOficialUrl = null,
        ?array $configuracion = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->organizacionId   = $this->validarOrganizacionId($organizacionId);
        $this->codigo           = $this->validarYNormalizarCodigo($codigo);
        $this->nombre           = $this->validarYNormalizarNombre($nombre);
        $this->anio             = $this->validarAnio($anio);
        $this->estado           = $this->resolverEstado($estado);
        $this->fechaInicio      = $this->validarFecha($fechaInicio, 'fecha_inicio');
        $this->fechaFin         = $this->validarFecha($fechaFin, 'fecha_fin');
        $this->validarRangoFechas($this->fechaInicio, $this->fechaFin);
        $this->descripcion      = $descripcion !== null ? trim($descripcion) : null;
        $this->esActual         = $esActual;
        $this->flyerOficialUrl  = !empty($flyerOficialUrl) ? trim($flyerOficialUrl) : null;
        $this->configuracion    = $configuracion;
        $this->creadoEn         = $creadoEn;
        $this->actualizadoEn    = $actualizadoEn;
    }

    private function validarOrganizacionId(int $organizacionId): int
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException('El identificador de la organización es obligatorio y debe ser mayor a 0.');
        }
        return $organizacionId;
    }

    private function validarYNormalizarCodigo(string $codigo): string
    {
        $codigoNormalizado = strtolower(trim($codigo));
        if (empty($codigoNormalizado)) {
            throw new InvalidArgumentException('El código identificador de la edición es obligatorio.');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $codigoNormalizado)) {
            throw new InvalidArgumentException("El código '{$codigo}' no tiene un formato slug válido (solo letras minúsculas, números y guiones).");
        }
        if (strlen($codigoNormalizado) > 60) {
            throw new InvalidArgumentException('El código de la edición no puede superar los 60 caracteres.');
        }
        return $codigoNormalizado;
    }

    private function validarYNormalizarNombre(string $nombre): string
    {
        $nombreLimpio = trim($nombre);
        if (empty($nombreLimpio)) {
            throw new InvalidArgumentException('El nombre de la edición es obligatorio.');
        }
        if (strlen($nombreLimpio) < 3 || strlen($nombreLimpio) > 120) {
            throw new InvalidArgumentException('El nombre de la edición debe tener entre 3 y 120 caracteres.');
        }
        return mb_strtoupper($nombreLimpio, 'UTF-8');
    }

    private function validarAnio(int $anio): int
    {
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException("El año de la edición ({$anio}) debe estar comprendido entre los años 2000 y 2100.");
        }
        return $anio;
    }

    private function resolverEstado(EstadoEdicion|string $estado): EstadoEdicion
    {
        if ($estado instanceof EstadoEdicion) {
            return $estado;
        }

        $resuelto = EstadoEdicion::intentarDesde($estado);
        if ($resuelto === null) {
            throw new InvalidArgumentException("El estado '{$estado}' no es un estado válido del ciclo de vida de la edición.");
        }
        return $resuelto;
    }

    private function validarFecha(string $fecha, string $campo): string
    {
        $fechaLimpia = trim($fecha);
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $fechaLimpia);
        if ($d === false || $d->format('Y-m-d') !== $fechaLimpia) {
            throw new InvalidArgumentException("El campo '{$campo}' debe tener el formato canónico YYYY-MM-DD.");
        }
        return $fechaLimpia;
    }

    private function validarRangoFechas(string $inicio, string $fin): void
    {
        if ($inicio > $fin) {
            throw new InvalidArgumentException("Inconsistencia temporal: la fecha de inicio ({$inicio}) no puede ser posterior a la fecha de fin ({$fin}).");
        }
    }

    public function aArreglo(): array
    {
        return [
            'id'                 => $this->id,
            'organizacion_id'    => $this->organizacionId,
            'codigo'             => $this->codigo,
            'nombre'             => $this->nombre,
            'anio'               => $this->anio,
            'estado'             => $this->estado->value,
            'estado_etiqueta'    => $this->estado->etiqueta(),
            'fecha_inicio'       => $this->fechaInicio,
            'fecha_fin'          => $this->fechaFin,
            'descripcion'        => $this->descripcion,
            'es_actual'          => $this->esActual,
            'flyer_oficial_url'  => $this->flyerOficialUrl,
            'configuracion'      => $this->configuracion,
            'creado_en'          => $this->creadoEn,
            'actualizado_en'     => $this->actualizadoEn,
        ];
    }
}

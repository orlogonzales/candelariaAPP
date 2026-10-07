<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\EstadoSalida;
use Aplicacion\Reservas\TipoCapacidad;
use InvalidArgumentException;

/**
 * Entidad de dominio OperacionSalida:
 * Cabecera y unidad de ejecución física y logística de campo.
 */
class OperacionSalida
{
    /**
     * @param OperacionSalidaRecurso[] $recursos
     * @param OperacionSalidaPrestacion[] $prestaciones
     * @param OperacionAsistencia[] $asistencias
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly int $edicionId,
        public readonly int $itemComercialId,
        public readonly string $correlativo,
        public readonly string $titulo,
        public readonly string $fechaSalida,
        public readonly string $horaCitacion,
        public readonly string $horaSalida,
        public readonly ?string $horaInicioReal = null,
        public readonly ?string $horaFinReal = null,
        public readonly string $puntoEncuentro = '',
        public readonly TipoCapacidad $tipoCapacidad = TipoCapacidad::COLECTIVA,
        public readonly ?int $capacidadMaxima = null,
        public readonly EstadoSalida $estado = EstadoSalida::PROGRAMADA,
        public readonly ?string $motivoCancelacionInterrupcion = null,
        public readonly int $versionBloqueo = 1,
        public readonly int $creadoPor = 1,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null,
        public readonly array $recursos = [],
        public readonly array $prestaciones = [],
        public readonly array $asistencias = []
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El organizacionId debe ser un entero positivo.");
        }
        if ($this->edicionId <= 0) {
            throw new InvalidArgumentException("El edicionId debe ser un entero positivo.");
        }
        if ($this->itemComercialId <= 0) {
            throw new InvalidArgumentException("El itemComercialId debe ser un entero positivo.");
        }
        if (trim($this->correlativo) === '') {
            throw new InvalidArgumentException("El correlativo institucional de la salida no puede estar vacío.");
        }
        if (trim($this->titulo) === '') {
            throw new InvalidArgumentException("El título de la salida no puede estar vacío.");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fechaSalida)) {
            throw new InvalidArgumentException("Formato de fecha de salida inválido (debe ser YYYY-MM-DD).");
        }
        if (in_array($this->tipoCapacidad, [TipoCapacidad::COLECTIVA, TipoCapacidad::DISCRETA], true)) {
            if ($this->capacidadMaxima === null || $this->capacidadMaxima <= 0) {
                throw new InvalidArgumentException("Para capacidad COLECTIVA o DISCRETA, la capacidad máxima debe ser mayor a 0.");
            }
        }
    }
}

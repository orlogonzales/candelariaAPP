<?php

declare(strict_types=1);

namespace Aplicacion\Entidades;

use Aplicacion\Operaciones\EstadoRecursoFisico;
use Aplicacion\Operaciones\PropiedadRecurso;
use Aplicacion\Operaciones\TipoRecursoFisico;
use InvalidArgumentException;

/**
 * Entidad de dominio OperacionRecurso:
 * Flota física, embarcación lacustre, vehículo o equipo logístico para ejecución de campo.
 */
class OperacionRecurso
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $organizacionId,
        public readonly TipoRecursoFisico $tipoRecurso,
        public readonly string $codigoInterno,
        public readonly string $nombre,
        public readonly PropiedadRecurso $propiedadTipo,
        public readonly ?int $proveedorId = null,
        public readonly int $capacidadMaxima = 1,
        public readonly ?string $identificacionOficial = null,
        public readonly EstadoRecursoFisico $estado = EstadoRecursoFisico::DISPONIBLE,
        public readonly ?string $notas = null,
        public readonly ?string $creadoEn = null,
        public readonly ?string $actualizadoEn = null
    ) {
        $this->validarInvariantes();
    }

    private function validarInvariantes(): void
    {
        if ($this->organizacionId <= 0) {
            throw new InvalidArgumentException("El organizacionId debe ser un entero positivo.");
        }
        $codigoTrim = trim($this->codigoInterno);
        if ($codigoTrim === '') {
            throw new InvalidArgumentException("El código interno del recurso no puede estar vacío.");
        }
        $nombreTrim = trim($this->nombre);
        if ($nombreTrim === '') {
            throw new InvalidArgumentException("El nombre del recurso no puede estar vacío.");
        }
        if ($this->capacidadMaxima < 1) {
            throw new InvalidArgumentException("La capacidad máxima del recurso debe ser de al menos 1 plaza/unidad.");
        }
        if ($this->propiedadTipo === PropiedadRecurso::EXTERNO && ($this->proveedorId === null || $this->proveedorId <= 0)) {
            throw new InvalidArgumentException("Un recurso de propiedad EXTERNO exige obligatoriamente vincular un proveedor válido.");
        }
        if ($this->propiedadTipo === PropiedadRecurso::PROPIO && $this->proveedorId !== null) {
            throw new InvalidArgumentException("Un recurso de propiedad PROPIO no puede vincularse a un proveedor externo.");
        }
    }
}

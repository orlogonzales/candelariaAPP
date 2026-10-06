<?php

declare(strict_types=1);

namespace Aplicacion\Catalogo;

/**
 * Catálogo controlado y cerrado de unidades de medida para ítems comerciales.
 */
enum UnidadMedidaItem: string
{
    case UNIDAD = 'UNIDAD';
    case PERSONA = 'PERSONA';
    case NOCHE = 'NOCHE';
    case HABITACION = 'HABITACION';
    case TICKET = 'TICKET';
    case SERVICIO = 'SERVICIO';
    case TRAMO = 'TRAMO';
    case DIA = 'DIA';
    case HORA = 'HORA';

    public static function esValida(string $valor): bool
    {
        return self::tryFrom(strtoupper(trim($valor))) !== null;
    }

    /**
     * Retorna la semántica comercial descriptiva de la unidad de medida.
     */
    public function semantica(): string
    {
        return match ($this) {
            self::UNIDAD     => 'Bienes discretos o productos físicos tangibles',
            self::PERSONA    => 'Servicios o admisiones tarifadas por persona',
            self::NOCHE      => 'Alojamiento u hospedaje nocturno',
            self::HABITACION => 'Hospedaje por habitación completa',
            self::TICKET     => 'Entradas, boletería y pases de acceso',
            self::SERVICIO   => 'Prestaciones profesionales y asistencias técnicas',
            self::TRAMO      => 'Transporte, traslados o recorridos de viaje',
            self::DIA        => 'Alquiler o servicios tasados por jornada diaria',
            self::HORA       => 'Servicios o equipos tasados por hora',
        };
    }

    /**
     * Retorna el catálogo completo con código, etiqueta y semántica.
     *
     * @return array<int, array{codigo: string, etiqueta: string, semantica: string}>
     */
    public static function catalogo(): array
    {
        return array_map(fn(self $u) => [
            'codigo'    => $u->value,
            'etiqueta'  => $u->value,
            'semantica' => $u->semantica(),
        ], self::cases());
    }
}

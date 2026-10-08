<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de una cita de la agenda.
 */
enum EstadoCita: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Reprogramada = 'reprogramada';
    case Cancelada = 'cancelada';
    case Completada = 'completada';
    case NoAsistio = 'no_asistio';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::Reprogramada => 'Reprogramada',
            self::Cancelada => 'Cancelada',
            self::Completada => 'Completada',
            self::NoAsistio => 'No asistió',
        };
    }

    /**
     * Estados finales: no se reprograman ni se cancelan de nuevo.
     */
    public function esFinal(): bool
    {
        return in_array($this, [self::Cancelada, self::Completada, self::NoAsistio], true);
    }

    /**
     * Estados que ocupan un espacio en la agenda.
     */
    public function bloqueaAgenda(): bool
    {
        return in_array($this, [self::Pendiente, self::Confirmada, self::Reprogramada], true);
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $estado): array => [$estado->value => $estado->label()])
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Genera slugs únicos y legibles para URLs públicas.
 */
trait GeneraSlug
{
    /**
     * Construye un slug único a partir de uno o varios textos.
     *
     * @param  array<int, string|null>  $partes
     * @param  string  $columna  Columna donde validar la unicidad.
     */
    public static function slugUnico(array $partes, string $columna = 'slug'): string
    {
        $base = Str::slug(implode(' ', array_filter($partes, static fn (?string $p): bool => filled($p))));

        if ($base === '') {
            $base = Str::lower(Str::random(8));
        }

        // Se consulta incluyendo registros eliminados lógicamente: el índice
        // único sí los considera y un slug repetido provocaría un error 500.
        $consulta = in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::withTrashed()
            : static::query();

        $slug = $base;
        $contador = 1;

        while ((clone $consulta)->where($columna, $slug)->exists()) {
            $slug = $base.'-'.(++$contador);
        }

        return $slug;
    }
}

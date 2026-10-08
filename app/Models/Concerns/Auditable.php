<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;

/**
 * Configuración común de auditoría (spatie/laravel-activitylog): sólo se
 * registran los atributos declarados y únicamente si cambiaron.
 */
trait Auditable
{
    /**
     * Opciones del registro de auditoría.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName((string) config('activitylog.default_log_name', 'mercado-inmueble'))
            ->logOnly($this->atributosAuditables())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Atributos que se guardan en el log de auditoría.
     *
     * @return array<int, string>
     */
    abstract protected function atributosAuditables(): array;
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inmobiliaria;

use App\Http\Controllers\Api\Panel\ReporteController as PanelReporteController;

/**
 * Reportes del panel de inmobiliaria: resumen, conversión, ingresos y exportaciones (`/inmobiliaria/reportes`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:inmobiliaria` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class ReporteController extends PanelReporteController
{
}

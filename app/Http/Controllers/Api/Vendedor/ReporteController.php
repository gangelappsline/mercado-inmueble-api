<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\ReporteController as PanelReporteController;

/**
 * Reportes del panel de vendedor: resumen, conversión, ingresos y exportaciones (`/vendedor/reportes`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class ReporteController extends PanelReporteController
{
}

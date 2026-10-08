<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\CitaController as PanelCitaController;

/**
 * Agenda del panel de vendedor: citas, confirmación, reprogramación y cierre (`/vendedor/citas`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class CitaController extends PanelCitaController
{
}

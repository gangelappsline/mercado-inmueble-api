<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\InteresController as PanelInteresController;

/**
 * Bandeja de interesados del panel de vendedor (`/vendedor/interesados`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class InteresController extends PanelInteresController
{
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inmobiliaria;

use App\Http\Controllers\Api\Panel\InteresController as PanelInteresController;

/**
 * Bandeja de interesados del panel de inmobiliaria (`/inmobiliaria/interesados`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:inmobiliaria` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class InteresController extends PanelInteresController
{
}

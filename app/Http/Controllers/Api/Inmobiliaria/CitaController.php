<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inmobiliaria;

use App\Http\Controllers\Api\Panel\CitaController as PanelCitaController;

/**
 * Agenda del panel de inmobiliaria: citas, confirmación, reprogramación y cierre (`/inmobiliaria/citas`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:inmobiliaria` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class CitaController extends PanelCitaController
{
}

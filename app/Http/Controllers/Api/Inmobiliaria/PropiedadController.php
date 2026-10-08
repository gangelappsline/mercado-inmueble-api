<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inmobiliaria;

use App\Http\Controllers\Api\Panel\PropiedadController as PanelPropiedadController;

/**
 * Publicaciones del panel de inmobiliaria: CRUD, fotos, video, publicación y pausa (`/inmobiliaria/propiedades`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:inmobiliaria` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class PropiedadController extends PanelPropiedadController
{
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\PropiedadController as PanelPropiedadController;

/**
 * Publicaciones del panel de vendedor: CRUD, fotos, video, publicación y pausa (`/vendedor/propiedades`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class PropiedadController extends PanelPropiedadController
{
}

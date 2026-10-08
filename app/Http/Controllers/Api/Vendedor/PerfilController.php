<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\PerfilController as PanelPerfilController;

/**
 * Perfil y logotipo del panel de vendedor (`/vendedor/perfil`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class PerfilController extends PanelPerfilController
{
}

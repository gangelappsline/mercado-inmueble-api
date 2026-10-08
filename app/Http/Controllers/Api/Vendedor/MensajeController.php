<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Vendedor;

use App\Http\Controllers\Api\Panel\MensajeController as PanelMensajeController;

/**
 * Mensajes del panel de vendedor: hilos con los clientes (`/vendedor/mensajes`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:vendedor` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class MensajeController extends PanelMensajeController
{
}

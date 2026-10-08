<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inmobiliaria;

use App\Http\Controllers\Api\Panel\MensajeController as PanelMensajeController;

/**
 * Mensajes del panel de inmobiliaria: hilos con los clientes (`/inmobiliaria/mensajes`).
 *
 * Hereda el comportamiento común del panel; el middleware `role:inmobiliaria` de las
 * rutas garantiza que sólo este rol acceda a los endpoints.
 */
class MensajeController extends PanelMensajeController
{
}

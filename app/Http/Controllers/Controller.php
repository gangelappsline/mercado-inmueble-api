<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Controlador base de la API: expone `$this->authorize()` (policies) a todos
 * los controladores hijos.
 */
abstract class Controller
{
    use AuthorizesRequests;
}

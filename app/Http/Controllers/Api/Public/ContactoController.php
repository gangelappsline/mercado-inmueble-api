<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\StoreContactoRequest;
use App\Services\ContactoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Formulario público de contacto con la plataforma.
 */
class ContactoController extends Controller
{
    public function __construct(
        private readonly ContactoService $contactos,
    ) {
    }

    /**
     * Registra el mensaje y notifica al equipo de soporte.
     */
    public function store(StoreContactoRequest $request): JsonResponse
    {
        $contacto = $this->contactos->registrar($request->validated(), $request);

        return ApiResponse::created(
            ['id' => $contacto->getKey()],
            __('messages.contacto_recibido'),
        );
    }
}

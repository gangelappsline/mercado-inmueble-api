<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorito;

use App\Http\Requests\ApiFormRequest;

/**
 * Alta y actualización de un favorito del cliente.
 */
class FavoritoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'propiedad_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'exists:propiedades,id'],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'propiedad_id.required' => 'Indica la propiedad que quieres guardar.',
            'propiedad_id.exists' => 'La propiedad indicada no existe.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro de una cuenta con rol `vendedor` (persona natural).
 */
class RegisterVendedorRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],

            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'dni' => ['required', 'string', 'min:5', 'max:30', Rule::unique('vendedores', 'dni')],
            'telefono' => ['nullable', 'string', 'max:30'],
            'telefono_alternativo' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'estado_provincia' => ['nullable', 'string', 'max:120'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:-18 years'],
            'biografia' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta registrada con este correo electrónico.',
            'dni.unique' => 'Ya existe un vendedor registrado con este documento.',
            'fecha_nacimiento.before' => 'Debes ser mayor de edad para publicar propiedades.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'dni' => 'documento de identidad',
        ];
    }
}

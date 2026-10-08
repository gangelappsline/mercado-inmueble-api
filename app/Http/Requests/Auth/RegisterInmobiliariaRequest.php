<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro de una cuenta con rol `inmobiliaria`.
 */
class RegisterInmobiliariaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Cuenta
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],

            // Perfil de inmobiliaria
            'razon_social' => ['required', 'string', 'min:3', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'ruc' => ['required', 'string', 'min:6', 'max:30', Rule::unique('inmobiliarias', 'ruc')],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'web' => ['nullable', 'url', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'ciudad' => ['required', 'string', 'max:120'],
            'estado_provincia' => ['required', 'string', 'max:120'],
            'pais' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta registrada con este correo electrónico.',
            'ruc.unique' => 'Ya existe una inmobiliaria registrada con este RUC/NIT.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del responsable',
            'razon_social' => 'razón social',
            'ruc' => 'RUC/NIT',
        ];
    }
}

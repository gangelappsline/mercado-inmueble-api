<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta de una cuenta de administrador desde el panel (`POST /admin/usuarios`).
 *
 * Para los roles con perfil extendido (inmobiliaria, vendedor, cliente) el
 * panel reutiliza los Form Requests del registro público, de modo que las
 * reglas sean exactamente las mismas; este request cubre el rol
 * `administrador`, que no tiene perfil extendido.
 */
class StoreUsuarioRequest extends ApiFormRequest
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
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta registrada con este correo electrónico.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}

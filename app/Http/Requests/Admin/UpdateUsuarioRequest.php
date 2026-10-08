<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Edición administrativa de una cuenta (`PATCH /admin/usuarios/{usuario}`).
 *
 * El estado (`is_active`) y el rol no se editan aquí: tienen endpoints propios
 * (`/activar`, `/desactivar`, `/rol`) porque aplican reglas de protección del
 * panel (mínimo de administradores activos, perfil compatible, revocación de
 * tokens).
 */
class UpdateUsuarioRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $objetivo */
        $objetivo = $this->route('usuario');

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'email' => [
                'sometimes', 'string', 'email:rfc', 'max:180',
                Rule::unique('users', 'email')->ignore($objetivo?->getKey()),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'string', 'confirmed', Password::defaults()],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe otra cuenta registrada con este correo electrónico.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }

    /**
     * Campos validados (una contraseña nueva llega ya confirmada y se descarta
     * la confirmación antes de persistir).
     *
     * @return array<string, mixed>
     */
    public function datosDeLaCuenta(): array
    {
        return collect($this->validated())
            ->except('password_confirmation')
            ->all();
    }
}

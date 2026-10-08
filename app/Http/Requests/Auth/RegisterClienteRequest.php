<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\Moneda;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro de una cuenta con rol `cliente`.
 */
class RegisterClienteRequest extends ApiFormRequest
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

            'telefono' => ['nullable', 'string', 'max:30'],
            'presupuesto_min' => ['nullable', 'numeric', 'min:0'],
            'presupuesto_max' => ['nullable', 'numeric', 'min:0', 'gte:presupuesto_min'],
            'moneda' => ['nullable', Rule::enum(Moneda::class)],
            'tipo_propiedad_interes' => ['nullable', Rule::enum(TipoPropiedad::class)],
            'ciudad_interes' => ['nullable', 'string', 'max:120'],
            'habitaciones_min' => ['nullable', 'integer', 'between:0,30'],
            'acepta_terminos' => ['accepted'],
            'recibe_novedades' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta registrada con este correo electrónico.',
            'presupuesto_max.gte' => 'El presupuesto máximo debe ser mayor o igual al mínimo.',
            'acepta_terminos.accepted' => 'Debes aceptar los términos y condiciones.',
        ];
    }
}

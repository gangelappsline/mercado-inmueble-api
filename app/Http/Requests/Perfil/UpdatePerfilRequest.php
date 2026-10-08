<?php

declare(strict_types=1);

namespace App\Http\Requests\Perfil;

use App\Enums\Moneda;
use App\Enums\Role;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualización del perfil propio. Las reglas se resuelven según el rol del
 * usuario autenticado (inmobiliaria, vendedor o cliente).
 */
class UpdatePerfilRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rol = $this->user()?->role;

        $comunes = [
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => [
                'sometimes', 'string', 'email', 'max:180',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
            ],
        ];

        return array_merge($comunes, match ($rol) {
            Role::Inmobiliaria => $this->reglasInmobiliaria(),
            Role::Vendedor => $this->reglasVendedor(),
            Role::Cliente => $this->reglasCliente(),
            default => [],
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ese correo ya está en uso por otra cuenta.',
            'ruc.unique' => 'Ese RUC/NIT ya está registrado por otra inmobiliaria.',
            'dni.unique' => 'Ese documento ya está registrado.',
            'presupuesto_max.gte' => 'El presupuesto máximo debe ser mayor o igual al mínimo.',
        ];
    }

    /**
     * Campos de cuenta y perfil separados para actualizar cada tabla.
     *
     * @return array{usuario: array<string, mixed>, perfil: array<string, mixed>}
     */
    public function datosSeparados(): array
    {
        $datos = $this->validated();
        $deUsuario = array_intersect_key($datos, array_flip(['name', 'phone', 'email']));

        return [
            'usuario' => $deUsuario,
            'perfil' => array_diff_key($datos, $deUsuario),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasInmobiliaria(): array
    {
        return [
            'razon_social' => ['sometimes', 'string', 'min:3', 'max:200'],
            'nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:200'],
            'ruc' => [
                'sometimes', 'string', 'min:6', 'max:30',
                Rule::unique('inmobiliarias', 'ruc')->ignore($this->user()?->inmobiliaria?->getKey()),
            ],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'telefono_alternativo' => ['sometimes', 'nullable', 'string', 'max:30'],
            'web' => ['sometimes', 'nullable', 'url', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ciudad' => ['sometimes', 'string', 'max:120'],
            'estado_provincia' => ['sometimes', 'string', 'max:120'],
            'pais' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasVendedor(): array
    {
        return [
            'nombres' => ['sometimes', 'string', 'max:120'],
            'apellidos' => ['sometimes', 'string', 'max:120'],
            'dni' => [
                'sometimes', 'string', 'min:5', 'max:30',
                Rule::unique('vendedores', 'dni')->ignore($this->user()?->vendedor?->getKey()),
            ],
            'telefono' => ['sometimes', 'string', 'max:30'],
            'telefono_alternativo' => ['sometimes', 'nullable', 'string', 'max:30'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:120'],
            'estado_provincia' => ['sometimes', 'nullable', 'string', 'max:120'],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date', 'before:-18 years'],
            'biografia' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasCliente(): array
    {
        return [
            'telefono' => ['sometimes', 'string', 'max:30'],
            'telefono_alternativo' => ['sometimes', 'nullable', 'string', 'max:30'],
            'presupuesto_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'presupuesto_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:presupuesto_min'],
            'moneda' => ['sometimes', Rule::enum(Moneda::class)],
            'tipo_propiedad_interes' => ['sometimes', 'nullable', Rule::enum(TipoPropiedad::class)],
            'ciudad_interes' => ['sometimes', 'nullable', 'string', 'max:120'],
            'habitaciones_min' => ['sometimes', 'nullable', 'integer', 'between:0,30'],
            'preferencias' => ['sometimes', 'array'],
            'preferencias.*' => ['nullable'],
            'recibe_novedades' => ['sometimes', 'boolean'],
        ];
    }
}

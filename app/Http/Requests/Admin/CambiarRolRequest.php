<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Reasignación del rol de una cuenta (`PATCH /admin/usuarios/{usuario}/rol`).
 */
class CambiarRolRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(Role::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'rol'];
    }

    /**
     * Rol solicitado en la petición.
     */
    public function rol(): Role
    {
        return Role::from((string) $this->validated('role'));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

/**
 * Inicio de sesión (email + password → tokens OAuth2).
 */
class LoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:120'],
        ];
    }
}

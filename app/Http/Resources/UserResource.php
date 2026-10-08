<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cliente;
use App\Models\Inmobiliaria;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Cuenta de usuario (`users`) con su rol y, cuando se precarga, su perfil
 * extendido (inmobiliaria, vendedor o cliente).
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => [
                'value' => $this->role->value,
                'label' => $this->role->label(),
            ],
            'avatar_url' => $this->avatarUrl(),
            'is_active' => (bool) $this->is_active,
            'email_verificado' => $this->email_verified_at !== null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'perfil' => $this->perfilCuandoCargado($request),
        ];
    }

    /**
     * URL pública del avatar (disk `public`), si existe.
     */
    private function avatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    /**
     * Perfil polimórfico resuelto con el Resource del rol.
     *
     * @return array<string, mixed>|null
     */
    private function perfilCuandoCargado(Request $request): ?array
    {
        if (! $this->relationLoaded('perfil') || $this->perfil === null) {
            return null;
        }

        return match (true) {
            $this->perfil instanceof Inmobiliaria => (new InmobiliariaResource($this->perfil))->resolve($request),
            $this->perfil instanceof Vendedor => (new VendedorResource($this->perfil))->resolve($request),
            $this->perfil instanceof Cliente => (new ClienteResource($this->perfil))->resolve($request),
            default => null,
        };
    }
}

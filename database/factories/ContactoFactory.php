<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contacto>
 */
class ContactoFactory extends Factory
{
    protected $model = Contacto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->safeEmail(),
            'telefono' => fake()->optional()->numerify('+591 7#######'),
            'asunto' => fake()->randomElement([
                'Consulta sobre el servicio',
                'Problema con mi publicación',
                'Sugerencia',
                'Quiero publicar mi propiedad',
            ]),
            'mensaje' => fake()->paragraph(3),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'atendido' => false,
            'atendido_en' => null,
        ];
    }

    /**
     * Mensaje ya atendido por soporte.
     */
    public function atendido(): static
    {
        return $this->state(fn (): array => ['atendido' => true, 'atendido_en' => now()]);
    }
}

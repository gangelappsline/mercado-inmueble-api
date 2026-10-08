<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoPropiedad;
use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Models\Amenidad;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use App\Models\PropiedadVideo;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Propiedad>
 */
class PropiedadFactory extends Factory
{
    protected $model = Propiedad::class;

    /**
     * Ciudades de demostración con su departamento y coordenadas.
     *
     * @var array<string, array{departamento: string, lat: float, lng: float}>
     */
    public const CIUDADES = [
        'La Paz' => ['departamento' => 'La Paz', 'lat' => -16.4897, 'lng' => -68.1193],
        'Santa Cruz de la Sierra' => ['departamento' => 'Santa Cruz', 'lat' => -17.7833, 'lng' => -63.1821],
        'Cochabamba' => ['departamento' => 'Cochabamba', 'lat' => -17.3895, 'lng' => -66.1568],
        'El Alto' => ['departamento' => 'La Paz', 'lat' => -16.5000, 'lng' => -68.1500],
        'Sucre' => ['departamento' => 'Chuquisaca', 'lat' => -19.0333, 'lng' => -65.2627],
        'Tarija' => ['departamento' => 'Tarija', 'lat' => -21.5355, 'lng' => -64.7296],
        'Oruro' => ['departamento' => 'Oruro', 'lat' => -17.9833, 'lng' => -67.1500],
        'Trinidad' => ['departamento' => 'Beni', 'lat' => -14.8333, 'lng' => -64.9000],
    ];

    /**
     * Barrios por ciudad para títulos realistas.
     *
     * @var array<string, array<int, string>>
     */
    public const BARRIOS = [
        'La Paz' => ['Sopocachi', 'San Miguel', 'Calacoto', 'Miraflores', 'Obrajes', 'Villa Fátima'],
        'Santa Cruz de la Sierra' => ['Equipetrol', 'Sirari', 'Las Palmas', 'Urbarí', 'Hamacas'],
        'Cochabamba' => ['Cala Cala', 'Queru Queru', 'Sarco', 'Tiquipaya', 'Muyurina'],
        'El Alto' => ['Ciudad Satélite', 'Villa Adela', 'Río Seco'],
        'Sucre' => ['Centro Histórico', 'Sopocachi', 'Las Delicias'],
        'Tarija' => ['El Molino', 'San Martín', 'Los Parrales'],
        'Oruro' => ['Centro', 'Norte', 'Vinto'],
        'Trinidad' => ['Centro', 'Los Lirios', 'Puerto Almacén'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ciudad = fake()->randomElement(array_keys(self::CIUDADES));
        $coordenadas = self::CIUDADES[$ciudad];
        $barrio = fake()->randomElement(self::BARRIOS[$ciudad] ?? ['Centro']);

        $tipo = fake()->randomElement([
            TipoPropiedad::Casa,
            TipoPropiedad::Departamento,
            TipoPropiedad::Departamento,
            TipoPropiedad::Terreno,
            TipoPropiedad::LocalComercial,
            TipoPropiedad::Oficina,
        ]);

        $operacion = fake()->randomElement([OperacionPropiedad::Venta, OperacionPropiedad::Venta, OperacionPropiedad::Renta]);
        $moneda = $operacion === OperacionPropiedad::Renta || fake()->boolean(60) ? Moneda::Bob : Moneda::Usd;

        $habitaciones = $tipo->tieneAmbientes() ? fake()->numberBetween(1, 5) : 0;
        $banos = $tipo->tieneAmbientes() ? max(1, $habitaciones - fake()->numberBetween(0, 2)) : 0;
        $estacionamientos = $tipo->tieneAmbientes() ? fake()->numberBetween(0, 3) : fake()->numberBetween(0, 10);
        $areaTotal = match ($tipo) {
            TipoPropiedad::Terreno => fake()->randomFloat(2, 200, 3000),
            TipoPropiedad::Casa => fake()->randomFloat(2, 120, 600),
            TipoPropiedad::LocalComercial, TipoPropiedad::Oficina => fake()->randomFloat(2, 40, 400),
            default => fake()->randomFloat(2, 45, 260),
        };

        $precioBase = (float) $areaTotal * fake()->randomFloat(2, 350, 1400);
        $precio = match ($operacion) {
            OperacionPropiedad::Renta => round($precioBase / 500, -2),
            OperacionPropiedad::Anticretico => round($precioBase, -3),
            default => round($precioBase, -3),
        };

        $descripcion = implode("\n\n", [
            fake()->paragraph(4),
            sprintf(
                'La propiedad cuenta con %s, ideal para %s. Zona %s con acceso a transporte público, colegios y comercios.',
                $tipo->tieneAmbientes()
                    ? sprintf('%d dormitorios, %d baños y %d estacionamientos', $habitaciones, $banos, $estacionamientos)
                    : 'amplio frente sobre avenida principal',
                fake()->randomElement(['familias', 'inversión', 'oficinas', 'vivienda permanente']),
                $barrio,
            ),
            fake()->paragraph(3),
        ]);

        return [
            'propietario_type' => (new Inmobiliaria)->getMorphClass(),
            'propietario_id' => Inmobiliaria::factory(),
            'titulo' => sprintf(
                '%s %s en %s · %s',
                $tipo->label(),
                $tipo->tieneAmbientes() ? sprintf('%d dorm.', $habitaciones) : '',
                strtolower($operacion->label()),
                $barrio,
            ),
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'operacion' => $operacion,
            'estado' => EstadoPropiedad::Borrador,
            'destacada' => fake()->boolean(20),
            'precio' => max($precio, 500),
            'moneda' => $moneda,
            'expensas' => $tipo === TipoPropiedad::Departamento ? fake()->numberBetween(100, 600) : null,
            'precio_negociable' => fake()->boolean(80),
            'area_total' => $areaTotal,
            'area_construida' => $tipo === TipoPropiedad::Terreno ? null : round($areaTotal * fake()->randomFloat(2, 0.6, 0.95), 2),
            'habitaciones' => $habitaciones,
            'banos' => $banos,
            'estacionamientos' => $estacionamientos,
            'piso' => $tipo === TipoPropiedad::Departamento ? fake()->numberBetween(1, 12) : null,
            'anio_construccion' => $tipo === TipoPropiedad::Terreno ? null : fake()->numberBetween(1990, (int) date('Y')),
            'amoblado' => fake()->boolean(25),
            'direccion' => sprintf('%s %d, %s', fake()->streetName(), fake()->numberBetween(10, 4000), $barrio),
            'ciudad' => $ciudad,
            'estado_provincia' => $coordenadas['departamento'],
            'pais' => 'Bolivia',
            'codigo_postal' => fake()->optional()->numerify('####'),
            'latitud' => round($coordenadas['lat'] + fake()->randomFloat(5, -0.03, 0.03), 7),
            'longitud' => round($coordenadas['lng'] + fake()->randomFloat(5, -0.03, 0.03), 7),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Publicada y visible en el catálogo público.
     */
    public function publicada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoPropiedad::Publicada,
            'publicada_en' => now()->subDays(fake()->numberBetween(1, 45)),
        ]);
    }

    /**
     * En borrador (no visible).
     */
    public function borrador(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoPropiedad::Borrador, 'publicada_en' => null]);
    }

    /**
     * Publicación pausada.
     */
    public function pausada(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoPropiedad::Pausada]);
    }

    /**
     * Operación cerrada (vendida).
     */
    public function vendida(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoPropiedad::Vendida,
            'vendida_en' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Propiedad destacada en portada.
     */
    public function destacada(): static
    {
        return $this->state(fn (): array => ['destacada' => true]);
    }

    /**
     * Propiedad publicada por un vendedor particular (no inmobiliaria).
     */
    public function deVendedor(?Vendedor $vendedor = null): static
    {
        return $this->state(fn (): array => [
            'propietario_type' => (new Vendedor)->getMorphClass(),
            'propietario_id' => $vendedor?->getKey() ?? Vendedor::factory(),
        ]);
    }

    /**
     * Asigna el propietario explícitamente (morfología inmobiliaria|vendedor).
     */
    public function delPropietario(Model $propietario): static
    {
        return $this->state(fn (): array => [
            'propietario_type' => $propietario->getMorphClass(),
            'propietario_id' => $propietario->getKey(),
        ]);
    }

    /**
     * Ubica la propiedad en una ciudad conocida (para búsquedas por ubicación).
     */
    public function enCiudad(string $ciudad): static
    {
        $coordenadas = self::CIUDADES[$ciudad] ?? self::CIUDADES['La Paz'];

        return $this->state(fn (): array => [
            'ciudad' => $ciudad,
            'estado_provincia' => $coordenadas['departamento'],
            'latitud' => $coordenadas['lat'],
            'longitud' => $coordenadas['lng'],
        ]);
    }

    /**
     * Coordenadas exactas (para probar la búsqueda por cercanía).
     */
    public function conCoordenadas(float $latitud, float $longitud): static
    {
        return $this->state(fn (): array => ['latitud' => $latitud, 'longitud' => $longitud]);
    }

    /**
     * Precio y moneda explícitos (para probar filtros de rango).
     */
    public function conPrecio(float $precio, ?Moneda $moneda = null): static
    {
        return $this->state(fn (): array => [
            'precio' => $precio,
            'moneda' => $moneda ?? Moneda::Bob,
        ]);
    }

    /**
     * Fotos de demostración (una de ellas principal).
     */
    public function conFotos(int $cantidad = 4): static
    {
        return $this->afterCreating(function (Propiedad $propiedad) use ($cantidad): void {
            PropiedadFoto::factory()
                ->count($cantidad)
                ->sequence(fn ($secuencia) => ['orden' => $secuencia->index, 'es_principal' => $secuencia->index === 0])
                ->for($propiedad)
                ->create();
        });
    }

    /**
     * Video de demostración (máximo uno por propiedad).
     */
    public function conVideo(): static
    {
        return $this->afterCreating(function (Propiedad $propiedad): void {
            PropiedadVideo::factory()->for($propiedad)->create();
        });
    }

    /**
     * Amenidades del catálogo.
     */
    public function conAmenidades(int $cantidad = 5): static
    {
        return $this->afterCreating(function (Propiedad $propiedad) use ($cantidad): void {
            $propiedad->amenidades()->sync(
                Amenidad::query()->inRandomOrder()->limit($cantidad)->pluck('id')->all()
            );
        });
    }

    /**
     * Propiedad lista para publicar: publicada, con fotos y amenidades.
     */
    public function completa(): static
    {
        return $this->publicada()->conFotos(5)->conAmenidades(6);
    }
}

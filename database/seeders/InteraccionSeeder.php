<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoCita;
use App\Enums\EstadoInteres;
use App\Enums\Role;
use App\Enums\TipoReporte;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Favorito;
use App\Models\Hilo;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\Reporte;
use Illuminate\Database\Seeder;

/**
 * Actividad comercial de demostración: intereses con sus hilos de mensajes,
 * citas de la agenda, favoritos, reportes agregados y contactos del formulario.
 */
class InteraccionSeeder extends Seeder
{
    /**
     * Crea la actividad comercial demo sobre las propiedades publicadas.
     */
    public function run(): void
    {
        /** @var array<int, Cliente> $clientes */
        $clientes = Cliente::with('user')->orderBy('id')->get()->all();
        /** @var array<int, Propiedad> $publicadas */
        $publicadas = Propiedad::publicadas()->with(['propietario', 'amenidades'])->get()->all();

        if ($clientes === [] || $publicadas === []) {
            $this->command?->warn('⚠ Interacciones: se necesitan clientes y propiedades publicadas.');

            return;
        }

        $intereses = 0;
        $citas = 0;

        foreach ($publicadas as $indice => $propiedad) {
            $cantidad = $indice % 3; // 0, 1 o 2 interesados por propiedad
            $seleccionados = [];

            for ($i = 0; $i < $cantidad; $i++) {
                $cliente = $clientes[($indice + ($i * 3)) % count($clientes)];

                if (in_array($cliente->getKey(), $seleccionados, true)) {
                    continue;
                }

                $seleccionados[] = $cliente->getKey();

                $factory = match (($indice + $i) % 3) {
                    0 => Interes::factory(),
                    1 => Interes::factory()->atendido(),
                    default => Interes::factory()->cerrado(),
                };

                $interes = $factory->create([
                    'propiedad_id' => $propiedad->getKey(),
                    'cliente_id' => $cliente->getKey(),
                ]);

                $intereses++;

                $hilo = Hilo::factory()->paraInteres($interes, $propiedad)->create();

                $this->crearConversacion($hilo, $interes, $cliente, $propiedad);
                $citas += $this->crearCita($interes, $indice + $i);
            }

            $this->crearFavoritos($propiedad, $clientes);
            $this->crearReportes($propiedad);
        }

        Contacto::factory()->count(5)->create();
        Contacto::factory()->count(3)->atendido()->create();

        $this->command?->info(sprintf('✔ Interacciones: %d intereses, %d citas, %d favoritos, %d reportes y 8 contactos.', $intereses, $citas, Favorito::count(), Reporte::count()));
    }

    /**
     * Crea el intercambio de mensajes del hilo y actualiza sus contadores.
     *
     * @param  Hilo  $hilo  Hilo recién creado
     * @param  Interes  $interes  Interés del que nace el hilo
     * @param  Cliente  $cliente  Cliente que consulta
     * @param  Propiedad  $propiedad  Propiedad anunciada
     */
    private function crearConversacion(Hilo $hilo, Interes $interes, Cliente $cliente, Propiedad $propiedad): void
    {
        $propietario = $propiedad->propietario;
        $userPropietario = $propietario?->user;
        $rolPropietario = $propietario instanceof \App\Models\Inmobiliaria ? Role::Inmobiliaria : Role::Vendedor;

        Mensaje::factory()->delCliente($cliente->user)->create([
            'hilo_id' => $hilo->getKey(),
            'cuerpo' => $interes->mensaje,
        ]);
        $hilo->registrarMensaje(Role::Cliente);

        if ($userPropietario !== null) {
            Mensaje::factory()->delPropietario($userPropietario)->create([
                'hilo_id' => $hilo->getKey(),
                'cuerpo' => 'Gracias por tu interés. La propiedad sigue disponible; puedo coordinar una visita esta semana.',
            ]);
            $hilo->registrarMensaje($rolPropietario);
        }

        if ($hilo->total_mensajes > 1) {
            // El anunciante ya leyó la consulta.
            $hilo->marcarLeidoPor($rolPropietario);
        }

        $hilo->forceFill(['asunto' => $hilo->asuntoPara($propiedad)])->saveQuietly();
    }

    /**
     * Crea la cita asociada al interés (con estado variable) y devuelve 1 si se creó.
     */
    private function crearCita(Interes $interes, int $semilla): int
    {
        if ($semilla % 3 !== 0) {
            return 0;
        }

        $propiedad = $interes->propiedad;
        $estado = match ($semilla % 4) {
            0 => EstadoCita::Pendiente,
            1 => EstadoCita::Confirmada,
            2 => EstadoCita::Completada,
            default => EstadoCita::Cancelada,
        };

        $factory = match ($estado) {
            EstadoCita::Confirmada => Cita::factory()->confirmada(),
            EstadoCita::Completada => Cita::factory()->completada(),
            EstadoCita::Cancelada => Cita::factory()->cancelada(),
            default => Cita::factory(),
        };

        if ($semilla % 5 === 0) {
            $factory = $factory->virtual();
        }

        $factory->create([
            'propiedad_id' => $propiedad?->getKey(),
            'cliente_id' => $interes->cliente_id,
            'interes_id' => $interes->getKey(),
            'propietario_type' => $propiedad?->propietario_type,
            'propietario_id' => $propiedad?->propietario_id,
        ]);

        return 1;
    }

    /**
     * Guarda entre cero y tres favoritos del cliente sobre la propiedad.
     *
     * @param  array<int, Cliente>  $clientes
     */
    private function crearFavoritos(Propiedad $propiedad, array $clientes): void
    {
        foreach ($clientes as $posicion => $cliente) {
            if (($propiedad->getKey() + $posicion) % 4 !== 0) {
                continue;
            }

            Favorito::factory()->create([
                'cliente_id' => $cliente->getKey(),
                'propiedad_id' => $propiedad->getKey(),
                'nota' => $posicion % 2 === 0 ? 'Comparar con otras opciones del mismo barrio.' : null,
            ]);
        }
    }

    /**
     * Genera los reportes diarios de los últimos diez días para la propiedad.
     */
    private function crearReportes(Propiedad $propiedad): void
    {
        if (! $propiedad->estaPublicada()) {
            return;
        }

        $propietario = $propiedad->propietario;

        for ($dia = 0; $dia < 10; $dia++) {
            $fecha = now()->subDays($dia)->toDateString();
            $vistas = random_int(3, 60);

            Reporte::factory()->deTipo(TipoReporte::Vista)->enFecha($fecha)->create([
                'propietario_type' => $propiedad->propietario_type,
                'propietario_id' => $propiedad->propietario_id,
                'propiedad_id' => $propiedad->getKey(),
                'cantidad' => $vistas,
            ]);

            Reporte::factory()->deTipo(TipoReporte::Contacto)->enFecha($fecha)->create([
                'propietario_type' => $propiedad->propietario_type,
                'propietario_id' => $propiedad->propietario_id,
                'propiedad_id' => $propiedad->getKey(),
                'cantidad' => (int) round($vistas * 0.1),
            ]);

            if ($dia % 5 === 0 && $propietario !== null) {
                Reporte::factory()->deTipo(TipoReporte::Conversion)->enFecha($fecha)->create([
                    'propietario_type' => $propiedad->propietario_type,
                    'propietario_id' => $propiedad->propietario_id,
                    'propiedad_id' => $propiedad->getKey(),
                    'cantidad' => 1,
                    'valor' => (float) $propiedad->precio,
                    'metadata' => ['moneda' => $propiedad->moneda?->value],
                ]);
            }
        }
    }
}

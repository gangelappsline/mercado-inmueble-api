<?php

declare(strict_types=1);

use App\Enums\EstadoPropiedad;
use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propiedad: entidad central del dominio. El dueño se resuelve de forma
 * polimórfica (Inmobiliaria | Vendedor) mediante `propietario`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedades', function (Blueprint $table): void {
            $table->id();

            // Dueño polimórfico: inmobiliaria | vendedor
            $table->morphs('propietario');

            $table->string('codigo', 20)->unique()->comment('Código público de la publicación');
            $table->string('slug', 220)->unique();
            $table->string('titulo', 200);
            $table->text('descripcion');

            $table->enum('tipo', TipoPropiedad::valores());
            $table->enum('operacion', OperacionPropiedad::valores());
            $table->enum('estado', EstadoPropiedad::valores())->default(EstadoPropiedad::Borrador->value);
            $table->boolean('destacada')->default(false);

            $table->decimal('precio', 14, 2);
            $table->enum('moneda', Moneda::valores())->default(Moneda::Bob->value);
            $table->decimal('expensas', 12, 2)->nullable()->comment('Gastos comunes mensuales');
            $table->boolean('precio_negociable')->default(true);

            $table->decimal('area_total', 10, 2)->comment('Metros cuadrados del terreno');
            $table->decimal('area_construida', 10, 2)->nullable();
            $table->unsignedTinyInteger('habitaciones')->default(0);
            $table->unsignedTinyInteger('banos')->default(0);
            $table->unsignedTinyInteger('estacionamientos')->default(0);
            $table->unsignedSmallInteger('piso')->nullable();
            $table->unsignedSmallInteger('anio_construccion')->nullable();
            $table->boolean('amoblado')->default(false);

            // Ubicación
            $table->string('direccion', 255);
            $table->string('ciudad', 120);
            $table->string('estado_provincia', 120);
            $table->string('pais', 80)->default('Bolivia');
            $table->string('codigo_postal', 20)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // Contadores desnormalizados (reportes rápidos y listados)
            $table->unsignedInteger('vistas_count')->default(0);
            $table->unsignedInteger('contactos_count')->default(0);
            $table->unsignedInteger('favoritos_count')->default(0);
            $table->unsignedInteger('citas_count')->default(0);

            $table->timestamp('publicada_en')->nullable();
            $table->timestamp('vendida_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices para los filtros más usados del catálogo
            $table->index(['estado', 'publicada_en']);
            $table->index(['tipo', 'operacion']);
            $table->index(['ciudad', 'estado_provincia']);
            $table->index(['operacion', 'moneda', 'precio']);
            $table->index(['propietario_type', 'propietario_id', 'estado'], 'propiedades_propietario_estado_index');
            $table->index(['latitud', 'longitud']);
            $table->index('destacada');
        });

        // Búsqueda de texto completo: sólo MySQL (en SQLite se usa LIKE en tests).
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('propiedades', function (Blueprint $table): void {
                $table->fullText(['titulo', 'descripcion', 'direccion'], 'propiedades_busqueda_fulltext');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedades');
    }
};

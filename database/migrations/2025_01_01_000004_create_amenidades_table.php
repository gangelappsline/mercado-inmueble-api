<?php

declare(strict_types=1);

use App\Enums\CategoriaAmenidad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de amenidades (piscina, gimnasio, jardín...). Se cachea porque se
 * consulta en cada listado público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenidades', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('icono', 80)->nullable()->comment('Nombre del icono en el frontend');
            $table->enum('categoria', CategoriaAmenidad::valores())->default(CategoriaAmenidad::Interior->value);
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['categoria', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenidades');
    }
};

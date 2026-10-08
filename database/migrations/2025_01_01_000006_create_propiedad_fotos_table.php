<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos de una propiedad (N por propiedad). `es_principal` se garantiza desde
 * PropiedadService: sólo una foto principal por propiedad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedad_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('ruta', 2048);
            $table->string('disk', 40)->default('public');
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamanio')->nullable()->comment('Bytes');
            $table->unsignedSmallInteger('ancho')->nullable();
            $table->unsignedSmallInteger('alto')->nullable();
            $table->string('thumbnail', 2048)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('es_principal')->default(false);
            $table->timestamps();

            $table->index(['propiedad_id', 'orden']);
            $table->index(['propiedad_id', 'es_principal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedad_fotos');
    }
};

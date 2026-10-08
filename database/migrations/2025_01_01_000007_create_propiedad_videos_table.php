<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video de una propiedad: máximo UNO por propiedad. La regla se refuerza en dos
 * capas: índice único en base de datos + validación en PropiedadService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedad_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->unique()->constrained('propiedades')->cascadeOnDelete();
            $table->string('ruta', 2048);
            $table->string('disk', 40)->default('private')->comment('private | s3');
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamanio')->nullable()->comment('Bytes');
            $table->unsignedInteger('duracion')->nullable()->comment('Segundos');
            $table->string('thumbnail', 2048)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedad_videos');
    }
};

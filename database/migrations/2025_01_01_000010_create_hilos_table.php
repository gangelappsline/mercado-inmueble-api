<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hilo de conversación entre un cliente interesado y el anunciante
 * (inmobiliaria o vendedor). Se crea automáticamente con el interés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hilos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interes_id')->unique()->constrained('intereses')->cascadeOnDelete();
            $table->string('asunto', 200);
            $table->timestamp('ultimo_mensaje_en')->nullable();
            $table->unsignedInteger('no_leidos_cliente')->default(0);
            $table->unsignedInteger('no_leidos_propietario')->default(0);
            $table->unsignedSmallInteger('total_mensajes')->default(0);
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->index(['cerrado', 'ultimo_mensaje_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hilos');
    }
};

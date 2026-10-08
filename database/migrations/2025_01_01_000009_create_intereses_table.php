<?php

declare(strict_types=1);

use App\Enums\EstadoInteres;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interés de un cliente sobre una propiedad. Es el disparador del embudo:
 * crea el hilo de mensajes y notifica al anunciante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intereses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->text('mensaje')->nullable();
            $table->enum('estado', EstadoInteres::valores())->default(EstadoInteres::Nuevo->value);
            $table->string('origen', 30)->default('web')->comment('web | app | telefono | whatsapp');
            $table->boolean('contactado')->default(false);
            $table->timestamp('atendido_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Un cliente registra el interés una sola vez por propiedad.
            $table->unique(['propiedad_id', 'cliente_id']);
            $table->index(['estado', 'created_at']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intereses');
    }
};

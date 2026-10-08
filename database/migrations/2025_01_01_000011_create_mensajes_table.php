<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes de un hilo. `rol_autor` desnormalizado permite filtrar y contar
 * no leídos sin joins adicionales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hilo_id')->constrained('hilos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Sólo los participantes de la conversación escriben mensajes:
            // el rol `administrador` no participa en los hilos.
            $table->enum('rol_autor', Role::valoresParticipantes())
                ->comment('inmobiliaria | vendedor | cliente');
            $table->text('cuerpo');
            $table->string('adjunto', 2048)->nullable();
            $table->boolean('leido')->default(false);
            $table->timestamp('leido_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hilo_id', 'created_at']);
            $table->index(['hilo_id', 'leido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};

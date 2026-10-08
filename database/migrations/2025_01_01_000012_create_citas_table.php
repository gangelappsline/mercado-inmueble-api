<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use App\Enums\TipoCita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda: visita presencial, visita virtual o llamada. Los solapamientos se
 * validan en CitaService antes de insertar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('interes_id')->nullable()->constrained('intereses')->nullOnDelete();
            $table->morphs('propietario'); // inmobiliaria | vendedor

            $table->date('fecha');
            $table->time('hora');
            $table->unsignedSmallInteger('duracion_minutos')->default(30);
            $table->enum('tipo', TipoCita::valores())->default(TipoCita::Visita->value);
            $table->enum('estado', EstadoCita::valores())->default(EstadoCita::Pendiente->value);

            $table->string('lugar', 255)->nullable();
            $table->string('enlace_virtual', 2048)->nullable();
            $table->text('notas')->nullable();
            $table->text('motivo_cancelacion')->nullable();

            $table->timestamp('confirmada_en')->nullable();
            $table->timestamp('reprogramada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->timestamp('completada_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha', 'hora']);
            $table->index(['propietario_type', 'propietario_id', 'fecha'], 'citas_propietario_fecha_index');
            $table->index(['cliente_id', 'fecha']);
            $table->index(['estado', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};

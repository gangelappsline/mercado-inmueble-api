<?php

declare(strict_types=1);

use App\Enums\TipoReporte;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de agregados diarios para analytics: vistas, contactos, favoritos,
 * citas y conversiones por propiedad. Un registro por (propiedad, tipo, fecha)
 * que se incrementa con upsert atómico desde ReporteService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table): void {
            $table->id();
            $table->morphs('propietario'); // inmobiliaria | vendedor
            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->enum('tipo', TipoReporte::valores());
            $table->date('fecha');
            $table->unsignedInteger('cantidad')->default(0);
            $table->decimal('valor', 14, 2)->nullable()->comment('Monto asociado (ingresos)');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['propiedad_id', 'tipo', 'fecha'], 'reportes_propiedad_tipo_fecha_unique');
            $table->index(['propietario_type', 'propietario_id', 'fecha'], 'reportes_propietario_fecha_index');
            $table->index(['tipo', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};

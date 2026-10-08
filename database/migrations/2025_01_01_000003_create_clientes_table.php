<?php

declare(strict_types=1);

use App\Enums\Moneda;
use App\Enums\TipoPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil extendido de las cuentas con rol `cliente`: preferencias de búsqueda y
 * presupuesto, usados por /api/v1/cliente/propiedades (búsqueda personalizada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('telefono', 30);
            $table->string('telefono_alternativo', 30)->nullable();
            $table->decimal('presupuesto_min', 14, 2)->nullable();
            $table->decimal('presupuesto_max', 14, 2)->nullable();
            $table->enum('moneda', Moneda::valores())->default(Moneda::Bob->value);
            $table->enum('tipo_propiedad_interes', TipoPropiedad::valores())->nullable();
            $table->string('ciudad_interes', 120)->nullable();
            $table->unsignedTinyInteger('habitaciones_min')->nullable();
            $table->json('preferencias')->nullable()->comment('Filtros guardados del cliente');
            $table->boolean('acepta_terminos')->default(false);
            $table->boolean('recibe_novedades')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ciudad_interes', 'tipo_propiedad_interes']);
            $table->index(['presupuesto_min', 'presupuesto_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

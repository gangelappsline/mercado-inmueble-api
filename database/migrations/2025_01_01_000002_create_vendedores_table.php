<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil extendido de las cuentas con rol `vendedor` (persona natural).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendedores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('dni', 30)->unique()->comment('DNI / CI / cédula');
            $table->string('telefono', 30);
            $table->string('telefono_alternativo', 30)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('estado_provincia', 120)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('biografia')->nullable();
            $table->string('foto', 2048)->nullable();
            $table->boolean('verificado')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ciudad', 'estado_provincia']);
            $table->index('apellidos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendedores');
    }
};

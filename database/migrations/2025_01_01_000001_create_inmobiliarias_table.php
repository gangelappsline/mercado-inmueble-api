<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil extendido de las cuentas con rol `inmobiliaria`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inmobiliarias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('ruc', 30)->unique()->comment('RUC / NIT de la empresa');
            $table->string('logo', 2048)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('telefono_alternativo', 30)->nullable();
            $table->string('web', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('estado_provincia', 120)->nullable();
            $table->string('pais', 80)->default('Bolivia');
            $table->boolean('verificado')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ciudad', 'estado_provincia']);
            $table->index('verificado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inmobiliarias');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propiedades guardadas por un cliente (lista de deseos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoritos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'propiedad_id']);
            $table->index('propiedad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoritos');
    }
};

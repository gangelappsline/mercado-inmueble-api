<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot N:M entre propiedades y amenidades del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenidad_propiedad', function (Blueprint $table): void {
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('amenidad_id')->constrained('amenidades')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['propiedad_id', 'amenidad_id']);
            $table->index('amenidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenidad_propiedad');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del formulario público de contacto (POST /api/v1/contacto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('email', 180);
            $table->string('telefono', 30)->nullable();
            $table->string('asunto', 200);
            $table->text('mensaje');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('atendido')->default(false);
            $table->timestamp('atendido_en')->nullable();
            $table->timestamps();

            $table->index(['atendido', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de cambios (spatie/laravel-activitylog v4) con la columna `event`
 * y `batch_uuid` incluidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tabla = (string) config('activitylog.table_name', 'activity_log');

        Schema::connection(config('activitylog.database_connection'))->create($tabla, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->string('event')->nullable()->after('subject_type');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable()->after('properties');
            $table->timestamps();

            $table->index('log_name');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_log_subject_created_index');
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->dropIfExists((string) config('activitylog.table_name', 'activity_log'));
    }
};

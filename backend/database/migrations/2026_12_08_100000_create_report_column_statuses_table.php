<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0391 D-4: the working statuses each status-based column of
 * "Statistiche Gestione Richieste" counts. One row per (column, status);
 * deleting a status removes its choices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_column_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('column_key', 64);
            $table->foreignId('quote_workflow_status_id')->constrained('quote_workflow_statuses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['column_key', 'quote_workflow_status_id'], 'report_column_statuses_column_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_column_statuses');
    }
};

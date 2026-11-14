<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0394 D-11: one row per duplicate merged into a survivor (registries and
 * referents). `snapshot` keeps the duplicate's full preview slice plus what was
 * discarded or dropped on a uniqueness conflict. `merged_old_id` lets the legacy
 * migration resolve a deleted duplicate to its survivor (D-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_merges', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('survivor_id');
            $table->unsignedBigInteger('merged_id');
            $table->unsignedBigInteger('merged_old_id')->nullable();
            $table->string('merged_name');
            $table->json('snapshot');
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'merged_old_id']);
            $table->index(['entity_type', 'merged_id']);
            $table->index(['entity_type', 'survivor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_merges');
    }
};

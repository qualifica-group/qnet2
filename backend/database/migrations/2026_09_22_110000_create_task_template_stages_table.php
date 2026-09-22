<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskTemplateStage entity (spec 0146, D-2): the ordered "Fase" grouping
 * inside a `task_templates` header — "Senza fase" is simply a null
 * `task_template_stage_id` on the item, not a row here. Written ONLY through
 * the header's own create/update full-sync writer, the same shape as
 * `task_template_items` (D-2).
 *
 * `task_template_id` is `cascadeOnDelete`: deleting a template removes its
 * stages in the same breath, mirroring `task_template_items`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_template_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained('task_templates')->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_stages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nested sub-items in a task template (spec 0172, D-1/D-6): a self-referencing
 * `parent_id`, up to 3 levels below a root row — the FormRequest enforces the
 * depth cap (AC-003), this column only needs to allow the reference.
 * `cascadeOnDelete` is a safety net, not the intended deletion path: every
 * application-level delete (full-sync omission, template delete) removes a
 * sub-tree depth-first through Eloquent's own `::delete()` first, so
 * `HasAttachments`' `deleting` hook sweeps each row's files from disk (D-6) —
 * this FK only guarantees no orphaned `parent_id` can ever survive on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('task_template_stage_id')
                ->constrained('task_template_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskTemplateItem entity (spec 0124, D-1): the ordered rows of a
 * `task_templates` header — what becomes the title/description/
 * estimated_minutes/initial status/due-date-offset of each Task the
 * generator stamps out (D-3/D-6). Written ONLY through the header's own
 * create/update (full sync, mirroring
 * `App\Services\Quotes\QuoteWorkflowStatusWriter::syncCustoms`): there is no
 * dedicated endpoint for a row.
 *
 * `task_template_id` is `cascadeOnDelete` — deleting a template (only
 * possible when unreferenced by any Commessa, D-5) removes its rows (and
 * their attachments, via `HasAttachments`' own deleting hook) in the same
 * breath (AC-010). `task_status_id` is nullable + `restrictOnDelete`: D-4
 * lets a row start with no fixed status (the generator then falls back to
 * `TaskInitialStatusResolver`), but a status a row DOES reference cannot be
 * removed out from under it (AC-012, the same guard shape as
 * `TaskStatusService::delete()` already has for `tasks`).
 *
 * `due_offset_days` is `unsignedSmallInteger` (max ~65535, comfortably above
 * the 0..3650 the FormRequest enforces) rather than `unsignedTinyInteger`,
 * which would cap out at 255 — under the contract's own upper bound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained('task_templates')->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->foreignId('task_status_id')->nullable()->constrained('task_statuses')->restrictOnDelete();
            $table->unsignedSmallInteger('due_offset_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_items');
    }
};

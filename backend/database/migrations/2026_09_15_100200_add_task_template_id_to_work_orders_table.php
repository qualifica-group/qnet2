<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `work_orders.task_template_id` (spec 0124, D-5): nullable, written ONLY at
 * creation by `WorkOrderService::create()` (D-7) and IMMUTABLE afterwards —
 * `UpdateWorkOrderRequest` rejects it with `prohibited`, the same shape as
 * `quote_id`'s own immutability, not here at the schema.
 *
 * `restrictOnDelete`, not `nullOnDelete`: a model referenced by at least one
 * Commessa must stay undeletable (409, D-5) rather than silently
 * unlinking — the FK is the backstop behind
 * `TaskTemplateService::delete()`'s own guard, defence in depth exactly like
 * `task_status_id` on `tasks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('task_template_id')->nullable()->after('quote_id')
                ->constrained('task_templates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_template_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the WorkOrder <-> QuoteLine relation (spec 0093, D-6): a
 * DEDICATED table with its own `id` (not a bare Laravel default pivot) so a
 * future column (assigned quantity, per-row progress/status) can be added
 * here without touching `work_orders` or `quote_lines`. Both sides cascade —
 * deleting either the work order or the quote line drops the membership row
 * (AC-003). `UNIQUE(work_order_id, quote_line_id)` prevents the same line
 * being attached twice (AC-002). No timestamps: mirrors `quote_user` (spec
 * 0087), the closest dedicated-pivot precedent — nothing reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_work_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('quote_line_id')->constrained('quote_lines')->cascadeOnDelete();

            $table->unique(['work_order_id', 'quote_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_work_order');
    }
};

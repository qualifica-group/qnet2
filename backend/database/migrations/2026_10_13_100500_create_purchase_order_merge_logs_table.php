<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of purchase order merges (spec 0210, D-19): one row per source order,
 * with a JSON snapshot of the source taken before its lines were moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('source_purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('source_code', 20);
            $table->json('source_snapshot');
            $table->json('moved_line_ids');
            $table->string('source_action', 10);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_merge_logs');
    }
};

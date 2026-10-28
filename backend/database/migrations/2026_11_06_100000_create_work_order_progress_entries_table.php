<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual progress of a commessa (spec 0321, D-5): each row is the CUMULATIVE
 * percentage reached on a date, strictly increasing per work order, so the
 * current progress is MAX(percentage). The (work_order_id, percentage) index
 * backs the correlated MAX subquery of the grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percentage');
            $table->date('recorded_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'percentage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_progress_entries');
    }
};

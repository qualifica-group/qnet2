<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status transitions of a purchase request line (spec 0208, D-9): one row per
 * change, rows of the same mass change share `bulk_group_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_line_status_logs', function (Blueprint $table) {
            $table->id();
            // Explicit name: the default exceeds MySQL's 64-character limit.
            $table->foreignId('purchase_request_line_id')
                ->constrained('purchase_request_lines', 'id', 'pr_line_status_logs_line_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->uuid('bulk_group_id')->nullable()->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['purchase_request_line_id', 'created_at'], 'pr_line_status_logs_line_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_line_status_logs');
    }
};

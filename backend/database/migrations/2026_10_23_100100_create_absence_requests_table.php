<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0278 D-2/D-6: absence requests. `minutes` is frozen at submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('absence_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->boolean('is_full_day');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('minutes')->nullable();
            $table->unsignedInteger('requested_minutes')->nullable();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on'], 'absence_requests_user_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0284 D-3: a staff course taken by a person. `expires_on` is a snapshot
 * computed at save time from the course validity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_trainings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_course_id')->constrained()->restrictOnDelete();
            $table->date('completed_on');
            $table->date('expires_on')->nullable();
            $table->string('outcome', 16);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('staff_course_id');
            $table->index('expires_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_trainings');
    }
};

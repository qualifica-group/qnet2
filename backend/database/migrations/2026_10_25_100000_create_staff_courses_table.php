<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0284 D-2: catalogue of the internal staff courses and the job roles
 * that require each of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_courses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_role_staff_course', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_course_id')->constrained()->cascadeOnDelete();
            $table->unique(['job_role_id', 'staff_course_id'], 'job_role_staff_course_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_role_staff_course');
        Schema::dropIfExists('staff_courses');
    }
};

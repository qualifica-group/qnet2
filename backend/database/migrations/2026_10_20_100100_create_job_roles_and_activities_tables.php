<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 0271 D-1, D-2, D-5: job roles, job activities and their pivots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('job_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('job_activity_job_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->unique(['job_activity_id', 'job_role_id'], 'job_activity_job_role_unique');
        });

        Schema::create('employment_profile_job_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employment_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_role_id')->constrained()->restrictOnDelete();
            $table->unique(['employment_profile_id', 'job_role_id'], 'employment_profile_job_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_profile_job_role');
        Schema::dropIfExists('job_activity_job_role');
        Schema::dropIfExists('job_activities');
        Schema::dropIfExists('job_roles');
    }
};

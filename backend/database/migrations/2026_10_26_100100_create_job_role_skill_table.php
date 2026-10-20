<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0286 D-2: skills required by a job role, with the minimum level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_role_skill', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('min_level');
            $table->unique(['job_role_id', 'skill_id'], 'job_role_skill_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_role_skill');
    }
};

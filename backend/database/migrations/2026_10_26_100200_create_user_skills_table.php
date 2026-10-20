<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0286 D-4: the skill of a person, one row per person and skill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->date('assessed_on');
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'skill_id'], 'user_skills_user_skill_unique');
            $table->index('skill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skills');
    }
};

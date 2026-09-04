<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task importance lookup entity (spec 0101, D-4): shares the exact shape of
 * the sibling `task_types`/`task_categories`/`task_priorities` — see that
 * migration's docblock for the full rationale. NO numeric weight column: the
 * ordering by severity is `sort_order`, not a stored importance level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_importances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_importances');
    }
};

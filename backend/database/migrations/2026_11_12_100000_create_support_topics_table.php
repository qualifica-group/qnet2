<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tipi di problema" of the IT support request (spec 0334): each topic carries
 * the Task values pre-filled on submit plus its own assignees/watchers.
 * Lookup FKs are restrictOnDelete: a lookup used by a topic cannot be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('task_type_id')->nullable()->constrained('task_types')->restrictOnDelete();
            $table->foreignId('task_category_id')->nullable()->constrained('task_categories')->restrictOnDelete();
            $table->foreignId('task_priority_id')->nullable()->constrained('task_priorities')->restrictOnDelete();
            $table->foreignId('urgent_task_priority_id')->nullable()->constrained('task_priorities')->restrictOnDelete();
            $table->foreignId('task_importance_id')->nullable()->constrained('task_importances')->restrictOnDelete();
            $table->unsignedSmallInteger('due_days')->default(2);
            $table->unsignedSmallInteger('urgent_due_days')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('requires_closure_feedback')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('support_topic_assignee', function (Blueprint $table) {
            $table->foreignId('support_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['support_topic_id', 'user_id']);
        });

        Schema::create('support_topic_watcher', function (Blueprint $table) {
            $table->foreignId('support_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['support_topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_topic_watcher');
        Schema::dropIfExists('support_topic_assignee');
        Schema::dropIfExists('support_topics');
    }
};

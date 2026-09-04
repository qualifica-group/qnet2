<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task <-> Osservatori (spec 0101, D-1/D-10): the users who follow a Task
 * without being accountable for it. Twin of the sibling `task_assignee`
 * pivot — see that migration's docblock for why both carry an EXPLICIT name
 * instead of Laravel's alphabetical `task_user`.
 *
 * A user may legitimately be BOTH an assignee and a watcher of the same
 * Task (AC-083): the two pivots are independent sets, and neither excludes
 * the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_watcher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_watcher');
    }
};

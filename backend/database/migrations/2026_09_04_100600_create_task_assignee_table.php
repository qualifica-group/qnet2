<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task <-> Assegnatari (spec 0101, D-1/D-10): the users responsible for
 * carrying out a Task. Named EXPLICITLY rather than by Laravel's alphabetical
 * convention (`task_user`): a Task has TWO distinct user relations
 * (assegnatari and osservatori), so neither may claim the generic name — same
 * reasoning as `work_order_supervisor`/`work_order_participant`.
 *
 * Unordered, unlike `work_order_participant`: assignees are a set, with no
 * "n-th assignee" ranking to preserve (D-8). `cascadeOnDelete` on both sides:
 * this is an association row, not data — deleting the Task or the user
 * clears the link, it never blocks the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignee');
    }
};

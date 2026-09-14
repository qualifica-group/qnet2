<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day note (spec 0122, D-4): one free-text note per user per date, separate
 * from the individual segnatempo rows it accompanies. `unique(user_id, date)`
 * is the invariant PUT /api/time-entries/day-notes relies on (upsert by
 * user+date); `user_id` is `cascadeOnDelete`, same owned-by-user pattern as
 * `time_entries`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entry_day_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('note');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entry_day_notes');
    }
};

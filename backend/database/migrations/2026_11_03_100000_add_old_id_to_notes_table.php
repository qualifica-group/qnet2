<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for notes (spec 0311 D-6): the nullable + unique
 * `old_id` (the legacy `activity_log` id) the import engine reads for
 * idempotence and to attach a reply to its migrated root note. Added as its
 * own migration because `create_notes_table` is already committed
 * (backend.md §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};

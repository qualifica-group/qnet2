<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nested task categories (spec 0154, D-1): a self-referencing `parent_id`
 * with unlimited depth, as in q-net. `restrictOnDelete` keeps a parent with
 * children from disappearing under them. The name becomes unique per parent
 * instead of globally; root-level uniqueness (NULL parent) is enforced by the
 * FormRequest, since a unique index treats NULLs as distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('task_categories')
                ->restrictOnDelete();

            $table->dropUnique(['name']);
            $table->unique(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('task_categories', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'name']);
            $table->dropConstrainedForeignId('parent_id');
            $table->unique('name');
        });
    }
};

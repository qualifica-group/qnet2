<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0090 — a referent may be declared to BE a system user.
 *
 * Unique, so the correspondence is unambiguous in both directions: from the
 * referent to its user, and from a user back to the single referent that
 * represents it. `nullOnDelete` and not `cascadeOnDelete` (unlike
 * `employment_profiles`, which cannot exist without its user): a referent is a
 * record in its own right and must survive the deletion of the linked account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referents', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('referent_type_id')
                ->constrained()
                ->nullOnDelete();

            $table->unique('user_id', 'referents_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('referents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('referents_user_id_unique');
            $table->dropColumn('user_id');
        });
    }
};

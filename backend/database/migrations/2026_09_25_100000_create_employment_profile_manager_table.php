<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the employment-profile <-> manager membership (spec 0166, D-2):
 * a user's employment profile may report to any number of managers, not just
 * one. The pivot hangs off `employment_profiles`, not `users`, because
 * "who this employee reports to" has always been part of the employment
 * relationship — deleting the profile drops its manager rows with it, and
 * deleting a manager drops the rows pointing at it, the cascade equivalent
 * of today's `nullOnDelete` on the single FK column this replaces. Mirrors
 * `employment_profile_operational_site` (spec 0103 M1).
 *
 * Both sides cascade: deleting either the employment profile or the manager
 * (a User) drops the membership row, no orphaned pivot data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_profile_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_profile_id');
            $table->foreignId('user_id');
            $table->timestamps();

            // Named explicitly: the table name alone already pushes
            // Laravel's default `<table>_<column>_foreign` close to MySQL's
            // 64-character identifier limit, same reasoning as
            // ep_op_site_employment_fk.
            $table->foreign('employment_profile_id', 'ep_manager_employment_fk')
                ->references('id')->on('employment_profiles')->cascadeOnDelete();
            $table->foreign('user_id', 'ep_manager_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['employment_profile_id', 'user_id'], 'ep_manager_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_profile_manager');
    }
};

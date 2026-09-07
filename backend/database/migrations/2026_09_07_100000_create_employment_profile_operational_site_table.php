<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the employment-profile <-> operational-site membership (spec
 * 0103): a user's employment profile may hold at most one PHYSICAL site and
 * any number of REMOTE ones, `is_primary` being the source of truth for
 * which one is physical. The pivot hangs off `employment_profiles`, not
 * `users`, because the site has always been part of the employment
 * relationship: deleting the profile drops the sites with it, unchanged
 * from today's semantics on the single FK column it replaces.
 *
 * Both sides cascade: deleting either the employment profile or the site
 * drops the membership row, no orphaned pivot data — mirroring
 * business_function_operational_site.
 *
 * "At most one physical site" is NOT enforced here: MySQL has no partial
 * unique index, so a naive unique on (employment_profile_id, is_primary)
 * would also forbid more than one FALSE row per profile, which is exactly
 * what remote memberships need. The invariant is enforced by
 * EmploymentWriter inside the write transaction instead (D-10), the same
 * pattern already used for OperationalSite's unique-address invariant
 * (Models/OperationalSite.php:14-19). The schema only carries the
 * uniqueness of the (profile, site) pair itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_profile_operational_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_profile_id');
            $table->foreignId('operational_site_id');
            $table->boolean('is_primary')->default(false);

            // Named explicitly: the table name alone already pushes
            // Laravel's default `<table>_<column>_foreign` past MySQL's
            // 64-character identifier limit.
            $table->foreign('employment_profile_id', 'ep_op_site_employment_fk')
                ->references('id')->on('employment_profiles')->cascadeOnDelete();
            $table->foreign('operational_site_id', 'ep_op_site_site_fk')
                ->references('id')->on('operational_sites')->cascadeOnDelete();

            $table->unique(
                ['employment_profile_id', 'operational_site_id'],
                'ep_op_site_unique',
            );
            $table->index('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_profile_operational_site');
    }
};

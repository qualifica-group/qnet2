<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0103 (D-5): every `employment_profiles.operational_site_id` still set
 * becomes the PHYSICAL membership row in the pivot created by
 * `2026_09_07_100000_create_employment_profile_operational_site_table`, then
 * the column is dropped — no projection or cache of it survives. Style
 * mirrors `2026_09_02_200030_move_project_classification_to_product_lines`:
 * data backfill and schema drop share this one file's `up()`.
 *
 * `down()` is NOT a lossless round trip: it restores the column at its
 * original position (`2026_07_04_110000_create_employment_profiles_table`,
 * right after `company_id`) and repopulates it from the pivot's PRIMARY row
 * per profile, but any REMOTE membership has no column to go back to and is
 * simply left in the pivot — same asymmetry the project/product-lines
 * precedent declares for its own down(). The PRIMARY rows it reads are then
 * deleted from the pivot (mirrors `backfill_quote_managers_from_opportunity`:
 * down() reverses the data THIS migration wrote) — otherwise a later re-run
 * of up() would try to re-insert them and collide with `ep_op_site_unique`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateOperationalSitesToPivot();

        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->foreignId('operational_site_id')->nullable()
                ->after('company_id')
                ->constrained('operational_sites')
                ->nullOnDelete();
        });

        $this->backfillOperationalSiteFromPivot();
    }

    private function migrateOperationalSitesToPivot(): void
    {
        $rows = DB::table('employment_profiles')
            ->whereNotNull('operational_site_id')
            ->get(['id', 'operational_site_id'])
            ->map(fn ($profile): array => [
                'employment_profile_id' => $profile->id,
                'operational_site_id' => $profile->operational_site_id,
                'is_primary' => true,
            ]);

        if ($rows->isNotEmpty()) {
            DB::table('employment_profile_operational_site')->insert($rows->all());
        }
    }

    private function backfillOperationalSiteFromPivot(): void
    {
        $primaryMemberships = DB::table('employment_profile_operational_site')
            ->where('is_primary', true)
            ->get(['id', 'employment_profile_id', 'operational_site_id']);

        $primaryMemberships->each(function ($membership): void {
            DB::table('employment_profiles')
                ->where('id', $membership->employment_profile_id)
                ->update(['operational_site_id' => $membership->operational_site_id]);
        });

        DB::table('employment_profile_operational_site')
            ->whereIn('id', $primaryMemberships->pluck('id'))
            ->delete();
    }
};

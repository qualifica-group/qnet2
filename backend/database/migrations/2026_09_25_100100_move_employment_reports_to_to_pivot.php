<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0166 (D-2): every `employment_profiles.reports_to_id` still set
 * becomes a row in the `employment_profile_manager` pivot created by
 * `2026_09_25_100000_create_employment_profile_manager_table`, then the
 * column is dropped — no projection or cache of it survives. Style mirrors
 * `2026_09_07_100100_move_employment_operational_site_to_pivot` (spec 0103
 * M2): data backfill and schema drop share this one file's `up()`.
 *
 * `down()` is NOT a lossless round trip once the pivot has grown beyond one
 * manager per profile: it restores the column at its original position
 * (right after `job_description`, spec 0166 D-2) and repopulates it from the
 * LOWEST pivot id per profile — the earliest manager relationship recorded —
 * then deletes exactly those winning rows from the pivot. Any OTHER manager
 * a profile picked up after this migration's up() has no second column slot
 * to go back to and is simply left in the pivot, untouched — same asymmetry
 * the operational-site precedent declares for its own down(). Deleting only
 * the winning rows (not every row of the profile) keeps a bare up-then-down
 * round trip lossless: right after up() every profile has AT MOST one pivot
 * row, so "the lowest id" is that very row and nothing is left behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateReportsToPivot();

        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reports_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->foreignId('reports_to_id')->nullable()
                ->after('job_description')
                ->constrained('users')
                ->nullOnDelete();
        });

        $this->backfillReportsToFromPivot();
    }

    private function migrateReportsToPivot(): void
    {
        $rows = DB::table('employment_profiles')
            ->whereNotNull('reports_to_id')
            ->get(['id', 'reports_to_id'])
            ->map(fn ($profile): array => [
                'employment_profile_id' => $profile->id,
                'user_id' => $profile->reports_to_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if ($rows->isNotEmpty()) {
            DB::table('employment_profile_manager')->insert($rows->all());
        }
    }

    /**
     * One winning row per profile (the LOWEST pivot id), read via a
     * self-join against a per-profile MIN(id) so a single query resolves the
     * winners for every profile at once — no N+1 over the profile list.
     */
    private function backfillReportsToFromPivot(): void
    {
        $lowestIds = DB::table('employment_profile_manager')
            ->selectRaw('MIN(id) as id')
            ->groupBy('employment_profile_id');

        $winners = DB::table('employment_profile_manager')
            ->joinSub($lowestIds, 'lowest', 'employment_profile_manager.id', '=', 'lowest.id')
            ->get(['employment_profile_manager.id', 'employment_profile_manager.employment_profile_id', 'employment_profile_manager.user_id']);

        $winners->each(function ($winner): void {
            DB::table('employment_profiles')
                ->where('id', $winner->employment_profile_id)
                ->update(['reports_to_id' => $winner->user_id]);
        });

        DB::table('employment_profile_manager')
            ->whereIn('id', $winners->pluck('id'))
            ->delete();
    }
};

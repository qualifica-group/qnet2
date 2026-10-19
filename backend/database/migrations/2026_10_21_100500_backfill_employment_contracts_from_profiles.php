<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0272 D-2: every profile with a relationship type or a company gets a
 * first contractual condition starting at `hired_at` (or the profile creation
 * date). The condition's relationship type is required, so a profile with a
 * company but no type gets `other`. Rows are tagged with CHANGE_REASON so
 * down() removes exactly what this migration created.
 */
return new class extends Migration
{
    private const CHANGE_REASON = 'Migrated from employment profile';

    private const FALLBACK_RELATIONSHIP_TYPE = 'other';

    public function up(): void
    {
        $now = now();

        DB::table('employment_profiles')
            ->where(function ($query): void {
                $query->whereNotNull('relationship_type')->orWhereNotNull('company_id');
            })
            ->orderBy('id')
            ->each(function (object $profile) use ($now): void {
                DB::table('employment_contracts')->insert([
                    'employment_profile_id' => $profile->id,
                    'starts_on' => $profile->hired_at ?? Carbon::parse($profile->created_at)->toDateString(),
                    'relationship_type' => $profile->relationship_type ?? self::FALLBACK_RELATIONSHIP_TYPE,
                    'company_id' => $profile->company_id,
                    'change_reason' => self::CHANGE_REASON,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('employment_contracts')->where('change_reason', self::CHANGE_REASON)->delete();
    }
};

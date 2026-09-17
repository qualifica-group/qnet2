<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0139: `mobile` is no longer a contact type — every mobile contact
 * becomes a `phone` (D-2), and owners left with email/phone contacts but no
 * primary of that type get one (D-4, the rows written by the old lead import,
 * which flagged only the row's FIRST contact as primary).
 *
 * The single-primary-per-type invariant (ContactService) is kept by hand:
 * where an owner had BOTH a primary phone and a primary mobile, the former
 * mobile stays primary (user decision) and the phone is demoted BEFORE the
 * type change. `normalized_value` needs no backfill: phone and mobile always
 * normalized identically (ContactValueNormalizer).
 *
 * Query builder only, never Eloquent: no activity-log noise, no model hooks.
 */
return new class extends Migration
{
    private const string MOBILE = 'mobile';

    private const string PHONE = 'phone';

    /** Labels that only restated the dropped type (compared lowercase). */
    private const array MOBILE_LABELS = ['mobile', 'cellulare', 'cell'];

    /** The types the lead import writes, whose missing primary D-4 restores. */
    private const array PRIMARY_RESTORED_TYPES = ['email', self::PHONE];

    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        // Step 1: demote the phones competing with a primary mobile
        $this->demotePhonesOfPrimaryMobileOwners();
        // Step 2: drop labels that only said "mobile", then retype
        DB::table('contacts')
            ->where('type', self::MOBILE)
            ->whereIn(DB::raw('lower(label)'), self::MOBILE_LABELS)
            ->update(['label' => null]);
        DB::table('contacts')->where('type', self::MOBILE)->update(['type' => self::PHONE]);
        // Step 3: give every owner a primary of the imported types
        foreach (self::PRIMARY_RESTORED_TYPES as $type) {
            $this->promoteFirstContactWithoutPrimary($type);
        }
    }

    /**
     * Irreversible (D-9): once retyped, which phone was a mobile is unknown.
     */
    public function down(): void {}

    private function demotePhonesOfPrimaryMobileOwners(): void
    {
        DB::table('contacts')
            ->select(['id', 'contactable_type', 'contactable_id'])
            ->where('type', self::MOBILE)
            ->where('is_primary', true)
            ->whereNotNull('contactable_id')
            ->chunkById(self::CHUNK_SIZE, function ($mobiles): void {
                foreach ($mobiles->groupBy('contactable_type') as $ownerType => $rows) {
                    DB::table('contacts')
                        ->where('type', self::PHONE)
                        ->where('is_primary', true)
                        ->where('contactable_type', $ownerType)
                        ->whereIn('contactable_id', $rows->pluck('contactable_id')->all())
                        ->update(['is_primary' => false]);
                }
            });
    }

    private function promoteFirstContactWithoutPrimary(string $type): void
    {
        $ids = DB::table('contacts')
            ->where('type', $type)
            ->whereNotNull('contactable_id')
            ->groupBy('contactable_type', 'contactable_id')
            ->havingRaw('max(case when is_primary then 1 else 0 end) = 0')
            ->selectRaw('min(id) as id')
            ->pluck('id');

        foreach ($ids->chunk(self::CHUNK_SIZE) as $chunk) {
            DB::table('contacts')->whereIn('id', $chunk->all())->update(['is_primary' => true]);
        }
    }
};

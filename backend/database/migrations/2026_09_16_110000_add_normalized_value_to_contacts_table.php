<?php

declare(strict_types=1);

use App\Enums\ContactTypeEnum;
use App\Support\ContactValueNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0136 (D-1, D-2): `contacts.normalized_value` holds the comparison form
 * of `value` (`ContactValueNormalizer::contact`) so `LeadDuplicateMatcher` and
 * `IdentityDuplicateFinder` can match duplicates with an indexed
 * `(type, normalized_value)` lookup instead of hydrating and normalizing
 * every contact in PHP on every row. The model (`Contact::saving`) keeps this
 * column in sync going forward — this migration only backfills what already
 * exists. `value` itself is never rewritten (no canonicalization here).
 *
 * Backfill runs on the query builder (`DB::table`), never through Eloquent:
 * going through the model would fire activity-log events and the very
 * `saving` hook this migration predates. Rows whose `type` is not a member
 * of `ContactTypeEnum` (legacy data outside the current contract) are left
 * with `normalized_value = null`, matching D-1.
 */
return new class extends Migration
{
    private const int CHUNK_SIZE = 1000;

    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('normalized_value')->nullable()->after('value');
            $table->index(['type', 'normalized_value']);
        });

        $this->backfillNormalizedValue();
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex(['type', 'normalized_value']);
            $table->dropColumn('normalized_value');
        });
    }

    private function backfillNormalizedValue(): void
    {
        DB::table('contacts')
            ->select(['id', 'type', 'value'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($rows): void {
                foreach ($rows as $row) {
                    $type = ContactTypeEnum::tryFrom($row->type);

                    if ($type === null) {
                        continue;
                    }

                    DB::table('contacts')
                        ->where('id', $row->id)
                        ->update(['normalized_value' => ContactValueNormalizer::contact($type, $row->value)]);
                }
            });
    }
};

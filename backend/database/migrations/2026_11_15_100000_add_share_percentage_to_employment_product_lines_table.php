<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0346: each "Ambito di assegnazione" row carries the share (whole
 * percent, 1..100) of the person's commitment on it; a person's rows add up
 * to 100. Purely informative (D-1): the matching never reads it.
 *
 * The default 100 makes a row written outside the user form (fixtures, a
 * single-row seeder) read as the whole scope; the form always sends the value.
 *
 * D-3 backfill: the rows already on a profile split 100 evenly in id order,
 * the remainder going +1 to the first rows (3 rows = 34/33/33). Inline here,
 * not through an application class, so the migration stays stable over time.
 */
return new class extends Migration
{
    private const int TOTAL = 100;

    public function up(): void
    {
        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->unsignedTinyInteger('share_percentage')->default(self::TOTAL)->after('product_category_id');
        });

        DB::table('employment_product_lines')
            ->orderBy('employment_profile_id')
            ->orderBy('id')
            ->get(['id', 'employment_profile_id'])
            ->groupBy('employment_profile_id')
            ->filter(static fn ($lines): bool => $lines->count() > 1)
            ->each(function ($lines): void {
                foreach ($this->evenShares($lines->count()) as $index => $share) {
                    DB::table('employment_product_lines')
                        ->where('id', $lines[$index]->id)
                        ->update(['share_percentage' => $share]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->dropColumn('share_percentage');
        });
    }

    /**
     * @return array<int, int>
     */
    private function evenShares(int $count): array
    {
        $base = intdiv(self::TOTAL, $count);
        $remainder = self::TOTAL % $count;

        return array_map(
            static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
            range(0, $count - 1),
        );
    }
};

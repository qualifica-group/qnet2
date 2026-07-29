<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product code (spec 0065, D-1/D-1b/D-1c): `products` gains a manual/sequential
 * `code` (string(32), NOT NULL, UNIQUE), mirroring the pattern already in
 * production on Projects/Campaigns (spec 0025) — `PRD-{seq:4}` generated
 * server-side via `App\Services\Concerns\GeneratesSequentialCode`, editable
 * as-is at create, read-only afterwards. `code` is deliberately absent from
 * `Product::$fillable`: the service assigns it after mass-assignment, exactly
 * like `Project`/`Campaign`.
 *
 * The column is added in THREE steps because the table may already hold rows
 * (D-1c): (1) add it nullable, no index, so the ALTER never fails against
 * existing data; (2) backfill every existing row with a distinct `PRD-000N`
 * value ordered by `id`, using plain query-builder updates so the same
 * migration runs unchanged on both MySQL (prod) and SQLite (dev/test) — no
 * vendor-specific raw SQL; (3) only once every row is populated, tighten the
 * column to NOT NULL and add the UNIQUE index. Splitting backfill from the
 * schema change this way is what lets step 3 succeed without violating the
 * constraint it is about to enforce.
 */
return new class extends Migration
{
    private const CODE_PREFIX = 'PRD-';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('name');
        });

        $this->backfillExistingRows();

        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 32)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    private function backfillExistingRows(): void
    {
        DB::table('products')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id, int $index): void {
                $sequence = str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

                DB::table('products')
                    ->where('id', $id)
                    ->update(['code' => self::CODE_PREFIX.$sequence]);
            });
    }
};

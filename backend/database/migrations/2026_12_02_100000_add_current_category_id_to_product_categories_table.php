<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a legacy root category to the current one it continues (spec 0374,
 * D-9): the grids show the flexible columns of linked categories together.
 * Backfilled once (D-11) by comparing names, here and never at runtime: a
 * legacy root loses its "[OLD]" prefix and trailing spaces/dashes and is
 * linked when exactly one current root has that name.
 */
return new class extends Migration
{
    private const string LEGACY_PREFIX = '[OLD]';

    /** Legacy names that do not match their current category by name (user decision 2026-11-09). */
    private const array NAME_EXCEPTIONS = ['[OLD] SICUREZZA 81/08' => 'Sicurezza'];

    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('current_category_id')->nullable()->after('is_legacy')->constrained('product_categories')->nullOnDelete();
        });

        $this->linkByName();
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_category_id');
        });
    }

    private function linkByName(): void
    {
        $currentRoots = DB::table('product_categories')
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->get(['id', 'name'])
            ->groupBy(static fn (object $root): string => mb_strtolower(trim((string) $root->name)));

        $legacyRoots = DB::table('product_categories')
            ->whereNull('parent_id')
            ->where('is_legacy', true)
            ->whereNull('current_category_id')
            ->get(['id', 'name']);

        foreach ($legacyRoots as $legacy) {
            $matches = $currentRoots->get(mb_strtolower($this->currentName((string) $legacy->name)));

            if ($matches?->count() === 1) {
                DB::table('product_categories')->where('id', $legacy->id)->update(['current_category_id' => $matches->first()->id]);
            }
        }
    }

    private function currentName(string $legacyName): string
    {
        if (isset(self::NAME_EXCEPTIONS[$legacyName])) {
            return self::NAME_EXCEPTIONS[$legacyName];
        }

        $name = preg_replace('/^\s*'.preg_quote(self::LEGACY_PREFIX, '/').'/i', '', $legacyName);

        return trim((string) $name, " \t\n\r\0\x0B-");
    }
};

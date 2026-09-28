<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0171 rev.2 (D-8/D-9): marks an Offerta title typed by the user, so
 * the automatic `<code> - <products>` title leaves it alone. Existing rows
 * follow the mixed rule chosen by the user: a title that is a system copy of
 * the opportunity's `OPP_<number>` becomes automatic, every other title stays
 * manual. The automatic titles themselves are re-derived by
 * `php artisan titles:recalculate` (migrations never import App\ classes).
 */
return new class extends Migration
{
    private const string SYSTEM_COPY_PATTERN = '/^OPP_\d+$/';

    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->boolean('title_is_manual')->default(false)->after('title');
        });

        DB::table('quotes')->select('id', 'title')->orderBy('id')->chunk(self::CHUNK_SIZE, function ($quotes): void {
            $manualIds = $quotes
                ->reject(fn (object $quote): bool => preg_match(self::SYSTEM_COPY_PATTERN, (string) $quote->title) === 1)
                ->pluck('id');

            if ($manualIds->isNotEmpty()) {
                DB::table('quotes')->whereIn('id', $manualIds)->update(['title_is_manual' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('title_is_manual');
        });
    }
};

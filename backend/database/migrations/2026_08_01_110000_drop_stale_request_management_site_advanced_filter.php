<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `operational_site` advanced filter of the `request-management` domain
 * became an id-based picker (user directive 2026-07-31): its value is now a
 * list of site ids, no longer the free-text address substring it used to be.
 *
 * Persisted state is replayed verbatim on the next `POST /rows`
 * (TableFilterStateService allow-lists by NAME, not by value shape), so a
 * value saved under the old contract would 422 the grid for its owner until
 * they cleared the filter by hand. Dropping the key is enough: everything
 * stored for this domain predates the change and is therefore a string.
 *
 * Data-only; `down()` cannot restore a discarded free-text needle, and would
 * only reinstate a value the current contract rejects.
 */
return new class extends Migration
{
    private const string DOMAIN = 'request-management';

    private const string FILTER_NAME = 'operational_site';

    public function up(): void
    {
        foreach (['user_table_filters', 'table_filter_views'] as $table) {
            $this->dropFilter($table);
        }
    }

    public function down(): void
    {
        // Irreversible by design (see the class docblock).
    }

    private function dropFilter(string $table): void
    {
        DB::table($table)
            ->where('domain', self::DOMAIN)
            ->whereNotNull('advanced_filters')
            ->orderBy('id')
            ->each(function (object $row) use ($table): void {
                $filters = json_decode((string) $row->advanced_filters, true);

                if (! is_array($filters) || ! array_key_exists(self::FILTER_NAME, $filters)) {
                    return;
                }

                unset($filters[self::FILTER_NAME]);

                DB::table($table)->where('id', $row->id)->update([
                    'advanced_filters' => json_encode($filters),
                ]);
            });
    }
};

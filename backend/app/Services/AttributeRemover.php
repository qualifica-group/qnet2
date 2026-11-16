<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\ProductCategories\AttributeLayoutPruner;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deletes flexible fields for good, by code: their values leave every
 * `attribute_values` map (products, offers, work orders), then the fields
 * leave every category and every layout, and are deleted with their options.
 * Unlike AttributeService::delete(), which refuses a field still in use, this
 * is the data cleanup of a field nobody wants any more (spec 0402, user
 * request 2026-11-16). Writes through the query builder: no activity log, no
 * notification. Re-running it changes nothing.
 */
final class AttributeRemover
{
    private const int CHUNK_SIZE = 500;

    /**
     * @param  list<string>  $codes
     * @return array{values: int, detached: int, layouts: int, deleted: int}
     */
    public function remove(array $codes): array
    {
        $result = ['values' => 0, 'detached' => 0, 'layouts' => 0, 'deleted' => 0];

        if ($codes === []) {
            return $result;
        }

        // Step 1: the values, out of every record's flexible values.
        foreach (AttributeService::VALUE_TABLES as $table) {
            $result['values'] += $this->stripValues($table, $codes);
        }

        // Step 2: the fields out of every category and every layout.
        $attributeIds = DB::table('attributes')->whereIn('code', $codes)->pluck('id')->all();
        $result['detached'] = DB::table('attribute_category')->whereIn('attribute_id', $attributeIds)->delete();
        $result['layouts'] = $this->pruneLayouts($codes);

        // Step 3: the fields themselves, with their options.
        DB::table('attribute_options')->whereIn('attribute_id', $attributeIds)->delete();
        $result['deleted'] = DB::table('attributes')->whereIn('id', $attributeIds)->delete();

        return $result;
    }

    /**
     * @param  list<string>  $codes
     */
    private function stripValues(string $table, array $codes): int
    {
        $stripped = 0;

        $this->mentioning(DB::table($table), 'attribute_values', $codes)
            ->select(['id', 'attribute_values'])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table, $codes, &$stripped): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);

                    if (! is_array($values) || array_intersect_key($values, array_flip($codes)) === []) {
                        continue;
                    }

                    $kept = array_diff_key($values, array_flip($codes));
                    DB::table($table)->where('id', $row->id)->update([
                        'attribute_values' => $kept === [] ? null : json_encode($kept, JSON_UNESCAPED_UNICODE),
                    ]);
                    $stripped++;
                }
            });

        return $stripped;
    }

    /**
     * @param  list<string>  $codes
     */
    private function pruneLayouts(array $codes): int
    {
        $layouts = $this->mentioning(DB::table('attribute_layouts'), 'layout', $codes)->get(['id', 'layout']);

        foreach ($layouts as $row) {
            $layout = json_decode((string) $row->layout, true);
            $kept = AttributeLayoutPruner::prune(
                is_array($layout) ? $layout : [],
                static fn (?string $code): bool => ! in_array($code, $codes, true),
            );

            DB::table('attribute_layouts')->where('id', $row->id)->update(['layout' => json_encode($kept, JSON_UNESCAPED_UNICODE)]);
        }

        return $layouts->count();
    }

    /**
     * Rows whose JSON column quotes one of the codes: a cheap pre-filter, the
     * decoded JSON decides.
     *
     * @param  list<string>  $codes
     */
    private function mentioning(Builder $query, string $column, array $codes): Builder
    {
        return $query->where(function ($query) use ($column, $codes): void {
            foreach ($codes as $code) {
                $query->orWhere($column, 'like', '%"'.$code.'"%');
            }
        });
    }
}

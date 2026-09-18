<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The EFFECTIVE Gestione Richieste / Iscritti report column selection of a
 * product category (spec 0141, supersedes the hardcoded
 * `config('request-management-report.category_columns')` name map of spec
 * 0131 D-4-bis). `report_columns` is the node's OWN override: null = inherit
 * the nearest ancestor's (structural walk, reportable or not — same shape as
 * `is_reportable`/{@see ReportableInheritance}), an array (never empty, D-2
 * normalizes [] to null at write time) = this node's own selection; no
 * configured ancestor = no columns ([]).
 *
 * Resolved at read time, never denormalised: the whole tree is one small
 * projection query, exactly like ReportableInheritance. `resolveFromAncestors()`
 * (spec 0141 rev-1) is the single ancestors-only walk both `resolve()` (own
 * value first) and the controller's own "what would this inherit" reuse —
 * never duplicated.
 */
final class ReportColumnsInheritance
{
    /** Defensive cap on the ancestor walk, mirroring ReportableInheritance's. */
    private const int MAX_DEPTH = 100;

    /**
     * Effective columns of every category in $categories (keyed by id, each
     * carrying `parent_id` and `report_columns`), resolved top-down in
     * memory.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @return array<int, array<int, string>>
     */
    public function effectiveMap(Collection $categories): array
    {
        $effective = [];

        foreach ($categories as $category) {
            $this->resolveInto($category, $categories, $effective);
        }

        return $effective;
    }

    /**
     * $category's effective columns plus the category they are inherited
     * FROM — the nearest ancestor carrying its own non-null `report_columns`;
     * null when $category overrides it itself or nothing up the chain does
     * (then the value is []).
     *
     * @return array{value: array<int, string>, source_category: array{id: int, name: string}|null}
     */
    public function resolve(ProductCategory $category): array
    {
        if ($category->report_columns !== null) {
            return ['value' => $category->report_columns, 'source_category' => null];
        }

        return $this->resolveFromAncestors($category);
    }

    /**
     * What $category would inherit if its OWN `report_columns` were null —
     * the nearest ANCESTOR's (starting at the parent, never $category
     * itself) non-null selection, regardless of $category's own value (spec
     * 0141 rev-1, D-9's own bug fix): the FE's "back to inherited" action
     * needs this even when the category already has its own columns, to
     * know whether there IS anything to fall back to. [] / null when no
     * ancestor is configured.
     *
     * @return array{value: array<int, string>, source_category: array{id: int, name: string}|null}
     */
    public function resolveFromAncestors(ProductCategory $category): array
    {
        $parent = $category->parent_id !== null ? ProductCategory::find($category->parent_id) : null;
        $depth = 0;

        while ($parent !== null && $depth < self::MAX_DEPTH) {
            if ($parent->report_columns !== null) {
                return [
                    'value' => $parent->report_columns,
                    'source_category' => ['id' => $parent->id, 'name' => $parent->name],
                ];
            }

            $parent = $parent->parent_id !== null ? ProductCategory::find($parent->parent_id) : null;
            $depth++;
        }

        return ['value' => [], 'source_category' => null];
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, array<int, string>>  $effective
     */
    private function resolveInto(ProductCategory $category, Collection $categories, array &$effective): void
    {
        // Step 1: climb until a node whose value is known (own override or
        // already resolved), remembering the inheriting nodes on the way.
        $pending = [];
        $node = $category;
        $value = [];

        while ($node !== null && count($pending) < self::MAX_DEPTH) {
            if (isset($effective[$node->id])) {
                $value = $effective[$node->id];

                break;
            }

            if ($node->report_columns !== null) {
                $value = $node->report_columns;
                $effective[$node->id] = $value;

                break;
            }

            $pending[] = $node->id;
            $node = $categories->get($node->parent_id);
        }

        // Step 2: every inheriting node on the path takes that value.
        foreach ($pending as $id) {
            $effective[$id] = $value;
        }
    }
}

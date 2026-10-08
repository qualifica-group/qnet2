<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Services\ProductCategories\CategoryRootResolver;

/**
 * The value of a `product_lines`-edited column (spec 0075, shared by spec
 * 0206 between the Gestione Richieste and Opportunita' grids): one entry per
 * persisted product line of the opportunity, carrying BOTH ids (what the
 * inline editor commits) and both names (what the cell renders and the
 * editor labels its chips with). `root_category_*` (spec 0132) is the root
 * of the line's category tree, resolved for every line in one call
 * (CategoryRootResolver memoizes per instance: at most two queries per page).
 * `name` is the entry's plain label, so an export lists the category names.
 * A line whose relation is missing is skipped rather than projected half-empty.
 */
final class ProductLinePairsColumn
{
    public function __construct(private readonly CategoryRootResolver $rootResolver) {}

    /**
     * @return array<int, array{name: string, business_function_id: int, business_function_name: string, product_category_id: int, product_category_name: string, root_category_id: int|null, root_category_name: string|null}>
     */
    public function project(?Opportunity $opportunity): array
    {
        $lines = ($opportunity?->productLines ?? collect())
            ->filter(static fn (OpportunityProductLine $line): bool => $line->businessFunction !== null && $line->productCategory !== null);

        $rootCategories = $this->rootResolver->rootSummariesFor($lines->pluck('product_category_id')->all());

        return $lines
            ->map(fn (OpportunityProductLine $line): array => [
                'name' => (string) $line->productCategory->name,
                'business_function_id' => (int) $line->business_function_id,
                'business_function_name' => (string) $line->businessFunction->name,
                'product_category_id' => (int) $line->product_category_id,
                'product_category_name' => (string) $line->productCategory->name,
                'root_category_id' => $rootCategories[$line->product_category_id]['id'] ?? null,
                'root_category_name' => $rootCategories[$line->product_category_id]['name'] ?? null,
            ])
            ->values()
            ->all();
    }
}

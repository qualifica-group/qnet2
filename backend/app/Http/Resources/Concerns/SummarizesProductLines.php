<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Services\ProductCategories\CategoryRootResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * The `product_lines` row every card resource embeds (Opportunity, Project,
 * Campaign, Quote, RequestManagement — spec 0040/0094/0086): `{id,
 * root_category, product_category, business_function}`. `root_category`
 * (spec 0132) is the root of the tree the line's category hangs from, on a
 * root category itself it coincides with `product_category`. Resolved in
 * ONE batch query per record via CategoryRootResolver — never a query per
 * line. Was duplicated verbatim across all five resources before this
 * extraction (engineering.md §3, DRY on a proven repetition).
 *
 * Deliberately NOT used by EmploymentResource: the competence line has a
 * different shape (`product_category` legitimately nullable, spec 0129 D-3)
 * and stays out of this feature (spec 0132, D-4).
 */
trait SummarizesProductLines
{
    /**
     * @return array<int, array{id: int, root_category: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null, business_function: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        $lines = collect($lines)->values();

        $rootCategories = app(CategoryRootResolver::class)->rootSummariesFor(
            $lines->pluck('product_category_id')->filter()->unique()->values()->all(),
        );

        return $lines
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'root_category' => $rootCategories[$line->product_category_id] ?? null,
                'product_category' => $this->summarizeProductLineRelation($line->productCategory),
                'business_function' => $this->summarizeProductLineRelation($line->businessFunction),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeProductLineRelation(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }
}

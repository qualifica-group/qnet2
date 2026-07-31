<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\Product;
use App\Services\Opportunities\OpportunityProductLineWriter;
use Illuminate\Validation\ValidationException;

/**
 * "Funzione aziendale" + "categoria prodotto" as edited from the work panel
 * (user directive 2026-07-31: the commercials change them on an existing
 * request, not only while creating it — the same `product_lines` collection
 * the create form already writes).
 *
 * The replace itself is delegated to the shared OpportunityProductLineWriter,
 * so this channel and the opportunities CRUD sync the collection identically.
 * What lives HERE is what only this channel needs: the diff (nothing is
 * rewritten, nor logged, when the pairs are unchanged) and the guard below.
 *
 * THE GUARD: an opportunity's "prodotti di interesse" must stay covered by
 * its product lines — the invariant OpportunityProductLineCoverage maintains
 * from the other direction (a product outside the covered categories ADDS its
 * line). Dropping a line whose category still has selected products would
 * break it silently, so it is rejected 422 naming the products to remove
 * first. Only DROPPED categories are guarded: a product whose category was
 * never covered stays legal, and its line is added by the coverage rule as
 * before.
 */
final class RequestProductLineWriter
{
    public function __construct(private readonly OpportunityProductLineWriter $productLineWriter) {}

    /**
     * @param  array<int, array<string, mixed>>  $submitted  the validated `product_lines` rows
     * @param  array<string, mixed>  $data  the whole PATCH payload, read for the products of interest travelling with it
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     * @return bool whether the collection actually changed — a workflow
     *              resolution criterion the caller re-runs on (spec 0047)
     *
     * @throws ValidationException a dropped category still has products of interest
     */
    public function apply(Opportunity $opportunity, array $submitted, array $data, array &$changed, array &$old): bool
    {
        // Step 1: both sides in the same shape, compared as an unordered SET
        // of pairs (a line's position carries no meaning).
        $current = $this->currentPairs($opportunity);
        $next = $this->normalize($submitted);

        if ($this->pairKeys($current) === $this->pairKeys($next)) {
            return false;
        }

        // Step 2: the invariant above, before anything is written.
        $this->assertDroppedCategoriesUnused($opportunity, $current, $next, $data);

        // Step 3: replace, and report the change for the caller's audit entry
        // (the collection is a relation, so it never reaches the automatic
        // fillable-diff log).
        $this->productLineWriter->sync($opportunity, $next);

        $old['product_lines'] = $current;
        $changed['product_lines'] = $next;

        return true;
    }

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $current
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $next
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertDroppedCategoriesUnused(Opportunity $opportunity, array $current, array $next, array $data): void
    {
        $droppedCategoryIds = array_values(array_diff(
            array_column($current, 'product_category_id'),
            array_column($next, 'product_category_id'),
        ));

        if ($droppedCategoryIds === []) {
            return;
        }

        // The set THIS request leaves persisted: the submitted one when the
        // picker travelled too (removing a line together with its products is
        // the normal flow), else whatever is already stored.
        $productIds = array_key_exists('products_of_interest', $data)
            ? array_map(intval(...), (array) $data['products_of_interest'])
            : $opportunity->productsOfInterest()->pluck('products.id')->map(intval(...))->all();

        if ($productIds === []) {
            return;
        }

        $blocking = Product::query()
            ->whereIn('id', $productIds)
            ->whereIn('category_id', $droppedCategoryIds)
            ->orderBy('name')
            ->pluck('name');

        if ($blocking->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'product_lines' => [
                'These products of interest belong to a product category you are removing, remove them first: '.$blocking->implode(', ').'.',
            ],
        ]);
    }

    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function currentPairs(Opportunity $opportunity): array
    {
        return $opportunity->productLines()
            ->get(['business_function_id', 'product_category_id'])
            ->map(static fn ($line): array => [
                'business_function_id' => (int) $line->business_function_id,
                'product_category_id' => (int) $line->product_category_id,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function normalize(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'business_function_id' => (int) $row['business_function_id'],
            'product_category_id' => (int) $row['product_category_id'],
        ], array_values($rows));
    }

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $pairs
     * @return array<int, string>
     */
    private function pairKeys(array $pairs): array
    {
        $keys = array_map(
            static fn (array $pair): string => "{$pair['business_function_id']}:{$pair['product_category_id']}",
            $pairs,
        );
        sort($keys);

        return $keys;
    }
}

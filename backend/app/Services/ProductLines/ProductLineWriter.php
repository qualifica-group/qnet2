<?php

declare(strict_types=1);

namespace App\Services\ProductLines;

use Illuminate\Database\Eloquent\Model;

/**
 * The single write path for a record's `product_lines` collection (funzione
 * aziendale + categoria prodotto), shared by every owner that carries one —
 * Opportunity (plus its request-management channel, which writes the SAME
 * Opportunity rows), Project and Campaign (spec 0094), AND the user's
 * employment competence (`employment_product_lines`, spec 0111/0129 —
 * EmploymentWriter). One writer, so the delete-all + insert rule cannot
 * diverge between owners or between an owner's own write channels — the same
 * reason OpportunityProductInterestWriter exists for the sibling collection.
 *
 * $owner is untyped beyond Model on purpose: the collection is reached via
 * whichever concrete `productLines()` HasMany relation the owner exposes,
 * never re-declared here.
 *
 * Spec 0132, D-3/D-4 split the two remaining callers' row shape in two: a
 * CARD row (opportunity/project/campaign) now carries only
 * `product_category_id` — the business function is DERIVED here, in one
 * batch resolution, so every card write channel (form + inline cell) derives
 * identically and can never diverge. A COMPETENCE row is OUT of spec 0132's
 * scope (D-4): it still carries its own `business_function_id` (and a
 * nullable `product_category_id`) verbatim, exactly as before. `sync()`
 * dispatches on whether the row's `business_function_id` key is present —
 * the one thing that already tells the two shapes apart, since a card row's
 * FormRequest/cell validator never lets that key onto the payload it hands
 * this writer, and a competence row always carries it.
 */
final class ProductLineWriter
{
    public function __construct(private readonly BusinessFunctionResolver $businessFunctionResolver) {}

    /**
     * Full-replace sync: delete-all + insert, idempotent within the caller's
     * transaction. Every row is expected to be already valid (no duplicate
     * category / pair, category with a resolvable business function) —
     * ValidatesProductLines/CompetenceLineSetValidator is what enforces that,
     * on each write endpoint.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int|null}|array{product_category_id: int}>  $lines
     */
    public function sync(Model $owner, array $lines): void
    {
        $owner->productLines()->delete();

        foreach ($this->resolvedRows($lines) as $row) {
            $owner->productLines()->create($row);
        }

        // The relation is a resolution criterion for the workflow and for the
        // applicable attributes: a stale loaded copy would make both read the
        // pre-sync rows.
        $owner->unsetRelation('productLines');
    }

    /**
     * A CARD row's `business_function_id` is resolved here, batched off
     * BusinessFunctionResolver — never a query per row. A COMPETENCE row
     * (its `business_function_id` key already present) passes through
     * untouched.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{business_function_id: int|null, product_category_id: int|null}>
     */
    private function resolvedRows(array $lines): array
    {
        $cardCategoryIds = array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['product_category_id'],
            array_filter($lines, static fn (array $line): bool => ! array_key_exists('business_function_id', $line)),
        )));

        $businessFunctionByCategoryId = $this->businessFunctionResolver->resolveMany($cardCategoryIds);

        return array_map(
            static function (array $line) use ($businessFunctionByCategoryId): array {
                if (array_key_exists('business_function_id', $line)) {
                    return $line;
                }

                $categoryId = (int) $line['product_category_id'];

                return [
                    'business_function_id' => $businessFunctionByCategoryId[$categoryId] ?? null,
                    'product_category_id' => $categoryId,
                ];
            },
            $lines,
        );
    }
}

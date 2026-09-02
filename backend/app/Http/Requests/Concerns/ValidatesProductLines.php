<?php

namespace App\Http\Requests\Concerns;

use App\Models\Quote;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for the `product_lines` payload of every module that
 * carries the collection (spec 0040 amendment rev.3, generalized to
 * projects/campaigns by spec 0094): a to-many collection of
 * {business_function_id, product_category_id} rows, REPLACING the former
 * single scalar columns. A record must ALWAYS carry at least one row
 * (user directive 2026-07-17): create REQUIRES a non-empty collection, and
 * update — a full-replace sync — may omit `product_lines` (partial PATCH,
 * untouched) but may NOT clear it to `[]`.
 *
 * The rules themselves are NOT defined here (spec 0075, D-1): they live in
 * ProductLineSetValidator, the one definition shared with the channel that has
 * no FormRequest at all (the inline cell editor). This trait only binds them
 * to the request — the collection-level shape rule, the per-row rules, and the
 * cross-row invariants each request runs from its own withValidator().
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesProductLines
{
    /**
     * @param  bool  $required  create passes true (collection mandatory);
     *                          update passes false (`sometimes`, but `min:1`
     *                          still bars a clear-to-empty).
     * @return array<string, array<int, mixed>>
     */
    protected function productLinesRules(bool $required): array
    {
        return [
            'product_lines' => $required
                ? ['required', 'array', 'min:1']
                : ['sometimes', 'array', 'min:1'],
            ...$this->productLineSetValidator()->rules('product_lines', $this->exemptProductCategoryIds()),
        ];
    }

    /**
     * The product-category ids already persisted on the record being
     * updated. Empty on create (no route model), which is exactly the
     * "block only new associations" semantics of spec 0074 D-3.
     *
     * @return array<int, int>
     */
    protected function exemptProductCategoryIds(): array
    {
        $owner = $this->routeModelForProductLines();

        if ($owner === null) {
            return [];
        }

        return $owner->productLines()
            ->pluck('product_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * The record `product_lines` is validated/persisted against, found
     * generically among the route's bound models — the first one that
     * itself exposes a `productLines()` relation, whatever module owns it
     * (opportunities, projects, campaigns). Request-management is the one
     * indirect case (spec 0086, D-2): its route parameter is `{quote}`,
     * whose OWN Opportunity carries the collection, not the Quote itself.
     */
    private function routeModelForProductLines(): ?Model
    {
        $quote = $this->route('quote');

        if ($quote instanceof Quote) {
            return $quote->opportunity;
        }

        foreach ($this->route()->parameters() as $parameter) {
            if ($parameter instanceof Model && method_exists($parameter, 'productLines')) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Cross-row rules: (business_function_id, product_category_id) may not
     * repeat, and each row's category must belong to that EXACT business
     * function once inheritance is resolved.
     */
    protected function validateProductLines(Validator $validator): void
    {
        $lines = $this->input('product_lines');

        if (! is_array($lines)) {
            return;
        }

        foreach ($this->productLineSetValidator()->crossRowErrors($lines, 'product_lines') as $key => $message) {
            $validator->errors()->add($key, $message);
        }
    }

    private function productLineSetValidator(): ProductLineSetValidator
    {
        return app(ProductLineSetValidator::class);
    }
}

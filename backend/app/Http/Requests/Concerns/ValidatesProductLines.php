<?php

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for the `product_lines` payload of the opportunity write
 * endpoints (spec 0040, amendment rev.3): a to-many collection of
 * {business_function_id, product_category_id} rows, REPLACING the former
 * single scalar columns. An opportunity must ALWAYS carry at least one row
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
     * The product-category ids already persisted on the opportunity being
     * updated. Empty on create (no route model), which is exactly the
     * "block only new associations" semantics of spec 0074 D-3.
     *
     * @return array<int, int>
     */
    protected function exemptProductCategoryIds(): array
    {
        $opportunity = $this->routeOpportunityForProductLines();

        if ($opportunity === null) {
            return [];
        }

        return $opportunity->productLines()
            ->pluck('product_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * The Opportunity `product_lines` is validated/persisted against: the
     * route model directly for the opportunities module, or — spec 0086,
     * D-2 — the Quote's own Opportunity for request-management, whose route
     * parameter is `{quote}` since the record migrated off the Opportunity.
     */
    private function routeOpportunityForProductLines(): ?Opportunity
    {
        $opportunity = $this->route('opportunity');

        if ($opportunity instanceof Opportunity) {
            return $opportunity;
        }

        $quote = $this->route('quote');

        return $quote instanceof Quote ? $quote->opportunity : null;
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

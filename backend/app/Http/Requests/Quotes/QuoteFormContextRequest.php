<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/quotes/form-context (spec 0084, D-5): the Offerta form's live
 * preview of the applicable "Informazioni aggiuntive" and their layout, for
 * the offer lines composed so far — before anything is saved. Gemello of the
 * former `request-management/form-context` (spec 0057), but scoped to
 * `offer_lines[].product_id` (D-5: the trigger is the PRODUCT pick, not a
 * business-function/category pair) rather than `product_lines`.
 *
 * READ-ONLY despite the verb: the criteria are a collection of objects, which
 * has no sane query-string encoding, mirroring every other `form-context`
 * endpoint of this codebase.
 *
 * Deliberately LENIENT on absence: a row with no `product_id` yet (the
 * operator just added a blank line) is DROPPED, never a 422 — it simply
 * scopes nothing (AC-034). The referenced id must still exist: an unknown id
 * is a malformed request either way.
 *
 * Authorization is handled in the controller (`quotes.create`), mirroring
 * every other action of this module.
 */
class QuoteFormContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'offer_lines' => ['sometimes', 'array'],
            'offer_lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
        ];
    }

    /**
     * The distinct, complete product ids only — a row missing `product_id`
     * resolves nothing and is dropped, mirroring
     * RequestFormContextRequest::productLines()'s own "complete rows only"
     * rule.
     *
     * @return array<int, int>
     */
    public function productIds(): array
    {
        return collect((array) ($this->validated('offer_lines') ?? []))
            ->filter(static fn (mixed $row): bool => is_array($row) && ($row['product_id'] ?? null) !== null)
            ->map(static fn (array $row): int => (int) $row['product_id'])
            ->unique()
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/request-management/form-context (user directive 2026-08-07): the
 * create form's live preview of the applicable "Informazioni aggiuntive" and
 * their layout, for the product lines typed so far. Same endpoint the module
 * carried before spec 0084 moved the section to the Offerta, restored with
 * the SAME wire shape — its criteria are still `product_lines`, the create
 * form's own classification (the Offerta is not created yet, so there are no
 * `offer_lines` to key on the way POST /api/quotes/form-context does).
 *
 * READ-ONLY despite the verb: the criteria are a collection of objects, which
 * has no sane query-string encoding — the same reason the bulk endpoints of
 * this module POST too.
 *
 * Deliberately LENIENT where StoreRequestRequest is strict: this is called
 * while the operator is still filling the form, so a row with no categoria
 * yet is DROPPED, never a 422 — it simply scopes nothing. The referenced id
 * must still exist: an unknown id is a malformed request either way.
 *
 * Spec 0132, D-3: `business_function_id` is no longer part of the row's
 * identity — a row is still accepted with the key present (retrocompatibility
 * of the call, in case a stale client still sends it), but it carries no
 * rule of its own and is never read: only `product_category_id` scopes the
 * preview.
 *
 * Authorization is handled in the controller (`request-management.create`),
 * mirroring every other action of this module.
 */
class RequestFormContextRequest extends FormRequest
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
            'product_lines' => ['sometimes', 'array'],
            'product_lines.*.product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The categories of the rows that carry one: a row missing it is still
     * being filled in and is not a line the form is going to submit.
     *
     * @return array<int, int>
     */
    public function productCategoryIds(): array
    {
        return collect((array) ($this->validated('product_lines') ?? []))
            ->filter(static fn (mixed $row): bool => is_array($row) && ($row['product_category_id'] ?? null) !== null)
            ->map(static fn (array $row): int => (int) $row['product_category_id'])
            ->unique()
            ->values()
            ->all();
    }
}

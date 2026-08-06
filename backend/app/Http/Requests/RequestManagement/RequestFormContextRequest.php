<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/request-management/form-context (user directive 2026-07-31): the
 * create form's live preview of the applicable dynamic attributes and their
 * layout, for the criteria typed so far. Spec 0083, D-2: the working-status
 * set this endpoint used to preview is GONE — the Opportunity created by this
 * form resolves no working-state dimension of its own any more.
 *
 * READ-ONLY despite the verb: the criteria are a collection of objects, which
 * has no sane query-string encoding — the same reason the bulk endpoints of
 * this module POST too. Nothing is persisted, so there is no sparse/XOR
 * semantics to mirror from StoreRequestRequest.
 *
 * Deliberately LENIENT where StoreRequestRequest is strict: this is called
 * while the operator is still filling the form, so a half-picked product line
 * (a funzione with no categoria yet) is DROPPED, never a 422 — it simply
 * scopes nothing. The referenced ids must still exist: an unknown id is a
 * malformed request either way, and letting it through would make the
 * resolvers answer for a category that is not there.
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
            'product_lines.*.business_function_id' => ['nullable', 'integer', Rule::exists('business_functions', 'id')],
            'product_lines.*.product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The COMPLETE rows only: a row missing either id resolves nothing, and
     * passing it on would make the resolvers reason about a partial pair.
     *
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    public function productLines(): array
    {
        return collect((array) ($this->validated('product_lines') ?? []))
            ->filter(static fn (mixed $row): bool => is_array($row)
                && ($row['business_function_id'] ?? null) !== null
                && ($row['product_category_id'] ?? null) !== null)
            ->map(static fn (array $row): array => [
                'business_function_id' => (int) $row['business_function_id'],
                'product_category_id' => (int) $row['product_category_id'],
            ])
            ->values()
            ->all();
    }
}

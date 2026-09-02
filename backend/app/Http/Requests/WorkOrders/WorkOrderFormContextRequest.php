<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/work-orders/form-context (spec 0098, D-7): the WorkOrder form's
 * (and the Contract's "Programma" dialog's) live preview of the applicable
 * "Informazioni aggiuntive" and their layout, for the `quote_line_ids`
 * composed so far — before anything is saved. Gemello of
 * App\Http\Requests\Quotes\QuoteFormContextRequest, scoped to
 * `quote_line_ids` (D-1: the source is the work order's OWN lines) rather
 * than `offer_lines[].product_id`.
 *
 * READ-ONLY despite the verb: mirrors every other `form-context` endpoint of
 * this codebase.
 *
 * Deliberately LENIENT on absence (D-7/AC-017): no lines yet resolves an
 * empty set/null layout, never a 422. The referenced id must still exist: an
 * unknown id is a malformed request either way.
 *
 * Authorization is handled in the controller (`work-orders.create` OR
 * `work-orders.update`), mirroring the api-contract.
 */
class WorkOrderFormContextRequest extends FormRequest
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
            'quote_line_ids' => ['sometimes', 'array'],
            'quote_line_ids.*' => ['integer', Rule::exists('quote_lines', 'id')],
        ];
    }

    /**
     * The distinct, submitted quote-line ids — an empty/absent array
     * resolves nothing (D-7).
     *
     * @return array<int, int>
     */
    public function quoteLineIds(): array
    {
        return collect((array) ($this->validated('quote_line_ids') ?? []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}

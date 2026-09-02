<?php

namespace App\Http\Requests\QuoteOfferLines;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/quote-offer-lines/for-select (spec 0093,
 * D-8). Unlike every other for-select in the app (ADR 0011, amended
 * 2026-07-31 — no gate beyond `auth:sanctum`), `quote_id` is REQUIRED here:
 * this is not a generic lookup, it exposes ONE quote's own line/product
 * detail, so the endpoint narrows to it explicitly rather than defaulting to
 * "every line of every offer". Authorization (the OR gate documented on the
 * controller) is intentionally NOT handled here either.
 *
 * `except_work_order_id` (spec 0095, D-7): optional, readmits the lines
 * already programmed into THAT work order when the service excludes every
 * OTHER already-programmed line (D-4) — the work-order edit form's own
 * picker.
 */
class QuoteOfferLineForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller (work-orders.view OR
        // quotes.view, spec 0093 D-8).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'except_work_order_id' => ['sometimes', 'nullable', 'integer', 'exists:work_orders,id'],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}

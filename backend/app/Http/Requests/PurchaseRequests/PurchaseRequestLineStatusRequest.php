<?php

namespace App\Http\Requests\PurchaseRequests;

use App\Enums\PurchaseRequestLineStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/purchase-request-lines/status (spec 0208, D-9): one or
 * many lines (of one or several requests) moved to the same status. Who may do
 * it, and from which state, is decided by PurchaseRequestLineStatusService.
 */
class PurchaseRequestLineStatusRequest extends FormRequest
{
    public const int MAX_LINES = 200;

    public const int REASON_MAX_LENGTH = 2000;

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
            'line_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'line_ids.*' => ['required', 'integer', 'distinct', Rule::exists('purchase_request_lines', 'id')],
            'to_status' => ['required', Rule::enum(PurchaseRequestLineStatus::class)],
            'reason' => ['nullable', 'string', 'max:'.self::REASON_MAX_LENGTH],
            // Supplied for the lines that miss one when ordering (spec 0209, D-2).
            'suppliers' => ['nullable', 'array', 'max:'.self::MAX_LINES, 'prohibited_unless:to_status,'.PurchaseRequestLineStatus::Ordered->value],
            'suppliers.*.line_id' => ['required', 'integer', 'distinct', Rule::in(array_filter((array) $this->input('line_ids'), 'is_scalar'))],
            'suppliers.*.supplier_id' => ['required', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
        ];
    }

    /**
     * @return array<int, int> line id => supplier id
     */
    public function suppliers(): array
    {
        return collect($this->validated('suppliers') ?? [])
            ->mapWithKeys(fn (array $entry): array => [(int) $entry['line_id'] => (int) $entry['supplier_id']])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function lineIds(): array
    {
        return array_map('intval', $this->validated('line_ids'));
    }

    public function toStatus(): PurchaseRequestLineStatus
    {
        return PurchaseRequestLineStatus::from($this->validated('to_status'));
    }

    public function reason(): ?string
    {
        return $this->validated('reason');
    }
}

<?php

namespace App\Http\Requests\WorkOrderContractData;

use App\DataObjects\WorkOrders\UpdateWorkOrderLinePaymentData;
use App\Models\WorkOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates PATCH /api/work-orders/{workOrder}/contract-data/lines (spec 0333):
 * the same status and/or unpaid flag on several lines of the commessa.
 * Authorization is `managePayments` on the owning commessa, as for the single
 * line. Every id must be a line OF THIS commessa (D-2) and the status must be
 * active (D-3): no "already assigned" exception across many lines.
 */
class BulkUpdateWorkOrderLinePaymentsRequest extends FormRequest
{
    private const int MAX_LINE_IDS = 200;

    /** @var array<int, string> keys the bulk may set (D-1) */
    private const array PAYMENT_KEYS = ['work_order_payment_status_id', 'has_unpaid'];

    public function authorize(): bool
    {
        return $this->user()?->can('managePayments', $this->workOrder()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quote_line_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINE_IDS],
            'quote_line_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('quote_line_work_order', 'quote_line_id')->where('work_order_id', $this->workOrder()->id),
            ],
            'work_order_payment_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('work_order_payment_statuses', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'has_unpaid' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(self::PAYMENT_KEYS)) {
                $validator->errors()->add('work_order_payment_status_id', __('Choose at least one value to set.'));
            }
        }];
    }

    public function toData(): UpdateWorkOrderLinePaymentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateWorkOrderLinePaymentData::fromValidated($validated);
    }

    /**
     * @return array<int, int>
     */
    public function lineIds(): array
    {
        return array_map(intval(...), $this->validated('quote_line_ids'));
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $workOrder;
    }
}

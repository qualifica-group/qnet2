<?php

namespace App\Http\Requests\WorkOrderContractData;

use App\DataObjects\WorkOrders\UpdateWorkOrderLinePaymentData;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /api/work-orders/{workOrder}/contract-data/lines/{quoteLine}
 * (spec 0201, D-3/D-12). Authorization is `managePayments` on the owning
 * commessa (permission + membership scoping), checked before any payload
 * validation. The status must exist and be active, except the one the line
 * ALREADY holds: a status deactivated after being assigned stays saveable
 * unchanged.
 */
class UpdateWorkOrderLinePaymentRequest extends FormRequest
{
    private const int AGREEMENT_MAX = 2000;

    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $this->user()?->can('managePayments', $workOrder) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentStatusId = $this->currentStatusId();

        return [
            'work_order_payment_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('work_order_payment_statuses', 'id')->where(
                    fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('is_active', true)->orWhere('id', $currentStatusId)),
                ),
            ],
            'payment_agreement' => ['sometimes', 'nullable', 'string', 'max:'.self::AGREEMENT_MAX],
            'has_unpaid' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): UpdateWorkOrderLinePaymentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateWorkOrderLinePaymentData::fromValidated($validated);
    }

    private function currentStatusId(): ?int
    {
        /** @var QuoteLine $quoteLine */
        $quoteLine = $this->route('quoteLine');

        $current = WorkOrderLinePayment::query()->where('quote_line_id', $quoteLine->id)->value('work_order_payment_status_id');

        return $current === null ? null : (int) $current;
    }
}

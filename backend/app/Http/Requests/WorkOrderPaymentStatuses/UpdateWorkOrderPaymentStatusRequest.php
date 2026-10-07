<?php

namespace App\Http\Requests\WorkOrderPaymentStatuses;

use App\DataObjects\WorkOrderPaymentStatuses\UpdateWorkOrderPaymentStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT/PATCH /api/work-order-payment-statuses/{workOrderPaymentStatus}
 * (spec 0201). Every field is `sometimes` for partial updates; `color`, when
 * sent, cannot be empty. Authorization stays in the controller.
 */
class UpdateWorkOrderPaymentStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

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
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('work_order_payment_statuses', 'name')->ignore($this->paymentStatus()->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'allows_delivery' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'work-order-payment-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->paymentStatus();
    }

    public function toData(): UpdateWorkOrderPaymentStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateWorkOrderPaymentStatusData::fromValidated($validated);
    }

    private function paymentStatus(): WorkOrderPaymentStatus
    {
        /** @var WorkOrderPaymentStatus $status */
        $status = $this->route('workOrderPaymentStatus');

        return $status;
    }
}

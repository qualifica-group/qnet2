<?php

namespace App\Http\Requests\WorkOrderPaymentStatuses;

use App\DataObjects\WorkOrderPaymentStatuses\CreateWorkOrderPaymentStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/work-order-payment-statuses (spec 0201). Authorization
 * stays in the controller (WorkOrderPaymentStatusPolicy); `sort_order` and
 * `old_id` are absent from rules() so validated() drops them.
 */
class StoreWorkOrderPaymentStatusRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191', Rule::unique('work_order_payment_statuses', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'max:32'],
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
        return null;
    }

    public function toData(): CreateWorkOrderPaymentStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateWorkOrderPaymentStatusData::fromValidated($validated);
    }
}

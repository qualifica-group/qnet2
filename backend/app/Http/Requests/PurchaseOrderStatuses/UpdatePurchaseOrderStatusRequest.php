<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrderStatuses;

use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PurchaseOrderStatusLabel;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT/PATCH /api/purchase-order-statuses/{purchaseOrderStatus} (spec
 * 0248, D-2). Only name, color and icon are writable; `system_key` and
 * `sort_order` are `prohibited` (the order changes only via reorder).
 * Authorization stays in the controller (Policy update).
 */
class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $label = $this->route('purchaseOrderStatus');
        $ignoreId = $label instanceof PurchaseOrderStatusLabel ? $label->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('purchase_order_statuses', 'name')->ignore($ignoreId)],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'sort_order' => ['prohibited'],
            'system_key' => ['prohibited'],
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
        return 'purchase-order-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var PurchaseOrderStatusLabel $label */
        $label = $this->route('purchaseOrderStatus');

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    public function updatableAttributes(): array
    {
        return $this->safe()->only(['name', 'color', 'icon']);
    }
}

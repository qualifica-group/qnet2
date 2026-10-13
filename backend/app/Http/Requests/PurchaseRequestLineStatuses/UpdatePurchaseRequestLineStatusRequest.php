<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseRequestLineStatuses;

use App\DataObjects\PurchaseRequestLineStatuses\UpdatePurchaseRequestLineStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT/PATCH /api/purchase-request-line-statuses/{purchaseRequestLineStatus}
 * (spec 0248, D-2): only name, color and icon are writable; `system_key` and
 * `sort_order` are prohibited (the order changes only through reorder).
 * Authorization stays in the controller (Policy).
 */
class UpdatePurchaseRequestLineStatusRequest extends FormRequest
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
        $status = $this->route('purchaseRequestLineStatus');
        $ignoreId = $status instanceof PurchaseRequestLineStatusLabel ? $status->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('purchase_request_line_statuses', 'name')->ignore($ignoreId)],
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
        return 'purchase-request-line-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var PurchaseRequestLineStatusLabel $status */
        $status = $this->route('purchaseRequestLineStatus');

        return $status;
    }

    public function toData(): UpdatePurchaseRequestLineStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdatePurchaseRequestLineStatusData::fromValidated($validated);
    }
}

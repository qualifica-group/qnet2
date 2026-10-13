<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseRequestPriorities;

use App\DataObjects\PurchaseRequestPriorities\UpdatePurchaseRequestPriorityData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PurchaseRequestPriority;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/purchase-request-priorities/{purchaseRequestPriority} (spec 0101,
 * D-4). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $purchaseRequestPriority)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `name` is unique ignoring self; `color`, when submitted, cannot be
 * null/empty (`sometimes|required`); `color`/`icon` are checked against
 * App\Support\BadgeTokens' allow-lists (AC-046).
 *
 * `sort_order` and `system_key` are `prohibited` (AC-045).
 */
class UpdatePurchaseRequestPriorityRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via PurchaseRequestPriorityPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $purchaseRequestPriority = $this->route('purchaseRequestPriority');
        $ignoreId = $purchaseRequestPriority instanceof PurchaseRequestPriority ? $purchaseRequestPriority->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('purchase_request_priorities', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'is_active' => ['sometimes', 'boolean'],
            // Spec 0154, D-8: whether this is the row a new purchase request falls back to
            // when the field is omitted. TaskTypeService/PurchaseRequestPriorityService/
            // TaskImportanceService enforce "at most one default" and "a
            // default must be active" — never expressible as a validation rule
            // alone, since it depends on the OTHER rows' state.
            'is_default' => ['sometimes', 'boolean'],
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
        return 'purchase-request-priorities';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var PurchaseRequestPriority $purchaseRequestPriority */
        $purchaseRequestPriority = $this->route('purchaseRequestPriority');

        return $purchaseRequestPriority;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdatePurchaseRequestPriorityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdatePurchaseRequestPriorityData::fromValidated($validated);
    }
}

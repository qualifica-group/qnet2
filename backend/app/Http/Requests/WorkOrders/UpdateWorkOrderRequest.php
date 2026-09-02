<?php

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\WorkOrders\UpdateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\WorkOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/work-orders/{workOrder} (spec
 * 0093). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $workOrder)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `code`/`quote_id` (D-1/D-5): `prohibited`, UNCONDITIONALLY — the keys must
 * not even be present in the payload, regardless of value and regardless of
 * the actor's role. Field permissions alone cannot express this: the
 * privileged role bypasses every ceiling, so the immutability guard lives
 * here instead, ahead of and independent from that mechanism (mirrors
 * UpdateUnitOfMeasureRequest's own `code` rule).
 */
class UpdateWorkOrderRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int TITLE_MAX = 191;

    public function authorize(): bool
    {
        // Authorization handled in the controller via WorkOrderPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'quote_id' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:'.self::TITLE_MAX],
            'type' => ['sometimes', 'required', 'string', Rule::in(WorkOrderType::values())],
            'callback_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'is_force_closed' => ['sometimes', 'boolean'],
            // No `sometimes` here — same reasoning as StoreWorkOrderRequest:
            // `required_if:is_force_closed,true` only fires when this PATCH
            // also submits `is_force_closed: true` in the same payload
            // (its condition reads the REQUEST, not the DB), so a PATCH that
            // omits both keys entirely is unaffected either way.
            'force_close_reason' => ['nullable', 'string', 'required_if:is_force_closed,true'],
            'quote_line_ids' => ['sometimes', 'array'],
            'quote_line_ids.*' => ['integer'],
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
        return 'work-orders';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $workOrder;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateWorkOrderData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateWorkOrderData::fromValidated($validated);
    }
}

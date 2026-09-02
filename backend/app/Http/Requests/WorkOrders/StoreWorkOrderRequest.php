<?php

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/work-orders (spec 0093).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', WorkOrder::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null). `code`/
 * `quote_id` are writable ONLY here (D-1/D-5): permanently immutable once
 * persisted, enforced by UpdateWorkOrderRequest's own `prohibited` rules.
 *
 * `participant_slots` (spec 0096, D-3) is validated by the shared
 * ValidatesManagerSlots trait — the very same rules Registries, Opportunita'
 * and Offerte submit under `manager_slots`, never a second copy.
 *
 * `quote_line_ids`' membership invariant (D-7: must belong to `quote_id` AND
 * be REVENUE) is NOT checked here — it needs a query against the real rows,
 * so it is enforced server-side by
 * App\Services\WorkOrders\WorkOrderLineWriter inside the create transaction
 * (AC-022/AC-023), never trusted from the client.
 */
class StoreWorkOrderRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesManagerSlots;

    private const string PARTICIPANT_SLOTS_FIELD = 'participant_slots';

    private const int CODE_MAX = 32;

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
            'code' => ['sometimes', 'nullable', 'string', 'max:'.self::CODE_MAX, Rule::unique('work_orders', 'code')],
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'type' => ['required', 'string', Rule::in(WorkOrderType::values())],
            // Spec 0096, D-1: `start_date` is NOT NULL and a commessa always
            // has at least one Responsabile — required here and in the
            // Contract's "Programma" dialog alike.
            'start_date' => ['required', 'date'],
            'supervisor_ids' => ['required', 'array', 'min:1'],
            'supervisor_ids.*' => ['integer', Rule::exists('users', 'id')],
            'callback_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'is_force_closed' => ['sometimes', 'boolean'],
            // No `sometimes` here (AC-030): `sometimes` only validates a
            // field that IS present, which would let `required_if` never
            // fire when the client omits the key entirely — the exact case
            // AC-030 covers ("force_close_reason assente"). Without it,
            // Laravel validates the (absent-as-null) field unconditionally,
            // so `required_if` correctly rejects it when `is_force_closed`
            // is true.
            'force_close_reason' => ['nullable', 'string', 'required_if:is_force_closed,true'],
            'quote_line_ids' => ['sometimes', 'array'],
            'quote_line_ids.*' => ['integer'],
            // Spec 0098: "Informazioni aggiuntive" — per-code applicability/
            // type/required validated separately, server-side, by
            // WorkOrderAttributeValueWriter (WorkOrderService::create()),
            // never here (mirrors StoreQuoteRequest's own `attribute_values`).
            'attribute_values' => ['sometimes', 'array'],
            // Partecipanti (spec 0096, D-3): the SAME gap-aware slot shape
            // Registries/Opportunita'/Offerte submit as `manager_slots`,
            // validated by the shared trait under this module's own key —
            // cap and "one slot per user" included.
            ...$this->managerSlotsRules(self::PARTICIPANT_SLOTS_FIELD),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateManagerSlots($validator, self::PARTICIPANT_SLOTS_FIELD);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'work-orders';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateWorkOrderData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateWorkOrderData::fromValidated($validated);
    }
}

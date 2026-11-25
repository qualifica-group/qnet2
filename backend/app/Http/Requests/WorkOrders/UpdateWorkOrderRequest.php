<?php

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\WorkOrders\UpdateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\RejectsLockedWorkOrderAttributes;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesStandaloneWorkOrderReferences;
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
    use EnforcesFieldPermissions, RejectsLockedWorkOrderAttributes, ValidatesManagerSlots, ValidatesStandaloneWorkOrderReferences;

    private const string PARTICIPANT_SLOTS_FIELD = 'participant_slots';

    private const int TITLE_MAX = 191;

    /** The keys the "Chiusura forzata"/"Riapri" detail actions send. */
    private const array CLOSURE_FIELDS = ['is_force_closed', 'force_close_reason', 'force_close_status_id'];

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
            // Spec 0124, D-5: written only at creation, immutable afterwards
            // — the key must not even be present (AC-022), same shape as
            // code/quote_id above.
            'task_template_id' => ['prohibited'],
            'title' => ['sometimes', 'nullable', 'string', 'max:'.self::TITLE_MAX],
            'type' => ['sometimes', 'required', 'string', Rule::in(WorkOrderType::values())],
            // Spec 0096, D-6: editable after create (unlike code/quote_id).
            // `required` rejects an explicit null, so the 422 comes from
            // validation and not from the database. The Responsabili may be
            // emptied (user decision 2026-10-20).
            'start_date' => ['sometimes', 'required', 'date'],
            'supervisor_ids' => ['sometimes', 'array'],
            'supervisor_ids.*' => ['integer', Rule::exists('users', 'id')],
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
            // Spec 0324 D-6: domain (set + `closed` group) is checked by the status assigner.
            'force_close_status_id' => ['nullable', 'integer'],
            'quote_line_ids' => ['sometimes', 'array'],
            'quote_line_ids.*' => ['integer'],
            // Spec 0322: membership in the set resolved for the commessa is
            // enforced by WorkOrderWorkflowStatusAssigner (422), the single
            // choke point every write channel reaches.
            'work_order_workflow_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_order_workflow_statuses', 'id')],
            // Spec 0098: "Informazioni aggiuntive", same sparse-PATCH
            // convention as UpdateQuoteRequest — validated server-side by
            // WorkOrderAttributeValueWriter (WorkOrderService::update()).
            'attribute_values' => ['sometimes', 'array'],
            // Full-replace of the partecipanti pivot when — and only when —
            // the key is submitted (spec 0096, D-3/AC-024).
            ...$this->managerSlotsRules(self::PARTICIPANT_SLOTS_FIELD),
            // Spec 0431: the own references exist only on a commessa born without an offer.
            ...($this->workOrder()->is_standalone
                ? $this->standaloneReferenceRules(required: false, currentProductIds: $this->workOrder()->products()->pluck('products.id')->all())
                : array_fill_keys(self::STANDALONE_REFERENCE_KEYS, ['prohibited'])),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_fill_keys(
            array_map(static fn (string $key): string => $key.'.prohibited', self::STANDALONE_REFERENCE_KEYS),
            __('work_orders.references_owned_by_offer'),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateManagerSlots($validator, self::PARTICIPANT_SLOTS_FIELD);
            $this->enforceFieldPermissions($validator);

            $workOrder = $this->workOrder();
            $this->rejectLockedAttributeChangesOf($validator, $workOrder);

            if ($workOrder->is_standalone) {
                $this->validateStandaloneReferences(
                    $validator,
                    $workOrder->company_id,
                    $workOrder->company_site_id,
                    $workOrder->products()->pluck('products.id')->all(),
                    $workOrder->productLines()->pluck('product_category_id')->all(),
                );
                // Spec 0431, D-5: without an offer the commessa stays a project one.
                if ($this->has('type') && $this->input('type') !== WorkOrderType::Project->value) {
                    $validator->errors()->add('type', __('work_orders.standalone_type_is_project'));
                }
            }
        });
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $workOrder;
    }

    /**
     * Whether the payload carries only the closure keys (and at least
     * `is_force_closed`), so WorkOrderController authorizes it as the closure
     * action rather than as the form.
     */
    public function isClosureOnly(): bool
    {
        $keys = array_keys($this->all());

        return in_array('is_force_closed', $keys, true) && array_diff($keys, self::CLOSURE_FIELDS) === [];
    }

    protected function authorizationResource(): string
    {
        return 'work-orders';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->workOrder();
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

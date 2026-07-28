<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Notes\CreateNoteData;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use App\RequestManagement\OpportunityAttributeLayoutResolver;
use App\Services\Notes\NoteService;
use App\Services\Opportunities\OpportunityProductInterestWriter;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\Opportunities\RewardAssignmentWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the request-management work panel (spec 0049): the
 * record IS an Opportunity (D-1), read/written through this dedicated
 * service rather than OpportunityService/OpportunityController — the
 * operative endpoints have their OWN authorization (`request-management.*`)
 * and their own write rules (D-4/D-5).
 *
 * `loadWorkPanel()`/`updateWork()` both return the SAME shape —
 * {opportunity, applicable_attributes, workflow_statuses, attribute_layout} —
 * consumed directly by RequestManagementResource, so show/update render
 * identically (data contract: "Response identica alla GET").
 *
 * Activity logging: `opportunity_workflow_status_id`, `attribute_values` and
 * (spec 0052 D-2) `next_callback_at` are ALL deliberately excluded from
 * `Opportunity::$fillable` (mass-assignment guard), and
 * `LogsModelActivity::getActivitylogOptions()` calls
 * `logFillable()` — Spatie's dirty-diff only ever inspects the model's
 * fillable attributes. A change to either column therefore never reaches the
 * automatic model-event log. `updateWork()` compensates with an EXPLICIT
 * `activity()` call carrying the same `attributes`/`old` property shape the
 * automatic log would have produced, so GET /api/activity-log/request-
 * management/{id} (reading the Opportunity's own activity rows, D-7) still
 * sees the operative change (AC-043).
 *
 * Spec 0054, D-5: this is the ONE choke point for the working-status
 * advance, reached BOTH by the work panel (UpdateRequestRequest) and by the
 * inline-edit engine (RequestManagementTableDefinition::updateCell()) — a
 * status requiring a note (`requires_note`) enforces it HERE, so the two
 * write channels can never diverge on the rule.
 */
final class RequestManagementService
{
    /**
     * The `notes.notable_types` slug this module registers itself under
     * (config/notes.php) — the same entity a status-change note attaches to
     * as the collaborative-notes dialog would (spec 0052/0054 D-5).
     */
    private const string NOTE_ENTITY_TYPE = 'request-management';

    /**
     * Relations the work panel needs, eager-loaded in one shot (N+1-free):
     * contacts hang off each side's PersonalData card (HasPersonalData ->
     * HasContacts), never directly off Registry/Referent.
     *
     * @var array<int, string>
     */
    private const array WORK_PANEL_RELATIONS = [
        'registry.personalData.contacts',
        // The client's comuni of birth and residence, shown in the identity block.
        'registry.personalData.birthCity',
        'registry.personalData.residenceCity',
        // The client's address is edited inline in the panel's "anagrafica"
        // section, with its geo names hydrated for the cascading selects.
        'registry.personalData.addresses.city',
        'registry.personalData.addresses.province',
        'registry.personalData.addresses.state',
        'registry.personalData.addresses.country',
        'referent.personalData.contacts',
        'commercial',
        // Attribution block (user directive 2026-07-22): "Fonte" and
        // "Segnalatore"; the GA2 "Operatore" rides on `managers` below.
        'source',
        'reporter',
        // Spec 0056: the Sede operativa, also in the attribution block — the
        // site has no own name, its label composed from the primary address.
        'operationalSite.addresses.city',
        'opportunityStatus',
        'workflowStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'productsOfInterest.category',
        'managers',
    ];

    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly OpportunityAttributeLayoutResolver $attributeLayoutResolver,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeValueNormalizer $attributeValueNormalizer,
        private readonly OpportunityWorkflowResolver $workflowResolver,
        private readonly OpportunityProductInterestWriter $productInterestWriter,
        private readonly RequestClientProfileWriter $clientProfileWriter,
        private readonly NoteService $noteService,
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
    ) {}

    /**
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>, attribute_layout: array<string, mixed>|null}
     */
    public function loadWorkPanel(Opportunity $opportunity, FormMode $formMode = FormMode::Edit): array
    {
        $opportunity->loadMissing(self::WORK_PANEL_RELATIONS);

        return [
            'opportunity' => $opportunity,
            'applicable_attributes' => $this->attributesResolver->resolve($opportunity),
            'workflow_statuses' => $this->resolveWorkflowStatuses($opportunity),
            'attribute_layout' => $this->attributeLayoutResolver->resolve($opportunity, $formMode),
        ];
    }

    /**
     * Applies the sparse PATCH payload (spec 0049 data_contract: only the
     * submitted keys change) and returns the SAME work-panel shape as
     * loadWorkPanel(), post-save.
     *
     * @param  array{opportunity_workflow_status_id?: int|null, note?: string|null, attribute_values?: array<string, mixed>, next_callback_at?: string|null, products_of_interest?: array<int, int>, source_id?: int|null, reporter_id?: int|null, operator_id?: int|null, rewards?: array<int, array{reward_type_id: int}>, client_identity?: CreatePersonalData, client_contacts?: array<int, ContactInput>, client_address?: AddressInput, client_first_name?: string|null, client_last_name?: string|null, client_tax_code?: string|null, client_phone?: string|null}  $data
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>, attribute_layout: array<string, mixed>|null}
     */
    public function updateWork(Opportunity $opportunity, User $actor, array $data): array
    {
        return DB::transaction(function () use ($opportunity, $actor, $data): array {
            $changed = [];
            $old = [];
            // spec 0059, D-3/AC-022: captured BEFORE Step 0 mutates
            // reporter_id in-memory, so applyRewards() can tell a genuine
            // change apart from an untouched/no-op submission.
            $previousReporterId = $opportunity->reporter_id;

            // Step 0: attribution (user directive 2026-07-22) — applied
            // BEFORE the working-state step on purpose: `source_id` is one of
            // the criteria OpportunityWorkflowResolver resolves a workflow
            // from (spec 0047), so a PATCH that changes fonte AND status in
            // one shot must validate the status against the NEW set (the same
            // ordering ValidatesWorkflowStatus already applies request-side).
            $sourceChanged = $this->applyAttribution($opportunity, $data);

            // Step 1: working-state advance — set-membership (AC-011) and the
            // mandatory-note rule (spec 0054, D-5) both enforced HERE, the one
            // choke point both write channels pass through.
            if (array_key_exists('opportunity_workflow_status_id', $data) && $data['opportunity_workflow_status_id'] !== null) {
                $this->applyWorkflowStatus($opportunity, (int) $data['opportunity_workflow_status_id'], $actor, $data['note'] ?? null, $changed, $old);
            }

            // Step 2: dynamic field values — validate against the CURRENT
            // applicable set (AttributeValueValidator, keyed
            // attribute_values.<code> on failure), then merge into the
            // existing map (sparse: unset codes keep their persisted value).
            if (array_key_exists('attribute_values', $data)) {
                $this->applyAttributeValues($opportunity, (array) $data['attribute_values'], $changed, $old);
            }

            // Step 3: next planned callback (spec 0052 D-1/D-4) — sparse:
            // key absent leaves the persisted value untouched, `null` clears
            // it. A real value change also zeroes the reminder marker so a
            // rescheduled date is not skipped by the future reminder job.
            if (array_key_exists('next_callback_at', $data)) {
                $this->applyNextCallbackAt($opportunity, $data['next_callback_at'], $changed, $old);
            }

            $opportunity->save();

            // Step 3-bis: the GA2 "Operatore" — a pivot row, so it is written
            // after the model save like every other reference collection.
            if (array_key_exists('operator_id', $data)) {
                $this->applyOperator($opportunity, $data['operator_id'], $changed, $old);
            }

            // Step 3-ter: a changed fonte can move the opportunity onto a
            // different workflow (spec 0047). With no explicit status
            // submitted the resolver re-derives it exactly as
            // OpportunityService::update() does — targetStatus() keeps the
            // current row when it still belongs to the new set, so this is a
            // no-op whenever the two workflows share the status.
            if ($sourceChanged && ($data['opportunity_workflow_status_id'] ?? null) === null) {
                $this->workflowResolver->resolveAndAssign($opportunity);
            }

            // Step 4: "prodotti di interesse" (user directive 2026-07-22) —
            // a to-many reference, written after the model save like every
            // other collection. Adding a product outside the opportunity's
            // categories also adds its product line (the writer owns that
            // rule for both write channels).
            if (array_key_exists('products_of_interest', $data)) {
                $this->applyProductsOfInterest($opportunity, (array) $data['products_of_interest'], $changed, $old);
            }

            // Step 4-bis: reward assignments (spec 0059, AC-023) — identical
            // semantics to the opportunities payload (D-3): the retarget half
            // runs whenever `reporter_id` genuinely changed, INDEPENDENT of
            // whether `rewards` itself was submitted; the sync half only when
            // `rewards` was submitted. Both operate on the writer shared with
            // OpportunityService, so the two channels can never diverge.
            $this->applyRewards($opportunity, $previousReporterId, $data, $changed, $old);

            // Step 5: client anagraphic (spec 0049 amendment; spec 0055 D-7 for
            // the inline channel's four sparse single-field keys) — identity,
            // contacts and address land on the Registry's PersonalData card,
            // not on the opportunity, so they are written outside the model
            // save. The writer reports its single-field changes into
            // $changed/$old so Step 6 audits them (D-9).
            $this->clientProfileWriter->applyTo($opportunity, $data, $changed, $old);

            // Step 6: explicit activity entry (see class docblock).
            $this->logOperationalChange($opportunity, $actor, $changed, $old);

            return $this->loadWorkPanel($opportunity);
        });
    }

    /**
     * The attribution scalars: "Fonte" (`source_id`) and "Segnalatore"
     * (`reporter_id`), user directive 2026-07-22; "Sede operativa"
     * (`operational_site_id`), spec 0056. All three ARE in
     * Opportunity::$fillable, so — unlike the operative fields of this panel —
     * they are mass-assigned here and their change is picked up by the
     * automatic activity log (LogsModelActivity::logFillable()); no explicit
     * entry is added for them, which would double-log the same diff.
     *
     * @param  array<string, mixed>  $data
     * @return bool whether `source_id` actually changed — the workflow
     *              resolution criterion the caller re-runs on (spec 0047)
     */
    private function applyAttribution(Opportunity $opportunity, array $data): bool
    {
        $submitted = array_intersect_key($data, array_flip(['source_id', 'reporter_id', 'operational_site_id']));

        if ($submitted === []) {
            return false;
        }

        $previousSourceId = $opportunity->source_id;
        $opportunity->fill($submitted);

        return $opportunity->source_id !== $previousSourceId;
    }

    /**
     * The GA2 "Operatore" (user directive 2026-07-22): delegated to
     * RequestOperatorWriter, the ONE implementation of the operator-slot rule
     * shared with the bulk assignment (RequestAssignmentService).
     *
     * The pivot is not a fillable attribute, so — like every other operative
     * field of this panel — the change is logged explicitly by the caller.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyOperator(Opportunity $opportunity, mixed $value, array &$changed, array &$old): void
    {
        $this->operatorWriter->apply($opportunity, $value === null ? null : (int) $value, $changed, $old);
    }

    /**
     * "Prodotti di interesse" (user directive 2026-07-22): an authoritative
     * replace of the whole collection. Like every other operative field here
     * it is NOT mass-assignable (it is a relation), so the change is logged
     * explicitly — including the product lines the writer had to add for a
     * cross-category pick, which would otherwise be an invisible side effect.
     *
     * @param  array<int, int>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException
     */
    private function applyProductsOfInterest(Opportunity $opportunity, array $submitted, array &$changed, array &$old): void
    {
        $current = $opportunity->productsOfInterest()->pluck('products.id')->map(intval(...))->sort()->values()->all();
        $next = collect($submitted)->map(intval(...))->unique()->sort()->values()->all();

        if ($current === $next) {
            return;
        }

        $addedLines = $this->productInterestWriter->sync($opportunity, $next);

        $old['products_of_interest'] = $current;
        $changed['products_of_interest'] = $next;

        if ($addedLines !== []) {
            $changed['product_lines_added'] = $addedLines;
        }
    }

    /**
     * Reward assignments (spec 0059, AC-023): identical D-3 semantics to
     * OpportunityService::update() — the FormRequest (ValidatesRewards) has
     * already rejected the two invalid combinations (non-empty `rewards`
     * without a reporter; a `reporter_id` clear while rewards exist), so no
     * extra guard runs here.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyRewards(Opportunity $opportunity, ?int $previousReporterId, array $data, array &$changed, array &$old): void
    {
        $reporterChanged = array_key_exists('reporter_id', $data) && $opportunity->reporter_id !== $previousReporterId;

        if ($reporterChanged) {
            $this->rewardAssignmentWriter->retarget($opportunity);
        }

        if (! array_key_exists('rewards', $data)) {
            return;
        }

        $current = $opportunity->rewards()->pluck('reward_type_id')->map(intval(...))->sort()->values()->all();
        $next = collect((array) $data['rewards'])
            ->map(static fn (array $row): int => (int) $row['reward_type_id'])
            ->unique()->sort()->values()->all();

        if ($current === $next) {
            return;
        }

        $this->rewardAssignmentWriter->sync($opportunity, $next);

        $old['rewards'] = $current;
        $changed['rewards'] = $next;
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException the target status is outside the resolved workflow (AC-011), or it `requires_note` and none was given (spec 0054, D-5)
     * @throws AuthorizationException the actor cannot create the note (D-5)
     */
    private function applyWorkflowStatus(Opportunity $opportunity, int $newStatusId, User $actor, ?string $note, array &$changed, array &$old): void
    {
        $currentStatusId = $opportunity->opportunity_workflow_status_id;

        if ($currentStatusId === $newStatusId) {
            return; // resending the current status is not an advance (mirrors D-4's callback-instant comparison): no note requirement either.
        }

        // AC-011: the same resolved-workflow membership ValidatesWorkflowStatus
        // already enforces for the panel channel — re-checked here so the
        // inline-edit channel (which never goes through that FormRequest)
        // gets the identical guarantee, never a second/different rule.
        $allowedIds = $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity))->pluck('id');

        if (! $allowedIds->contains($newStatusId)) {
            throw ValidationException::withMessages([
                'opportunity_workflow_status_id' => ["The selected working status does not belong to the opportunity's resolved workflow."],
            ]);
        }

        // Spec 0054, D-5: a genuine advance to a `requires_note` status
        // demands one; the note itself is created via the SAME collaborative-
        // notes mechanism the dialog uses (spec 0052), inside THIS
        // transaction, so a note failure rolls back the status change too
        // (AC-010).
        $targetStatus = OpportunityWorkflowStatus::query()->findOrFail($newStatusId);

        if ($targetStatus->requires_note) {
            $this->createStatusChangeNote($opportunity, $actor, $note);
        }

        $old['opportunity_workflow_status_id'] = $currentStatusId;
        $opportunity->opportunity_workflow_status_id = $newStatusId;
        $changed['opportunity_workflow_status_id'] = $newStatusId;
    }

    /**
     * @throws ValidationException $note is missing/blank (AC-009)
     * @throws AuthorizationException the actor lacks `notes.create` (mirrors NoteController::store())
     */
    private function createStatusChangeNote(Opportunity $opportunity, User $actor, ?string $note): void
    {
        if ($note === null || trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => ['A note is required when moving to this working status.'],
            ]);
        }

        if (! $actor->can('notes.create')) {
            throw new AuthorizationException;
        }

        $this->noteService->create($actor, new CreateNoteData(
            entityType: self::NOTE_ENTITY_TYPE,
            entityId: $opportunity->getKey(),
            body: $note,
            parentId: null,
            mentionIds: [],
        ));
    }

    /**
     * `next_callback_at` (spec 0052 D-1/D-2): NOT in Opportunity::$fillable,
     * assigned directly here (never mass-assigned). $value is whatever the
     * request submitted — a date string or null — and the 'datetime' cast
     * normalizes it as soon as it is set, so both sides of the comparison
     * below read back through the SAME cast (D-4 invariant: the reminder
     * marker is zeroed if and only if the resolved instant actually changes).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyNextCallbackAt(Opportunity $opportunity, mixed $value, array &$changed, array &$old): void
    {
        $previous = $opportunity->next_callback_at;
        $opportunity->next_callback_at = $value;
        $current = $opportunity->next_callback_at;

        if ($this->callbackInstantKey($previous) === $this->callbackInstantKey($current)) {
            return;
        }

        $old['next_callback_at'] = $this->callbackInstantKey($previous);
        $changed['next_callback_at'] = $this->callbackInstantKey($current);
        $opportunity->next_callback_reminded_at = null;
    }

    /**
     * A comparable/loggable representation of a `next_callback_at` instant —
     * null-safe, so two nulls compare equal without a Carbon method call.
     */
    private function callbackInstantKey(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i');
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException
     */
    private function applyAttributeValues(Opportunity $opportunity, array $submitted, array &$changed, array &$old): void
    {
        $applicable = $this->attributesResolver->resolve($opportunity);
        $validated = $this->attributeValueValidator->validate($applicable, $submitted);
        $normalized = $this->attributeValueNormalizer->normalize($applicable, $validated);

        $current = $opportunity->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in Opportunity::$fillable (D-4 mass-
        // assignment guard): forceFill is the deliberate, single write path.
        $opportunity->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function logOperationalChange(Opportunity $opportunity, User $actor, array $changed, array $old): void
    {
        if ($changed === []) {
            return;
        }

        activity($opportunity->getTable())
            ->performedOn($opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management work update');
    }

    /**
     * @return Collection<int, OpportunityWorkflowStatus>
     */
    private function resolveWorkflowStatuses(Opportunity $opportunity): Collection
    {
        return $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity));
    }
}

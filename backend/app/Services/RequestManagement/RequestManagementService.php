<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\OpportunityAttributeLayoutResolver;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\OpportunityProductInterestWriter;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\Opportunities\ProductCategoryCoherence;
use App\Services\Opportunities\RewardAssignmentWriter;
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
 * inline-edit engine (RequestManagementTableDefinition::updateCell()) — the
 * rule itself lives in RequestWorkflowStatusWriter, which only this method
 * calls, so the two write channels can never diverge on it.
 */
final class RequestManagementService
{
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
        // Spec 0079: the origin Sede of a transfer, same composed-label need
        // — eager-loaded so RequestManagementResource never lazy-loads it
        // (Model::preventLazyLoading() outside production).
        'transferredFromOperationalSite.addresses.city',
        'quotes.quoteStatus',
        'workflowStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'productsOfInterest.category',
        'managers',
    ];

    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly OpportunityAttributeLayoutResolver $attributeLayoutResolver,
        private readonly OpportunityWorkflowResolver $workflowResolver,
        private readonly OpportunityProductInterestWriter $productInterestWriter,
        private readonly RequestAttributeValueWriter $attributeValueWriter,
        private readonly RequestClientProfileWriter $clientProfileWriter,
        private readonly RequestOperatorWriter $operatorWriter,
        private readonly ProductCategoryCoherence $coherence,
        private readonly RequestProductLineWriter $productLineWriter,
        private readonly RequestWorkflowStatusWriter $workflowStatusWriter,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly AssignmentNotifier $assignmentNotifier,
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
     * @param  array{opportunity_workflow_status_id?: int|null, note?: string|null, attribute_values?: array<string, mixed>, next_callback_at?: string|null, products_of_interest?: array<int, int>, product_lines?: array<int, array{business_function_id: int, product_category_id: int}>, source_id?: int|null, reporter_id?: int|null, operator_id?: int|null, rewards?: array<int, array{reward_type_id: int}>, client_identity?: CreatePersonalData, client_contacts?: array<int, ContactInput>, client_address?: AddressInput, client_first_name?: string|null, client_last_name?: string|null, client_tax_code?: string|null, client_phone?: string|null}  $data
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

            // Step 1: dynamic field values — validate against the applicable
            // set as it is BEFORE Step 2 replaces the product lines, i.e. the
            // set the panel rendered its fields from (AttributeValueValidator,
            // keyed attribute_values.<code> on failure), then merge into the
            // existing map (sparse: unset codes keep their persisted value).
            if (array_key_exists('attribute_values', $data)) {
                $this->attributeValueWriter->apply($opportunity, (array) $data['attribute_values'], $changed, $old);
            }

            // Step 2: funzione aziendale + categoria prodotto (user directive
            // 2026-07-31) — applied BEFORE the working-state step, like the
            // attribution above and for the same reason: the product lines are
            // one of the criteria OpportunityWorkflowResolver resolves a
            // workflow from (spec 0047), so a PATCH that changes them AND the
            // status in one shot must validate the status against the NEW set
            // (the ordering ValidatesWorkflowStatus already applies
            // request-side).
            $productLinesChanged = array_key_exists('product_lines', $data)
                && $this->productLineWriter->apply($opportunity, (array) $data['product_lines'], $changed, $old);

            // Step 2-bis: the coherence rule (user directive 2026-07-31) —
            // every product of interest THIS write leaves persisted must
            // belong to a category the request carries. Run once the lines
            // are final and BEFORE Step 7 writes the products, so the
            // coverage step shared with the opportunities module (which would
            // otherwise silently add the missing line) finds nothing to add.
            $this->assertProductCategoryCoherence($opportunity, $data);

            // Step 3: working-state advance — set-membership (AC-011) and the
            // mandatory-note rule (spec 0054, D-5) both enforced by the
            // dedicated writer, the one choke point both write channels reach.
            if (array_key_exists('opportunity_workflow_status_id', $data) && $data['opportunity_workflow_status_id'] !== null) {
                $this->workflowStatusWriter->apply($opportunity, (int) $data['opportunity_workflow_status_id'], $actor, $data['note'] ?? null, $changed, $old);
            }

            // Step 4: next planned callback (spec 0052 D-1/D-4) — sparse:
            // key absent leaves the persisted value untouched, `null` clears
            // it. A real value change also zeroes the reminder marker so a
            // rescheduled date is not skipped by the future reminder job.
            if (array_key_exists('next_callback_at', $data)) {
                $this->applyNextCallbackAt($opportunity, $data['next_callback_at'], $changed, $old);
            }

            $opportunity->save();

            // Step 5: the GA2 "Operatore" — a pivot row, so it is written
            // after the model save like every other reference collection.
            if (array_key_exists('operator_id', $data)) {
                $this->applyOperator($opportunity, $data['operator_id'], $actor, $changed, $old);
            }

            // Step 6: a changed fonte — or a changed product line, same
            // criterion family — can move the opportunity onto a different
            // workflow (spec 0047). With no explicit status submitted the
            // resolver re-derives it exactly as OpportunityService::update()
            // does — targetStatus() keeps the current row when it still
            // belongs to the new set, so this is a no-op whenever the two
            // workflows share the status.
            if (($sourceChanged || $productLinesChanged) && ($data['opportunity_workflow_status_id'] ?? null) === null) {
                $this->workflowResolver->resolveAndAssign($opportunity);
            }

            // Step 7: "prodotti di interesse" (user directive 2026-07-22) —
            // a to-many reference, written after the model save like every
            // other collection. The writer re-checks the coherence Step 2-bis
            // has already asserted; the redundancy is what keeps every OTHER
            // channel (including this module's inline editor, which reaches
            // the writer directly) covered by one rule.
            if (array_key_exists('products_of_interest', $data)) {
                $this->applyProductsOfInterest($opportunity, (array) $data['products_of_interest'], $changed, $old);
            }

            // Step 8: reward assignments (spec 0059, AC-023) — identical
            // semantics to the opportunities payload (D-3): the retarget half
            // runs whenever `reporter_id` genuinely changed, INDEPENDENT of
            // whether `rewards` itself was submitted; the sync half only when
            // `rewards` was submitted. Both operate on the writer shared with
            // OpportunityService, so the two channels can never diverge.
            $this->applyRewards($opportunity, $previousReporterId, $data, $changed, $old);

            // Step 9: client anagraphic (spec 0049 amendment; spec 0055 D-7 for
            // the inline channel's four sparse single-field keys) — identity,
            // contacts and address land on the Registry's PersonalData card,
            // not on the opportunity, so they are written outside the model
            // save. The writer reports its single-field changes into
            // $changed/$old so the last step audits them (D-9).
            $this->clientProfileWriter->applyTo($opportunity, $data, $changed, $old);

            // Step 10: explicit activity entry (see class docblock).
            $this->logOperationalChange($opportunity, $actor, $changed, $old);

            return $this->loadWorkPanel($opportunity);
        });
    }

    /**
     * The coherence rule (user directive 2026-07-31), on the sets THIS write
     * leaves persisted: the submitted collection when the key travelled, the
     * stored one otherwise.
     *
     * Gated on either key being submitted, like every other rule of this
     * sparse endpoint: a legacy record whose products predate the rule must
     * stay savable for any unrelated edit — the actor who did not touch
     * either collection is not the one to fix it.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertProductCategoryCoherence(Opportunity $opportunity, array $data): void
    {
        $productsSubmitted = array_key_exists('products_of_interest', $data);

        if (! $productsSubmitted && ! array_key_exists('product_lines', $data)) {
            return;
        }

        $productIds = $productsSubmitted
            ? array_map(intval(...), (array) $data['products_of_interest'])
            : $opportunity->productsOfInterest()->pluck('products.id')->map(intval(...))->all();

        $this->coherence->assert(
            $productIds,
            $opportunity->productLines()->pluck('product_category_id')->map(intval(...))->all(),
            // The 422 lands on the key the actor actually edited, so the
            // panel highlights the field they were working in.
            $productsSubmitted ? 'products_of_interest' : 'product_lines',
            ProductCategoryCoherence::REQUEST_MESSAGE,
        );
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
    private function applyOperator(Opportunity $opportunity, mixed $value, User $actor, array &$changed, array &$old): void
    {
        $this->operatorWriter->apply($opportunity, $value === null ? null : (int) $value, $changed, $old);

        // spec 0081: only a GENUINE transition is an assignment — apply()
        // leaves `operator_id` unset in $changed when the slot already held
        // this user, which is exactly the "renamed nothing" case that must
        // notify nobody.
        $newOperatorId = $changed['operator_id'] ?? null;

        if ($newOperatorId === null) {
            return;
        }

        $this->assignmentNotifier->notify(
            $opportunity,
            $actor,
            null,
            [$newOperatorId => Opportunity::OPERATOR_MANAGER_POSITION],
        );
    }

    /**
     * "Prodotti di interesse" (user directive 2026-07-22): an authoritative
     * replace of the whole collection. Like every other operative field here
     * it is NOT mass-assignable (it is a relation), so the change is logged
     * explicitly.
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

        $this->productInterestWriter->sync($opportunity, $next);

        $old['products_of_interest'] = $current;
        $changed['products_of_interest'] = $next;
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

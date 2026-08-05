<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Opportunities\UpdateOpportunityData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\OpportunityProductInterestWriter;
use App\Services\Opportunities\OpportunityProductLineWriter;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Services\RequestManagement\RequestAttributeValueWriter;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `opportunities` resource (spec 0040): create/
 * update/delete plus the BR-1 lead-derivation on create. `managerSlots`
 * sync mirrors RegistryService::syncPivots/managerSyncMap verbatim (the
 * pivot shape is identical, `opportunity_user` mirroring `registry_user`).
 *
 * Spec 0082: the Opportunity carries NO status FK any more — its status is
 * computed from its quotes by App\Services\Opportunities\OpportunityStatusResolver,
 * so nothing is defaulted, validated or synced here for it.
 */
class OpportunityService
{
    /**
     * Relations eager-loaded for the detail read tree (OpportunityResource),
     * so a single query never N+1s — including the linked lead's own chain
     * (LeadOpportunityDefaultsResolver's requirements, for `locked_fields`
     * and the `lead.label` summary).
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'registry',
        'referent',
        'commercial',
        'reporter',
        'supervisor',
        'source',
        // Spec 0056: the site has no own name, its label is composed
        // server-side from its primary address' `line1`+city.
        'operationalSite.addresses.city',
        'productLines.businessFunction',
        'productLines.productCategory',
        'productsOfInterest.category',
        'rewards.rewardType',
        'managers',
        'lead.registry',
        'lead.operationalSite.addresses.city',
        'lead.source',
        'lead.campaign.businessFunction',
        'lead.campaign.productCategory',
        'lead.campaign.project.businessFunction',
        'lead.campaign.project.productCategory',
        // spec 0047 (AC-003): Regione + resolved working-state row.
        'state',
        'workflowStatus',
    ];

    public function __construct(
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly OpportunityWorkflowResolver $workflowResolver,
        private readonly OpportunityProductInterestWriter $productInterestWriter,
        private readonly OpportunityProductLineWriter $productLineWriter,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly AssignmentNotifier $assignmentNotifier,
        // User directive 2026-08-05: the same writer the request-management
        // channels use — it is already typed on Opportunity (the dynamic
        // values ARE an opportunity-level concept, spec 0049 D-4), so the two
        // forms can never validate or merge the map differently.
        private readonly RequestAttributeValueWriter $attributeValueWriter,
    ) {}

    public function loadDetail(Opportunity $opportunity): Opportunity
    {
        // Spec 0067, AC-020/021: quotes_count feeds the panel's initial
        // counter — loadCount(), never load('quotes'), so the read stays a
        // single aggregate query with no quote rows materialized. Spec 0082's
        // computed status keeps that property: OpportunityStatusResolver runs
        // its own 3-column aggregate query on this un-loaded relation.
        return $opportunity->load(self::DETAIL_RELATIONS)->loadCount('quotes');
    }

    /**
     * Minimal, searchable, paginated opportunity list for the for-select
     * standard (ADR 0011, MT-10/spec 0059 — feeds the `rewarded-referents`
     * "opportunity" advanced filter). Searches on `name` directly: unlike
     * Lead, an Opportunity owns its own descriptive column (spec 0057, D-5:
     * `OPP_{id}`), so no registry subquery is needed.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Opportunity> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedForSelectIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Base for-select query: opportunities with the three commercial roles
     * AND the sede operativa eager-loaded, so OpportunityForSelectResource's
     * `meta` (the Quote snapshot prefill, spec 0065 D-3 + directive
     * 2026-07-30) never N+1s.
     *
     * @return Builder<Opportunity>
     */
    private function forSelectBaseQuery(): Builder
    {
        return Opportunity::query()
            ->select(['id', 'name', 'commercial_id', 'reporter_id', 'supervisor_id', 'operational_site_id'])
            ->with([
                'commercial:id,name', 'reporter:id,name', 'supervisor:id,name',
                // The sede operativa's label is composed from its primary
                // address + city (it has no name column of its own).
                'operationalSite.addresses.city',
            ]);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name/meta projection applies. Total is unaffected.
     *
     * @param  Collection<int, Opportunity>  $page
     * @return Collection<int, Opportunity>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Opportunity> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Create a new opportunity. When `lead_id` is submitted, the 2 BR-1-
     * derivable attributes are overwritten with LeadOpportunityDefaultsResolver's
     * values (StoreOpportunityRequest already rejected a conflicting
     * submission as `prohibited`, so this only ever fills in fields the
     * client left absent). `name` (spec 0057, D-5) is derived as `OPP_{id}`
     * right after the insert — never a client input.
     */
    public function create(CreateOpportunityData $data, ?User $actor = null): Opportunity
    {
        $opportunity = DB::transaction(function () use ($data, $actor): Opportunity {
            $attributes = $data->attributes();

            if ($data->leadId !== null) {
                $attributes = $this->applyLeadDefaults($attributes, $data->leadId);
            }

            // `opportunities.name` is NOT NULL but the authoritative value
            // (spec 0057, D-5: `OPP_{id}`) depends on the row's own id — seed
            // only a non-null placeholder here to satisfy the constraint at
            // INSERT, mirroring RegistryService::create's own placeholder.
            $attributes['name'] = '';

            $opportunity = Opportunity::create($attributes);

            $opportunity->forceFill(['name' => 'OPP_'.$opportunity->id])->save();

            $attachedManagers = [];

            if ($data->hasManagerSlots()) {
                $syncMap = $this->managerSyncMap($data->managerSlots);
                $attachedManagers = ManagerPositions::attachedPositions($syncMap, $opportunity->managers()->sync($syncMap));
            }

            if ($data->hasProductLines()) {
                $this->productLineWriter->sync($opportunity, $data->productLines);
            }

            // "Prodotti di interesse" (user directive 2026-07-22): synced
            // AFTER the product lines, so a product from a category the
            // submission did not cover adds its own row on top of them
            // (OpportunityProductInterestWriter owns that rule) and the
            // workflow resolution below already sees the final set.
            if ($data->hasProductsOfInterest()) {
                $this->productInterestWriter->sync($opportunity, $data->productsOfInterest);
            }

            // spec 0059: `reporter_id` is part of the initial insert (not a
            // "change" on create), so a sync here already targets the right
            // beneficiary — no retarget() step is needed, unlike update().
            if ($data->hasRewards()) {
                $this->rewardAssignmentWriter->sync($opportunity, $data->rewards);
            }

            // "Informazioni aggiuntive" (user directive 2026-08-05): written
            // AFTER the product lines, so the applicable set validated
            // against is the one those lines produce — exactly the set the
            // create form rendered its fields from. Discarded audit buffers:
            // the row's own `created` entry is the trail on create (same
            // reasoning as RequestCreationService::applyOperativeFields).
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($opportunity, $data->attributeValues, $changed, $old);
                $opportunity->save();
            }

            // spec 0047 (AC-015/017): an explicit, already-validated override
            // wins; otherwise the resolver derives the 'open' row of the
            // resolved set — product lines are already synced above, so
            // business_function_id/product_category_id criteria see their
            // final values.
            $this->resolveWorkflowStatus($opportunity, $data->workflowStatusId);

            // spec 0081: dispatched last, when `name` is already the derived
            // `OPP_{id}` and the manager slots are final.
            $this->assignmentNotifier->notify(
                $opportunity,
                $actor,
                $opportunity->supervisor_id,
                $attachedManagers,
            );

            return $opportunity;
        });

        return $this->loadDetail($opportunity);
    }

    /**
     * Update an existing opportunity. Only keys present in $data are
     * touched (partial PATCH); a BR-2-locked field, if submitted, has
     * already been validated (UpdateOpportunityRequest) to match its
     * current derived value, so no extra enforcement runs here.
     */
    public function update(Opportunity $opportunity, UpdateOpportunityData $data, ?User $actor = null): Opportunity
    {
        DB::transaction(function () use ($opportunity, $data, $actor): void {
            // Unconditional save: fire the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $opportunity->fill($data->submittedAttributes())->save();

            // spec 0081: same reason as the reporter_id check right below —
            // resolveWorkflowStatus() saves again and resets the diff.
            $newSupervisorId = $opportunity->wasChanged('supervisor_id') ? $opportunity->supervisor_id : null;

            // spec 0059, D-3/AC-022: a genuine `reporter_id` change retargets
            // EVERY existing reward row, independent of whether `rewards`
            // itself was submitted in this same request. Checked right after
            // THIS save() — resolveWorkflowStatus() below may save() again
            // and reset wasChanged()'s diff.
            if ($data->reporterIdSubmitted && $opportunity->wasChanged('reporter_id')) {
                $this->rewardAssignmentWriter->retarget($opportunity);
            }

            // "Informazioni aggiuntive" (user directive 2026-08-05):
            // validated against the applicable set as it is BEFORE the
            // product lines below replace it — i.e. the set the form rendered
            // its fields from — then merged sparsely (a code the map leaves
            // out keeps its persisted value). Same ordering the work panel
            // applies (RequestManagementService::updateWork, Step 1).
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($opportunity, $data->attributeValues, $changed, $old);
                $opportunity->save();
            }

            $attachedManagers = [];

            if ($data->hasManagerSlots()) {
                $syncMap = $this->managerSyncMap($data->managerSlots);
                $attachedManagers = ManagerPositions::attachedPositions($syncMap, $opportunity->managers()->sync($syncMap));
            }

            if ($data->hasProductLines()) {
                $this->productLineWriter->sync($opportunity, $data->productLines);
            }

            // See create(): same ordering, same writer, same rule.
            if ($data->hasProductsOfInterest()) {
                $this->productInterestWriter->sync($opportunity, $data->productsOfInterest);
            }

            if ($data->hasRewards()) {
                $this->rewardAssignmentWriter->sync($opportunity, $data->rewards);
            }

            // spec 0047 (AC-016/017): re-resolve after any change to the
            // resolving criteria (source_id/state_id/product lines) unless
            // the client explicitly (and already-validated) chose a status.
            $this->resolveWorkflowStatus(
                $opportunity,
                $data->workflowStatusIdSubmitted ? $data->workflowStatusId : null,
            );

            $this->assignmentNotifier->notify(
                $opportunity,
                $actor,
                $newSupervisorId,
                $attachedManagers,
            );
        });

        return $this->loadDetail($opportunity);
    }

    /**
     * Delete the opportunity. The linked lead (if any) is left untouched
     * (D-5); the `opportunity_user` pivot rows cascade away via their own
     * cascadeOnDelete foreign keys (BR-3 explicitly excludes this pivot).
     *
     * Restrictive (spec 0065/0067, D-27/AC-022): an opportunity referenced by
     * at least one quote cannot be removed — `quotes.opportunity_id` is
     * already `restrictOnDelete` at the schema level, but an explicit guard
     * here, mirroring RegistryService::delete()'s own precedent, answers with
     * the same 409 envelope instead of letting the FK violation bubble up as
     * an unhandled QueryException (500).
     */
    public function delete(Opportunity $opportunity): void
    {
        if ($opportunity->quotes()->exists()) {
            abort(409, 'This opportunity has quotes and cannot be deleted.');
        }

        $opportunity->delete();
    }

    /**
     * The single write-side entry point for `opportunity_workflow_status_id`
     * (spec 0047): an explicit, non-null $submittedStatusId (already
     * validated by ValidatesWorkflowStatus to belong to the resolved set) is
     * written verbatim; otherwise OpportunityWorkflowResolver derives and
     * persists it — the SAME resolver Lane A's delete-reassign flow calls,
     * never duplicated here.
     */
    private function resolveWorkflowStatus(Opportunity $opportunity, ?int $submittedStatusId): void
    {
        if ($submittedStatusId !== null) {
            $opportunity->opportunity_workflow_status_id = $submittedStatusId;
            $opportunity->save();

            return;
        }

        $this->workflowResolver->resolveAndAssign($opportunity);
    }

    /**
     * Overwrite $attributes' 2 BR-1-derivable keys with the linked lead's
     * current defaults, for every field whose derivation is non-null.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyLeadDefaults(array $attributes, int $leadId): array
    {
        $lead = Lead::findOrFail($leadId);
        $defaults = $this->defaultsResolver->resolve($lead);

        foreach ($defaults->lockedFields as $field) {
            $attributes[$field] = $defaults->values[$field];
        }

        return $attributes;
    }

    /**
     * Turn the ordered, gap-aware manager slots into the pivot sync map
     * `[userId => ['position' => n]]` (mirrors RegistryService::managerSyncMap
     * verbatim — identical pivot shape).
     *
     * @param  array<int, int|null>  $slots
     * @return array<int, array{position: int}>
     */
    private function managerSyncMap(array $slots): array
    {
        $map = [];

        foreach (array_values($slots) as $index => $userId) {
            if ($userId !== null) {
                $map[$userId] = ['position' => $index + 1];
            }
        }

        return $map;
    }
}

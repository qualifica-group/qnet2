<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\OpportunityWorkflows\CreateOpportunityWorkflowData;
use App\DataObjects\OpportunityWorkflows\UpdateOpportunityWorkflowData;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\OpportunityWorkflows\WorkflowStatusWriter;
use App\Services\Rewards\RewardLifecycleManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `opportunity-workflows` configurator resource (spec
 * 0047, Lane A): create/update/delete of a workflow plus its criteria/
 * statuses child collections, and the global default status set.
 *
 * create()/update() have NO dependency on OpportunityWorkflowResolver
 * (they only ever write THIS workflow's own rows); delete() does, to
 * re-resolve every Opportunity left referencing one of the deleted
 * workflow's statuses (AC-018) — the SAME resolver
 * App\Services\OpportunityService uses, never duplicated here.
 */
class OpportunityWorkflowService
{
    public function __construct(
        private readonly WorkflowStatusWriter $statusWriter,
        private readonly OpportunityWorkflowResolver $resolver,
        private readonly RewardLifecycleManager $rewardLifecycleManager,
    ) {}

    public function loadDetail(OpportunityWorkflow $workflow): OpportunityWorkflow
    {
        return $workflow->load([
            'criteria',
            'statuses' => fn ($query) => $query->orderBy('sort_order'),
        ]);
    }

    /**
     * Creates the workflow, its criteria, and its status set — the 4 system
     * rows (AC-004) always, plus $data->statuses' custom rows — atomically.
     */
    public function create(CreateOpportunityWorkflowData $data): OpportunityWorkflow
    {
        $workflow = DB::transaction(function () use ($data): OpportunityWorkflow {
            // Step 1: the criteria combination must be globally unique
            // (AC-009) — checked here too (defense in depth beyond the
            // FormRequest) since the signature is computed and persisted by
            // THIS layer.
            $signature = CreateOpportunityWorkflowData::computeSignature($data->criteria);
            $this->assertSignatureUnique($signature, excludeWorkflowId: null);

            // `criteria_signature` is DELIBERATELY absent from #[Fillable]
            // (never mass-assignable from a request) — forceCreate() writes
            // it alongside the plain create() attributes.
            $workflow = OpportunityWorkflow::query()->forceCreate([...$data->attributes(), 'criteria_signature' => $signature]);

            $this->syncCriteria($workflow, $data->criteria);
            $this->statusWriter->createWithCustoms($workflow->id, $data->statuses, $data->openStatus, $data->validatedStatus, $data->closedWonStatus, $data->closedLostStatus);

            return $workflow;
        });

        return $this->loadDetail($workflow);
    }

    /**
     * Partial update: `name`/`is_active` when submitted; `criteria` re-
     * synced + signature recomputed/revalidated when submitted; `statuses`
     * synced (custom rows full-replace, system rows name/color-only) when
     * submitted — every sub-part independently optional (PATCH).
     */
    public function update(OpportunityWorkflow $workflow, UpdateOpportunityWorkflowData $data): OpportunityWorkflow
    {
        DB::transaction(function () use ($workflow, $data): void {
            $workflow->fill($data->submittedAttributes());

            if ($data->hasCriteria()) {
                $signature = CreateOpportunityWorkflowData::computeSignature($data->criteria);
                $this->assertSignatureUnique($signature, excludeWorkflowId: $workflow->id);

                $workflow->criteria_signature = $signature;
            }

            // Unconditional save: fires the model's saved event even when no
            // native attribute changed (e.g. only `statuses` submitted).
            $workflow->save();

            if ($data->hasCriteria()) {
                $this->syncCriteria($workflow, $data->criteria);
            }

            if ($data->hasStatuses()) {
                $this->statusWriter->syncCustoms($workflow->id, $data->statuses);
            }
        });

        return $this->loadDetail($workflow->fresh());
    }

    /**
     * Deletes the workflow (cascading its criteria/statuses at the DB
     * layer), then RE-RESOLVES every Opportunity that referenced one of its
     * statuses (AC-018): the FK is `nullOnDelete`, so those rows already sit
     * at `opportunity_workflow_status_id = null` by the time the resolver
     * runs — never left orphaned.
     *
     * Both `productLines` and `customFieldValueRow` are eager-loaded UP
     * FRONT (verifier flag, spec 0047 amendment 2026-07-27): resolver's own
     * `resolve()` step 1 only ever `loadMissing()`s them, which is a no-op
     * when already loaded and a query PER ROW of this batch otherwise —
     * `productLines` was already missing this before the amendment (a
     * pre-existing N+1 this fix also closes, same line, not left implicit).
     */
    public function delete(OpportunityWorkflow $workflow): void
    {
        DB::transaction(function () use ($workflow): void {
            $statusIds = $workflow->statuses()->pluck('id');

            $impactedOpportunityIds = Opportunity::query()
                ->whereIn('opportunity_workflow_status_id', $statusIds)
                ->pluck('id');

            $workflow->delete();

            Opportunity::query()
                ->whereIn('id', $impactedOpportunityIds)
                ->with(['productLines', 'customFieldValueRow'])
                ->get()
                ->each(function (Opportunity $opportunity): void {
                    $this->resolver->resolveAndAssign($opportunity);

                    // Spec 0073: the re-resolution can move a request out of
                    // (or into) a closed-negative status, so its buoni follow.
                    $this->rewardLifecycleManager->reconcile($opportunity);
                });
        });
    }

    /**
     * Authoritative sync of the GLOBAL default status set (opportunity_
     * workflow_id null, AC-005/AC-010): same custom-rows-sync/system-rows-
     * guard rules as a workflow's own set, via the same scoped writer.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statuses
     * @return EloquentCollection<int, OpportunityWorkflowStatus>
     */
    public function syncDefaultStatuses(array $statuses): EloquentCollection
    {
        $this->statusWriter->syncCustoms(null, $statuses);

        return $this->defaultStatuses();
    }

    /**
     * @return EloquentCollection<int, OpportunityWorkflowStatus>
     */
    public function defaultStatuses(): EloquentCollection
    {
        return OpportunityWorkflowStatus::query()
            ->whereNull('opportunity_workflow_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Full-replace sync of $workflow's criteria (mirrors
     * OpportunityService::syncProductLines' delete-all + insert shape),
     * idempotent within the surrounding transaction.
     *
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     */
    private function syncCriteria(OpportunityWorkflow $workflow, array $criteria): void
    {
        $workflow->criteria()->delete();

        foreach ($criteria as $criterion) {
            $workflow->criteria()->create($criterion);
        }
    }

    private function assertSignatureUnique(string $signature, ?int $excludeWorkflowId): void
    {
        $query = OpportunityWorkflow::query()->where('criteria_signature', $signature);

        if ($excludeWorkflowId !== null) {
            $query->where('id', '!=', $excludeWorkflowId);
        }

        if ($query->exists()) {
            abort(422, 'A workflow with this exact combination of criteria already exists.');
        }
    }
}

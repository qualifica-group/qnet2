<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\DataObjects\QuoteWorkflows\UpdateQuoteWorkflowData;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\QuoteWorkflows\QuoteWorkflowStatusUsageGuard;
use App\Services\QuoteWorkflows\WorkflowStatusWriter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `quote-workflows` configurator resource (spec 0047,
 * moved onto the Offerta by spec 0083 D-6): create/update/delete of a
 * workflow plus its criteria/statuses child collections, and the global
 * default status set.
 */
class QuoteWorkflowService
{
    public function __construct(
        private readonly WorkflowStatusWriter $statusWriter,
        private readonly QuoteWorkflowStatusUsageGuard $statusUsageGuard,
    ) {}

    public function loadDetail(QuoteWorkflow $workflow): QuoteWorkflow
    {
        return $workflow->load([
            'criteria',
            'statuses' => fn ($query) => $query->orderBy('sort_order'),
        ]);
    }

    /**
     * Creates the workflow, its criteria, and its status set — the mandatory
     * system rows (AC-004) plus $data->statuses' custom rows — atomically.
     */
    public function create(CreateQuoteWorkflowData $data): QuoteWorkflow
    {
        $workflow = DB::transaction(function () use ($data): QuoteWorkflow {
            // Step 1: the criteria combination must be globally unique
            // (AC-009) — checked here too (defense in depth beyond the
            // FormRequest) since the signature is computed and persisted by
            // THIS layer.
            $signature = CreateQuoteWorkflowData::computeSignature($data->criteria);
            $this->assertSignatureUnique($signature, excludeWorkflowId: null);

            // `criteria_signature` is DELIBERATELY absent from #[Fillable]
            // (never mass-assignable from a request) — forceCreate() writes
            // it alongside the plain create() attributes.
            $workflow = QuoteWorkflow::query()->forceCreate([...$data->attributes(), 'criteria_signature' => $signature]);

            $this->syncCriteria($workflow, $data->criteria);
            $this->statusWriter->createWithCustoms($workflow->id, $data->statuses, $data->openStatus, $data->closedWonStatus, $data->closedLostStatus);

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
    public function update(QuoteWorkflow $workflow, UpdateQuoteWorkflowData $data): QuoteWorkflow
    {
        DB::transaction(function () use ($workflow, $data): void {
            $workflow->fill($data->submittedAttributes());

            if ($data->hasCriteria()) {
                $signature = CreateQuoteWorkflowData::computeSignature($data->criteria);
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
     * Deletes the workflow, cascading its criteria/statuses at the DB layer.
     * Refused while one of its statuses is in use (spec 0377).
     */
    public function delete(QuoteWorkflow $workflow): void
    {
        $this->statusUsageGuard->assertUnused($workflow->statuses()->get());

        $workflow->delete();
    }

    /**
     * Authoritative sync of the GLOBAL default status set (quote_workflow_id
     * null, AC-005/AC-010): same custom-rows-sync/system-rows-guard rules as
     * a workflow's own set, via the same scoped writer.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statuses
     * @return EloquentCollection<int, QuoteWorkflowStatus>
     */
    public function syncDefaultStatuses(array $statuses): EloquentCollection
    {
        $this->statusWriter->syncCustoms(null, $statuses);

        return $this->defaultStatuses();
    }

    /**
     * @return EloquentCollection<int, QuoteWorkflowStatus>
     */
    public function defaultStatuses(): EloquentCollection
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
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
    private function syncCriteria(QuoteWorkflow $workflow, array $criteria): void
    {
        $workflow->criteria()->delete();

        foreach ($criteria as $criterion) {
            $workflow->criteria()->create($criterion);
        }
    }

    private function assertSignatureUnique(string $signature, ?int $excludeWorkflowId): void
    {
        $query = QuoteWorkflow::query()->where('criteria_signature', $signature);

        if ($excludeWorkflowId !== null) {
            $query->where('id', '!=', $excludeWorkflowId);
        }

        if ($query->exists()) {
            abort(422, 'A workflow with this exact combination of criteria already exists.');
        }
    }
}

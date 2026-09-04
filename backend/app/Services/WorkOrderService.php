<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\DataObjects\WorkOrders\UpdateWorkOrderData;
use App\Models\WorkOrder;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\WorkOrders\WorkOrderAttributeValueWriter;
use App\Services\WorkOrders\WorkOrderLineWriter;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use App\Support\ManagerPositions;
use App\Support\PositionalPivotSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `work-orders` resource (spec 0093): create/update
 * with the server-generated `COM-0001` code (D-1, same
 * GeneratesSequentialCode pattern as QuoteService), the REVENUE-only line
 * membership invariant (D-7, delegated to WorkOrderLineWriter) and the D-4
 * force-close/reason pairing. The controller stays thin; this Service is the
 * single authority.
 *
 * Spec 0098 (D-6): the dynamic "Informazioni aggiuntive"
 * (WorkOrderAttributeValueWriter) is written AFTER
 * WorkOrderLineWriter::writeSubmitted(), inside the SAME transaction as
 * every other step — the applicable set validated against is exactly the one
 * the form rendered its fields from, and a 422 there rolls back the whole
 * write (AC-014/AC-015).
 */
class WorkOrderService
{
    use GeneratesSequentialCode;

    private const string CODE_PREFIX = 'COM';

    private const string CODE_TABLE = 'work_orders';

    private const string CODE_COLUMN = 'code';

    /**
     * Relations eager-loaded for the detail read tree (WorkOrderResource), so
     * a single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'quote',
        // Spec 0098, AC-016: `.category` deepened so WorkOrderResource's
        // attribute trio (`applicable_attributes`/`attribute_layout`) never
        // N+1s on top of the pre-existing `quote_lines` projection.
        'quoteLines.product.category',
        'supervisors',
        'participants',
    ];

    public function __construct(
        private readonly WorkOrderLineWriter $lineWriter,
        private readonly WorkOrderAttributeValueWriter $attributeValueWriter,
    ) {}

    public function loadDetail(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->load(self::DETAIL_RELATIONS);
    }

    /**
     * The next sequential code (COM-0001...) as a non-binding suggestion for
     * the create form's auto-fill (D-1). Lock-free: the binding value is
     * still resolved atomically in create().
     */
    public function previewNextCode(): string
    {
        return $this->peekNextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
    }

    /**
     * Minimal, searchable, paginated commessa list for the for-select
     * standard (ADR 0011), mirroring OpportunityService::forSelect. Added by
     * spec 0101 (T-04b): the Task form's "Commessa" picker needs it, and
     * spec 0093 never shipped one.
     *
     * The rows are narrowed by WorkOrderVisibilityScope exactly as
     * WorkOrdersTableDefinition::baseQuery() narrows the grid (spec 0096,
     * user directive 2026-09-02). Without it this endpoint would leak, with
     * code and title, the very commesse the Commesse table hides from the
     * actor — the disclosure a membership scope exists to close.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($query->hasSearch()) {
            $base->where(function (Builder $scoped) use ($query): void {
                $scoped->where('code', 'like', '%'.$query->search.'%')
                    ->orWhere('title', 'like', '%'.$query->search.'%');
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, WorkOrder> $page */
        $page = $base->orderBy('code')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedForSelectIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * The SCOPED, minimally-projected for-select base query.
     * WorkOrderForSelectResource composes its label from `code` + `title`,
     * so nothing else is selected and no relation is loaded.
     *
     * @return Builder<WorkOrder>
     */
    private function forSelectBaseQuery(): Builder
    {
        return WorkOrderVisibilityScope::scopeToActor(
            WorkOrder::query()->select(['work_orders.id', 'work_orders.code', 'work_orders.title']),
            Auth::user(),
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass the search filter —
     * but NOT the visibility scope, which is a security boundary and not a
     * filter, so an out-of-scope id stays absent even when asked for by id.
     * Total is unaffected.
     *
     * @param  Collection<int, WorkOrder>  $page
     * @return Collection<int, WorkOrder>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat(
            $this->forSelectBaseQuery()->whereKey($missingIds)->orderBy('code')->orderBy('id')->get()
        );
    }

    /**
     * Create a new work order. A manual `code` (D-1) is persisted as
     * submitted; otherwise one is generated inside the transaction with a
     * pessimistic lock, so two concurrent creates never collide. The line
     * membership invariant (D-7) is checked AFTER the insert but still
     * inside the transaction: a violation rolls back the whole write
     * (AC-022/AC-023).
     */
    public function create(CreateWorkOrderData $data): WorkOrder
    {
        $workOrder = DB::transaction(function () use ($data): WorkOrder {
            // Step 1: insert with the server-generated (or manual) code.
            $workOrder = new WorkOrder($data->attributes());
            $workOrder->code = $data->code ?? $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $this->enforceForceCloseInvariant($workOrder);
            $workOrder->save();

            // Step 2: validate + sync the REVENUE line membership (D-7).
            $this->lineWriter->writeSubmitted($workOrder, $data->quoteId, $data->quoteLineIds);

            // Step 2b (spec 0098, D-6): "Informazioni aggiuntive" — written
            // AFTER the quote lines, so the applicable set validated against
            // is the one THOSE lines' categories produce, exactly the set the
            // create form rendered its fields from.
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($workOrder, $data->attributeValues, $changed, $old);
                $workOrder->save();
            }

            // Step 3: the two user pivots (spec 0096, D-1/D-3), inside the
            // same transaction: a 422 from Step 2 rolls both back (AC-027).
            $workOrder->supervisors()->sync($data->supervisorIds);
            PositionalPivotSync::sync($workOrder->participants(), ManagerPositions::syncMap($data->participantSlots));

            return $workOrder;
        });

        return $this->loadDetail($workOrder);
    }

    /**
     * Update an existing work order. Only the submitted scalar keys are
     * touched (partial PATCH); `code`/`quote_id` never reach $data (rejected
     * upstream as immutable). `quote_line_ids` is full-replaced only when
     * its own key was submitted (AC-025/AC-026).
     */
    public function update(WorkOrder $workOrder, UpdateWorkOrderData $data): WorkOrder
    {
        DB::transaction(function () use ($workOrder, $data): void {
            $workOrder->fill($data->submittedAttributes());
            $this->enforceForceCloseInvariant($workOrder);
            $workOrder->save();

            $this->lineWriter->writeSubmitted($workOrder, $workOrder->quote_id, $data->quoteLineIds);

            // "Informazioni aggiuntive" (spec 0098, D-6): validated against
            // the applicable set as it is AFTER any submitted
            // `quote_line_ids` replace it above — i.e. the set the form
            // rendered its fields from — then merged sparsely (a code the
            // map leaves out keeps its persisted value).
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($workOrder, $data->attributeValues, $changed, $old);
                $workOrder->save();
            }

            // Full-replace only when the key was actually submitted
            // (AC-024): an untouched relation must not trigger a no-op sync.
            if ($data->hasSupervisorIds()) {
                $workOrder->supervisors()->sync($data->supervisorIds ?? []);
                $workOrder->unsetRelation('supervisors');
            }

            if ($data->hasParticipantSlots()) {
                PositionalPivotSync::sync($workOrder->participants(), ManagerPositions::syncMap($data->participantSlots ?? []));
                $workOrder->unsetRelation('participants');
            }
        });

        return $this->loadDetail($workOrder);
    }

    /**
     * Delete the work order. `quote_line_work_order`, `work_order_supervisor`
     * and `work_order_participant` pivot rows cascade away via their own FKs
     * (AC-003/AC-060); the linked Quote/QuoteLine/User rows are untouched. No guard (D-11): nothing references a work order yet.
     */
    public function delete(WorkOrder $workOrder): void
    {
        $workOrder->delete();
    }

    /**
     * D-4: whenever the model's FINAL `is_force_closed` is false, its
     * `force_close_reason` is zeroed to null in the SAME save — regardless of
     * which combination of the two fields a partial PATCH actually submitted
     * (AC-031). Applied uniformly in both create() and update(), right
     * before save(), so this is the single point where the pairing can never
     * drift.
     */
    private function enforceForceCloseInvariant(WorkOrder $workOrder): void
    {
        if (! $workOrder->is_force_closed) {
            $workOrder->force_close_reason = null;
        }
    }
}

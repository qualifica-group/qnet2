<?php

namespace App\Services;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `proforma-requests` resource (spec 0193). Turns the
 * lines a work order covers into the requests Accounting must act on, and owns
 * the duplicate guard (D-4).
 */
class ProformaRequestService
{
    /** Relations the ProformaRequestResource reads. */
    private const array RESOURCE_RELATIONS = [
        'workOrder:id,code,title,quote_id',
        'quote:id,code,company_id,opportunity_id',
        'quote.company:id,denomination',
        'quote.opportunity:id,registry_id',
        'quote.opportunity.registry:id,name',
        'supplier:id,name',
        'paymentMethod:id,name',
        'assignee:id,name',
        'assigner:id,name',
        'invoice:id,proforma_request_id,number,year',
    ];

    /**
     * Generate every request the work order lines call for, in one transaction.
     *
     * @return Collection<int, ProformaRequest>
     */
    public function createForWorkOrder(WorkOrder $workOrder, User $actor, string $note): Collection
    {
        return DB::transaction(function () use ($workOrder, $actor, $note): Collection {
            // Step 1: serialize concurrent submits on the work order row, then refuse a duplicate (D-4)
            WorkOrder::query()->whereKey($workOrder->getKey())->lockForUpdate()->first();
            $this->assertFirstGeneration($workOrder);

            // Step 2: group the covered lines into the requests to raise (D-1/D-2)
            $drafts = $this->buildDrafts($workOrder);

            if ($drafts === []) {
                abort(422, __('proforma_requests.no_billable_lines'));
            }

            // Step 3: persist them with the payment method of their own offer as a snapshot (D-9, spec 0267 D-6)
            $paymentMethods = Quote::query()
                ->whereIn('id', array_unique(array_column($drafts, 'quote_id')))
                ->pluck('payment_method_id', 'id');

            $created = collect($drafts)->map(fn (array $draft): ProformaRequest => ProformaRequest::create([
                'work_order_id' => $workOrder->getKey(),
                'quote_id' => $draft['quote_id'],
                'kind' => $draft['kind'],
                'supplier_id' => $draft['supplier_id'],
                'payment_method_id' => $paymentMethods[$draft['quote_id']],
                'status' => ProformaRequestStatus::Pending,
                'note' => $note,
                'assigned_to' => $actor->getKey(),
                'assigned_by' => $actor->getKey(),
            ]));

            // Step 4: hand back the fully loaded set for the Resource
            return $created->each(fn (ProformaRequest $request) => $request->load(self::RESOURCE_RELATIONS));
        });
    }

    /**
     * The modal read model: request state, latest request date and the
     * offer payment method (none on a multi-offer work order: each request
     * carries the one of its own offer).
     *
     * @return array<string, mixed>
     */
    public function summary(WorkOrder $workOrder): array
    {
        $statuses = $workOrder->proformaRequests()->pluck('status');
        $paymentMethod = $workOrder->isMultiQuote() ? null : $workOrder->loadMissing('quote.paymentMethod')->quote->paymentMethod;
        $lastRequest = $workOrder->proformaRequests()->latest('created_at')->first(['id', 'created_at']);

        return [
            'status' => $this->aggregateStatus($statuses),
            'last_requested_at' => $lastRequest?->created_at?->toISOString(),
            'payment_method' => $paymentMethod === null ? null : ['id' => $paymentMethod->id, 'name' => $paymentMethod->name],
            'work_order' => ['id' => $workOrder->id, 'code' => $workOrder->code],
        ];
    }

    public function detail(ProformaRequest $request): ProformaRequest
    {
        return $request->load(self::RESOURCE_RELATIONS);
    }

    /** Only the note is editable (D-7). */
    public function updateNote(ProformaRequest $request, string $note): ProformaRequest
    {
        $request->update(['note' => $note]);

        return $this->detail($request);
    }

    /** Refuses issued or invoiced requests: the work order -> request -> invoice chain must not break (spec 0243, D-4). */
    public function delete(ProformaRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            // Step 1: lock the request so a concurrent issue cannot slip in
            $locked = ProformaRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            // Step 2: refuse when issued or linked to an invoice
            if (! $locked->isDeletable()) {
                abort(409, __('proforma_requests.delete_invoiced'));
            }

            // Step 3: delete
            $locked->delete();
        });
    }

    /** A work order generates requests once only (spec 0243, D-2). */
    private function assertFirstGeneration(WorkOrder $workOrder): void
    {
        $statuses = $workOrder->proformaRequests()->pluck('status');

        if ($statuses->contains(ProformaRequestStatus::Pending)) {
            abort(409, __('proforma_requests.already_pending'));
        }

        if ($statuses->isNotEmpty()) {
            abort(409, __('proforma_requests.already_issued'));
        }
    }

    /**
     * Per offer: one consultancy request for all its consultancy lines, one
     * institution request per distinct supplier (null supplier = one shared
     * request). The product typology's proforma_kind decides the group (spec
     * 0247, D-6): lines whose typology has none are ignored (D-2). A request
     * never mixes offers (spec 0267, D-6).
     *
     * @return array<int, array{quote_id: int, kind: ProformaRequestKind, supplier_id: int|null}>
     */
    private function buildDrafts(WorkOrder $workOrder): array
    {
        $lines = $workOrder->quoteLines()
            ->with('product:id,supplier_id,product_typology_id', 'product.productTypology:id,proforma_kind')
            ->get();
        $drafts = [];

        foreach ($lines->groupBy('quote_id') as $quoteId => $quoteLines) {
            if ($this->linesOfKind($quoteLines, ProformaRequestKind::Consultancy)->isNotEmpty()) {
                $drafts[] = ['quote_id' => (int) $quoteId, 'kind' => ProformaRequestKind::Consultancy, 'supplier_id' => null];
            }

            $suppliers = $this->linesOfKind($quoteLines, ProformaRequestKind::Institution)
                ->map(fn (QuoteLine $line): ?int => $line->product->supplier_id)
                ->unique();

            foreach ($suppliers as $supplierId) {
                $drafts[] = ['quote_id' => (int) $quoteId, 'kind' => ProformaRequestKind::Institution, 'supplier_id' => $supplierId];
            }
        }

        return $drafts;
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     * @return Collection<int, QuoteLine>
     */
    private function linesOfKind(Collection $lines, ProformaRequestKind $kind): Collection
    {
        return $lines->filter(fn (QuoteLine $line): bool => $line->product?->productTypology?->proforma_kind === $kind);
    }

    /**
     * @param  Collection<int, mixed>  $statuses
     */
    private function aggregateStatus(Collection $statuses): string
    {
        return match (true) {
            $statuses->isEmpty() => 'none',
            $statuses->contains(ProformaRequestStatus::Pending) => ProformaRequestStatus::Pending->value,
            default => ProformaRequestStatus::Issued->value,
        };
    }
}

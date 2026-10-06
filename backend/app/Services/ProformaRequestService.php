<?php

namespace App\Services;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Models\ProformaRequest;
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
    /** Product typology codes are immutable (ProductTypologySeeder): never resolve by name. */
    private const string CONSULTANCY_TYPOLOGY = 'consultancy';

    private const string INSTITUTION_TYPOLOGY = 'institution';

    /** Relations the ProformaRequestResource reads. */
    private const array RESOURCE_RELATIONS = [
        'workOrder:id,code,title,quote_id',
        'workOrder.quote:id,company_id',
        'workOrder.quote.company:id,denomination',
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
            $this->assertNoPendingRequest($workOrder);

            // Step 2: group the covered lines into the requests to raise (D-1/D-2)
            $drafts = $this->buildDrafts($workOrder);

            if ($drafts === []) {
                abort(422, 'No billable lines on this work order.');
            }

            // Step 3: persist them with the offer payment method as a snapshot (D-9)
            $paymentMethodId = $workOrder->quote->payment_method_id;

            $created = collect($drafts)->map(fn (array $draft): ProformaRequest => ProformaRequest::create([
                'work_order_id' => $workOrder->getKey(),
                'kind' => $draft['kind'],
                'supplier_id' => $draft['supplier_id'],
                'payment_method_id' => $paymentMethodId,
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
     * offer payment method.
     *
     * @return array<string, mixed>
     */
    public function summary(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing('quote.paymentMethod');
        $statuses = $workOrder->proformaRequests()->pluck('status');
        $paymentMethod = $workOrder->quote->paymentMethod;
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

    public function delete(ProformaRequest $request): void
    {
        $request->delete();
    }

    private function assertNoPendingRequest(WorkOrder $workOrder): void
    {
        $hasPending = $workOrder->proformaRequests()
            ->where('status', ProformaRequestStatus::Pending)
            ->exists();

        if ($hasPending) {
            abort(409, 'This work order already has a pending proforma request.');
        }
    }

    /**
     * One consultancy request for all consultancy lines, one institution
     * request per distinct supplier (null supplier = one shared request).
     * Lines with no/other typology are ignored (D-2).
     *
     * @return array<int, array{kind: ProformaRequestKind, supplier_id: int|null}>
     */
    private function buildDrafts(WorkOrder $workOrder): array
    {
        $lines = $workOrder->quoteLines()
            ->with('product:id,supplier_id,product_typology_id', 'product.productTypology:id,code')
            ->get();
        $drafts = [];

        if ($this->linesOfTypology($lines, self::CONSULTANCY_TYPOLOGY)->isNotEmpty()) {
            $drafts[] = ['kind' => ProformaRequestKind::Consultancy, 'supplier_id' => null];
        }

        $suppliers = $this->linesOfTypology($lines, self::INSTITUTION_TYPOLOGY)
            ->map(fn (QuoteLine $line): ?int => $line->product->supplier_id)
            ->unique();

        foreach ($suppliers as $supplierId) {
            $drafts[] = ['kind' => ProformaRequestKind::Institution, 'supplier_id' => $supplierId];
        }

        return $drafts;
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     * @return Collection<int, QuoteLine>
     */
    private function linesOfTypology(Collection $lines, string $code): Collection
    {
        return $lines->filter(fn (QuoteLine $line): bool => $line->product?->productTypology?->code === $code);
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

<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\CostType;
use App\Models\WorkOrder;
use App\Services\Purchasing\CommittedOrderLineQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The commessa's actual costs by cost type and source (spec 0252, ADR 0018):
 * manual costs, committed purchase order lines (invoiceable orders, quantity
 * not yet billed by a payable document, spec 0254 D-9) and payable document
 * lines. Billed quantity leaves the committed: the document line replaces it. Three
 * aggregate queries plus one for the cost types, whatever the number of rows.
 * Amounts are net, summed as integer cents. The committed formula is shared
 * through CommittedOrderLineQuery (spec 0268, D-14).
 */
final class WorkOrderPassiveCostAggregator
{
    public function __construct(private readonly CommittedOrderLineQuery $committed) {}

    /**
     * Ordered by cost type sort order, the row without a type last.
     *
     * @return Collection<int, array{cost_type: array<string, mixed>|null, manual_cents: int, committed_cents: int, invoiced_cents: int}>
     */
    public function byCostType(WorkOrder $workOrder): Collection
    {
        // Step 1: the three sources, summed per cost type id (0 = no type)
        $sources = [
            'manual_cents' => $this->committed->centsBy(DB::table('work_order_costs')->where('work_order_id', $workOrder->id), 'net_amount', 'cost_type_id'),
            'committed_cents' => $this->committed->committedCentsBy($this->committed->forWorkOrders([$workOrder->id]), 'purchase_order_lines.cost_type_id'),
            'invoiced_cents' => $this->committed->centsBy(DB::table('purchase_invoice_lines')->where('work_order_id', $workOrder->id), 'net_amount', 'cost_type_id'),
        ];

        // Step 2: the types involved, in display order
        $ids = collect($sources)->flatMap(fn (array $cents): array => array_keys($cents))->unique()->values();
        $types = CostType::query()->whereIn('id', $ids->all())->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();

        // Step 3: one row per type, the untyped one last
        $rows = $types->map(fn (CostType $type): array => $this->row($type->id, [
            'id' => $type->id, 'name' => $type->name, 'color' => $type->color, 'nature' => $type->nature?->value,
        ], $sources));

        if ($ids->contains(0)) {
            $rows->push($this->row(0, null, $sources));
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>|null  $type
     * @param  array<string, array<int, int>>  $sources
     * @return array{cost_type: array<string, mixed>|null, manual_cents: int, committed_cents: int, invoiced_cents: int}
     */
    private function row(int $typeId, ?array $type, array $sources): array
    {
        return [
            'cost_type' => $type,
            'manual_cents' => $sources['manual_cents'][$typeId] ?? 0,
            'committed_cents' => $sources['committed_cents'][$typeId] ?? 0,
            'invoiced_cents' => $sources['invoiced_cents'][$typeId] ?? 0,
        ];
    }
}

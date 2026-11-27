<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\ProductUsage;
use App\Models\ProductSupplier;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the commessa's actual costs with the offer's budget lines (spec
 * 0436): one cost per COST line allocated to this commessa or generic (D-1),
 * mapped as D-2 and written through WorkOrderCostWriter, so amounts, unit of
 * measure and commission pricing follow the same rules as a manual save.
 */
final class WorkOrderCostQuoteSync
{
    public function __construct(
        private readonly WorkOrderBudgetLines $budgetLines,
        private readonly WorkOrderCostWriter $writer,
    ) {}

    public function handle(WorkOrder $workOrder): void
    {
        // Step 1: the offer's budget lines of this commessa
        $lines = $this->budgetLines->for($workOrder, ['product:id,name,usages,cost_type_id']);

        // Step 2: refuse an empty budget or products no longer usable as a cost (D-5)
        $this->assertSyncable($lines);

        // Step 3: map each line onto a cost row and replace the whole set (D-2, D-3)
        $defaultSuppliers = $this->defaultSuppliers($lines);
        $today = now()->toDateString();
        $rows = $lines->map(fn (QuoteLine $line): array => [
            'product_id' => $line->product_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'vat_rate_id' => $line->vat_rate_id,
            'quote_line_id' => $line->offer_line_id,
            'cost_type_id' => $line->product?->cost_type_id,
            'incurred_on' => $today,
            'supplier_id' => $line->supplier_id ?? ($defaultSuppliers[$line->product_id] ?? null),
            'document_reference' => null,
            'additional_description' => $line->additional_description,
        ])->all();

        $this->writer->handle($workOrder, $rows);
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     */
    private function assertSyncable(Collection $lines): void
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => [__('work_order_costs.sync_no_quote_costs')]]);
        }

        $unusable = $lines
            ->reject(static fn (QuoteLine $line): bool => $line->product?->isUsableAs(ProductUsage::Cost) ?? false)
            ->map(static fn (QuoteLine $line): string => (string) ($line->product?->name ?? $line->product_id))
            ->unique()
            ->implode(', ');

        if ($unusable !== '') {
            throw ValidationException::withMessages(['lines' => [__('work_order_costs.sync_products_not_usable', ['products' => $unusable])]]);
        }
    }

    /**
     * Default supplier per product, for the lines saved without one (D-2).
     *
     * @param  Collection<int, QuoteLine>  $lines
     * @return array<int, int>
     */
    private function defaultSuppliers(Collection $lines): array
    {
        $productIds = $lines->whereNull('supplier_id')->pluck('product_id')->unique()->all();

        return $productIds === [] ? [] : ProductSupplier::query()
            ->whereIn('product_id', $productIds)
            ->where('is_default', true)
            ->pluck('supplier_id', 'product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}

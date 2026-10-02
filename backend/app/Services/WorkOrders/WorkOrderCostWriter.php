<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use App\Services\Quotes\QuoteTotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the commessa's whole set of actual costs (spec 0190, D-3): rows
 * with `id` are updated, rows without are created, persisted rows absent from
 * the payload are deleted, and the array order becomes `sort_order`. Never
 * touches `quote_lines`.
 */
final class WorkOrderCostWriter
{
    public function __construct(private readonly QuoteTotalsCalculator $calculator) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  validated rows of SyncWorkOrderCostsRequest
     */
    public function handle(WorkOrder $workOrder, array $rows): void
    {
        DB::transaction(function () use ($workOrder, $rows): void {
            // Step 1: snapshot of what is persisted, keyed by id
            $existing = $workOrder->costs()->get()->keyBy('id');

            // Step 2: a new or product-changed row must use a COST product
            $this->assertProductsUsable($rows, $existing->all());

            // Step 3: batch-resolve the VAT rates and the products' units
            $rates = $this->vatRates($rows);
            $units = Product::query()
                ->whereIn('id', array_unique(array_column($rows, 'product_id')))
                ->pluck('unit_of_measure_id', 'id');

            // Step 4: upsert every submitted row, freezing the unit only when new/changed
            foreach ($rows as $index => $row) {
                $this->persist($workOrder, $existing->get($row['id'] ?? null), $row, $index, $rates, $units->all());
            }

            // Step 5: drop the costs the payload no longer carries
            $submittedIds = array_filter(array_column($rows, 'id'));
            $existing->except($submittedIds)->each->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, float>  $rates
     * @param  array<int, int|null>  $units
     */
    private function persist(WorkOrder $workOrder, ?WorkOrderCost $cost, array $row, int $index, array $rates, array $units): void
    {
        $isNew = $cost === null;
        $cost ??= new WorkOrderCost(['work_order_id' => $workOrder->id]);
        $productChanged = ! $isNew && $cost->product_id !== (int) $row['product_id'];
        $vatRateId = $row['vat_rate_id'] ?? null;
        $amounts = $this->calculator->lineAmounts(
            (float) $row['quantity'],
            (float) $row['unit_price'],
            $vatRateId === null ? null : ($rates[$vatRateId] ?? null),
        );

        $cost->fill([
            'product_id' => $row['product_id'],
            'quote_line_id' => $row['quote_line_id'] ?? null,
            'quantity' => $row['quantity'],
            'unit_of_measure_id' => $isNew || $productChanged ? ($units[$row['product_id']] ?? null) : $cost->unit_of_measure_id,
            'unit_price' => $row['unit_price'],
            'vat_rate_id' => $vatRateId,
            'net_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
            'incurred_on' => $row['incurred_on'],
            'supplier_id' => $row['supplier_id'] ?? null,
            'document_reference' => $row['document_reference'] ?? null,
            'additional_description' => array_key_exists('additional_description', $row) ? $row['additional_description'] : $cost->additional_description,
            'sort_order' => $index,
        ])->save();
    }

    /**
     * A persisted row resubmitting its own product is exempt, so a historic
     * cost stays saveable after its product's usages change (same rule as
     * QuoteLineWriter::assertProductsUsable()).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, WorkOrderCost>  $existing
     */
    private function assertProductsUsable(array $rows, array $existing): void
    {
        $toCheck = array_filter($rows, static fn (array $row): bool => ! isset($row['id'])
            || ($existing[$row['id']]->product_id ?? null) !== (int) $row['product_id']);

        if ($toCheck === []) {
            return;
        }

        $usable = Product::query()
            ->whereIn('id', array_unique(array_column($toCheck, 'product_id')))
            ->whereJsonContains('usages', ProductUsage::Cost->value)
            ->pluck('id')
            ->all();
        $errors = [];

        foreach ($toCheck as $index => $row) {
            if (! in_array((int) $row['product_id'], $usable, true)) {
                $errors["lines.{$index}.product_id"] = [__('quotes.product_not_usable.'.ProductUsage::Cost->value)];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, float>
     */
    private function vatRates(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'vat_rate_id'))));

        return $ids === [] ? [] : VatRate::query()
            ->whereIn('id', $ids)
            ->pluck('rate', 'id')
            ->map(static fn (mixed $rate): float => (float) $rate)
            ->all();
    }
}

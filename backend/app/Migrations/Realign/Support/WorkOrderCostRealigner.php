<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Support\LegacyWorkOrderCostWriter;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use App\Products\SupplierCommissionLookup;
use App\Services\WorkOrders\WorkOrderCostWriter;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Aligns the actual costs of a commessa (spec 0375 D-13). A cost entered by hand on
 * qnet-2 is recognised by its activity entry (the import runs without log) and is
 * never rewritten; the write goes through the same full-set writer as the import,
 * with every other cost passed back as it is.
 */
final class WorkOrderCostRealigner
{
    public function __construct(
        private readonly LegacyWorkOrderCostWriter $legacyWriter,
        private readonly WorkOrderCostWriter $writer,
        private readonly SupplierCommissionLookup $supplierCommissions,
        private readonly CostRowMatcher $matcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $legacyCosts
     */
    public function handle(WorkOrder $workOrder, array $legacyCosts, LegacyRealignNotes $notes): void
    {
        // Step 1: the legacy costs in qnet-2 terms, keeping the import's rules.
        $warnings = [];
        $lineIds = $workOrder->quoteLines()->whereNotNull('old_id')->pluck('quote_lines.id', 'old_id')->map(intval(...))->all();
        $rows = array_map(fn (array $cost): array => ['ref' => $cost['id'] ?? '?', ...$this->legacyWriter->row($workOrder, $cost, $lineIds, $warnings)], $legacyCosts);

        // Step 2: pair them with what is stored.
        $locals = $workOrder->costs()->get();
        $manualIds = $this->manualCostIds($locals);
        $plan = $this->matcher->plan(
            $rows,
            $locals,
            static fn (array $row): string => self::key($row['product_id'], $row['quote_line_id']),
            static fn (WorkOrderCost $cost): string => self::key($cost->product_id, $cost->quote_line_id),
            $this->sameValues(...),
            static fn (array $row): bool => true,
            static fn (WorkOrderCost $cost): bool => ! isset($manualIds[$cost->id]),
        );

        foreach ($plan['ambiguous'] as $row) {
            $notes->warn("Cost {$row['ref']}: more than one possible match on the work order, or entered by hand; not updated.");
        }

        // Step 3: write only when a cost really moves.
        if ($plan['update'] !== [] || $plan['create'] !== []) {
            $this->writer->handle($workOrder, $this->fullSet($locals, $plan['update'], $plan['create']));
            array_map($notes->warn(...), $warnings);
        }
    }

    private static function key(int|string|null $productId, int|string|null $lineId): string
    {
        return "{$productId}|{$lineId}";
    }

    /**
     * @param  Collection<int, WorkOrderCost>  $locals
     * @return array<int, true>
     */
    private function manualCostIds(Collection $locals): array
    {
        return Activity::query()
            ->where('subject_type', (new WorkOrderCost)->getMorphClass())
            ->whereIn('subject_id', $locals->modelKeys())
            ->pluck('subject_id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sameValues(array $row, WorkOrderCost $cost): bool
    {
        return round((float) $row['quantity'], 2) === round((float) $cost->quantity, 2)
            && round((float) $row['unit_price'], 2) === round($this->comparablePrice($row, $cost), 2)
            && (int) $row['vat_rate_id'] === (int) $cost->vat_rate_id
            && (int) $row['supplier_id'] === (int) $cost->supplier_id
            && ($row['incurred_on'] ?? null) === $cost->incurred_on?->format('Y-m-d')
            && ($row['document_reference'] ?? null) === $cost->document_reference
            && ($row['additional_description'] ?? null) === $cost->additional_description;
    }

    /**
     * A supplier paid through a commission costs 0 here whatever the legacy says
     * (spec 0320 D-23): that zero is not a difference.
     *
     * @param  array<string, mixed>  $row
     */
    private function comparablePrice(array $row, WorkOrderCost $cost): float
    {
        return $this->isCommissionZero($cost) ? (float) $row['unit_price'] : (float) $cost->unit_price;
    }

    private function isCommissionZero(WorkOrderCost $cost): bool
    {
        return (float) $cost->unit_price === 0.0 && $cost->supplier_id !== null
            && $this->supplierCommissions->hasCommission($cost->product_id, $cost->supplier_id);
    }

    /**
     * @param  Collection<int, WorkOrderCost>  $locals
     * @param  list<array{0: array<string, mixed>, 1: WorkOrderCost}>  $updates
     * @param  list<array<string, mixed>>  $creates
     * @return list<array<string, mixed>>
     */
    private function fullSet(Collection $locals, array $updates, array $creates): array
    {
        $updatesById = [];

        foreach ($updates as [$row, $cost]) {
            $updatesById[$cost->id] = $row;
        }

        return [
            ...$locals->map(fn (WorkOrderCost $cost): array => $this->storedRow($cost, $updatesById[$cost->id] ?? []))->all(),
            ...array_map(static fn (array $row): array => array_diff_key($row, ['ref' => true]), $creates),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacyRow  empty = the cost stays as it is
     * @return array<string, mixed>
     */
    private function storedRow(WorkOrderCost $cost, array $legacyRow): array
    {
        $stored = [
            'id' => $cost->id,
            'product_id' => $cost->product_id,
            'quote_line_id' => $cost->quote_line_id,
            'cost_type_id' => $cost->cost_type_id,
            'quantity' => (float) $cost->quantity,
            'unit_price' => (float) $cost->unit_price,
            'vat_rate_id' => $cost->vat_rate_id,
            'incurred_on' => $cost->incurred_on?->format('Y-m-d'),
            'supplier_id' => $cost->supplier_id,
            'document_reference' => $cost->document_reference,
            'additional_description' => $cost->additional_description,
        ];

        return $legacyRow === [] ? $stored : [
            ...$stored,
            ...array_diff_key($legacyRow, ['ref' => true, 'cost_type_id' => true]),
            'incurred_on' => $legacyRow['incurred_on'] ?? $stored['incurred_on'],
            'unit_price' => $this->isCommissionZero($cost) ? 0.0 : $legacyRow['unit_price'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\Registry;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderCostWriter;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Writes the legacy actual costs of a freshly imported commessa (spec 0189,
 * G-23): the costs of the offer's "Offerta" panel the legacy assigned to this
 * commessa, through WorkOrderCostWriter (amounts and unit computed there).
 * Each cost points at the commessa's line selling its service (`quote_line_id`,
 * a legacy line id resolved through the commessa's own lines) or at none.
 * The product is the migrated cost product of the legacy table named by
 * `product_source`; missing or not usable as a cost -> the whole commessa fails
 * with the reason (user decision 2026-10-14). The cost type is the product's default.
 */
final class LegacyWorkOrderCostWriter
{
    private const int DOCUMENT_REFERENCE_MAX = 100;

    public function __construct(private readonly WorkOrderCostWriter $writer) {}

    /**
     * @param  array<int, mixed>  $legacyCosts
     * @param  array<int, int>  $lineIdsByOldId  legacy line id => the commessa's line id
     * @param  array<int, string>  $warnings
     */
    public function write(WorkOrder $workOrder, array $legacyCosts, array $lineIdsByOldId, array &$warnings): void
    {
        $rows = [];

        foreach ($legacyCosts as $legacyCost) {
            $rows[] = $this->row($workOrder, (array) $legacyCost, $lineIdsByOldId, $warnings);
        }

        if ($rows !== []) {
            $this->writer->handle($workOrder, $rows);
        }
    }

    /**
     * @param  array<string, mixed>  $cost
     * @param  array<int, int>  $lineIdsByOldId
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function row(WorkOrder $workOrder, array $cost, array $lineIdsByOldId, array &$warnings): array
    {
        $ref = $cost['id'] ?? '?';
        $product = $this->product($cost['product_source'] ?? null, $cost['product_id'] ?? null, $ref);

        return [
            'product_id' => $product->id,
            'quote_line_id' => $this->line($cost['quote_line_id'] ?? null, $lineIdsByOldId, $ref, $warnings),
            'cost_type_id' => $product->cost_type_id,
            'quantity' => (float) ($cost['quantity'] ?? 0),
            'unit_price' => (float) ($cost['unit_price'] ?? 0),
            'vat_rate_id' => $this->oldIdOf(VatRate::query(), $cost['vat_rate_id'] ?? null, "Cost {$ref}: VAT rate", $warnings),
            'incurred_on' => $cost['incurred_on'] ?? $workOrder->start_date?->toDateString(),
            'supplier_id' => $this->oldIdOf(Registry::query()->where('is_supplier', true), $cost['supplier_id'] ?? null, "Cost {$ref}: supplier", $warnings),
            'document_reference' => $this->documentReference($cost['document_reference'] ?? null),
            'additional_description' => $this->text($cost['description'] ?? null),
        ];
    }

    private function product(mixed $source, mixed $legacyId, int|string $ref): Product
    {
        if (! array_key_exists((string) $source, CostProductCatalogue::BRANCHES) || $legacyId === null || $legacyId === '') {
            throw new RuntimeException("Cost {$ref} has no cost product; the work order is not imported.");
        }

        $product = Product::query()->where('old_source', $source)->where('old_id', $legacyId)->first(['id', 'usages', 'cost_type_id']);

        if ($product === null) {
            throw new RuntimeException("Cost {$ref}: cost product ({$source} legacy id {$legacyId}) not migrated; the work order is not imported.");
        }

        if (! $product->isUsableAs(ProductUsage::Cost)) {
            throw new RuntimeException("Cost {$ref}: product ({$source} legacy id {$legacyId}) is not usable as a cost; the work order is not imported.");
        }

        return $product;
    }

    /**
     * @param  array<int, int>  $lineIdsByOldId
     * @param  array<int, string>  $warnings
     */
    private function line(mixed $legacyLineId, array $lineIdsByOldId, int|string $ref, array &$warnings): ?int
    {
        if ($legacyLineId === null || $legacyLineId === '') {
            return null;
        }

        $lineId = $lineIdsByOldId[(int) $legacyLineId] ?? null;

        if ($lineId === null) {
            $warnings[] = "Cost {$ref}: its line (legacy id {$legacyLineId}) is not on the work order; kept as an unattributed cost.";
        }

        return $lineId;
    }

    /**
     * @param  Builder<VatRate>|Builder<Registry>  $query
     * @param  array<int, string>  $warnings
     */
    private function oldIdOf(Builder $query, mixed $legacyId, string $label, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $id = $query->where('old_id', $legacyId)->value('id');

        if ($id === null) {
            $warnings[] = "{$label} (legacy id {$legacyId}) not migrated; left empty.";
        }

        return $id === null ? null : (int) $id;
    }

    private function documentReference(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_substr($text, 0, self::DOCUMENT_REFERENCE_MAX);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}

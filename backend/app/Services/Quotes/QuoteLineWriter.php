<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\ProductUsage;
use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Services\Commissions\QuoteLineCommissionWriter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class QuoteLineWriter
{
    public function __construct(
        private readonly QuoteTotalsCalculator $calculator,
        private readonly QuoteLineCommissionWriter $commissionWriter,
        private readonly CostLineAllocationResolver $allocationResolver,
    ) {}

    /**
     * @param  array<int, QuoteLineData>  $lines
     * @param  array<int, QuoteLine>|null  $revenueLines  spec 0144, D-4: the REVENUE rows just persisted in the SAME request, keyed by their own submitted index — only read when $type is COST; null means `offer_lines` was not submitted at all.
     * @return array<int, QuoteLine> the saved rows, keyed by $lines' own payload index
     */
    public function sync(Quote $quote, QuoteLineType $type, array $lines, ?array $revenueLines = null): array
    {
        $existing = QuoteLine::query()
            ->where('quote_id', $quote->id)
            ->where('line_type', $type)
            ->get()
            ->keyBy('id');
        $submittedIds = array_values(array_filter(array_map(
            static fn (QuoteLineData $line): ?int => $line->id,
            $lines,
        )));

        if (count($submittedIds) !== count(array_unique($submittedIds))
            || collect($submittedIds)->contains(fn (int $id): bool => ! $existing->has($id))) {
            throw ValidationException::withMessages([
                $type === QuoteLineType::Revenue ? 'offer_lines' : 'cost_lines' => [
                    __('commission_configurations.invalid_quote_line_id'),
                ],
            ]);
        }

        $this->assertProductsUsable($type, $lines, $existing);

        $rates = $this->resolveVatRates($lines);
        $units = $this->resolveProductUnits($lines);
        // Spec 0144, D-2/D-4: only a COST row ever carries an allocation — a
        // REVENUE row's own `offer_line_id` is always NULL, so resolving it
        // for the other type would be dead work at best.
        $allocations = $type === QuoteLineType::Cost
            ? $this->allocationResolver->resolve($quote, $lines, $revenueLines)
            : [];

        $saved = [];

        foreach (array_values($lines) as $index => $data) {
            /** @var QuoteLine|null $line */
            $line = $data->id === null ? null : $existing->get($data->id);
            $isNew = $line === null;
            $line ??= new QuoteLine(['quote_id' => $quote->id, 'line_type' => $type]);
            $productChanged = ! $isNew && $line->product_id !== $data->productId;
            $rate = $data->vatRateId === null ? null : ($rates[$data->vatRateId] ?? null);
            $amounts = $this->calculator->lineAmounts($data->quantity, $data->unitPrice, $rate);

            $line->fill([
                'product_id' => $data->productId,
                'quantity' => $data->quantity,
                'unit_price' => $data->unitPrice,
                'vat_rate_id' => $data->vatRateId,
                // Spec 0088, D-5 (AC-054/AC-055): frozen from the Product ONLY
                // when the row is created or its product actually changes —
                // NEVER on a resubmit of an otherwise-untouched line. offer_
                // lines/cost_lines full-replace (D-8) resends every row on
                // every quote save, so resolving this key unconditionally
                // would silently re-sync every line to its product's CURRENT
                // unit on the very next unrelated edit, erasing the freeze
                // (bug found by the verifier, fixed 2026-09-01).
                'unit_of_measure_id' => $isNew || $productChanged ? ($units[$data->productId] ?? null) : $line->unit_of_measure_id,
                'additional_description' => $data->hasAdditionalDescription ? $data->additionalDescription : $line->additional_description,
                'net_amount' => $amounts['net'],
                'vat_amount' => $amounts['vat'],
                'total_amount' => $amounts['total'],
                'sort_order' => $data->sortOrder ?? $index,
                // Spec 0144, D-2/D-3: full-replace like every other column on
                // this row — a COST row resubmitted without either key goes
                // back to a generic cost, exactly like it would for quantity
                // or unit_price. Never set on a REVENUE row.
                'offer_line_id' => $allocations[$index] ?? null,
            ])->save();

            $saved[$index] = $line;

            if ($type === QuoteLineType::Revenue) {
                $this->commissionWriter->sync($line, $data->commissions, $productChanged);
            } else {
                $line->commissions()->delete();
            }
        }

        $existing->except($submittedIds)->each->delete();
        $quote->unsetRelations();

        return $saved;
    }

    /**
     * Spec 0142, D-5: every row that is new, or whose product changes, must
     * carry a product usable on this tab (Sellable for REVENUE, Usable as
     * cost for COST). A persisted row resubmitting its own product is exempt,
     * so a historic quote stays saveable after its product's usages change.
     * Enforced HERE because every channel (Offerte, Gestione Richieste,
     * inline edit, Lead conversion) reaches this one writer.
     *
     * @param  array<int, QuoteLineData>  $lines
     * @param  Collection<int, QuoteLine>  $existing
     */
    private function assertProductsUsable(QuoteLineType $type, array $lines, Collection $existing): void
    {
        $toCheck = array_filter(
            array_values($lines),
            static fn (QuoteLineData $line): bool => $line->id === null || $existing->get($line->id)?->product_id !== $line->productId,
        );

        if ($toCheck === []) {
            return;
        }

        $usage = ProductUsage::forLineType($type);
        $usable = Product::query()
            ->whereIn('id', array_unique(array_map(static fn (QuoteLineData $line): int => $line->productId, $toCheck)))
            ->whereJsonContains('usages', $usage->value)
            ->pluck('id')
            ->all();
        $field = $type === QuoteLineType::Revenue ? 'offer_lines' : 'cost_lines';
        $errors = [];

        foreach ($toCheck as $index => $line) {
            if (! in_array($line->productId, $usable, true)) {
                $errors["{$field}.{$index}.product_id"] = [__('quotes.product_not_usable.'.$usage->value)];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<int, QuoteLineData> $lines @return array<int, float> */
    private function resolveVatRates(array $lines): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (QuoteLineData $line): ?int => $line->vatRateId,
            $lines,
        ))));

        return $ids === [] ? [] : VatRate::query()
            ->whereIn('id', $ids)
            ->pluck('rate', 'id')
            ->map(static fn (mixed $rate): float => (float) $rate)
            ->all();
    }

    /**
     * The unit of measure currently set on each submitted line's Product
     * (spec 0088, D-5), keyed by product id — the value frozen onto the
     * line's own `unit_of_measure_id` at this exact write, mirroring
     * resolveVatRates()'s batch shape.
     *
     * @param  array<int, QuoteLineData>  $lines
     * @return array<int, int|null>
     */
    private function resolveProductUnits(array $lines): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (QuoteLineData $line): int => $line->productId,
            $lines,
        )));

        return $ids === [] ? [] : Product::query()
            ->whereIn('id', $ids)
            ->pluck('unit_of_measure_id', 'id')
            ->all();
    }
}

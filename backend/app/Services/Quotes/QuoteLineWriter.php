<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Services\Commissions\QuoteLineCommissionWriter;
use Illuminate\Validation\ValidationException;

final class QuoteLineWriter
{
    public function __construct(
        private readonly QuoteTotalsCalculator $calculator,
        private readonly QuoteLineCommissionWriter $commissionWriter,
    ) {}

    /** @param array<int, QuoteLineData> $lines */
    public function sync(Quote $quote, QuoteLineType $type, array $lines): void
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

        $rates = $this->resolveVatRates($lines);

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
                'net_amount' => $amounts['net'],
                'vat_amount' => $amounts['vat'],
                'total_amount' => $amounts['total'],
                'sort_order' => $data->sortOrder ?? $index,
            ])->save();

            if ($type === QuoteLineType::Revenue) {
                $this->commissionWriter->sync($line, $data->commissions, $productChanged);
            } else {
                $line->commissions()->delete();
            }
        }

        $existing->except($submittedIds)->each->delete();
        $quote->unsetRelations();
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
}

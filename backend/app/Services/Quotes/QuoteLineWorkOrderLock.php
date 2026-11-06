<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Product;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec 0360, D-4: a REVENUE row assigned to a work order can be neither removed
 * nor changed while the work order exists. Called by QuoteLineWriter::sync()
 * (the only writer of the rows) before any write, so the rule holds on every
 * channel: Offerte, Contratto, Gestione Richieste. Commissions and
 * `sort_order` are not part of the lock (D-5).
 */
final class QuoteLineWorkOrderLock
{
    private const float DECIMAL_TOLERANCE = 0.000001;

    /**
     * @param  array<int, QuoteLineData>  $lines
     * @param  Collection<int, QuoteLine>  $existing  the quote's stored REVENUE rows keyed by id
     *
     * @throws ValidationException
     */
    public function assertRespected(array $lines, Collection $existing): void
    {
        // Step 1: the work order codes of every assigned stored row.
        $codes = $this->codesByLine($existing->keys()->all());

        if ($codes === []) {
            return;
        }

        $lines = array_values($lines);
        $submitted = [];
        $errors = [];

        // Step 2: a locked row that was submitted must come back unchanged.
        foreach ($lines as $index => $data) {
            if ($data->id === null || ! isset($codes[$data->id])) {
                continue;
            }

            $submitted[$data->id] = true;

            if ($this->isChanged($existing->get($data->id), $data)) {
                $errors["offer_lines.{$index}"] = [__('quotes.line_locked_by_work_order', ['code' => $codes[$data->id]])];
            }
        }

        // Step 3: a locked row that was omitted would be deleted.
        foreach ($codes as $lineId => $code) {
            if (! isset($submitted[$lineId])) {
                $errors['offer_lines'][] = __('quotes.line_removal_blocked_by_work_order', [
                    'product' => Product::query()->whereKey($existing->get($lineId)->product_id)->value('name') ?? '#'.$lineId,
                    'code' => $code,
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array<int, string> line id => comma separated work order codes
     */
    private function codesByLine(array $lineIds): array
    {
        return DB::table('quote_line_work_order')
            ->join('work_orders', 'work_orders.id', '=', 'quote_line_work_order.work_order_id')
            ->whereIn('quote_line_work_order.quote_line_id', $lineIds)
            ->orderBy('work_orders.code')
            ->get(['quote_line_work_order.quote_line_id', 'work_orders.code'])
            ->groupBy('quote_line_id')
            ->map(static fn (Collection $rows): string => $rows->pluck('code')->unique()->implode(', '))
            ->all();
    }

    private function isChanged(QuoteLine $stored, QuoteLineData $data): bool
    {
        if ($stored->product_id !== $data->productId
            || $stored->vat_rate_id !== $data->vatRateId
            || ! $this->sameNumber($stored->quantity, $data->quantity)
            || ! $this->sameNumber($stored->unit_price, $data->unitPrice)) {
            return true;
        }

        if ($data->hasSupplier && $stored->supplier_id !== $data->supplierId) {
            return true;
        }

        return $data->hasAdditionalDescription
            && ($stored->additional_description ?? '') !== ($data->additionalDescription ?? '');
    }

    private function sameNumber(mixed $stored, float $submitted): bool
    {
        return abs((float) $stored - $submitted) < self::DECIMAL_TOLERANCE;
    }
}

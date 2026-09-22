<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;

/**
 * The ONE place (spec 0145, D-1/D-2) a REVENUE line's commission base is
 * computed: max(0, its own `net_amount` minus the sum of the `net_amount` of
 * the COST lines imputed to it via `offer_line_id` (spec 0144). Unallocated
 * (generic) costs never reduce it — a base clamped at zero also zeroes any
 * PERCENTAGE commission built on it, while FIXED_AMOUNT ignores the value
 * entirely (CommissionCalculator only reads it for PERCENTAGE). Consumed by
 * QuoteLineCommissionWriter on every path that feeds CommissionCalculator —
 * defaults, manual override and the post-sync margin recalculation — so the
 * formula itself lives nowhere else.
 */
final class QuoteLineCommissionBaseResolver
{
    /**
     * One line's base — a single aggregate query.
     */
    public function resolve(QuoteLine $line): string
    {
        return $this->resolveForLines(collect([$line]))[$line->id];
    }

    /**
     * The batched sibling of resolve(): ONE aggregate query for every line in
     * $lines, grouped by `offer_line_id` — avoids an N+1 across a whole
     * quote's REVENUE lines (spec 0145, D-6).
     *
     * @param  Collection<int, QuoteLine>  $lines
     * @return array<int, string> keyed by `quote_lines.id`
     */
    public function resolveForLines(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $allocatedNet = QuoteLine::query()
            ->where('line_type', QuoteLineType::Cost)
            ->whereIn('offer_line_id', $lines->pluck('id'))
            ->selectRaw('offer_line_id, SUM(net_amount) as allocated_net')
            ->groupBy('offer_line_id')
            ->pluck('allocated_net', 'offer_line_id');

        return $lines->mapWithKeys(fn (QuoteLine $line): array => [
            $line->id => $this->clamp((float) $line->net_amount - (float) ($allocatedNet[$line->id] ?? 0)),
        ])->all();
    }

    private function clamp(float $base): string
    {
        return number_format(max(0.0, round($base, 2, PHP_ROUND_HALF_UP)), 2, '.', '');
    }
}

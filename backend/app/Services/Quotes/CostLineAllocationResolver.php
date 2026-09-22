<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a COST line's `offer_line_id`/`offer_line_index` (spec 0144, D-4)
 * into the concrete `quote_lines.id` it is imputed to — extracted out of
 * `QuoteLineWriter::sync()` to keep that file under the file-size soft limit.
 * Called AFTER the REVENUE tab has already been synced (D-4/D-5), so an
 * `offer_line_id` check reads the quote's REVENUE rows as they stand right
 * now: a row removed earlier in the SAME request is already gone from the
 * table by the time this runs, and one belonging to another quote or of the
 * COST type never matches the scoped query below — both rejected the exact
 * same way as any other invalid id (AC-005).
 */
final class CostLineAllocationResolver
{
    /**
     * @param  array<int, QuoteLineData>  $lines  the submitted `cost_lines`, in payload order
     * @param  array<int, QuoteLine>|null  $revenueLines  the REVENUE rows just persisted in the SAME request, keyed by their own submitted index — null when `offer_lines` was not submitted at all
     * @return array<int, int|null> the resolved `offer_line_id`, keyed like $lines
     *
     * @throws ValidationException AC-005/AC-006: an unresolvable id or index
     */
    public function resolve(Quote $quote, array $lines, ?array $revenueLines): array
    {
        // Step 1: one query for every direct `offer_line_id` reference, scoped
        // to THIS quote's REVENUE rows — no per-row query.
        $existingRevenueIds = $this->existingRevenueIds($quote, $lines);

        // Step 2: resolve each row, collecting every failure before throwing
        // (so a single request reports all of them, not just the first).
        $resolved = [];
        $errors = [];

        foreach (array_values($lines) as $index => $data) {
            if ($data->offerLineId !== null) {
                if (in_array($data->offerLineId, $existingRevenueIds, true)) {
                    $resolved[$index] = $data->offerLineId;
                } else {
                    $errors["cost_lines.{$index}.offer_line_id"] = [__('quotes.cost_line_offer_line_id_invalid')];
                }

                continue;
            }

            if ($data->offerLineIndex !== null) {
                $revenueLine = $revenueLines[$data->offerLineIndex] ?? null;

                if ($revenueLine !== null) {
                    $resolved[$index] = $revenueLine->id;
                } else {
                    $errors["cost_lines.{$index}.offer_line_index"] = [__('quotes.cost_line_offer_line_index_invalid')];
                }

                continue;
            }

            $resolved[$index] = null;
        }

        // Step 3: no partial resolution — the caller writes inside the same
        // DB transaction as the REVENUE sync, so throwing here still leaves
        // NOTHING persisted for either tab (AC-005).
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    /**
     * @param  array<int, QuoteLineData>  $lines
     * @return array<int, int>
     */
    private function existingRevenueIds(Quote $quote, array $lines): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (QuoteLineData $line): ?int => $line->offerLineId,
            $lines,
        ))));

        if ($ids === []) {
            return [];
        }

        return QuoteLine::query()
            ->where('quote_id', $quote->id)
            ->where('line_type', QuoteLineType::Revenue)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }
}

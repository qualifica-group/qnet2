<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes the `quote_line_work_order` pivot (spec 0093, D-6/D-7; spec 0095,
 * D-4/D-5) and enforces its TWO membership invariants SERVER-SIDE against
 * the real rows, both in create and update: every submitted
 * `quote_line_ids` entry must belong to the work order's OWN `quote_id` and
 * be a REVENUE line (`Quote::offerLines()`'s own filter, D-7 of spec 0093),
 * AND must not already be programmed into a DIFFERENT work order IN THE
 * SAME contract renewal period ("una riga, una sola Commessa per periodo",
 * spec 0095 D-4 as amended by spec 0358 D-5) — never trusted from the
 * client. The period is never an input: RenewalPeriodResolver decides it.
 *
 * Called from inside WorkOrderService's DB transaction: a thrown
 * ValidationException there rolls back the whole write (AC-022/AC-023/
 * AC-032 — "nessuna commessa viene creata").
 */
final class WorkOrderLineWriter
{
    public function __construct(
        private readonly MultiQuoteLineRules $multiQuoteRules,
        private readonly RenewalPeriodResolver $periods,
    ) {}

    /**
     * Full-replace sync (AC-025), a no-op when `$quoteLineIds` is null — the
     * key was not submitted at all (AC-026, PATCH only; create always
     * submits an array, defaulting to `[]`).
     *
     * `$allowedQuoteIds` is the single own offer of a mono-offer commessa, or
     * the selected contracts' offers (create) / the current source offers
     * (update) of a multi-offer one (spec 0267, D-2/D-5), whose extra rules
     * live in MultiQuoteLineRules.
     *
     * @param  array<int, int>  $allowedQuoteIds
     * @param  array<int, int>|null  $quoteLineIds
     */
    public function writeSubmitted(WorkOrder $workOrder, array $allowedQuoteIds, ?array $quoteLineIds): void
    {
        if ($quoteLineIds === null) {
            return;
        }

        $this->assertBelongToRevenueLines($allowedQuoteIds, $quoteLineIds);

        $periodsByLine = $this->periodsByLine($workOrder, $quoteLineIds);
        $this->assertNotAlreadyProgrammed($workOrder, $periodsByLine);

        if ($workOrder->isMultiQuote()) {
            $this->multiQuoteRules->assert($workOrder, $quoteLineIds);
        }

        $syncPayload = [];

        foreach ($periodsByLine as $lineId => $period) {
            $syncPayload[$lineId] = ['renewal_number' => $period];
        }

        $changes = $workOrder->quoteLines()->sync($syncPayload);

        // Spec 0201, D-11: a line that leaves the commessa takes its payment data with it.
        $workOrder->linePayments()->whereIn('quote_line_id', $changes['detached'])->delete();
    }

    /**
     * @param  array<int, int>  $allowedQuoteIds
     * @param  array<int, int>  $quoteLineIds
     */
    public function assertBelongToRevenueLines(array $allowedQuoteIds, array $quoteLineIds): void
    {
        if ($quoteLineIds === []) {
            return;
        }

        $validIds = QuoteLine::query()
            ->whereIn('quote_id', $allowedQuoteIds)
            ->where('line_type', QuoteLineType::Revenue)
            ->whereIn('id', $quoteLineIds)
            ->pluck('id')
            ->all();

        $invalidIds = array_diff($quoteLineIds, $validIds);

        if ($invalidIds !== []) {
            throw ValidationException::withMessages([
                'quote_line_ids' => __('work_orders.lines_not_offer_revenue'),
            ]);
        }
    }

    /**
     * Period of each submitted line (spec 0358, D-4): lines the commessa
     * already holds keep their own; new ones take the resolver's period for
     * their offer.
     *
     * @param  array<int, int>  $quoteLineIds
     * @return array<int, int> quote line id => period
     */
    private function periodsByLine(WorkOrder $workOrder, array $quoteLineIds): array
    {
        if ($quoteLineIds === []) {
            return [];
        }

        $quoteIdByLine = QuoteLine::query()->whereIn('id', $quoteLineIds)->pluck('quote_id', 'id');
        $held = $workOrder->exists
            ? DB::table('quote_line_work_order')->where('work_order_id', $workOrder->id)->pluck('renewal_number', 'quote_line_id')
            : collect();
        $byQuote = $this->periods->periodForWorkOrderLines($workOrder, $quoteIdByLine->unique()->values()->all());

        $periods = [];

        foreach ($quoteLineIds as $lineId) {
            $periods[$lineId] = (int) ($held[$lineId] ?? $byQuote[$quoteIdByLine[$lineId]] ?? 0);
        }

        return $periods;
    }

    /**
     * "Una riga, una sola Commessa per periodo" (spec 0095, D-4; spec 0358,
     * D-5): rejects any submitted line already attached, IN ITS OWN PERIOD,
     * to a work order OTHER than `$workOrder` itself — lines the SAME work
     * order already owns are explicitly allowed back through (AC-042).
     * Named lookup: the 422 message names the occupying commessa's `code`.
     *
     * @param  array<int, int>  $periodsByLine  quote line id => period
     */
    public function assertNotAlreadyProgrammed(WorkOrder $workOrder, array $periodsByLine): void
    {
        if ($periodsByLine === []) {
            return;
        }

        $conflict = DB::table('quote_line_work_order')
            ->join('work_orders', 'work_orders.id', '=', 'quote_line_work_order.work_order_id')
            ->where('quote_line_work_order.work_order_id', '!=', $workOrder->id)
            ->where(function ($matches) use ($periodsByLine): void {
                foreach ($periodsByLine as $lineId => $period) {
                    $matches->orWhere(fn ($pair) => $pair
                        ->where('quote_line_work_order.quote_line_id', $lineId)
                        ->where('quote_line_work_order.renewal_number', $period));
                }
            })
            ->select('quote_line_work_order.quote_line_id', 'work_orders.code')
            ->first();

        if ($conflict === null) {
            return;
        }

        throw ValidationException::withMessages([
            'quote_line_ids' => __('work_orders.line_already_programmed', ['code' => $conflict->code]),
        ]);
    }
}

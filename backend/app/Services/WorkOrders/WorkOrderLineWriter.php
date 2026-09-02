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
 * AND must not already be programmed into a DIFFERENT work order ("una
 * riga, una sola Commessa", D-4 of spec 0095) — never trusted from the
 * client.
 *
 * Called from inside WorkOrderService's DB transaction: a thrown
 * ValidationException there rolls back the whole write (AC-022/AC-023/
 * AC-032 — "nessuna commessa viene creata").
 */
final class WorkOrderLineWriter
{
    /**
     * Full-replace sync (AC-025), a no-op when `$quoteLineIds` is null — the
     * key was not submitted at all (AC-026, PATCH only; create always
     * submits an array, defaulting to `[]`).
     *
     * @param  array<int, int>|null  $quoteLineIds
     */
    public function writeSubmitted(WorkOrder $workOrder, int $quoteId, ?array $quoteLineIds): void
    {
        if ($quoteLineIds === null) {
            return;
        }

        $this->assertBelongToRevenueLines($quoteId, $quoteLineIds);
        $this->assertNotAlreadyProgrammed($workOrder, $quoteLineIds);

        $workOrder->quoteLines()->sync($quoteLineIds);
    }

    /**
     * @param  array<int, int>  $quoteLineIds
     */
    public function assertBelongToRevenueLines(int $quoteId, array $quoteLineIds): void
    {
        if ($quoteLineIds === []) {
            return;
        }

        $validIds = QuoteLine::query()
            ->where('quote_id', $quoteId)
            ->where('line_type', QuoteLineType::Revenue)
            ->whereIn('id', $quoteLineIds)
            ->pluck('id')
            ->all();

        $invalidIds = array_diff($quoteLineIds, $validIds);

        if ($invalidIds !== []) {
            throw ValidationException::withMessages([
                'quote_line_ids' => 'The selected quote_line_ids must belong to the quote and be revenue lines.',
            ]);
        }
    }

    /**
     * "Una riga, una sola Commessa" (spec 0095, D-4): rejects any submitted
     * line already attached to a work order OTHER than `$workOrder` itself —
     * lines the SAME work order already owns are explicitly allowed back
     * through (AC-042, a PATCH that resends its own lines is not a
     * duplicate). Named lookup, not a bare pivot check: the 422 message
     * names the occupying commessa's `code` (AC-032), never just an id.
     *
     * @param  array<int, int>  $quoteLineIds
     */
    public function assertNotAlreadyProgrammed(WorkOrder $workOrder, array $quoteLineIds): void
    {
        if ($quoteLineIds === []) {
            return;
        }

        $conflict = DB::table('quote_line_work_order')
            ->join('work_orders', 'work_orders.id', '=', 'quote_line_work_order.work_order_id')
            ->whereIn('quote_line_work_order.quote_line_id', $quoteLineIds)
            ->where('quote_line_work_order.work_order_id', '!=', $workOrder->id)
            ->select('quote_line_work_order.quote_line_id', 'work_orders.code')
            ->first();

        if ($conflict === null) {
            return;
        }

        throw ValidationException::withMessages([
            'quote_line_ids' => "Quote line #{$conflict->quote_line_id} is already programmed in work order {$conflict->code}.",
        ]);
    }
}

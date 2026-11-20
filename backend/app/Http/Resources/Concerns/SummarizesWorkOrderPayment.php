<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\QuoteLine;
use Illuminate\Http\Request;

/**
 * "Importo" of the commessa (spec 0402): the net amount of its product lines,
 * the same total the contract data sum. Money, so only for an actor who may
 * view the contract data (null otherwise). Relies on
 * WorkOrderService::DETAIL_RELATIONS eager-loading `quoteLines`.
 */
trait SummarizesWorkOrderPayment
{
    private function summarizeAmount(Request $request): ?string
    {
        if ($request->user()?->can('viewContractData', $this->resource) !== true) {
            return null;
        }

        $cents = $this->quoteLines->sum(static fn (QuoteLine $line): int => (int) round((float) $line->net_amount * 100));

        return number_format($cents / 100, 2, '.', '');
    }
}

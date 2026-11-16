<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\QuoteLine;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Http\Request;

/**
 * "Importo" and "Stato pagamento" of the commessa (spec 0402), shown under
 * its title in "Dati contrattuali commessa". The amount is the net amount of
 * the commessa's product lines, the same total the contract data sum: money,
 * so only for an actor who may view the contract data (null otherwise).
 * Relies on WorkOrderService::DETAIL_RELATIONS eager-loading `quoteLines`
 * and `paymentStatus`.
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

    /**
     * @return array{id: int, name: string, color: string, allows_delivery: bool}|null
     */
    private function summarizePaymentStatus(): ?array
    {
        /** @var WorkOrderPaymentStatus|null $status */
        $status = $this->paymentStatus;

        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'allows_delivery' => $status->allows_delivery,
        ];
    }
}

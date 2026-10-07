<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\CommissionRecipientRole;
use App\Enums\SupplierCommissionDirection;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;

/**
 * The effective revenue of a commessa's REVENUE line (spec 0202, D-8/D-11),
 * shared by the Dati contrattuali tab and the Costi comparison so the two can
 * never diverge: a line whose frozen supplier commission direction is
 * RECEIVED earns only its supplier commission, every other line its net
 * amount. Amounts are integer cents; the caller eager loads `commissions`.
 */
final class LineEffectiveRevenue
{
    public function cents(QuoteLine $line): int
    {
        if ($line->supplier_commission_direction !== SupplierCommissionDirection::Received) {
            return $this->toCents($line->net_amount);
        }

        return $this->supplierCommissionCents($line);
    }

    public function supplierCommissionCents(QuoteLine $line): int
    {
        return $line->commissions
            ->filter(fn (QuoteLineCommission $commission): bool => $commission->recipient_role === CommissionRecipientRole::Supplier)
            ->sum(fn (QuoteLineCommission $commission): int => $this->toCents($commission->calculated_amount));
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}

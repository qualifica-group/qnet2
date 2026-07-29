<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\Enums\CommissionRecipientRole;
use App\Enums\QuoteLineType;
use App\Models\Quote;

final class QuoteCommissionSummaryCalculator
{
    /** @return array{commercial: string, reporter: string, supervisor: string, supplier: string} */
    public function totals(Quote $quote): array
    {
        $totals = $quote->lines()
            ->where('quote_lines.line_type', QuoteLineType::Revenue)
            ->join('quote_line_commissions', 'quote_lines.id', '=', 'quote_line_commissions.quote_line_id')
            ->selectRaw('quote_line_commissions.recipient_role, SUM(quote_line_commissions.calculated_amount) as total')
            ->groupBy('quote_line_commissions.recipient_role')
            ->pluck('total', 'recipient_role');

        return [
            'commercial' => $this->format($totals[CommissionRecipientRole::Commercial->value] ?? 0),
            'reporter' => $this->format($totals[CommissionRecipientRole::Reporter->value] ?? 0),
            'supervisor' => $this->format($totals[CommissionRecipientRole::Supervisor->value] ?? 0),
            'supplier' => $this->format($totals[CommissionRecipientRole::Supplier->value] ?? 0),
        ];
    }

    private function format(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Quotes\QuoteLineCommissionData;
use App\Enums\CommissionRecalculationMode;
use App\Enums\SupplierCommissionDirection;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Services\Quotes\SupplierCommissionEnablement;
use App\Services\QuoteService;
use Illuminate\Support\Facades\DB;

/**
 * Explicit realignment of an open quote's commissions to the current rules
 * (spec 0308, D-5). The only code path allowed to touch commissions of a
 * saved quote without the user editing them (D-9); callers decide WHICH
 * quotes are eligible (see CommissionRecalculationImpact).
 */
final class QuoteCommissionRecalculator
{
    public function __construct(
        private readonly QuoteLineCommissionWriter $writer,
        private readonly SupplierCommissionEnablement $enablement,
        private readonly QuoteService $quoteService,
        private readonly CommissionRecalculationImpact $impact,
    ) {}

    /**
     * Returns false, touching nothing, when the quote turned out to be closed:
     * the status is re-read under lock inside the transaction, so a quote closed
     * after the caller selected it is never recalculated (D-3).
     *
     * @param  array<int, int>|null  $lineIds  restrict to these REVENUE lines (rule propagation, D-6); null = every line
     */
    public function recalculate(Quote $quote, CommissionRecalculationMode $mode, ?array $lineIds = null): bool
    {
        return DB::transaction(function () use ($quote, $mode, $lineIds): bool {
            // Step 0: closed quotes are never touched
            $current = Quote::query()->with('quoteWorkflowStatus')->lockForUpdate()->find($quote->id);

            if ($current === null || $this->impact->isClosed($current)) {
                return false;
            }

            // Step 1: the REVENUE lines to realign, with their typology switch
            $lines = $quote->offerLines()
                ->whereNotNull('product_id')
                ->when($lineIds !== null, fn ($query) => $query->whereKey($lineIds))
                ->with('commissions')
                ->get();
            $enabled = $this->enablement->enabledForProducts($lines->pluck('product_id')->all());

            // Step 2: per line, snapshot first (a), then commissions (b)
            foreach ($lines as $line) {
                $this->syncDirectionSnapshot($line, $enabled[$line->product_id] ?? false);
                $this->recalculateLine($line, $mode);
            }

            // Step 3: margin base and header aggregates (c)
            $this->writer->recalculateQuoteMargins($quote);
            $this->quoteService->persistAggregates($quote);

            return true;
        });
    }

    /** Switch off = no Supplier commission at all (0202 D-6); switch on with no snapshot yet = PAID. */
    private function syncDirectionSnapshot(QuoteLine $line, bool $supplierEnabled): void
    {
        if (! $supplierEnabled) {
            if ($line->supplier_commission_direction !== null) {
                $line->update(['supplier_commission_direction' => null]);
            }

            return;
        }

        if ($line->supplier_commission_direction === null) {
            $line->update(['supplier_commission_direction' => SupplierCommissionDirection::Paid]);
        }
    }

    private function recalculateLine(QuoteLine $line, CommissionRecalculationMode $mode): void
    {
        if ($mode === CommissionRecalculationMode::RegenerateAll) {
            $this->writer->sync($line, null, regenerate: true);

            return;
        }

        // Re-submitting the persisted commissions makes sync() keep the manual
        // ones (amount recomputed) and swap the rule-driven ones for the
        // current default of their role, or drop them when no rule applies.
        $this->writer->sync($line, $line->commissions
            ->map(fn (QuoteLineCommission $commission): QuoteLineCommissionData => new QuoteLineCommissionData(
                id: $commission->id,
                role: $commission->recipient_role,
                recipientType: $commission->recipient_type,
                recipientId: $commission->recipient_id,
                type: $commission->commission_type,
                value: (string) $commission->value,
                internalNote: $commission->internal_note,
                origin: $commission->origin,
                configurationId: $commission->commission_configuration_id,
                supplierDirection: $line->supplier_commission_direction,
            ))
            ->all());
    }
}

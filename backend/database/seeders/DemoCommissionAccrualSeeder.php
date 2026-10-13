<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Services\Commissions\Accruals\CommissionAccrualSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Demo commission accrual ledger (spec 0212): runs the real synchronizer on
 * every invoice with at least one collected installment, so the movements are
 * the ones the module would have posted. Rows are never written by hand. The
 * synchronizer posts only differences, so a re-run creates nothing new.
 * Depends on DemoInvoiceSeeder.
 */
class DemoCommissionAccrualSeeder extends Seeder
{
    public function __construct(private readonly CommissionAccrualSynchronizer $accruals) {}

    public function run(): void
    {
        Invoice::query()
            ->whereHas('installments', fn ($query) => $query->whereNotNull('collected_at'))
            ->orderBy('id')
            ->each(fn (Invoice $invoice) => $this->accruals->syncInvoice($invoice));
    }
}

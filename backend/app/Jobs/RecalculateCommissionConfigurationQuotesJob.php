<?php

namespace App\Jobs;

use App\Enums\CommissionRecalculationMode;
use App\Models\CommissionConfiguration;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Commissions\CommissionRecalculationImpact;
use App\Services\Commissions\QuoteCommissionRecalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Propagates a saved commission rule to the open quotes that use it (spec 0308,
 * D-6/D-7). Each quote runs in its own transaction (QuoteCommissionRecalculator),
 * so one that fails is skipped and counted without blocking the others; the
 * author is notified with the outcome. `$tries = 1`: a retry would redo the
 * quotes already realigned.
 */
class RecalculateCommissionConfigurationQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        private readonly int $commissionConfigurationId,
        private readonly CommissionRecalculationMode $mode,
        private readonly int $userId,
    ) {
        $this->timeout = (int) config('commission_recalculation.job_timeout');
    }

    public function handle(CommissionRecalculationImpact $impact, QuoteCommissionRecalculator $recalculator): void
    {
        // Under the sync queue driver this runs inside the HTTP request.
        set_time_limit(0);

        $configuration = CommissionConfiguration::query()->findOrFail($this->commissionConfigurationId);
        $updated = 0;
        $failed = 0;

        foreach ($impact->touchedLineIdsByQuote($configuration) as $quoteId => $lineIds) {
            try {
                // A quote closed after the selection is skipped and counted as not updated.
                $recalculator->recalculate(Quote::query()->findOrFail($quoteId), $this->mode, $lineIds) ? $updated++ : $failed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        User::query()->find($this->userId)?->notify(new GenericNotification(
            __('commission_configurations.recalculation.title'),
            __('commission_configurations.recalculation.message', ['updated' => $updated, 'failed' => $failed]),
            $failed > 0 ? 'warning' : 'success',
            "/commission-configurations/{$configuration->id}",
        ));
    }
}

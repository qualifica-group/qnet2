<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkflowStatusSystemKey;
use App\Migrations\Sources\QuotesSource;
use App\Migrations\Support\LegacyQuoteState;
use App\Migrations\Support\LegacyQuoteStatusApplier;
use App\Migrations\Support\LegacyQuoteStatusResolver;
use App\Models\Quote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * One-off realignment of the offers already imported from the legacy QNet
 * to the 2026-10-13 status mapping (spec 0189 G-6): a presented offer
 * follows its opportunity phase, an offer closed without migrated lines
 * keeps its outcome, an offer accepted by the client falls back to the
 * `validated` group.
 *
 * Walks the legacy `quotes` endpoint and touches only an imported offer that
 * still sits on the `open` system status of its set and has no activity
 * since the import (which ran without logs): any user action on it wins. The
 * write is the import's own (LegacyQuoteStatusApplier: quiet, contract
 * opened and aligned on a positive close). Idempotent: a second run touches
 * nothing.
 */
class RealignLegacyQuoteStatuses extends Command
{
    protected $signature = 'quotes:realign-legacy-status {--dry-run : Report what would change without writing}';

    protected $description = 'Realign the status of the offers imported from the legacy QNet to the current legacy status mapping';

    public function handle(QuotesSource $source, LegacyQuoteStatusResolver $resolver, LegacyQuoteStatusApplier $applier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $realigned = 0;

        $source->eachLegacyRecord(function (array $record) use ($resolver, $applier, $dryRun, &$realigned): void {
            $realigned += $this->realign($record, $resolver, $applier, $dryRun) ? 1 : 0;
        });

        $this->info($dryRun
            ? "{$realigned} offer(s) would be realigned."
            : "{$realigned} offer(s) realigned.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return bool whether the offer's status moved (or would move, in dry-run)
     */
    private function realign(array $record, LegacyQuoteStatusResolver $resolver, LegacyQuoteStatusApplier $applier, bool $dryRun): bool
    {
        // Step 1: only an imported offer nobody has touched since the import.
        $quote = Quote::query()->with('quoteWorkflowStatus')->where('old_id', $record['id'] ?? null)->first();

        if ($quote === null || ! $this->isUntouchedOpen($quote)) {
            return false;
        }

        // Step 2: the target under the current mapping.
        $state = LegacyQuoteState::fromRecord($record);
        $warnings = [];
        $target = $resolver->target($quote, $state, $warnings);

        if ($target === null || $target->id === $quote->quote_workflow_status_id) {
            return false;
        }

        $this->line(sprintf('  %s %s -> %s', $quote->code, $quote->quoteWorkflowStatus->name, $target->name));

        // Step 3: the import's own quiet write, contract included.
        if (! $dryRun) {
            DB::transaction(fn () => activity()->withoutLogs(fn () => $applier->apply($quote, $state, $warnings)));
        }

        return true;
    }

    private function isUntouchedOpen(Quote $quote): bool
    {
        return $quote->quoteWorkflowStatus?->system_key === WorkflowStatusSystemKey::Open->value
            && Activity::query()->forSubject($quote)->doesntExist();
    }
}

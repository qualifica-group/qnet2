<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Quote;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * One-off resync of `quotes.quote_workflow_status_id` for the offers the
 * 2026-09-08 fallback changed the resolution of: those with NO revenue line
 * of their own, which now classify on their Opportunita's product lines
 * (App\Support\QuoteWorkflows\QuoteClassificationSource) instead of matching
 * no category criterion at all.
 *
 * The column is persisted, re-resolved only on a write (QuoteService,
 * RequestManagementService), so rows created before the fix keep the GLOBAL
 * default status they were born with while the grid already offers them the
 * destinations of the workflow they now resolve onto. This command closes
 * that gap.
 *
 * It reuses QuoteWorkflowResolver verbatim — the SAME resolution and the same
 * targetStatus() precedence (current status kept when still in the set, else
 * remapped by system_key, else the set's `open` row) every write path
 * applies, never a second implementation. Idempotent: a second run touches
 * nothing.
 */
class ResyncQuoteWorkflowStatuses extends Command
{
    protected $signature = 'quotes:resync-workflow-status {--dry-run : Report what would change without writing}';

    protected $description = 'Re-resolve the working status of the offers with no revenue line, whose workflow is now matched on their opportunity product lines';

    private const int CHUNK_SIZE = 200;

    public function handle(QuoteWorkflowResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resynced = 0;

        // Only the offers the fallback applies to: one carrying revenue lines
        // resolves exactly as it did before, so re-resolving it would be pure
        // blast radius.
        Quote::query()
            ->whereDoesntHave('offerLines')
            ->with([
                'offerLines.product.category',
                'opportunity.productLines',
                'opportunity.customFieldValueRow',
                'quoteWorkflowStatus',
            ])
            ->chunkById(self::CHUNK_SIZE, function (Collection $quotes) use ($resolver, $dryRun, &$resynced): void {
                foreach ($quotes as $quote) {
                    $resynced += $this->resync($resolver, $quote, $dryRun) ? 1 : 0;
                }
            });

        $this->info($dryRun
            ? "{$resynced} offer(s) would be resynced."
            : "{$resynced} offer(s) resynced.");

        return self::SUCCESS;
    }

    /**
     * @return bool whether $quote's status moved (or would move, in dry-run)
     */
    private function resync(QuoteWorkflowResolver $resolver, Quote $quote, bool $dryRun): bool
    {
        $target = $resolver->targetStatus($quote, $resolver->resolve($quote));

        if ($target->id === $quote->quote_workflow_status_id) {
            return false;
        }

        $this->line(sprintf(
            '  #%d %s -> %s',
            $quote->id,
            $quote->quoteWorkflowStatus?->name ?? '(none)',
            $target->name,
        ));

        if (! $dryRun) {
            $quote->quote_workflow_status_id = $target->id;
            $quote->save();
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Contracts\ContractUntouchedCheck;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for spec 0072 D-3 rev. (user decision 2026-11-20): before
 * the revision an offer leaving Won always suspended its contract, so offers
 * no longer won can still carry a suspended contract nobody worked on. The
 * revision deletes such a contract on the status change only; this command
 * applies the same rule (ContractUntouchedCheck) and the same deletion
 * (ContractLifecycleManager::deleteContract, `contract.removed` on the offer)
 * to the ones left behind. A worked-on contract stays suspended. Idempotent.
 */
class RemoveUntouchedSuspendedContracts extends Command
{
    protected $signature = 'contracts:remove-untouched-suspended {--dry-run : Report what would be removed without writing}';

    protected $description = 'Delete the suspended contracts nobody worked on whose offer is no longer won';

    private const int CHUNK_SIZE = 200;

    public function handle(ContractUntouchedCheck $untouchedCheck, ContractLifecycleManager $lifecycleManager): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $removed = 0;
        $kept = 0;

        $this->candidates()->chunkById(self::CHUNK_SIZE, function (Collection $contracts) use ($untouchedCheck, $lifecycleManager, $dryRun, &$removed, &$kept): void {
            foreach ($contracts as $contract) {
                if (! $untouchedCheck->isUntouched($contract)) {
                    $kept++;

                    continue;
                }

                $this->line(sprintf('  contract #%d, offer %s', $contract->id, $contract->quote->code ?? '#'.$contract->quote_id));

                if (! $dryRun) {
                    DB::transaction(static fn () => $lifecycleManager->deleteContract($contract->quote, $contract));
                }

                $removed++;
            }
        });

        $this->info(sprintf(
            '%d contract(s) %s, %d worked-on contract(s) kept suspended.',
            $removed,
            $dryRun ? 'would be removed' : 'removed',
            $kept,
        ));

        return self::SUCCESS;
    }

    /** @return Builder<Contract> */
    private function candidates(): Builder
    {
        return Contract::query()
            ->whereNotNull('suspended_at')
            ->whereHas('quote.quoteWorkflowStatus', static fn (Builder $status) => $status->where('group', '!=', WorkflowStatusGroup::ClosedWon->value))
            ->with('quote');
    }
}

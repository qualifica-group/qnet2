<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Models\User;

/**
 * Imports a chart-of-accounts file (spec 0349): read, validate everything,
 * then apply all-or-nothing unless it is only a preview.
 */
final class ChartOfAccountsImporter
{
    public function __construct(
        private readonly ChartOfAccountsFileReader $reader,
        private readonly ChartOfAccountsPlanner $planner,
        private readonly ChartOfAccountsApplier $applier,
    ) {}

    /**
     * @return array{applied: bool, summary: array<string, mixed>, errors: list<array{row: int, column: ?string, message: string}>}
     */
    public function handle(string $path, bool $dryRun, User $actor): array
    {
        // Step 1: read the file (422 when unreadable or the columns are missing)
        $rows = $this->reader->read($path);

        // Step 2: validate every row and decide create/update
        $plan = $this->planner->plan($rows, $actor);

        // Step 3: write only a preview-free, error-free plan
        $applied = ! $dryRun && ! $plan->hasErrors();
        if ($applied) {
            $this->applier->apply($plan);
        }

        return ['applied' => $applied, 'summary' => $plan->summary(), 'errors' => $plan->errors->toResponse()];
    }
}

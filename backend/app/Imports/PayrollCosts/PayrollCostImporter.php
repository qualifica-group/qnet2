<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use App\Models\User;

/**
 * Imports the labour consultant's monthly cost file (spec 0430): read,
 * validate everything, then replace the month all-or-nothing unless it is
 * only a preview.
 */
final class PayrollCostImporter
{
    public function __construct(
        private readonly PayrollCostFileReader $reader,
        private readonly PayrollCostPlanner $planner,
        private readonly PayrollCostApplier $applier,
    ) {}

    /**
     * @return array{applied: bool, period: ?string, summary: array<string, mixed>, errors: list<array{row: int, column: ?string, message: string}>}
     */
    public function handle(string $path, bool $dryRun, User $actor): array
    {
        // Step 1: read the file (422 when unreadable or the columns are missing)
        $rows = $this->reader->read($path);

        // Step 2: validate every row and resolve people and companies
        $plan = $this->planner->plan($rows, $this->reader->headers(), $actor);

        // Step 3: write only an error-free plan, never for a preview
        $applied = ! $dryRun && ! $plan->hasErrors();
        if ($applied) {
            $this->applier->apply($plan);
        }

        return [
            'applied' => $applied,
            'period' => $plan->period,
            'summary' => $plan->summary(),
            'errors' => $plan->errors->toResponse(),
        ];
    }
}

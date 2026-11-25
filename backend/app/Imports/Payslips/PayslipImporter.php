<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Imports the labour consultant's monthly net file (spec 0431): read, validate
 * everything, then write the payslips all-or-nothing unless it is only a preview.
 */
final class PayslipImporter
{
    public function __construct(
        private readonly PayslipFileReader $reader,
        private readonly PayslipPlanner $planner,
        private readonly PayslipApplier $applier,
    ) {}

    /**
     * @return array{applied: bool, period: ?string, summary: array<string, mixed>, errors: list<array{row: int, column: ?string, message: string}>, warnings: list<array{row: int, column: ?string, message: string}>}
     */
    public function handle(string $path, bool $dryRun, string $paymentDate, User $actor): array
    {
        // Step 1: read the file (422 when unreadable or the columns are missing)
        $rows = $this->reader->read($path);

        // Step 2: validate every row and resolve people, companies and suppliers
        $plan = $this->planner->plan($rows, $this->reader->headers());

        // Step 3: write only an error-free plan, never for a preview
        $applied = ! $dryRun && ! $plan->hasErrors();
        if ($applied) {
            $this->applier->apply($plan, CarbonImmutable::parse($paymentDate), $actor);
        }

        return [
            'applied' => $applied,
            'period' => $plan->period,
            'summary' => $plan->summary(),
            'errors' => $plan->issues->errors(),
            'warnings' => $plan->issues->warnings(),
        ];
    }
}

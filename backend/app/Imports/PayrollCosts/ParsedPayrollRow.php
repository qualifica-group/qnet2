<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

/**
 * A cost-file row whose cells are all well-formed. Amounts are in cents; the
 * company and the person are resolved later by the planner.
 */
final readonly class ParsedPayrollRow
{
    public function __construct(
        public int $number,
        public string $companyCode,
        public string $taxCode,
        public string $period,
        public int $salaryCents,
        public int $contributionCents,
        public int $insuranceCents,
        public int $totalCents,
        public float $ordinaryHours,
        public ?string $hiredOn,
        public ?string $terminatedOn,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

/**
 * Outcome of validating a cost file: the rows to write (all of them valid
 * only when there are no errors) plus what the preview reports.
 */
final readonly class PayrollCostPlan
{
    /**
     * @param  list<array<string, mixed>>  $entries  attributes of the `payroll_costs` rows to create
     */
    public function __construct(
        public array $entries,
        public PayrollCostImportErrors $errors,
        public ?string $period,
        public int $rowCount,
        public int $replacedRows,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors->count() > 0;
    }

    /**
     * @return array{rows: int, people: int, companies: int, total_cost: float, replaced_rows: int}
     */
    public function summary(): array
    {
        $totalCents = array_sum(array_map(static fn (array $entry): int => (int) round(((float) $entry['total_cost']) * 100), $this->entries));

        return [
            'rows' => $this->rowCount,
            'people' => count(array_unique(array_column($this->entries, 'user_id'))),
            'companies' => count(array_unique(array_column($this->entries, 'company_id'))),
            'total_cost' => round($totalCents / 100, 2),
            'replaced_rows' => $this->replacedRows,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

/**
 * Outcome of validating a net file: what would be written (the error-free
 * rows, all of them applied only when there are no errors) plus what the
 * preview reports.
 */
final readonly class PayslipPlan
{
    /**
     * @param  list<PayslipEntry>  $entries
     * @param  array<int, array{action: string, registry_id: int|null, duplicate: bool}>  $suppliers  user id => how the supplier registry is resolved
     * @param  array<int, string>  $ibanUpdates  user id => IBAN to store on the employment profile
     */
    public function __construct(
        public array $entries,
        public PayslipIssues $issues,
        public ?string $period,
        public int $rowCount,
        public array $suppliers,
        public array $ibanUpdates,
    ) {}

    public function hasErrors(): bool
    {
        return $this->issues->errorCount() > 0;
    }

    /**
     * @return array{rows: int, people: int, companies: int, total_net: float, created: int, updated: int, suppliers_created: int, suppliers_linked: int}
     */
    public function summary(): array
    {
        $updated = count(array_filter($this->entries, static fn (PayslipEntry $entry): bool => $entry->existing !== null));
        $actions = array_column($this->suppliers, 'action');

        return [
            'rows' => $this->rowCount,
            'people' => count(array_unique(array_map(static fn (PayslipEntry $entry): int => $entry->userId, $this->entries))),
            'companies' => count(array_unique(array_map(static fn (PayslipEntry $entry): int => $entry->companyId, $this->entries))),
            'total_net' => round(array_sum(array_map(static fn (PayslipEntry $entry): int => $entry->cents, $this->entries)) / 100, 2),
            'created' => count($this->entries) - $updated,
            'updated' => $updated,
            'suppliers_created' => count(array_keys($actions, PayslipSupplierResolver::CREATE, true)),
            'suppliers_linked' => count(array_keys($actions, PayslipSupplierResolver::LINK, true)),
        ];
    }
}

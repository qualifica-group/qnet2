<?php

declare(strict_types=1);

namespace App\DataObjects\Ledger;

use Carbon\CarbonImmutable;

/**
 * A ledger document (active or payable) already reduced to what the
 * calculation needs, amounts in integer cents (spec 0266).
 */
final readonly class LedgerEntry
{
    /**
     * @param  array{id: int, name: string}|null  $company
     * @param  array{id: int, name: string}|null  $companySite
     * @param  array{id: int, name: string}|null  $operationalSite
     * @param  array<int, LedgerInstallmentEntry>  $installments  ordered by sequence
     */
    public function __construct(
        public int $id,
        public string $documentType,
        public ?string $numberLabel,
        public ?string $externalNumber,
        public CarbonImmutable $documentDate,
        public ?array $company,
        public ?array $companySite,
        public ?array $operationalSite,
        public int $totalCents,
        public array $installments,
    ) {}

    public function settledCents(): int
    {
        return array_sum(array_map(fn (LedgerInstallmentEntry $row): int => $row->settledCents ?? 0, $this->installments));
    }

    /** Sum of the installment residuals; the whole total when the plan is missing. */
    public function residualCents(): int
    {
        if ($this->installments === []) {
            return $this->totalCents;
        }

        return array_sum(array_map(fn (LedgerInstallmentEntry $row): int => $row->residualCents(), $this->installments));
    }
}

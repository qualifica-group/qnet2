<?php

declare(strict_types=1);

namespace App\DataObjects\Ledger;

use Carbon\CarbonImmutable;

/**
 * One installment of a ledger document, amounts in integer cents.
 */
final readonly class LedgerInstallmentEntry
{
    public function __construct(
        public int $id,
        public int $sequence,
        public CarbonImmutable $dueDate,
        public int $amountCents,
        public ?int $settledCents,
        public ?CarbonImmutable $settledAt,
    ) {}

    public function residualCents(): int
    {
        return $this->amountCents - ($this->settledCents ?? 0);
    }
}

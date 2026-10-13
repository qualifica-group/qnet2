<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

/** Movements created by one synchronization (spec 0212). */
final readonly class SyncResult
{
    public function __construct(
        public int $accruals = 0,
        public int $reversals = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self($this->accruals + $other->accruals, $this->reversals + $other->reversals);
    }
}

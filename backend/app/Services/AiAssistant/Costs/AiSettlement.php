<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

use App\Models\AiCostEntry;

/**
 * Result of settling a reservation. `limitReached` is "person" or "total" when the month cap is
 * now exceeded (the turn must close with `limit_reached`), else null.
 */
final readonly class AiSettlement
{
    public function __construct(
        public AiCostEntry $entry,
        public ?string $limitReached,
    ) {}
}

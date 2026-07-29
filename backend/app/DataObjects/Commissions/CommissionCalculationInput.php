<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionType;

final readonly class CommissionCalculationInput
{
    public function __construct(
        public CommissionType $type,
        public string $value,
        public string $lineNetAmount,
    ) {}
}

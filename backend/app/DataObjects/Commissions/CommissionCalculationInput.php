<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionType;

final readonly class CommissionCalculationInput
{
    public function __construct(
        public CommissionType $type,
        public string $value,
        // Spec 0145, D-1: no longer the line's plain net amount — its
        // PERCENTAGE base is now the margin (revenue net minus imputed
        // costs, QuoteLineCommissionBaseResolver). FIXED_AMOUNT never reads
        // this field.
        public string $baseAmount,
    ) {}
}

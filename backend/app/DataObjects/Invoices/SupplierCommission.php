<?php

declare(strict_types=1);

namespace App\DataObjects\Invoices;

use App\Enums\CommissionType;

/**
 * The commission billed to the supplier for one sold line (spec 0288, D-4).
 * A null type means no rule applies: the amount is zero.
 */
final readonly class SupplierCommission
{
    public function __construct(
        public ?CommissionType $type,
        public ?string $value,
        public string $baseAmount,
        public string $amount,
    ) {}
}

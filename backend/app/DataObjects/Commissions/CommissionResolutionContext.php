<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionRecipientRole;
use DateTimeImmutable;

final readonly class CommissionResolutionContext
{
    /**
     * @param  array<int, CommissionRecipientRole>  $roles
     */
    public function __construct(
        public int $productId,
        public ?int $productCategoryId,
        public array $roles,
        public DateTimeImmutable $referenceDate,
    ) {}
}

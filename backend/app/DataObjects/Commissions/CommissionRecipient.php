<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

final readonly class CommissionRecipient
{
    public function __construct(
        public string $type,
        public int $id,
    ) {}
}

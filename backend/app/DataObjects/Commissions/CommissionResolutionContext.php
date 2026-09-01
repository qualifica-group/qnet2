<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionRecipientRole;
use DateTimeImmutable;

final readonly class CommissionResolutionContext
{
    /**
     * @param  array<int, CommissionRecipientRole>  $roles
     * @param  array<string, CommissionRecipient|null>  $recipients  keyed by
     *                                                               CommissionRecipientRole value (spec 0089 D-5). Defaults to
     *                                                               empty so every pre-0089 caller/test keeps resolving role-wide
     *                                                               rules unchanged (AC-019).
     */
    public function __construct(
        public int $productId,
        public ?int $productCategoryId,
        public array $roles,
        public DateTimeImmutable $referenceDate,
        public array $recipients = [],
    ) {}
}

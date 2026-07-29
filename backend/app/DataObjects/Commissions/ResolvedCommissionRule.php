<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;

final readonly class ResolvedCommissionRule
{
    public function __construct(
        public int $configurationId,
        public CommissionRecipientRole $role,
        public CommissionType $type,
        public string $value,
        public ?string $internalNote,
        public CommissionOrigin $origin,
    ) {}
}

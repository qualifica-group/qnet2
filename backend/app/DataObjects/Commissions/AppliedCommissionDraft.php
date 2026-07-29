<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;

final readonly class AppliedCommissionDraft
{
    public function __construct(
        public CommissionRecipientRole $role,
        public CommissionRecipient $recipient,
        public CommissionType $type,
        public string $value,
        public string $calculatedAmount,
        public ?string $internalNote,
        public CommissionOrigin $origin,
        public ?int $configurationId,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'recipient_role' => $this->role->value,
            'recipient_type' => $this->recipient->type,
            'recipient_id' => $this->recipient->id,
            'commission_type' => $this->type->value,
            'value' => $this->value,
            'calculated_amount' => $this->calculatedAmount,
            'internal_note' => $this->internalNote,
            'origin' => $this->origin->value,
            'commission_configuration_id' => $this->configurationId,
        ];
    }
}

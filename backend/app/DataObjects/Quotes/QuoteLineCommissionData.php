<?php

declare(strict_types=1);

namespace App\DataObjects\Quotes;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;

final readonly class QuoteLineCommissionData
{
    public function __construct(
        public ?int $id,
        public CommissionRecipientRole $role,
        public ?string $recipientType,
        public ?int $recipientId,
        public CommissionType $type,
        public string $value,
        public ?string $internalNote,
        public CommissionOrigin $origin,
        public ?int $configurationId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            role: CommissionRecipientRole::from($data['recipient_role']),
            recipientType: $data['recipient_type'] ?? null,
            recipientId: isset($data['recipient_id']) ? (int) $data['recipient_id'] : null,
            type: CommissionType::from($data['commission_type']),
            value: (string) $data['value'],
            internalNote: $data['internal_note'] ?? null,
            origin: CommissionOrigin::from($data['origin']),
            configurationId: isset($data['commission_configuration_id']) ? (int) $data['commission_configuration_id'] : null,
        );
    }
}

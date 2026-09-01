<?php

declare(strict_types=1);

namespace App\DataObjects\CommissionConfigurations;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;

final readonly class CreateCommissionConfigurationData
{
    public function __construct(
        public string $name,
        public CommissionRecipientRole $recipientRole,
        public CommissionApplicationScope $applicationScope,
        public ?int $productCategoryId,
        public ?int $productId,
        public ?int $recipientId,
        public ?string $recipientType,
        public CommissionType $commissionType,
        public string $value,
        public int $priority,
        public string $validFrom,
        public ?string $validUntil,
        public CommissionConfigurationStatus $status,
        public ?string $internalNote,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        $recipientRole = CommissionRecipientRole::from($data['recipient_role']);
        $recipientId = isset($data['recipient_id']) ? (int) $data['recipient_id'] : null;

        return new self(
            name: (string) $data['name'],
            recipientRole: $recipientRole,
            applicationScope: CommissionApplicationScope::from($data['application_scope']),
            productCategoryId: isset($data['product_category_id']) ? (int) $data['product_category_id'] : null,
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            recipientId: $recipientId,
            // Spec 0090 D-4 (emends 0089 D-7): honours a submitted
            // `recipient_type` (validated against the role's allow-list by
            // the FormRequest); omitted, falls back to the role's default —
            // identical to the pre-0090 always-derived behaviour (AC-009).
            recipientType: $recipientId === null ? null : ($data['recipient_type'] ?? $recipientRole->recipientType()),
            commissionType: CommissionType::from($data['commission_type']),
            value: (string) $data['value'],
            priority: (int) $data['priority'],
            validFrom: (string) $data['valid_from'],
            validUntil: $data['valid_until'] ?? null,
            status: CommissionConfigurationStatus::from($data['status']),
            internalNote: $data['internal_note'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'recipient_role' => $this->recipientRole,
            'application_scope' => $this->applicationScope,
            'product_category_id' => $this->productCategoryId,
            'product_id' => $this->productId,
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'commission_type' => $this->commissionType,
            'value' => $this->value,
            'priority' => $this->priority,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'status' => $this->status,
            'internal_note' => $this->internalNote,
        ];
    }
}

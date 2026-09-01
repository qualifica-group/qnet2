<?php

declare(strict_types=1);

namespace App\DataObjects\CommissionConfigurations;

use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;

final readonly class UpdateCommissionConfigurationData
{
    /** @param  array<string, mixed>  $attributes */
    public function __construct(public array $attributes) {}

    /**
     * Spec 0090 D-4 (emends 0089 D-7): `recipient_type` is (re)computed here
     * ONLY when `recipient_id` is actually part of this partial update — an
     * update that leaves `recipient_id` untouched must leave `recipient_type`
     * untouched too. When it IS part of the update, a submitted
     * `recipient_type` (validated against the role's allow-list by the
     * FormRequest) wins; omitted, it falls back to the role's default, same
     * as before 0090.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, CommissionConfiguration $configuration): self
    {
        if (array_key_exists('recipient_id', $data)) {
            $recipientId = $data['recipient_id'] === null ? null : (int) $data['recipient_id'];
            $role = isset($data['recipient_role'])
                ? CommissionRecipientRole::from($data['recipient_role'])
                : $configuration->recipient_role;

            $data['recipient_id'] = $recipientId;
            $data['recipient_type'] = $recipientId === null ? null : ($data['recipient_type'] ?? $role->recipientType());
        }

        return new self($data);
    }
}

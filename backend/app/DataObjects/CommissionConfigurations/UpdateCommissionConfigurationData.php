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
     * `recipient_type` is never submitted (spec 0089 D-7): it is only
     * (re)derived here, and ONLY when `recipient_id` is actually part of
     * this partial update — an update that leaves `recipient_id` untouched
     * must leave `recipient_type` untouched too.
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
            $data['recipient_type'] = $recipientId === null ? null : $role->recipientType();
        }

        return new self($data);
    }
}
